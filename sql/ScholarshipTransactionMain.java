package com.edencass.batch;

import java.io.FileInputStream;
import java.nio.file.*;
import java.sql.Connection;
import java.sql.DriverManager;
import java.util.*;
import java.util.stream.Collectors;

/**
 * Runner for batch processing. Usage:
 *  - mvn package
 *  - java -jar target/batch-runner-1.0.0.jar [inputDir]
 *
 * If inputDir not passed, uses config.properties input.dir.
 */
public class ScholarshipTransactionMain {

    public static void main(String[] args) throws Exception {
        Properties cfg = new Properties();
        try (FileInputStream fis = new FileInputStream("src/main/resources/config.properties")) {
            cfg.load(fis);
        } catch (Exception e) {
            // ignore; will use defaults
        }
        String inputDir = args.length > 0 ? args[0] : cfg.getProperty("input.dir", "sample/processed");
        String dbUrl = cfg.getProperty("db.url", "jdbc:sqlite:batch.db");

        System.out.println("Input dir: " + inputDir);
        System.out.println("DB URL: " + dbUrl);

        List<Path> files;
        try {
            files = Files.list(Paths.get(inputDir))
                    .filter(p -> {
                        String n = p.getFileName().toString().toLowerCase();
                        return n.endsWith(".csv") || n.endsWith(".xlsx") || n.endsWith(".xls");
                    })
                    .collect(Collectors.toList());
        } catch (Exception e) {
            System.err.println("Failed to list input directory: " + e.getMessage());
            return;
        }

        if (files.isEmpty()) {
            System.out.println("No input files found in " + inputDir);
            return;
        }

        try (Connection conn = DriverManager.getConnection(dbUrl)) {
            ScholarshipTransaction.BatchResult res = ScholarshipTransaction.processFilesAsBatch(files, conn);
            System.out.println("BatchId: " + res.batchId);
            System.out.println("Rows processed: " + res.rowsProcessed);
            System.out.println("Rows failed: " + res.rowsFailed);
            System.out.println("Success: " + res.success);
            if (!res.success) System.out.println("Error: " + res.errorMessage);
        }
    }
}