# Post-Audit Update Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stabilize the post-audit codebase into a reviewable, release-ready update with browser verification, focused runtime coverage, clean documentation, and safe git handoff.

**Architecture:** Keep the current PHP/MySQL/vanilla JavaScript architecture. This plan does not introduce frameworks or Composer dependencies; it adds verification scripts, release documentation, and small compatibility fixes only when tests expose a concrete failure.

**Tech Stack:** PHP 8.3, MySQL, Apache/Laragon, PowerShell smoke tests, vanilla JavaScript, Git.

---

## Current Baseline

Recent checks passed after the audit fixes:

```text
PHP lint passed for 91 file(s)
JS syntax check passed for 18 file(s)
PASS api_response_standardization
PASS api_smoke
PASS security_smoke
PASS payment_booking_smoke
PASS admin_observability_smoke
PASS csp_inline_smoke
```

Known remaining risks:

- Browser console and interaction verification has not been run in this environment.
- The worktree is heavily modified and untracked, so changes need to be grouped before any commit.
- API response conversion was broad; runtime smoke passed, but admin CRUD endpoints need targeted smoke coverage.
- External SePay end-to-end payment still needs real provider/staging verification.

---

## File Structure

- Modify: `tests/smoke/admin_crud_smoke.ps1` - add focused authenticated admin endpoint checks for users, menu, vouchers, orders, bookings, and table status.
- Modify: `tests/smoke/frontend_pages_smoke.ps1` - add HTTP page-load checks for customer/admin pages and static asset availability.
- Modify: `TESTING_CHECKLIST.md` - record browser/manual release checks and external payment checks.
- Modify: `docs/operations.md` - add post-audit release handoff procedure and rollback notes.
- Modify: `README.md` - point contributors to the expanded smoke suite.
- Optional Modify: `api/admin/*.php`, `api/user/*.php`, `api/menu/*.php` - only if new smoke tests expose a regression.

---

### Task 1: Add Frontend Page and Asset Smoke

**Files:**
- Create: `tests/smoke/frontend_pages_smoke.ps1`
- Modify: `TESTING_CHECKLIST.md`

- [ ] **Step 1: Create failing page-load smoke**

Create `tests/smoke/frontend_pages_smoke.ps1`:

```powershell
$ErrorActionPreference = "Stop"

$baseUrl = $env:RESTAURANT_BASE_URL
if ([string]::IsNullOrWhiteSpace($baseUrl)) {
    $baseUrl = "http://restaurant.test"
}
$baseUrl = $baseUrl.TrimEnd("/")

$paths = @(
    "/",
    "/index.html",
    "/booking.html",
    "/login.html",
    "/forgot_password.html",
    "/profile.html",
    "/admin/",
    "/admin/dashboard.php",
    "/admin/bookings.php",
    "/admin/orders.php",
    "/admin/menu.php",
    "/admin/users.php",
    "/admin/vouchers.php",
    "/admin/logs.php",
    "/style.css",
    "/js/utils.js",
    "/js/menu.js",
    "/js/admin-login.js",
    "/photo/default-food.png",
    "/photo/default-user.png"
)

foreach ($path in $paths) {
    $response = Invoke-WebRequest -Uri "$baseUrl$path" -UseBasicParsing -TimeoutSec 10
    if ($response.StatusCode -lt 200 -or $response.StatusCode -ge 400) {
        throw "Unexpected status $($response.StatusCode) for $path"
    }
    Write-Output "PASS page_load $path $($response.StatusCode)"
}
```

- [ ] **Step 2: Run test to verify current behavior**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\smoke\frontend_pages_smoke.ps1
```

Expected if assets/pages are reachable:

```text
PASS page_load / 200
PASS page_load /admin/logs.php 200
PASS page_load /js/admin-login.js 200
```

If any page returns 404/500, fix only the missing route or asset path named in the failure.

- [ ] **Step 3: Update checklist**

Append to `TESTING_CHECKLIST.md` under `Automated Baseline`:

```markdown
- Run `.\tests\smoke\frontend_pages_smoke.ps1` and confirm customer/admin pages plus key static assets return HTTP 2xx/3xx.
```

- [ ] **Step 4: Verify**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\smoke\frontend_pages_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_js_check.ps1
```

Expected:

```text
PASS page_load / 200
JS syntax check passed
```

- [ ] **Step 5: Commit**

```bash
git add tests/smoke/frontend_pages_smoke.ps1 TESTING_CHECKLIST.md
git commit -m "test: add frontend page smoke"
```

---

### Task 2: Add Admin Runtime Smoke Coverage

**Files:**
- Create: `tests/smoke/admin_crud_smoke.ps1`
- Modify: `TESTING_CHECKLIST.md`
- Optional Modify: `api/admin/*.php`

- [ ] **Step 1: Create authenticated admin smoke**

