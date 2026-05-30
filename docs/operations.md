# Operations Guide

Use this guide when demoing, monitoring, backing up, or troubleshooting the app.

## Routine Health Checks

Admin UI:

```text
/admin/logs.php
```

Health API:

```text
/api/admin/get_system_health.php
```

The health API checks:
- Database connectivity.
- `logs/` writability.
- `logs/rate_limits/` writability.
- `_migrations` table presence.
- PHP version.

## Log Channels

Logs are stored as NDJSON files under `logs/`.

Channels:
- `auth.log`: login/logout/register events.
- `payment.log`: payment and webhook events.
- `security.log`: suspicious requests and rejected webhook attempts.
- `app.log`: general application logs.

Direct access to `logs/` is blocked by `.htaccess`. Admins should use:

```text
/admin/logs.php
```

## Verification Commands

Run from project root:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_php_unit.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_js_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\static_auth_frontend_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\static_auth_backend_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\auth_lifecycle_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\api_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\security_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\payment_booking_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\admin_observability_smoke.ps1
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe Database\migrate.php status
```

Latest local audit verification captured on 2026-05-21:

- `run_php_lint.ps1`: passed for 91 PHP files.
- `run_js_check.ps1`: passed for 17 JavaScript files.
- `api_smoke.ps1`: passed public API and admin login/stats checks.
- `security_smoke.ps1`: passed auth, CSRF and webhook rejection checks.
- `payment_booking_smoke.ps1`: passed invalid checkout/booking and duplicate webhook idempotency checks.
- `admin_observability_smoke.ps1`: passed admin health/log checks and direct log blocking.
- Migration status: `2026_04_29_120000_add_token_version_to_users` is `Ran`.
- HTTP page-load check: `/`, `/profile.html`, `/admin/`, `/admin/bookings.php`, and `/admin/logs.php` returned `200`.

Browser console verification should still be performed during release handoff because this audit session could not access the browser automation runtime.

## Backup

Back up these items together:
- MySQL database.
- `.env`.
- `photo/` uploads.
- `logs/` if audit history matters.

`photo/avatars/` and `photo/menu/` are runtime upload directories and are intentionally ignored by Git. Restore or mount them with the database during deploys, otherwise records that reference uploaded images will render broken image links.

Local MySQL example:

```powershell
mysqldump -u root duong_bau_restaurant > backup-duong-bau.sql
```

If `mysqldump` is not in PATH, use the MySQL binary from Laragon.

## Restore

1. Restore files.
2. Restore `.env`.
3. Import database backup.
4. Run migrations:

```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe Database\migrate.php
```

5. Run smoke tests.

## Incident Checklist

Login problem:
- Check `logs/auth.log`.
- Confirm sessions are writable.
- Admin user edits and soft-deletes intentionally revoke existing sessions/tokens through `token_version`.
- Sessions without `token_version` are intentionally rejected after the auth hardening update; ask users to log in again after deploy if they hit a 401.
- Confirm `JWT_SECRET` is at least 32 characters.

Checkout problem:
- Check `logs/payment_error.log`.
- Check `logs/payment.log`.
- Confirm menu items are active and not soft-deleted.
- Confirm voucher limits and expiry.

Webhook problem:
- Check `logs/payment.log` and `logs/security.log`.
- Confirm `SEPAY_WEBHOOK_TOKEN`.
- Confirm SePay sends `Authorization: Apikey <token>`.
- Confirm `SEPAY_VA_ACCOUNT` and `SEPAY_BANK_NAME`.
- Confirm transfer content includes `DH{id}` or `BKG{id}`.

Booking problem:
- Confirm selected table exists.
- Confirm booking time is not in the past.
- Confirm table capacity is high enough.
- Confirm no booking exists within the overlap window.

Admin logs problem:
- Confirm admin session is active.
- Confirm `logs/` is writable.
- Confirm `.htaccess` still blocks direct `/logs/` access.

## Production Hardening Notes

- Rotate `SEPAY_WEBHOOK_TOKEN` and `JWT_SECRET` before any public deployment.
- Keep `TRUST_PROXY_HEADERS=false` unless Apache receives traffic only from a trusted proxy/CDN.
- If enabling proxy headers, set `TRUSTED_PROXY_IPS` to the direct proxy IPs that connect to Apache.
- Revenue exports are intentionally date-limited; use `from=YYYY-MM-DD&to=YYYY-MM-DD` for bounded exports.

## Operational Rules

- Never commit `.env`.
- Never reuse production passwords for demos.
- Rotate `SEPAY_WEBHOOK_TOKEN` if it leaks.
- Rotate `JWT_SECRET` if it leaks; users will need to log in again.
- Export or back up the database before running manual SQL changes.
