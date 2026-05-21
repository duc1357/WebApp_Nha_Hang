# Local Test Commands

Run these from the project root on Laragon/Windows.

## PHP Syntax

```powershell
.\tests\run_php_lint.ps1
```

If Windows blocks local scripts, run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1
```

Expected result:

```text
PHP lint passed
```

## JavaScript Syntax

```powershell
.\tests\run_js_check.ps1
```

If Windows blocks local scripts, run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_js_check.ps1
```

Expected result:

```text
JS syntax check passed
```

## API Smoke Test

```powershell
.\tests\smoke\api_smoke.ps1
```

If Windows blocks local scripts, run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\smoke\api_smoke.ps1
```

Expected result:

```text
PASS csrf
PASS menu
PASS tables
PASS featured_reviews
PASS booked_tables
PASS admin_login_and_stats
```

## Security Smoke Test

```powershell
.\tests\smoke\security_smoke.ps1
```

If Windows blocks local scripts, run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\smoke\security_smoke.ps1
```

Expected result:

```text
PASS admin_stats_requires_login
PASS post_without_csrf_rejected
PASS wrong_password_rejected
PASS webhook_requires_authorization
```

## Payment and Booking Smoke Test

```powershell
.\tests\smoke\payment_booking_smoke.ps1
```

If Windows blocks local scripts, run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\smoke\payment_booking_smoke.ps1
```

Expected result:

```text
PASS empty_cart_rejected
PASS invalid_table_rejected_before_order_insert
PASS past_booking_rejected
PASS empty_preorder_rejected
```

## Admin Observability Smoke Test

```powershell
.\tests\smoke\admin_observability_smoke.ps1
```

If Windows blocks local scripts, run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\smoke\admin_observability_smoke.ps1
```

Expected result:

```text
PASS admin_health
PASS admin_logs
PASS admin_logs_requires_login
PASS direct_log_access_blocked
```

Set `RESTAURANT_BASE_URL` to test another local host:

```powershell
$env:RESTAURANT_BASE_URL = "http://restaurant.test"
.\tests\smoke\api_smoke.ps1
```
