package com.edencass.batch;

import com.opencsv.CSVReader;
import com.opencsv.exceptions.CsvValidationException;
import org.apache.poi.ss.usermodel.*;
import org.apache.poi.xssf.usermodel.XSSFWorkbook;

import java.io.*;
import java.nio.file.Path;
import java.sql.*;
import java.time.Instant;
import java.util.*;

/**
 * ScholarshipTransaction - parser + DB operations for batching.
 *
 * Minimal but complete demo:
 *  - Parses CSV and XLSX files (first row = header).
 *  - Generates a batchId (UUID) for each run of processFilesAsBatch.
 *  - Inserts rows into scholarship_transactions and records import_history.
 *  - Uses JDBC transaction (autoCommit=false) and commits/rolls back per batch.
 *
 * For production integrate with your DB, add robust validation, and parameterize SQL.
 */
public class ScholarshipTransaction {

    // Model fields for a single transaction row
    public String sourceFileName;
    public String contributorLastName;
    public String contributorFirstName;
    public Map<String, String> attributes; // header -> value
    public String batchId;

    public ScholarshipTransaction() {
        attributes = new LinkedHashMap<>();
    }

    // Parse contributor from filename using LastName_FirstName convention
    public static String[] parseContributorFromFileName(String filename) {
        String base = new File(filename).getName();
        int dot = base.lastIndexOf('.');
        if (dot > 0) base = base.substring(0, dot);
        // allow separators underscore or space/dash but prefer underscore between last/first
        String[] parts = base.split("[_\\- ]+");
        if (parts.length >= 2) {
            return new String[]{parts[0].trim(), parts[1].trim()};
        }
        return new String[]{null, null};
    }

    // Convert attribute map to a small JSON-ish string (simple escape)
    public String attributesAsJson() {
        StringBuilder sb = new StringBuilder();
        sb.append("{");
        boolean first = true;
        for (Map.Entry<String, String> e : attributes.entrySet()) {
            if (!first) sb.append(",");
            sb.append("\"").append(escape(e.getKey())).append("\":");
            sb.append("\"").append(escape(e.getValue())).append("\"");
            first = false;
        }
        sb.append("}");
        return sb.toString();
    }

    private String escape(String s) {
        if (s == null) return "";
        return s.replace("\\", "\\\\").replace("\"", "\\\"").replace("\n", "\\n");
    }

    // parse CSV file into a list of ScholarshipTransaction
    public static List<ScholarshipTransaction> parseCsv(Path filePath) throws IOException, CsvValidationException {
        List<ScholarshipTransaction> out = new ArrayList<>();
        try (Reader fr = new FileReader(filePath.toFile());
             CSVReader r = new CSVReader(fr)) {
            String[] header = r.readNext();
            if (header == null) return out;
            String[] row;
            while ((row = r.readNext()) != null) {
                ScholarshipTransaction st = new ScholarshipTransaction();
                st.sourceFileName = filePath.getFileName().toString();
                String[] contrib = parseContributorFromFileName(st.sourceFileName);
                st.contributorLastName = contrib[0];
                st.contributorFirstName = contrib[1];
                for (int i = 0; i < header.length; i++) {
                    String key = header[i] == null ? ("col" + i) : header[i].trim();
                    String val = i < row.length ? row[i] : "";
                    st.attributes.put(key, val);
                }
                out.add(st);
            }
        }
        return out;
    }

