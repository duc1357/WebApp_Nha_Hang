# Source Code Upgrade Roadmap Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Upgrade the restaurant application in safe phases so it becomes easier to test, maintain, secure, and extend without rewriting the current PHP/MySQL/vanilla JS stack.

**Architecture:** Keep the current zero-dependency PHP architecture, but add stronger internal boundaries around request handling, validation, services, logging, and frontend modules. Each phase should leave the app runnable on Laragon and avoid unrelated rewrites.

**Tech Stack:** PHP 8.3, MySQL, Apache/Laragon, vanilla JavaScript, vanilla CSS, custom migration runner, custom logging, no Composer/framework dependency.

---

## Current Baseline

The project is a PHP/MySQL restaurant ordering and booking system with public pages, customer auth, admin dashboard, table booking, cart checkout, SePay QR payment, and webhook handling.

Recent checks passed:
- PHP lint passed for 83 PHP files.
- JavaScript syntax check passed for 8 JS files.
- `Database/migrate.php status` connects to DB and shows the token version migration has run.
- `restaurant.test` returns HTTP 200.
- Public endpoints for CSRF, menu, tables, reviews, and booked tables respond successfully.
- Admin session flow works with demo credentials: get CSRF, login admin, fetch dashboard stats.

Known project constraints:
- No Composer/framework dependency, by architecture decision.
- Shared-hosting friendly deploy model.
- Existing user-facing flows should continue working after each phase.
- Worktree is currently dirty; preserve unrelated existing changes.

---

## Upgrade Strategy

This roadmap should be implemented as a series of smaller implementation plans, not as one giant patch.

Recommended order:

1. Baseline verification and safety net.
2. API response and validation standardization.
3. Security hardening.
4. Order, booking, and payment robustness.
5. Admin observability and operations.
6. Frontend UX and maintainability.
7. Performance, deployment, and documentation.

Each phase must produce working, testable software on its own.

---

### Task 1: Baseline Verification and Test Harness

**Files:**
- Create: `tests/README.md`
- Create: `tests/run_php_lint.ps1`
- Create: `tests/run_js_check.ps1`
- Create: `tests/smoke/api_smoke.ps1`
- Modify: `README.md`
- Modify: `TESTING_CHECKLIST.md`

**Purpose:** Add a repeatable local safety net before changing behavior.

- [x] **Step 1: Document the current test commands**

Create `tests/README.md` with:

````markdown
# Local Test Commands

Run these from the project root on Laragon/Windows.

## PHP syntax

```powershell
.\tests\run_php_lint.ps1
```

Expected result:

```text
PHP lint passed
```

## JavaScript syntax

```powershell
.\tests\run_js_check.ps1
```

Expected result:

```text
JS syntax check passed
```

## API smoke test

```powershell
.\tests\smoke\api_smoke.ps1
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
````

- [x] **Step 2: Add PHP lint runner**

Create `tests/run_php_lint.ps1`:

```powershell
$ErrorActionPreference = "Stop"
$php = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"

if (-not (Test-Path $php)) {
    Write-Error "PHP binary not found at $php"
}

$files = rg --files -g "*.php"
$failed = 0

foreach ($file in $files) {
    $output = & $php -l $file 2>&1
    if ($LASTEXITCODE -ne 0) {
        $failed++
        Write-Output "FAIL $file"
        Write-Output $output
    }
}

if ($failed -gt 0) {
    Write-Error "PHP lint failed for $failed file(s)"
}

Write-Output "PHP lint passed for $($files.Count) file(s)"
```

- [x] **Step 3: Add JS syntax runner**

Create `tests/run_js_check.ps1`:

```powershell
$ErrorActionPreference = "Stop"

$files = rg --files -g "*.js"
$failed = 0

foreach ($file in $files) {
    $output = node --check $file 2>&1
    if ($LASTEXITCODE -ne 0) {
        $failed++
        Write-Output "FAIL $file"
        Write-Output $output
    }
}

if ($failed -gt 0) {
    Write-Error "JS syntax check failed for $failed file(s)"
}

Write-Output "JS syntax check passed for $($files.Count) file(s)"
```

- [x] **Step 4: Add API smoke runner**

Create `tests/smoke/api_smoke.ps1`:

```powershell
$ErrorActionPreference = "Stop"
$baseUrl = $env:RESTAURANT_BASE_URL

if ([string]::IsNullOrWhiteSpace($baseUrl)) {
    $baseUrl = "http://restaurant.test"
}

