-- ============================================================
--  MIGRATION: Content pipeline (Content Studio + Approval Center)
--  Run this on an EXISTING MaxxHits database. Fresh installs get
--  all of this from sql/maxxhits-schema.sql and can skip it.
-- ============================================================

-- 1. Post statuses for the review workflow
ALTER TABLE posts
  MODIFY status ENUM('draft','review','approved','scheduled','publishing','published','failed','rejected')
    NOT NULL DEFAULT 'draft';

-- 2. Approval tracking on posts
ALTER TABLE posts
  ADD COLUMN approved_by  VARCHAR(255) DEFAULT NULL AFTER publish_attempts,
  ADD COLUMN approved_at  DATETIME     DEFAULT NULL AFTER approved_by,
  ADD COLUMN review_note  TEXT         DEFAULT NULL AFTER approved_at,
  ADD COLUMN ai_generated TINYINT(1)   NOT NULL DEFAULT 0 AFTER review_note;

-- 3. Brand voice for AI generation
ALTER TABLE companies
  ADD COLUMN website_url VARCHAR(500) DEFAULT NULL AFTER color,
  ADD COLUMN brand_voice TEXT         DEFAULT NULL AFTER website_url;

-- 4. AI settings defaults (API keys are saved encrypted by the app)
INSERT INTO settings (setting_key, setting_value) VALUES
  ('ai_provider', 'anthropic'),
  ('ai_model',    'claude-opus-5-5')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