    // parse XLSX file into a list of ScholarshipTransaction
    public static List<ScholarshipTransaction> parseXlsx(Path filePath) throws IOException {
        List<ScholarshipTransaction> out = new ArrayList<>();
        try (FileInputStream fis = new FileInputStream(filePath.toFile());
             Workbook wb = new XSSFWorkbook(fis)) {
            Sheet sheet = wb.getSheetAt(0);
            Iterator<Row> it = sheet.iterator();
            if (!it.hasNext()) return out;
            Row headerRow = it.next();
            List<String> headers = new ArrayList<>();
            for (Cell c : headerRow) {
                headers.add(c.getStringCellValue());
            }
            while (it.hasNext()) {
                Row r = it.next();
                ScholarshipTransaction st = new ScholarshipTransaction();
                st.sourceFileName = filePath.getFileName().toString();
                String[] contrib = parseContributorFromFileName(st.sourceFileName);
                st.contributorLastName = contrib[0];
                st.contributorFirstName = contrib[1];
                for (int i = 0; i < headers.size(); i++) {
                    String key = headers.get(i) == null ? ("col" + i) : headers.get(i).trim();
                    Cell cell = r.getCell(i);
                    String val = cell == null ? "" : cellToString(cell);
                    st.attributes.put(key, val);
                }
                out.add(st);
            }
        }
        return out;
    }

    private static String cellToString(Cell c) {
        if (c == null) return "";
        switch (c.getCellType()) {
            case STRING:
                return c.getStringCellValue();
            case NUMERIC:
                if (DateUtil.isCellDateFormatted(c)) return c.getLocalDateTimeCellValue().toString();
                else return Double.toString(c.getNumericCellValue());
            case BOOLEAN:
                return Boolean.toString(c.getBooleanCellValue());
            case FORMULA:
                return c.getCellFormula();
            case BLANK:
                return "";
            default:
                return c.toString();
        }
    }

    // Create schema if not exists
    public static void ensureSchema(Connection conn) throws SQLException {
        try (Statement s = conn.createStatement()) {
            s.executeUpdate("CREATE TABLE IF NOT EXISTS scholarship_transactions (" +
                    "id INTEGER PRIMARY KEY AUTOINCREMENT," +
                    "batch_id TEXT," +
                    "source_file TEXT," +
                    "contributor_last TEXT," +
                    "contributor_first TEXT," +
                    "row_data TEXT," +
                    "created_at TEXT" +
                    ")");
            s.executeUpdate("CREATE TABLE IF NOT EXISTS import_history (" +
                    "id INTEGER PRIMARY KEY AUTOINCREMENT," +
                    "batch_id TEXT," +
                    "file_name TEXT," +
                    "contributor_last TEXT," +
                    "contributor_first TEXT," +
                    "rows_processed INTEGER," +
                    "rows_failed INTEGER," +
                    "status TEXT," +
                    "error_message TEXT," +
                    "created_at TEXT" +
                    ")");
        }
    }