function Assert-SuccessJson {
    param(
        [string] $Name,
        [string] $Url
    )

    $response = Invoke-RestMethod -Uri $Url -TimeoutSec 10
    if ($null -eq $response) {
        throw "$Name returned empty response"
    }
    Write-Output "PASS $Name"
    return $response
}

Assert-SuccessJson "csrf" "$baseUrl/api/auth/get_csrf.php" | Out-Null
Assert-SuccessJson "menu" "$baseUrl/api/menu/get_menu.php" | Out-Null
Assert-SuccessJson "tables" "$baseUrl/api/tables/read.php" | Out-Null
Assert-SuccessJson "featured_reviews" "$baseUrl/api/public/get_featured_reviews.php" | Out-Null
Assert-SuccessJson "booked_tables" "$baseUrl/api/public/get_booked_tables.php?date=2026-05-20&time=18:00" | Out-Null

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$csrf = (Invoke-RestMethod -Uri "$baseUrl/api/auth/get_csrf.php" -WebSession $session -TimeoutSec 10).csrf_token
$body = @{
    email = "admin.demo@example.test"
    password = "ChangeMeDemo123!"
} | ConvertTo-Json

$login = Invoke-RestMethod `
    -Uri "$baseUrl/api/admin/login.php" `
    -Method Post `
    -Body $body `
    -ContentType "application/json; charset=utf-8" `
    -Headers @{ "X-CSRF-Token" = $csrf } `
    -WebSession $session `
    -TimeoutSec 10

if ($login.success -ne $true) {
    throw "admin login failed"
}

$stats = Invoke-RestMethod -Uri "$baseUrl/api/admin/get_stats.php" -WebSession $session -TimeoutSec 10
if ($stats.success -ne $true) {
    throw "admin stats failed"
}

Write-Output "PASS admin_login_and_stats"
```

- [x] **Step 5: Run baseline verification**

Run:

```powershell
.\tests\run_php_lint.ps1
.\tests\run_js_check.ps1
.\tests\smoke\api_smoke.ps1
```

Expected:

```text
PHP lint passed
JS syntax check passed
PASS csrf
PASS menu
PASS tables
PASS featured_reviews
PASS booked_tables
PASS admin_login_and_stats
```

- [ ] **Step 6: Commit**

```bash
git add tests README.md TESTING_CHECKLIST.md
git commit -m "test: add local verification harness"
```

---

### Task 2: API Response and Validation Standardization

**Files:**
- Modify: `api/base.php`
- Create: `api/services/response_service.php`
- Create: `api/services/request_service.php`
- Create: `api/services/validation_service.php`
- Modify selected endpoints first: `api/auth/login.php`, `api/auth/register.php`, `api/payment/create_payment.php`, `api/user/book_table.php`

**Purpose:** Reduce repeated JSON/header/input handling and make API behavior predictable.

- [x] **Step 1: Create a dedicated detailed plan**

Create `docs/superpowers/plans/2026-05-20-api-standardization.md`.

The plan must cover:
- `ResponseService::success(array $data = [], int $status = 200): void`
- `ResponseService::error(string $message, int $status = 400, array $extra = []): void`
- `RequestService::json(): array`
- `ValidationService` helpers for required fields, email, phone, integer ranges, money values, dates, and times.
- Conversion of four pilot endpoints before touching the rest.

- [x] **Step 2: Preserve current public response shapes**

The pilot conversion must keep existing frontend fields such as:

```json
{
  "success": true,
  "message": "Đăng nhập thành công"
}
```

and:

```json
{
  "success": false,
  "message": "CSRF Validation Failed"
}
```

- [x] **Step 3: Run verification**

Run:

```powershell
.\tests\run_php_lint.ps1
.\tests\smoke\api_smoke.ps1
```

Expected:

```text
PHP lint passed
PASS admin_login_and_stats
```

- [ ] **Step 4: Commit**

```bash
git add api tests docs/superpowers/plans
git commit -m "refactor: standardize pilot API responses"
```

---

### Task 3: Security Hardening

**Files:**
- Modify: `.htaccess`
- Modify: `config/session_config.php`
- Modify: `api/services/csrf_service.php`
- Modify: `api/services/rate_limit_service.php`
- Modify: `api/services/jwt_service.php`
- Modify: `api/auth/login.php`
- Modify: `api/auth/logout.php`
- Modify: `api/auth/refresh_token.php`
- Modify: `api/auth/change_password.php`
- Modify: `api/payment/webhook.php`

**Purpose:** Tighten security around sessions, CSRF, JWT revocation, rate limits, upload validation, and webhook authentication.

