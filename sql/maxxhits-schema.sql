-- ============================================================
--  MAXXHITS 2.0 — Marketing OS schema
--  Shared-hosting friendly (phpMyAdmin import, no CREATE DATABASE).
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

DROP TABLE IF EXISTS module_records;
DROP TABLE IF EXISTS automation_runs;
DROP TABLE IF EXISTS automations;
DROP TABLE IF EXISTS audit_events;
DROP TABLE IF EXISTS posts;
DROP TABLE IF EXISTS destinations;
DROP TABLE IF EXISTS brands;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS settings;

-- Legacy tables from the v1 content-board app (safe to run on a
-- fresh database — these only exist on pre-2.0 installs).
DROP TABLE IF EXISTS automation_logs;
DROP TABLE IF EXISTS channels;
DROP TABLE IF EXISTS social_login_companies;
DROP TABLE IF EXISTS social_logins;
DROP TABLE IF EXISTS revenue_snapshots;
DROP TABLE IF EXISTS person_companies;
DROP TABLE IF EXISTS companies;
DROP TABLE IF EXISTS people;
DROP VIEW  IF EXISTS v_company_summary;
DROP VIEW  IF EXISTS v_platform_monthly;
DROP VIEW  IF EXISTS v_team_earnings;
DROP VIEW  IF EXISTS v_posting_today;
DROP VIEW  IF EXISTS v_revenue_totals;