    // Main method: process files as a single batch (commit/rollback per batch)
    public static BatchResult processFilesAsBatch(List<Path> files, Connection dbConnection) {
        String batchId = UUID.randomUUID().toString();
        int totalProcessed = 0;
        int totalFailed = 0;
        boolean success = false;
        String errorMessage = null;

        try {
            dbConnection.setAutoCommit(false);
            ensureSchema(dbConnection);

            String insertSql = "INSERT INTO scholarship_transactions " +
                    "(batch_id, source_file, contributor_last, contributor_first, row_data, created_at) " +
                    "VALUES (?, ?, ?, ?, ?, ?)";
            String historySql = "INSERT INTO import_history " +
                    "(batch_id, file_name, contributor_last, contributor_first, rows_processed, rows_failed, status, error_message, created_at) " +
                    "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            try (PreparedStatement ps = dbConnection.prepareStatement(insertSql);
                 PreparedStatement ph = dbConnection.prepareStatement(historySql)) {

                for (Path p : files) {
                    List<ScholarshipTransaction> rows = new ArrayList<>();
                    String fname = p.getFileName().toString();
                    try {
                        if (fname.toLowerCase().endsWith(".csv")) {
                            rows = parseCsv(p);
                        } else if (fname.toLowerCase().endsWith(".xlsx") || fname.toLowerCase().endsWith(".xls")) {
                            rows = parseXlsx(p);
                        } else {
                            throw new IOException("Unsupported file extension for file: " + fname);
                        }
                    } catch (Exception ex) {
                        // record failed import_history for this file and abort batch
                        ph.setString(1, batchId);
                        ph.setString(2, fname);
                        String[] c = parseContributorFromFileName(fname);
                        ph.setString(3, c[0]);
                        ph.setString(4, c[1]);
                        ph.setInt(5, 0);
                        ph.setInt(6, 0);
                        ph.setString(7, "FAILED");
                        ph.setString(8, ex.getMessage());
                        ph.setString(9, Instant.now().toString());
                        ph.executeUpdate();

                        throw ex;
                    }

                    int fileProcessed = 0;
                    int fileFailed = 0;
                    for (ScholarshipTransaction st : rows) {
                        try {
                            ps.setString(1, batchId);
                            ps.setString(2, st.sourceFileName);
                            ps.setString(3, st.contributorLastName);
                            ps.setString(4, st.contributorFirstName);
                            ps.setString(5, st.attributesAsJson());
                            ps.setString(6, Instant.now().toString());
                            ps.executeUpdate();
                            fileProcessed++;
                        } catch (SQLException ex) {
                            fileFailed++;
                        }
                    }

                    // record file-level import_history (status will be updated to COMMITTED or ROLLED_BACK later)
                    ph.setString(1, batchId);
                    ph.setString(2, fname);
                    String[] c = parseContributorFromFileName(fname);
                    ph.setString(3, c[0]);
                    ph.setString(4, c[1]);
                    ph.setInt(5, fileProcessed);
                    ph.setInt(6, fileFailed);
                    ph.setString(7, "PENDING");
                    ph.setString(8, null);
                    ph.setString(9, Instant.now().toString());
                    ph.executeUpdate();

                    totalProcessed += fileProcessed;
                    totalFailed += fileFailed;
                }

                // If any rows failed, treat as failure for entire batch (rollback)
                if (totalFailed > 0) {
                    dbConnection.rollback();
                    // update import_history rows for this batch to ROLLED_BACK
                    try (PreparedStatement pu = dbConnection.prepareStatement(
                            "UPDATE import_history SET status = ?, error_message = ? WHERE batch_id = ?")) {
                        pu.setString(1, "ROLLED_BACK");
                        pu.setString(2, "Rows failed during insert; batch rolled back.");
                        pu.setString(3, batchId);
                        pu.executeUpdate();
                    }
                    success = false;
                    errorMessage = "One or more rows failed to insert; rolled back batch.";
                } else {
                    dbConnection.commit();
                    // update import_history rows for this batch to COMMITTED
                    try (PreparedStatement pu = dbConnection.prepareStatement(
                            "UPDATE import_history SET status = ? WHERE batch_id = ?")) {
                        pu.setString(1, "COMMITTED");
                        pu.setString(2, batchId);
                        pu.executeUpdate();
                    }
                    success = true;
                }
            }
        } catch (Exception ex) {
            try {
                dbConnection.rollback();
            } catch (SQLException ignore) {}
            success = false;
            errorMessage = ex.getMessage();
            // best-effort: mark import_history for this batch as FAILED
            try (PreparedStatement pu = dbConnection.prepareStatement(
                    "UPDATE import_history SET status = ?, error_message = ? WHERE batch_id = ?")) {
                pu.setString(1, "FAILED");
                pu.setString(2, errorMessage);
                pu.setString(3, batchId);
                pu.executeUpdate();
            } catch (Exception ignore) {}
        } finally {
            try {
                dbConnection.setAutoCommit(true);
            } catch (SQLException ignore) {}
        }

        return new BatchResult(batchId, totalProcessed, totalFailed, success, errorMessage);
    }

    // Helper result class
    public static class BatchResult {
        public final String batchId;
        public final int rowsProcessed;
        public final int rowsFailed;
        public final boolean success;
        public final String errorMessage;

        public BatchResult(String batchId, int rowsProcessed, int rowsFailed, boolean success, String errorMessage) {
            this.batchId = batchId;
            this.rowsProcessed = rowsProcessed;
            this.rowsFailed = rowsFailed;
            this.success = success;
            this.errorMessage = errorMessage;
        }
    }
}
