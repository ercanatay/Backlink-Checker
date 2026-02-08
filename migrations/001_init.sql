CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  display_name TEXT NOT NULL,
  locale TEXT NOT NULL DEFAULT 'en-US',
  is_active INTEGER NOT NULL DEFAULT 1,
  last_login_at TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS roles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS user_roles (
  user_id INTEGER NOT NULL,
  role_id INTEGER NOT NULL,
  created_at TEXT NOT NULL,
  PRIMARY KEY (user_id, role_id),
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS api_tokens (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  token_hash TEXT NOT NULL UNIQUE,
  scopes TEXT NOT NULL,
  last_used_at TEXT,
  expires_at TEXT,
  revoked_at TEXT,
  created_at TEXT NOT NULL,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS projects (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  description TEXT,
  root_domain TEXT NOT NULL,
  created_by INTEGER NOT NULL,
  archived_at TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  FOREIGN KEY(created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS project_members (
  project_id INTEGER NOT NULL,
  user_id INTEGER NOT NULL,
  role TEXT NOT NULL,
  created_at TEXT NOT NULL,
  PRIMARY KEY(project_id, user_id),
  FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS scans (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  project_id INTEGER NOT NULL,
  requested_by INTEGER NOT NULL,
  status TEXT NOT NULL,
  provider TEXT NOT NULL,
  root_domain TEXT NOT NULL,
  total_targets INTEGER NOT NULL,
  processed_targets INTEGER NOT NULL DEFAULT 0,
  correlation_id TEXT NOT NULL,
  error_summary TEXT,
  started_at TEXT,
  finished_at TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY(requested_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS scan_targets (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  scan_id INTEGER NOT NULL,
  url TEXT NOT NULL,
  normalized_url TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'queued',
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  FOREIGN KEY(scan_id) REFERENCES scans(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS scan_results (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  scan_id INTEGER NOT NULL,
  target_id INTEGER NOT NULL,
  source_url TEXT NOT NULL,
  source_domain TEXT,
  final_url TEXT,
  final_domain TEXT,
  http_status INTEGER,
  fetch_status TEXT NOT NULL,
  redirect_chain TEXT,
  robots_noindex INTEGER NOT NULL DEFAULT 0,
  x_robots_noindex INTEGER NOT NULL DEFAULT 0,
  backlink_found INTEGER NOT NULL DEFAULT 0,
  best_link_type TEXT NOT NULL DEFAULT 'none',
  anchor_text TEXT,
  page_authority REAL,
  domain_authority REAL,
  provider_status TEXT,
  error_message TEXT,
  fetched_at TEXT NOT NULL,
  created_at TEXT NOT NULL,
  FOREIGN KEY(scan_id) REFERENCES scans(id) ON DELETE CASCADE,
  FOREIGN KEY(target_id) REFERENCES scan_targets(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS scan_links (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  result_id INTEGER NOT NULL,
  href TEXT,
  resolved_url TEXT,
  rel TEXT,
  link_type TEXT NOT NULL,
  anchor_text TEXT,
  is_target INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL,
  FOREIGN KEY(result_id) REFERENCES scan_results(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS provider_cache (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  provider TEXT NOT NULL,
  cache_key TEXT NOT NULL UNIQUE,
  value_json TEXT NOT NULL,
  expires_at TEXT NOT NULL,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS jobs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  type TEXT NOT NULL,
  payload_json TEXT NOT NULL,
  status TEXT NOT NULL,
  attempts INTEGER NOT NULL DEFAULT 0,
  max_attempts INTEGER NOT NULL DEFAULT 3,
  available_at TEXT NOT NULL,
  locked_at TEXT,
  finished_at TEXT,
  last_error TEXT,
  correlation_id TEXT NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS exports (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  scan_id INTEGER NOT NULL,
  project_id INTEGER NOT NULL,
  format TEXT NOT NULL,
  file_path TEXT,
  status TEXT NOT NULL,
  requested_by INTEGER NOT NULL,
  error_message TEXT,
  created_at TEXT NOT NULL,
  completed_at TEXT,
  FOREIGN KEY(scan_id) REFERENCES scans(id) ON DELETE CASCADE,
  FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY(requested_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS schedules (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  project_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  root_domain TEXT NOT NULL,
  targets_json TEXT NOT NULL,
  rrule TEXT NOT NULL,
  timezone TEXT NOT NULL DEFAULT 'UTC',
  next_run_at TEXT NOT NULL,
  last_run_at TEXT,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_by INTEGER NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY(created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS schedule_runs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  schedule_id INTEGER NOT NULL,
  scan_id INTEGER,
  status TEXT NOT NULL,
  error_message TEXT,
  created_at TEXT NOT NULL,
  completed_at TEXT,
  FOREIGN KEY(schedule_id) REFERENCES schedules(id) ON DELETE CASCADE,
  FOREIGN KEY(scan_id) REFERENCES scans(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS notifications (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  project_id INTEGER NOT NULL,
  event_type TEXT NOT NULL,
  channel TEXT NOT NULL,
  destination TEXT NOT NULL,
  secret TEXT,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_by INTEGER NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY(created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS webhook_deliveries (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  notification_id INTEGER NOT NULL,
  scan_id INTEGER,
  endpoint TEXT NOT NULL,
  payload_json TEXT NOT NULL,
  status_code INTEGER,
  attempt INTEGER NOT NULL,
  success INTEGER NOT NULL DEFAULT 0,
  response_excerpt TEXT,
  created_at TEXT NOT NULL,
  FOREIGN KEY(notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
  FOREIGN KEY(scan_id) REFERENCES scans(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS audit_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER,
  action TEXT NOT NULL,
  target_type TEXT,
  target_id TEXT,
  metadata_json TEXT,
  ip_address TEXT,
  user_agent TEXT,
  created_at TEXT NOT NULL,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS settings (
  key TEXT PRIMARY KEY,
  value_json TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS telemetry_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  event_name TEXT NOT NULL,
  event_payload_json TEXT NOT NULL,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS i18n_catalog_versions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  locale TEXT NOT NULL,
  version TEXT NOT NULL,
  last_reviewed TEXT NOT NULL,
  checksum TEXT NOT NULL,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS rate_limits (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  key TEXT NOT NULL UNIQUE,
  window_started_at TEXT NOT NULL,
  hit_count INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS saved_views (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  project_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  filters_json TEXT NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_scans_project_status ON scans(project_id, status);
CREATE INDEX IF NOT EXISTS idx_scan_results_scan ON scan_results(scan_id);
CREATE INDEX IF NOT EXISTS idx_scan_targets_scan ON scan_targets(scan_id);
CREATE INDEX IF NOT EXISTS idx_jobs_status_available ON jobs(status, available_at);
CREATE INDEX IF NOT EXISTS idx_provider_cache_provider_key ON provider_cache(provider, cache_key);
CREATE INDEX IF NOT EXISTS idx_schedules_active_next ON schedules(is_active, next_run_at);
CREATE INDEX IF NOT EXISTS idx_telemetry_event_name ON telemetry_events(event_name);
