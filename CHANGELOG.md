# Changelog

All notable changes to Backlink Checker Pro are documented in this file.

## [3.0.0] - 2026-02-10

### Added

#### Competitor Backlink Analysis
- New `CompetitorService` for managing competitor domains per project (max 20)
- Compare backlink sources between your site and competitors
- API v2 endpoints for listing and adding competitors

#### Backlink Health Score
- New `HealthScoreService` with weighted scoring algorithm (backlink ratio, dofollow ratio, avg DA, toxic links)
- Health score snapshots stored per scan with project-level trend tracking
- Toxic link detection (DA < 10 threshold)
- API v2 endpoint for health score retrieval

#### Google Disavow File Generator
- New `DisavowService` for managing domain and URL disavow rules per project
- One-click Google Disavow Tool compatible `.txt` file generation
- Auto-suggest toxic backlinks from scan results
- Web UI and API v2 support

#### Anchor Text Analysis
- New `AnchorAnalysisService` with anchor text classification (exact match, partial match, branded, naked URL, generic)
- Over-optimization detection (warns if exact match > 60%)
- Aggregated anchor summaries per scan
- API v2 endpoint for anchor analysis data

#### Link Velocity Tracking
- New `VelocityService` comparing consecutive scans for gained/lost backlinks
- Per-project velocity history with snapshot storage
- API v2 endpoint for velocity data

#### Bulk URL Import
- New `ImportService` supporting CSV and XML Sitemap formats
- Duplicate URL filtering and validation
- Import directly creates scan with imported URLs

#### Additional SEO Providers
- New `AhrefsMetricsProvider` — Domain Rating (DR), URL Rating (UR)
- New `SemrushMetricsProvider` — Authority Score
- New `MajesticMetricsProvider` — Trust Flow, Citation Flow
- Extended `ProviderType` enum with `ahrefs`, `semrush`, `majestic`
- All providers use existing cache infrastructure with configurable TTL

#### Two-Factor Authentication (2FA)
- New `TwoFactorService` with TOTP (RFC 6238) implementation
- Google Authenticator / Authy compatible
- Recovery codes (8 codes, single-use)
- Clock drift tolerance (±30 seconds)
- Enable/disable via settings UI

#### Scheduled Email Reports
- New `ReportService` with daily/weekly/monthly report scheduling
- Multi-recipient support (comma-separated emails)
- Report includes: scan summary, backlink count, avg DA, health score, velocity
- `sendDueReports()` method for scheduler integration

#### User Activity Dashboard
- New `ActivityService` with filterable audit log queries
- Activity stats: total actions, today's count, top users, top actions
- CSV export of activity logs
- New `/activity` route (admin-only)
- Dedicated `activity.php` template

#### Dark Mode
- User theme preference (`light`/`dark`) stored in database
- Theme toggle in settings UI
- CSS custom properties ready for dark theme rendering

#### REST API v2
- New `/api/v2` routes with pagination support (page/per_page params)
- Enhanced scan endpoint includes health score data
- New endpoints: health, anchors, velocity, competitors, disavow, import
- Rate limit headers in responses

#### Webhook Event Enrichment
- Extended notification system supports new event types
- `backlink.lost`, `backlink.gained`, `health_score.drop`, `scan.error` events

#### Robots.txt Detailed Analysis
- Enhanced `BacklinkAnalyzerService` with deeper robots directive parsing
- X-Robots-Tag header analysis with noarchive support

### Changed

- `ProviderType` enum extended with `AHREFS`, `SEMRUSH`, `MAJESTIC` constants
- `App.php` constructor now wires 9 additional services
- `WebController` accepts 9 new service dependencies
- `ApiController` accepts 6 new service dependencies
- CSP header updated to allow Chart.js CDN (`cdn.jsdelivr.net`)
- Feature matrix expanded from 40 to 55 professional features

### Database

- New migration `002_features.sql` adding:
  - `competitors` table
  - `disavow_rules` table
  - `health_scores` table
  - `anchor_summaries` table
  - `velocity_snapshots` table
  - `report_schedules` table
  - `users.totp_secret`, `users.totp_enabled`, `users.recovery_codes` columns
  - `users.theme` column

### Localization

- 76 new translation keys added to all 10 locale catalogs
- Categories: competitor, health, disavow, anchor, velocity, import, provider, report, 2fa, activity, theme, nav

## [2.1.5] - 2026-02-10

- Added SSRF validation to `HttpClient` request paths (`GET` and `POST`)
- Ensured redirect-following scans stay protected
- Added regression coverage for SSRF protection

## [2.0.0] - 2026-02-08

- Initial modular release with 40 professional features
- Asynchronous scan engine with queue workers
- Team RBAC with admin/editor/viewer roles
- REST API v1 with token authentication
- 10-language localization with Arabic RTL support
- Moz provider integration with caching
- Webhook delivery with HMAC-SHA256 signing
- Automatic application updater