Create `tests/smoke/admin_crud_smoke.ps1`:

```powershell
$ErrorActionPreference = "Stop"

$baseUrl = $env:RESTAURANT_BASE_URL
if ([string]::IsNullOrWhiteSpace($baseUrl)) {
    $baseUrl = "http://restaurant.test"
}
$baseUrl = $baseUrl.TrimEnd("/")

function Assert-True($Name, $Condition) {
    if (-not $Condition) {
        throw "FAIL $Name"
    }
    Write-Output "PASS $Name"
}

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$csrf = (Invoke-RestMethod -Uri "$baseUrl/api/auth/get_csrf.php" -WebSession $session -TimeoutSec 10).csrf_token

$login = Invoke-RestMethod `
    -Uri "$baseUrl/api/admin/login.php" `
    -Method Post `
    -ContentType "application/json" `
    -Headers @{ "X-CSRF-Token" = $csrf } `
    -Body (@{ email = "admin.demo@example.test"; password = "ChangeMeDemo123!" } | ConvertTo-Json) `
    -WebSession $session `
    -TimeoutSec 10

Assert-True "admin_login" ($login.success -eq $true)

$checks = @(
    @{ name = "admin_stats"; url = "/api/admin/get_stats.php"; field = "success" },
    @{ name = "admin_users"; url = "/api/admin/get_users.php"; field = "success" },
    @{ name = "admin_vouchers"; url = "/api/admin/get_vouchers.php"; field = "success" },
    @{ name = "admin_orders"; url = "/api/admin/get_orders.php"; field = "success" },
    @{ name = "admin_bookings"; url = "/api/admin/get_bookings.php"; field = "success" },
    @{ name = "admin_table_status"; url = "/api/admin/get_table_status.php"; field = "success" },
    @{ name = "admin_top_dishes"; url = "/api/admin/get_top_dishes.php"; field = "success" },
    @{ name = "admin_revenue_stats"; url = "/api/admin/get_revenue_stats.php"; field = "success" }
)

foreach ($check in $checks) {
    $response = Invoke-RestMethod -Uri "$baseUrl$($check.url)" -WebSession $session -TimeoutSec 10
    Assert-True $check.name ($response.($check.field) -eq $true)
}
```

- [ ] **Step 2: Run test to verify it fails or passes honestly**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\smoke\admin_crud_smoke.ps1
```

Expected when demo admin credentials exist:

```text
PASS admin_login
PASS admin_stats
PASS admin_users
PASS admin_vouchers
PASS admin_orders
PASS admin_bookings
PASS admin_table_status
PASS admin_top_dishes
PASS admin_revenue_stats
```

If it fails with `admin_login`, seed or update the local demo admin account instead of weakening auth.

- [ ] **Step 3: Fix only failing endpoint response shapes**

If a GET endpoint fails because it no longer returns `success: true`, update that endpoint to preserve the frontend-compatible shape through `ResponseService::json`.

Example for `api/admin/get_users.php`:

```php
ResponseService::json([
    'success' => true,
    'users' => $users,
]);
```

- [ ] **Step 4: Update checklist**

Append to `TESTING_CHECKLIST.md` under `Admin Flows`:

```markdown
- Run `.\tests\smoke\admin_crud_smoke.ps1` and confirm admin list/stat endpoints return authenticated success responses.
```

- [ ] **Step 5: Verify**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\smoke\admin_crud_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\admin_observability_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1
```

Expected:

```text
PASS admin_login
PASS admin_health
PHP lint passed
```

- [ ] **Step 6: Commit**

```bash
git add tests/smoke/admin_crud_smoke.ps1 TESTING_CHECKLIST.md api/admin
git commit -m "test: add admin runtime smoke"
```

---

### Task 3: Browser Console Release Verification

**Files:**
- Modify: `TESTING_CHECKLIST.md`
- Modify: `docs/operations.md`

- [ ] **Step 1: Add manual browser verification checklist**

Add this section to `TESTING_CHECKLIST.md`:

```markdown
## Browser Console Release Check

