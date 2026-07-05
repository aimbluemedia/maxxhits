-- ============================================================
--  MAXXHITS — MySQL Database Schema
--  Compatible with shared hosting (cPanel / Hostinger / etc.)
--  Import via phpMyAdmin or: mysql -u user -p existing_db < maxxhits-schema.sql
--  No DROP DATABASE or CREATE DATABASE needed.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ============================================================
--  DROP existing tables (clean reinstall)
-- ============================================================
DROP TABLE IF EXISTS automation_logs;
DROP TABLE IF EXISTS channels;
DROP TABLE IF EXISTS social_login_companies;
DROP TABLE IF EXISTS social_logins;
DROP TABLE IF EXISTS revenue_snapshots;
DROP TABLE IF EXISTS posts;
DROP TABLE IF EXISTS person_companies;
DROP TABLE IF EXISTS companies;
DROP TABLE IF EXISTS people;
DROP TABLE IF EXISTS settings;

-- ============================================================
--  TABLE: people — content managers and sales people
-- ============================================================
CREATE TABLE people (
  id          VARCHAR(36)   NOT NULL,
  role        ENUM('content_manager','sales_person') NOT NULL,
  name        VARCHAR(255)  NOT NULL,
  email       VARCHAR(255)  DEFAULT NULL,
  notes       TEXT          DEFAULT NULL,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE  KEY uq_email (email),
  KEY     idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: companies
-- ============================================================
CREATE TABLE companies (
  id                  VARCHAR(36)      NOT NULL,
  name                VARCHAR(255)     NOT NULL,
  color               VARCHAR(7)       NOT NULL DEFAULT '#6c47ff',
  content_manager_id  VARCHAR(36)      DEFAULT NULL,
  sales_person_id     VARCHAR(36)      DEFAULT NULL,
  monthly_posts       INT UNSIGNED     DEFAULT NULL,
  fee                 DECIMAL(10,2)    DEFAULT NULL,
  fee_sp_pct          DECIMAL(5,2)     NOT NULL DEFAULT 40.00,
  fee_cm_pct          DECIMAL(5,2)     NOT NULL DEFAULT 40.00,
  fee_sm_pct          DECIMAL(5,2)     NOT NULL DEFAULT 20.00,
  payment_date        TINYINT UNSIGNED DEFAULT NULL COMMENT 'Day of month 1-31',
  posting_days        VARCHAR(100)     DEFAULT NULL COMMENT 'Comma separated: Mon,Tue,Wed,Thu,Fri,Sat,Sun',
  created_at          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cm (content_manager_id),
  KEY idx_sp (sales_person_id),
  CONSTRAINT fk_co_cm FOREIGN KEY (content_manager_id) REFERENCES people (id) ON DELETE SET NULL,
  CONSTRAINT fk_co_sp FOREIGN KEY (sales_person_id)    REFERENCES people (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: person_companies — many-to-many
-- ============================================================
CREATE TABLE person_companies (
  person_id   VARCHAR(36) NOT NULL,
  company_id  VARCHAR(36) NOT NULL,
  created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (person_id, company_id),
  KEY idx_company (company_id),
  CONSTRAINT fk_pc_person  FOREIGN KEY (person_id)  REFERENCES people    (id) ON DELETE CASCADE,
  CONSTRAINT fk_pc_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: channels — publishing destinations (automation)
--  config_enc holds AES-encrypted JSON with the credentials
--  each platform needs:
--    wordpress: {site_url, username, app_password}
--    facebook:  {page_id, access_token}
--    instagram: {ig_user_id, access_token}
--    twitter:   {access_token}            (OAuth2 user token, tweet.write)
--    linkedin:  {author_urn, access_token}
-- ============================================================
CREATE TABLE channels (
  id            VARCHAR(36)  NOT NULL,
  company_id    VARCHAR(36)  NOT NULL,
  platform      ENUM('wordpress','facebook','instagram','twitter','linkedin') NOT NULL,
  name          VARCHAR(255) NOT NULL,
  config_enc    TEXT         DEFAULT NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  last_test_at  DATETIME     DEFAULT NULL,
  last_test_ok  TINYINT(1)   DEFAULT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_channel_company (company_id),
  KEY idx_channel_platform (platform),
  CONSTRAINT fk_channel_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: posts
--  Extended for automation: content body, image, publish mode,
--  target channel, publish time and result tracking.
-- ============================================================
CREATE TABLE posts (
  id               VARCHAR(36)  NOT NULL,
  company_id       VARCHAR(36)  NOT NULL,
  title            VARCHAR(500) NOT NULL,
  content          MEDIUMTEXT   DEFAULT NULL COMMENT 'Post body used by auto-publishing',
  image_url        TEXT         DEFAULT NULL COMMENT 'Public image URL (required for Instagram)',
  platform         ENUM('blog','facebook','instagram','twitter','linkedin','tiktok','youtube','reddit','pinterest') NOT NULL,
  status           ENUM('draft','scheduled','publishing','published','failed') NOT NULL DEFAULT 'scheduled',
  post_date        DATE         DEFAULT NULL,
  publish_time     TIME         DEFAULT NULL COMMENT 'Time of day for auto-publish (NULL = any time)',
  publish_mode     ENUM('manual','auto') NOT NULL DEFAULT 'manual',
  channel_id       VARCHAR(36)  DEFAULT NULL COMMENT 'Channel used for auto-publish',
  assignee         VARCHAR(255) DEFAULT NULL COMMENT 'Content manager name',
  post_url         TEXT         DEFAULT NULL,
  external_id      VARCHAR(255) DEFAULT NULL COMMENT 'ID returned by the platform after publishing',
  published_at     DATETIME     DEFAULT NULL,
  publish_error    TEXT         DEFAULT NULL,
  publish_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_company   (company_id),
  KEY idx_platform  (platform),
  KEY idx_status    (status),
  KEY idx_post_date (post_date),
  KEY idx_co_month  (company_id, post_date),
  KEY idx_queue     (publish_mode, status, post_date),
  CONSTRAINT fk_post_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
  CONSTRAINT fk_post_channel FOREIGN KEY (channel_id) REFERENCES channels (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: automation_logs — audit trail of every publish run
-- ============================================================
CREATE TABLE automation_logs (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id     VARCHAR(36)  DEFAULT NULL,
  channel_id  VARCHAR(36)  DEFAULT NULL,
  level       ENUM('info','success','error') NOT NULL DEFAULT 'info',
  message     TEXT         NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_log_post (post_id),
  KEY idx_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: settings
-- ============================================================
CREATE TABLE settings (
  setting_key   VARCHAR(100) NOT NULL,
  setting_value TEXT         DEFAULT NULL,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: revenue_snapshots — monthly fee split history
-- ============================================================
CREATE TABLE revenue_snapshots (
  id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  company_id     VARCHAR(36)   NOT NULL,
  snapshot_month DATE          NOT NULL COMMENT 'First day of month e.g. 2026-05-01',
  total_fee      DECIMAL(10,2) NOT NULL,
  sp_pct         DECIMAL(5,2)  NOT NULL,
  cm_pct         DECIMAL(5,2)  NOT NULL,
  sm_pct         DECIMAL(5,2)  NOT NULL,
  sp_amount      DECIMAL(10,2) NOT NULL,
  cm_amount      DECIMAL(10,2) NOT NULL,
  sm_amount      DECIMAL(10,2) NOT NULL,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_snapshot (company_id, snapshot_month),
  KEY idx_month (snapshot_month),
  CONSTRAINT fk_rs_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: users — dual-role login accounts
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
  id            VARCHAR(36)     NOT NULL,
  username      VARCHAR(100)    NOT NULL,
  password_hash VARCHAR(255)    NOT NULL,
  role          VARCHAR(100)    NOT NULL DEFAULT 'content_manager' COMMENT 'Comma separated: admin | content_manager | sales_person',
  cm_person_id  VARCHAR(36)     DEFAULT NULL,
  sp_person_id  VARCHAR(36)     DEFAULT NULL,
  full_name     VARCHAR(255)    NOT NULL,
  email         VARCHAR(255)    DEFAULT NULL,
  last_login    DATETIME        DEFAULT NULL,
  is_active     TINYINT(1)      NOT NULL DEFAULT 1,
  created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE  KEY uq_username (username),
  KEY idx_cm_person (cm_person_id),
  KEY idx_sp_person (sp_person_id),
  CONSTRAINT fk_user_cm FOREIGN KEY (cm_person_id) REFERENCES people (id) ON DELETE SET NULL,
  CONSTRAINT fk_user_sp FOREIGN KEY (sp_person_id) REFERENCES people (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: social_logins — encrypted credentials vault
-- ============================================================
CREATE TABLE social_logins (
  id            VARCHAR(36)  NOT NULL,
  platform      VARCHAR(50)  NOT NULL,
  title         VARCHAR(255) NOT NULL,
  channel_url   TEXT         DEFAULT NULL,
  username      VARCHAR(255) DEFAULT NULL,
  password_enc  TEXT         DEFAULT NULL,
  notes         TEXT         DEFAULT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE social_login_companies (
  login_id    VARCHAR(36) NOT NULL,
  company_id  VARCHAR(36) NOT NULL,
  PRIMARY KEY (login_id, company_id),
  KEY idx_slc_company (company_id),
  CONSTRAINT fk_slc_login   FOREIGN KEY (login_id)   REFERENCES social_logins (id) ON DELETE CASCADE,
  CONSTRAINT fk_slc_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  VIEWS
-- ============================================================

CREATE OR REPLACE VIEW v_company_summary AS
SELECT
  c.id,
  c.name,
  c.color,
  c.monthly_posts,
  c.fee,
  c.fee_sp_pct,
  c.fee_cm_pct,
  c.fee_sm_pct,
  c.payment_date,
  c.posting_days,
  cm.name                                   AS content_manager,
  sp.name                                   AS sales_person,
  ROUND(c.fee * c.fee_sp_pct / 100, 2)      AS sp_earnings,
  ROUND(c.fee * c.fee_cm_pct / 100, 2)      AS cm_earnings,
  ROUND(c.fee * c.fee_sm_pct / 100, 2)      AS sm_earnings,
  COUNT(p.id)                               AS total_posts,
  SUM(p.status = 'published')               AS published,
  SUM(p.status = 'scheduled')               AS scheduled
FROM companies c
LEFT JOIN people cm ON cm.id = c.content_manager_id
LEFT JOIN people sp ON sp.id = c.sales_person_id
LEFT JOIN posts  p  ON p.company_id = c.id
GROUP BY
  c.id, c.name, c.color, c.monthly_posts, c.fee,
  c.fee_sp_pct, c.fee_cm_pct, c.fee_sm_pct,
  c.payment_date, c.posting_days, cm.name, sp.name;

CREATE OR REPLACE VIEW v_platform_monthly AS
SELECT
  p.company_id,
  c.name                                    AS company_name,
  p.platform,
  DATE_FORMAT(p.post_date, '%Y-%m-01')      AS month,
  COUNT(*)                                  AS total,
  SUM(p.status = 'published')               AS published,
  SUM(p.status = 'scheduled')               AS scheduled
FROM posts p
JOIN companies c ON c.id = p.company_id
WHERE p.post_date IS NOT NULL
GROUP BY p.company_id, c.name, p.platform, DATE_FORMAT(p.post_date, '%Y-%m-01');

CREATE OR REPLACE VIEW v_team_earnings AS
SELECT
  ppl.id                                    AS person_id,
  ppl.name,
  ppl.role,
  ppl.email,
  COUNT(DISTINCT pc.company_id)             AS num_companies,
  SUM(CASE ppl.role
        WHEN 'content_manager' THEN ROUND(c.fee * c.fee_cm_pct / 100, 2)
        WHEN 'sales_person'    THEN ROUND(c.fee * c.fee_sp_pct / 100, 2)
        ELSE 0
      END)                                  AS total_monthly_earnings,
  SUM(IFNULL(c.monthly_posts, 0))           AS total_monthly_posts
FROM people ppl
JOIN person_companies pc ON pc.person_id  = ppl.id
JOIN companies        c  ON c.id          = pc.company_id
GROUP BY ppl.id, ppl.name, ppl.role, ppl.email;

CREATE OR REPLACE VIEW v_posting_today AS
SELECT
  c.id                                      AS company_id,
  c.name,
  c.color,
  cm.name                                   AS content_manager,
  sp.name                                   AS sales_person,
  c.monthly_posts,
  c.fee
FROM companies c
LEFT JOIN people cm ON cm.id = c.content_manager_id
LEFT JOIN people sp ON sp.id = c.sales_person_id
WHERE c.posting_days IS NULL
   OR FIND_IN_SET(
        ELT(DAYOFWEEK(CURDATE()),'Sun','Mon','Tue','Wed','Thu','Fri','Sat'),
        c.posting_days
      ) > 0;

CREATE OR REPLACE VIEW v_revenue_totals AS
SELECT
  COUNT(*)                                  AS total_companies,
  SUM(fee)                                  AS total_monthly_revenue,
  SUM(ROUND(fee * fee_sp_pct / 100, 2))     AS total_sp_earnings,
  SUM(ROUND(fee * fee_cm_pct / 100, 2))     AS total_cm_earnings,
  SUM(ROUND(fee * fee_sm_pct / 100, 2))     AS total_sm_earnings
FROM companies;

-- ============================================================
--  DEFAULT SETTINGS
-- ============================================================
INSERT INTO settings (setting_key, setting_value) VALUES
  ('app_version',    '1.0.0'),
  ('app_name',       'MaxxHits'),
  ('currency',       'USD'),
  ('default_fee_sp', '40'),
  ('default_fee_cm', '40'),
  ('default_fee_sm', '20'),
  ('cron_key',       NULL)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ============================================================
--  DEFAULT ADMIN USER
--  Username: admin — set the password via setadmin.php after
--  import (the placeholder hash below cannot be logged in with).
-- ============================================================
INSERT IGNORE INTO users (id, username, password_hash, role, full_name, email) VALUES (
  'usr_admin',
  'admin',
  '!locked-run-setadmin-php!',
  'admin',
  'Administrator',
  'admin@maxxhits.com'
);
