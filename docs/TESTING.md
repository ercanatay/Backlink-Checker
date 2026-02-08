# Testing Strategy

Run all checks:
```bash
php tools/lint.php
php tests/run.php
```

Test coverage buckets:
- Unit: URL normalization, link classification, locale fallback.
- Integration: queue lifecycle, RRULE schedule handling.
- Security: CSRF validity, rate limiting behavior.
- Localization: 10-locale key parity + RTL marker.
- API: endpoint contract presence + error payload shape.
- E2E smoke: entrypoint compatibility.
- Performance guardrail: 500-URL run limit enforcement.

CI pipeline: `.github/workflows/ci.yml`
