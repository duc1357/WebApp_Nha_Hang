# Duong Bau Restaurant

Full-stack restaurant ordering and booking system built with PHP, MySQL and vanilla JavaScript.

## Features

- Customer registration/login, profile, password reset via OTP
- Menu browsing, cart, voucher, checkout
- Table booking with preorder deposit
- SePay QR payment webhook
- Admin dashboard for users, menu, orders, bookings, vouchers

## Tech Stack

- PHP 8
- MySQL
- Apache/Laragon
- Vanilla JavaScript
- CSS

## Security Highlights

- Prepared statements
- `password_hash`
- CSRF tokens
- Rate limiting
- Secure upload validation
- Webhook token verification

## Setup

1. Import `Database/duong_bau_restaurant.sql`
2. Copy `.env.example` to `.env`
3. Configure DB, mail, SePay and JWT secret
4. Run migrations in `Database/migrations`

For full deployment details, see [docs/deployment.md](docs/deployment.md).

On Laragon/Windows, if `php` is not available in `PATH`, use the bundled PHP binary directly:

```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe Database\migrate.php status
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe Database\migrate.php
```

## Local Verification

Run the baseline checks before demoing or changing shared code:

```powershell
.\tests\run_php_lint.ps1
.\tests\run_php_unit.ps1
.\tests\run_js_check.ps1
.\tests\static_auth_frontend_check.ps1
.\tests\static_auth_backend_check.ps1
.\tests\static_source_quality_check.ps1
.\tests\smoke\api_smoke.ps1
```

Before running HTTP smoke tests, open `http://restaurant.test` in a browser or run:

```powershell
Invoke-WebRequest http://restaurant.test -UseBasicParsing
```

If it cannot connect, start Laragon/Apache and confirm the virtual host points to this project root.

If Windows blocks local `.ps1` files, run the same scripts with:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_php_unit.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_js_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\static_auth_frontend_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\static_auth_backend_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\static_source_quality_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\auth_lifecycle_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\api_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\security_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\payment_booking_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\admin_observability_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\page_load_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\browser_console_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\browser_workflow_smoke.ps1
```

The smoke test uses `http://restaurant.test` by default. Override it with:

```powershell
$env:RESTAURANT_BASE_URL = "http://restaurant.test"
.\tests\smoke\api_smoke.ps1
```

### Windows PowerShell Encoding Note
Tất cả các file mã nguồn và log trong dự án đều sử dụng chuẩn mã hóa UTF-8. Tuy nhiên, trên môi trường Windows PowerShell mặc định (đặc biệt là phiên bản cũ), các ký tự tiếng Việt có dấu có thể bị hiển thị sai do thiết lập encoding mặc định của hệ thống.

Để hiển thị chính xác tiếng Việt trong PowerShell, bạn nên cấu hình encoding của terminal sang UTF-8 trước khi chạy lệnh:
```powershell
[Console]::InputEncoding = [System.Text.Encoding]::UTF8
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$OutputEncoding = [System.Text.Encoding]::UTF8
```

Hoặc khi đọc nội dung file/log bằng PowerShell, hãy sử dụng tùy chọn `-Encoding UTF8`:
```powershell
Get-Content -Encoding UTF8 .\logs\payment_error.log
```


Latest source-audit verification was captured on 2026-05-21. PHP lint, JavaScript syntax checks, API smoke, security smoke, payment/booking smoke, admin observability smoke, migration status, and HTTP page-load checks passed locally. See [TESTING_CHECKLIST.md](TESTING_CHECKLIST.md) and [docs/operations.md](docs/operations.md) for the recorded handoff details.

`payment_booking_smoke.ps1` calls the SePay webhook over HTTP and requires local `.env` values for `SEPAY_WEBHOOK_TOKEN`, `SEPAY_BANK_NAME`, and `SEPAY_VA_ACCOUNT` to match the application configuration.
`auth_lifecycle_smoke.ps1` creates a temporary local user and verifies admin user edits/deletes revoke existing sessions and JWT refresh.
`browser_console_smoke.ps1` launches local Chrome/Edge in headless mode. Set `CHROME_PATH` if neither browser is installed in a standard Windows location.
`browser_workflow_smoke.ps1` uses the same browser path and checks non-destructive UI workflows such as cart toggle, booking drawer, auth tabs, and admin log controls.

## Demo Accounts

Seed demo accounts locally with non-production credentials before recording or presenting:

| Role | Email | Password | Notes |
| --- | --- | --- | --- |
| Admin | `admin.demo@example.test` | `ChangeMeDemo123!` | Create only in local/staging data. |
| Customer | `customer.demo@example.test` | `ChangeMeDemo123!` | Use for checkout and booking flows. |

Never reuse production passwords or customer data for demos.

## Operations

- Admin health and logs: `admin/logs.php`
- Deployment guide: [docs/deployment.md](docs/deployment.md)
- Operations guide: [docs/operations.md](docs/operations.md)
- Testing checklist: [TESTING_CHECKLIST.md](TESTING_CHECKLIST.md)

## Screenshots

Recommended screenshot set for the portfolio:

- Homepage/menu browsing
- Cart with voucher applied
- Checkout/payment QR modal
- Table booking map with preorder
- Admin dashboard
- Admin voucher management

## Known Limitations

Baseline lint and smoke scripts are available in `tests/`; payment still requires SePay webhook configuration for full external end-to-end verification. Browser console verification should be run manually before release when browser automation is unavailable.