-- ── Brands: isolated client workspaces ──────────────────────
CREATE TABLE brands (
  id          VARCHAR(36)  NOT NULL,
  name        VARCHAR(255) NOT NULL,
  color       VARCHAR(7)   NOT NULL DEFAULT '#7555df',
  website_url VARCHAR(500) DEFAULT NULL,
  brand_voice TEXT         DEFAULT NULL COMMENT 'Tone/style instructions fed to AI generation',
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_brand_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Users: admin | staff ────────────────────────────────────
CREATE TABLE users (
  id            VARCHAR(36)  NOT NULL,
  username      VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  full_name     VARCHAR(255) NOT NULL,
  email         VARCHAR(255) DEFAULT NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  last_login    DATETIME     DEFAULT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Destinations: where content publishes (per brand) ──────
--  config_enc = AES-encrypted JSON credentials per platform.
--  Platforms without an adapter are manual-handoff only.
CREATE TABLE destinations (
  id           VARCHAR(36)  NOT NULL,
  brand_id     VARCHAR(36)  NOT NULL,
  platform     ENUM('wordpress','facebook','instagram','twitter','linkedin','reddit','pinterest','tiktok','youtube','threads') NOT NULL,
  name         VARCHAR(255) NOT NULL,
  config_enc   TEXT         DEFAULT NULL,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  last_test_at DATETIME     DEFAULT NULL,
  last_test_ok TINYINT(1)   DEFAULT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dest_brand (brand_id),
  CONSTRAINT fk_dest_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Posts: every piece of content in the pipeline ──────────
CREATE TABLE posts (
  id               VARCHAR(36)  NOT NULL,
  brand_id         VARCHAR(36)  NOT NULL,
  destination_id   VARCHAR(36)  DEFAULT NULL,
  automation_id    VARCHAR(36)  DEFAULT NULL,
  source           ENUM('manual','studio','automation') NOT NULL DEFAULT 'manual',
  platform         ENUM('wordpress','facebook','instagram','twitter','linkedin','reddit','pinterest','tiktok','youtube','threads') NOT NULL,
  title            VARCHAR(500) NOT NULL,
  content          MEDIUMTEXT   DEFAULT NULL,
  image_url        TEXT         DEFAULT NULL,
  status           ENUM('draft','review','approved','scheduled','publishing','published','failed','rejected') NOT NULL DEFAULT 'draft',
  publish_mode     ENUM('manual','auto') NOT NULL DEFAULT 'manual',
  post_date        DATE         DEFAULT NULL,
  publish_time     TIME         DEFAULT NULL,
  ai_generated     TINYINT(1)   NOT NULL DEFAULT 0,
  approved_by      VARCHAR(255) DEFAULT NULL,
  approved_at      DATETIME     DEFAULT NULL,
  review_note      TEXT         DEFAULT NULL,
  external_id      VARCHAR(255) DEFAULT NULL,
  post_url         TEXT         DEFAULT NULL,
  published_at     DATETIME     DEFAULT NULL,
  publish_error    TEXT         DEFAULT NULL,
  publish_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_by       VARCHAR(255) DEFAULT NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_post_brand (brand_id),
  KEY idx_post_status (status),
  KEY idx_post_queue (publish_mode, status, post_date),
  CONSTRAINT fk_post_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE,
  CONSTRAINT fk_post_dest  FOREIGN KEY (destination_id) REFERENCES destinations (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Automations: recurring AI research + draft creation ────
CREATE TABLE automations (
  id             VARCHAR(36)  NOT NULL,
  brand_id       VARCHAR(36)  NOT NULL,
  platform       ENUM('wordpress','facebook','instagram','twitter','linkedin','reddit','pinterest','tiktok','youtube','threads') NOT NULL,
  name           VARCHAR(255) NOT NULL,
  destination_id VARCHAR(36)  DEFAULT NULL COMMENT 'Where approved drafts will publish (adapter platforms)',
  topic_prompt   TEXT         NOT NULL COMMENT 'Topics, research instructions and audience',
  frequency      ENUM('daily','weekdays','weekly','monthly') NOT NULL DEFAULT 'daily',
  run_time       TIME         NOT NULL DEFAULT '06:00:00' COMMENT 'Arizona time',
  max_drafts     TINYINT UNSIGNED NOT NULL DEFAULT 1,
  enabled        TINYINT(1)   NOT NULL DEFAULT 0,
  last_run_at    DATETIME     DEFAULT NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_auto_brand (brand_id),
  KEY idx_auto_platform (platform),
  CONSTRAINT fk_auto_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE,
  CONSTRAINT fk_auto_dest  FOREIGN KEY (destination_id) REFERENCES destinations (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Automation runs: one row per schedule occurrence ───────
--  The unique occurrence key guarantees the same scheduled
--  occurrence can never run twice (overlapping crons included).
CREATE TABLE automation_runs (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  automation_id  VARCHAR(36)  NOT NULL,
  occurrence_key VARCHAR(64)  NOT NULL COMMENT 'automation_id + occurrence date (or manual/test id)',
  kind           ENUM('scheduled','test') NOT NULL DEFAULT 'scheduled',
  status         ENUM('running','success','error') NOT NULL DEFAULT 'running',
  drafts_created TINYINT UNSIGNED NOT NULL DEFAULT 0,
  message        TEXT         DEFAULT NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_occurrence (automation_id, occurrence_key),
  CONSTRAINT fk_run_auto FOREIGN KEY (automation_id) REFERENCES automations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Audit log (append-only) ─────────────────────────────────
CREATE TABLE audit_events (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor       VARCHAR(100) NOT NULL,
  action      VARCHAR(100) NOT NULL,
  target_type VARCHAR(50)  DEFAULT NULL,
  target_id   VARCHAR(64)  DEFAULT NULL,
  message     TEXT         DEFAULT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Placeholder module workspaces ───────────────────────────
CREATE TABLE module_records (
  id         VARCHAR(36)  NOT NULL,
  module     VARCHAR(60)  NOT NULL,
  brand_id   VARCHAR(36)  DEFAULT NULL,
  title      VARCHAR(500) NOT NULL,
  notes      TEXT         DEFAULT NULL,
  created_by VARCHAR(255) DEFAULT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rec_module (module),
  CONSTRAINT fk_rec_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Settings ────────────────────────────────────────────────
CREATE TABLE settings (
  setting_key   VARCHAR(100) NOT NULL,
  setting_value TEXT         DEFAULT NULL,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO settings (setting_key, setting_value) VALUES
  ('app_version',    '2.0.0'),
  ('emergency_stop', '0'),
  ('cron_key',       NULL),
  ('ai_provider',    'anthropic'),
  ('ai_model',       'claude-opus-5-5')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Default admin — set the real password via setadmin.php.
INSERT IGNORE INTO users (id, username, password_hash, role, full_name) VALUES
  ('usr_admin', 'admin', '!locked-run-setadmin-php!', 'admin', 'Administrator');

-- Brand directory (from the AimBlue brand list — edit freely).
INSERT IGNORE INTO brands (id, name) VALUES
  (UUID(), 'eDrugstore'),
  (UUID(), 'HealthyMale'),
  (UUID(), 'HugeMeds'),
  (UUID(), 'PrescriptionHub'),
  (UUID(), 'OurDoctor'),
  (UUID(), 'TelehealthCash'),
  (UUID(), 'Cheatmeal'),
  (UUID(), 'Secure Medical');