- [ ] Open `http://restaurant.test/` and confirm menu cards render without console errors.
- [ ] Open `http://restaurant.test/booking.html` and confirm table/date controls plus preorder menu render without console errors.
- [ ] Open `http://restaurant.test/login.html` and confirm login/register tabs render without console errors.
- [ ] Open `http://restaurant.test/profile.html` after customer login and confirm profile tabs/history modals render without console errors.
- [ ] Open `http://restaurant.test/admin/` and confirm admin login submits without CSP inline-script errors.
- [ ] Open `http://restaurant.test/admin/dashboard.php`, `bookings.php`, `orders.php`, `menu.php`, `users.php`, `vouchers.php`, and `logs.php`.
- [ ] Confirm browser console has no CSP violations for blocked inline scripts.
```

- [ ] **Step 2: Add operations handoff note**

Append to `docs/operations.md` under `Verification Commands`:

```markdown
Browser verification is required before demo/release because CSP behavior is enforced by the browser. The automated smoke scripts confirm HTTP/API behavior, but the browser console must be checked for CSP violations and JavaScript runtime errors.
```

- [ ] **Step 3: Perform manual browser verification**

Open these URLs:

```text
http://restaurant.test/
http://restaurant.test/booking.html
http://restaurant.test/login.html
http://restaurant.test/profile.html
http://restaurant.test/admin/
http://restaurant.test/admin/dashboard.php
http://restaurant.test/admin/bookings.php
http://restaurant.test/admin/logs.php
```

Expected:

```text
No CSP inline-script errors.
No JavaScript runtime errors that block core page actions.
```

- [ ] **Step 4: Record result**

If manual browser verification passes, add this line to `docs/operations.md`:

```markdown
- Browser console release check: passed on YYYY-MM-DD for customer and admin pages.
```

If it fails, record exact URL, console error, and file/line before fixing.

- [ ] **Step 5: Commit**

```bash
git add TESTING_CHECKLIST.md docs/operations.md
git commit -m "docs: add browser release verification"
```

---

### Task 4: Normalize Git Handoff Into Reviewable Commits

**Files:**
- No source changes required.

- [ ] **Step 1: Inspect grouped status**

Run:

```powershell
git status --short
git diff --stat
```

Expected:

```text
Modified and untracked files are visible.
```

- [ ] **Step 2: Create API standardization commit**

Run:

```powershell
git add api tests/smoke/api_response_standardization_smoke.ps1
git diff --cached --check
git commit -m "refactor: standardize api json responses"
```

Expected:

```text
No whitespace errors.
[main <sha>] refactor: standardize api json responses
```

- [ ] **Step 3: Create CSP/frontend commit**

Run:

```powershell
git add .htaccess admin profile.html js tests/smoke/csp_inline_smoke.ps1
git diff --cached --check
git commit -m "security: remove inline scripts for csp"
```

Expected:

```text
[main <sha>] security: remove inline scripts for csp
```

- [ ] **Step 4: Create payment/booking verification commit**

Run:

```powershell
git add api/payment api/services/OrderService.php api/services/payment_state_service.php api/user/book_table.php tests/smoke/payment_booking_smoke.ps1
git diff --cached --check
git commit -m "test: cover payment and booking transitions"
```

Expected:

```text
[main <sha>] test: cover payment and booking transitions
```

- [ ] **Step 5: Create docs/ops commit**

Run:

```powershell
git add README.md TESTING_CHECKLIST.md docs
git diff --cached --check
git commit -m "docs: record post-audit verification"
```

Expected:

```text
[main <sha>] docs: record post-audit verification
```

- [ ] **Step 6: Verify clean or understood status**

Run:

```powershell
git status --short
```

Expected:

```text
Only intentionally uncommitted local files remain, or no output.
```

Stop and ask before staging `.env`, `logs/`, local uploads, or unknown generated files.

---

### Task 5: Final Release Gate

**Files:**
- Modify: `docs/operations.md`

- [ ] **Step 1: Run all automated checks**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_js_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\api_response_standardization_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\csp_inline_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\api_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\security_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\payment_booking_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\admin_observability_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\frontend_pages_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\admin_crud_smoke.ps1
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe Database\migrate.php status
```

Expected:

```text
PHP lint passed
JS syntax check passed
PASS api_response_standardization
PASS csp_inline_smoke
PASS admin_login_and_stats
PASS webhook_requires_authorization
PASS duplicate_order_webhook_is_idempotent
PASS admin_health
PASS page_load /
PASS admin_login
2026_04_29_120000_add_token_version_to_users       ✅ Ran
```

- [ ] **Step 2: Record final release gate**

Append to `docs/operations.md`:

```markdown
## Release Gate - YYYY-MM-DD

- Automated smoke suite: passed.
- Migration status: `2026_04_29_120000_add_token_version_to_users` is `Ran`.
- Browser console release check: passed or documented with known issues.
- External SePay staging payment: pending unless provider credentials were available.
```

- [ ] **Step 3: Commit release gate note**

```bash
git add docs/operations.md
git commit -m "docs: record release gate status"
```

---

## Self-Review

Spec coverage:
- Covers remaining risks from the current code state: browser verification, admin runtime coverage, frontend page coverage, git handoff, and final release gate.

Placeholder scan:
- No TBD/TODO placeholders are present.
- Each task includes exact file paths and commands.

Type and behavior consistency:
- Smoke scripts use existing PowerShell style and `RESTAURANT_BASE_URL`.
- API checks preserve current `{ success: true }` response expectation.
- No framework, Composer dependency, or schema change is introduced.
