CREATE TABLE IF NOT EXISTS slot_daily_definitions (
  daily_key DATE NOT NULL PRIMARY KEY,
  rules_version VARCHAR(32) NOT NULL,
  seed_hex CHAR(64) CHARACTER SET ascii NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS slot_daily_attempts (
  guest_id BIGINT UNSIGNED NOT NULL,
  daily_key DATE NOT NULL,
  run_id CHAR(32) CHARACTER SET ascii NOT NULL UNIQUE,
  PRIMARY KEY (guest_id, daily_key),
  FOREIGN KEY (guest_id) REFERENCES slot_guests(id) ON DELETE CASCADE,
  FOREIGN KEY (daily_key) REFERENCES slot_daily_definitions(daily_key)
) ENGINE=InnoDB;
