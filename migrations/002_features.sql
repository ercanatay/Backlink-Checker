-- Migration 002: New features (v3.0.0)
-- Competitor Analysis, Health Score, Disavow, Anchor Text, Velocity, Bulk Import,
-- Additional Providers, 2FA, Reports, Webhook Events, Activity Dashboard, Dark Mode, API v2

-- Competitor domains per project
CREATE TABLE IF NOT EXISTS competitors (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  project_id INTEGER NOT NULL,
  domain TEXT NOT NULL,
  created_by INTEGER NOT NULL,
  created_at TEXT NOT NULL,
  FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY(created_by) REFERENCES users(id)
);
CREATE INDEX IF NOT EXISTS idx_competitors_project ON competitors(project_id);

-- Disavow rules per project
CREATE TABLE IF NOT EXISTS disavow_rules (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  project_id INTEGER NOT NULL,
  rule_type TEXT NOT NULL DEFAULT 'domain',
  value TEXT NOT NULL,
  reason TEXT,
  created_by INTEGER NOT NULL,
  created_at TEXT NOT NULL,
  FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY(created_by) REFERENCES users(id)
);
CREATE INDEX IF NOT EXISTS idx_disavow_project ON disavow_rules(project_id);

-- Health score snapshots per scan
CREATE TABLE IF NOT EXISTS health_scores (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  scan_id INTEGER NOT NULL UNIQUE,
  project_id INTEGER NOT NULL,
  overall_score REAL NOT NULL DEFAULT 0,
  backlink_ratio REAL NOT NULL DEFAULT 0,
  dofollow_ratio REAL NOT NULL DEFAULT 0,
  avg_da REAL NOT NULL DEFAULT 0,
  toxic_count INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL,
  FOREIGN KEY(scan_id) REFERENCES scans(id) ON DELETE CASCADE,
  FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_health_scores_project ON health_scores(project_id);

-- Anchor text aggregation per scan
CREATE TABLE IF NOT EXISTS anchor_summaries (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  scan_id INTEGER NOT NULL,
  anchor_text TEXT NOT NULL,
  occurrences INTEGER NOT NULL DEFAULT 1,
  category TEXT NOT NULL DEFAULT 'generic',
  created_at TEXT NOT NULL,
  FOREIGN KEY(scan_id) REFERENCES scans(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_anchor_summaries_scan ON anchor_summaries(scan_id);

-- Link velocity snapshots
CREATE TABLE IF NOT EXISTS velocity_snapshots (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  project_id INTEGER NOT NULL,
  scan_id INTEGER NOT NULL,
  gained INTEGER NOT NULL DEFAULT 0,
  lost INTEGER NOT NULL DEFAULT 0,
  net INTEGER NOT NULL DEFAULT 0,
  snapshot_date TEXT NOT NULL,
  created_at TEXT NOT NULL,
  FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY(scan_id) REFERENCES scans(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_velocity_project ON velocity_snapshots(project_id);

-- Scheduled email reports
CREATE TABLE IF NOT EXISTS report_schedules (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  project_id INTEGER NOT NULL,
  frequency TEXT NOT NULL DEFAULT 'weekly',
  recipients TEXT NOT NULL,
  last_sent_at TEXT,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_by INTEGER NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY(created_by) REFERENCES users(id)
);

-- 2FA: add TOTP columns to users table
ALTER TABLE users ADD COLUMN totp_secret TEXT;
ALTER TABLE users ADD COLUMN totp_enabled INTEGER NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN recovery_codes TEXT;

-- Dark mode: add theme preference to users
ALTER TABLE users ADD COLUMN theme TEXT NOT NULL DEFAULT 'light';
