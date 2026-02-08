# API v1 Reference

Base path: `/api/v1`

Authentication:
- Obtain bearer token via `POST /api/v1/auth/login`
- Send `Authorization: Bearer <token>`
- Scope-based access is enforced.

## Endpoints

### `POST /auth/login`
Request:
```json
{
  "email": "admin@example.com",
  "password": "ChangeThisNow!",
  "token_name": "CI Token",
  "scopes": ["*"]
}
```
Response:
```json
{
  "token": "bct_...",
  "token_id": 1,
  "user": {"id": 1, "email": "admin@example.com", "roles": ["admin"]}
}
```

### `POST /projects`
Scopes: `projects:write`
```json
{
  "name": "Main Campaign",
  "root_domain": "example.com",
  "description": "External link monitoring"
}
```

### `POST /scans`
Scopes: `scans:write`
```json
{
  "project_id": 1,
  "root_domain": "example.com",
  "provider": "moz",
  "urls": [
    "https://site-a.example/article-1",
    "https://site-b.example/post"
  ]
}
```
Response: `202 Accepted` with `scan_id`.

### `GET /scans/{scanId}`
Scopes: `scans:read`

### `GET /scans/{scanId}/results`
Scopes: `scans:read`
Query params:
- `status`
- `link_type`
- `search`
- `sort`: `id_desc`, `da_desc`, `da_asc`, `status_asc`

### `POST /scans/{scanId}/cancel`
Scopes: `scans:write`

### `POST /schedules`
Scopes: `schedules:write`
```json
{
  "project_id": 1,
  "name": "Daily morning",
  "root_domain": "example.com",
  "targets": ["https://publisher.example/page"],
  "rrule": "FREQ=HOURLY;INTERVAL=24",
  "timezone": "UTC"
}
```
Supported RRULE modes:
- Hourly: `FREQ=HOURLY;INTERVAL=<hours>`
- Weekly: `FREQ=WEEKLY;BYDAY=MO,WE;BYHOUR=9;BYMINUTE=0`

### `GET /exports/{exportId}`
Scopes: `exports:read`
Formats: CSV, TSV/TXT, XLSX, JSON

### `POST /webhooks/test`
Scopes: `webhooks:test`
```json
{
  "url": "https://hooks.example/test",
  "secret": "optional-secret"
}
```

## Error Schema
```json
{
  "error": {
    "code": "validation_error",
    "message": "Invalid root domain"
  }
}
```
