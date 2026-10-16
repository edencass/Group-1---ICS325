-- Run once to create tables (the Java code will auto-create tables if missing)
CREATE TABLE IF NOT EXISTS scholarship_transactions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  batch_id TEXT,
  source_file TEXT,
  contributor_last TEXT,
  contributor_first TEXT,
  row_data TEXT,
  created_at TEXT
);

CREATE TABLE IF NOT EXISTS import_history (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  batch_id TEXT,
  file_name TEXT,
  contributor_last TEXT,
  contributor_first TEXT,
  rows_processed INTEGER,
  rows_failed INTEGER,
  status TEXT,
  error_message TEXT,
  created_at TEXT
);