- [x] **Step 1: Create a dedicated detailed plan**

Create `docs/superpowers/plans/2026-05-20-security-hardening.md`.

The plan must include:
- Session cookie review for local HTTP and production HTTPS.
- Standard CSRF failure format.
- Consistent rate limit keys that include IP and user ID where available.
- JWT secret validation at startup or first use.
- Webhook token verification test cases.
- Upload avatar MIME/type/size test cases.

- [x] **Step 2: Add security smoke checks**

Extend `tests/smoke/api_smoke.ps1` or add `tests/smoke/security_smoke.ps1` to verify:
- Admin stats returns 401 before login.
- POST without CSRF returns 403.
- Login with wrong password returns 401.
- Webhook without token returns unauthorized response.

- [x] **Step 3: Run verification**

Run:

```powershell
.\tests\run_php_lint.ps1
.\tests\smoke\api_smoke.ps1
.\tests\smoke\security_smoke.ps1
```

- [ ] **Step 4: Commit**

```bash
git add .htaccess config api tests docs/superpowers/plans
git commit -m "security: harden auth and webhook boundaries"
```

---

### Task 4: Order, Booking, and Payment Robustness

**Files:**
- Modify: `api/services/OrderService.php`
- Modify: `api/payment/create_payment.php`
- Modify: `api/payment/check_status.php`
- Modify: `api/payment/check_status_booking.php`
- Modify: `api/payment/webhook.php`
- Modify: `api/user/book_table.php`
- Modify: `js/cart.js`
- Modify: `js/payment.js`
- Modify: `js/booking.js`
- Add migrations only if schema gaps are confirmed.

**Purpose:** Make checkout, table booking, preorders, deposits, and SePay status handling more reliable.

- [x] **Step 1: Create a dedicated detailed plan**

Create `docs/superpowers/plans/2026-05-20-order-booking-payment-robustness.md`.

The plan must answer:
- What statuses are valid for orders, bookings, and payments?
- Which transitions are allowed?
- When should voucher usage increment?
- How are duplicate webhook events handled?
- How does booking deposit map to final payment status?
- What should the frontend show for pending, paid, failed, expired, and cancelled states?

- [x] **Step 2: Add transition tests or smoke scripts**

Use CLI or HTTP smoke tests to verify:
- Invalid table input is rejected without SQL error.
- Empty cart is rejected.
- Voucher discount cannot make total negative.
- Duplicate webhook does not double-increment voucher usage.
- Booking overlap rejects conflicting table/time.

- [x] **Step 3: Run verification**

Run:

```powershell
.\tests\run_php_lint.ps1
.\tests\run_js_check.ps1
.\tests\smoke\api_smoke.ps1
```

- [ ] **Step 4: Commit**

```bash
git add api js tests docs/superpowers/plans Database/migrations
git commit -m "fix: harden order booking and payment flows"
```

---

### Task 5: Admin Observability and Operations

**Files:**
- Modify: `admin/sidebar.php`
- Create: `admin/logs.php`
- Create: `api/admin/get_logs.php`
- Create: `api/admin/get_system_health.php`
- Modify: `api/services/logger_service.php`
- Modify: `.htaccess`
- Modify: `style.css`

**Purpose:** Give admins a safe operational view of logs, health, payment events, and suspicious activity.

- [x] **Step 1: Create a dedicated detailed plan**

Create `docs/superpowers/plans/2026-05-20-admin-observability.md`.

The plan must include:
- Admin-only log viewer.
- Pagination over NDJSON logs.
- Channel filter: `auth`, `payment`, `security`, `app`.
- Safe redaction for email, tokens, raw webhook payloads, and IPs if needed.
- System health endpoint for DB connectivity, migration status, writable logs, and current PHP version.

- [x] **Step 2: Preserve log directory protection**

Confirm `.htaccess` still blocks direct web access to `logs/`.

- [x] **Step 3: Run verification**

Run:

```powershell
.\tests\run_php_lint.ps1
.\tests\smoke\api_smoke.ps1
```

Manually verify:
- Login as admin.
- Open `/admin/logs.php`.
- Confirm unauthenticated access to log API returns 401.

- [ ] **Step 4: Commit**

```bash
git add admin api style.css .htaccess tests docs/superpowers/plans
git commit -m "feat: add admin observability views"
```

---

### Task 6: Frontend UX and Maintainability

