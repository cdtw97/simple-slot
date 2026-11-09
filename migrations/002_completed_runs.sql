CREATE TABLE IF NOT EXISTS slot_records (
  run_id CHAR(32) CHARACTER SET ascii PRIMARY KEY,
  guest_id BIGINT UNSIGNED NOT NULL,
  mode VARCHAR(12) NOT NULL,
  machine VARCHAR(24) NOT NULL,
  rules_version VARCHAR(32) NOT NULL,
  daily_key VARCHAR(10) NOT NULL DEFAULT '',
  official TINYINT(1) NOT NULL DEFAULT 1,
  score_units BIGINT UNSIGNED NOT NULL,
  record_json TEXT NOT NULL,
  completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX personal_best (guest_id,mode,machine,rules_version,daily_key,official,score_units),
  FOREIGN KEY (guest_id) REFERENCES slot_guests(id) ON DELETE CASCADE
) ENGINE=InnoDB;