**Files:**
- Modify: `index.html`
- Modify: `booking.html`
- Modify: `login.html`
- Modify: `profile.html`
- Modify: `style.css`
- Modify: `js/utils.js`
- Modify: `js/cart.js`
- Modify: `js/menu.js`
- Modify: `js/payment.js`
- Modify: `js/booking.js`
- Modify: `js/profile.js`
- Modify: `js/reviews.js`

**Purpose:** Improve customer and admin usability without adding a bundler yet.

- [x] **Step 1: Create a dedicated detailed plan**

Create `docs/superpowers/plans/2026-05-20-frontend-ux-maintainability.md`.

The plan must include:
- Shared loading/error/toast helpers in `js/utils.js`.
- Consistent empty states for menu, cart, bookings, orders, and reviews.
- Responsive audit for mobile checkout and booking.
- Accessible button labels, focus states, and form errors.
- CSS cleanup focused only on touched components.

- [x] **Step 2: Add browser verification checklist**

Update `TESTING_CHECKLIST.md` with:
- Homepage menu browsing.
- Cart add/remove/update quantity.
- Voucher apply/remove.
- Checkout cash flow.
- Checkout QR flow.
- Booking table selection.
- Profile order history.
- Admin dashboard navigation.

- [x] **Step 3: Run verification**

Run:

```powershell
.\tests\run_js_check.ps1
.\tests\smoke\api_smoke.ps1
```

Then verify in browser at:

```text
http://restaurant.test/
http://restaurant.test/booking.html
http://restaurant.test/login.html
http://restaurant.test/profile.html
http://restaurant.test/admin/
```

- [ ] **Step 4: Commit**

```bash
git add *.html admin js style.css TESTING_CHECKLIST.md docs/superpowers/plans
git commit -m "feat: improve frontend user flows"
```

---

### Task 7: Performance, Deployment, and Documentation

**Files:**
- Modify: `README.md`
- Modify: `ARCHITECTURE.md`
- Modify: `.env.example`
- Modify: `.htaccess`
- Modify: `TESTING_CHECKLIST.md`
- Create: `docs/deployment.md`
- Create: `docs/operations.md`

**Purpose:** Make the app easier to deploy, demo, operate, and hand off.

- [x] **Step 1: Create a dedicated detailed plan**

Create `docs/superpowers/plans/2026-05-20-deployment-operations.md`.

The plan must include:
- Local Laragon setup.
- Required `.env` keys.
- Database import and migration order.
- Demo account seeding guidance.
- SePay webhook setup.
- Mail setup.
- Log maintenance.
- Backup and restore basics.

- [x] **Step 2: Review static asset handling**

Check:
- Image paths under `photo/`.
- Cache headers in `.htaccess`.
- CSS/JS load order in HTML pages.

- [x] **Step 3: Run final verification**

Run:

```powershell
.\tests\run_php_lint.ps1
.\tests\run_js_check.ps1
.\tests\smoke\api_smoke.ps1
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe Database\migrate.php status
```

- [ ] **Step 4: Commit**

```bash
git add README.md ARCHITECTURE.md .env.example .htaccess TESTING_CHECKLIST.md docs
git commit -m "docs: add deployment and operations guide"
```

---

## Phase Acceptance Criteria

Before moving from one phase to the next:

- PHP lint passes.
- JS syntax check passes when JS files changed.
- API smoke test passes.
- No unrelated files are modified.
- User-facing flows touched by the phase are manually verified.
- Each phase is committed separately.
- Any schema change includes a migration and rollback notes.

---

## Recommended First Implementation Plan

Start with Task 1: Baseline Verification and Test Harness.

Reason:
- It does not change application behavior.
- It gives every later phase a safety net.
- It turns the current manual checks into repeatable commands.
- It fits the existing zero-dependency architecture.

After Task 1 is complete, proceed to Task 2 if the goal is maintainability, or Task 3 if the goal is production hardening first.

---

## Self-Review

Spec coverage:
- Stability covered by Task 1.
- Maintainability covered by Task 2 and Task 6.
- Security covered by Task 3.
- Payment and booking correctness covered by Task 4.
- Admin operations covered by Task 5.
- Deployment and documentation covered by Task 7.

Placeholder scan:
- No TBD/TODO placeholders remain.
- Large implementation areas are intentionally decomposed into dedicated child plans.

Scope check:
- This is a roadmap plan, not a single implementation patch. The app has multiple independent subsystems, so each subsystem gets its own dedicated plan before code changes.

Ambiguity check:
- The first executable phase is explicitly Task 1.
- Later phases define exact target files and verification gates.
