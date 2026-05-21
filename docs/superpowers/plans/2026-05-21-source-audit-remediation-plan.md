# Source Audit Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the current source audit into a short, verifiable hardening pass for runtime checks, encoding, API consistency, payment/booking safety, frontend CSP readiness, and documentation.

**Architecture:** Keep the existing zero-dependency PHP/MySQL/vanilla JavaScript architecture. Work in small phases that preserve current public response shapes where frontend code depends on them, and verify each phase with the existing PowerShell lint and smoke scripts.

**Tech Stack:** PHP 8.3, MySQL, Apache/Laragon, vanilla JavaScript, vanilla CSS, custom migration runner, custom logging, custom smoke tests, no Composer/framework dependency.

---

## Audit Baseline

Static review found these strengths:
- PHP lint passes for 91 PHP files.
- JavaScript syntax check passes for 8 JS files.
- High-risk flows already have prepared statements, CSRF validation, rate limiting, JWT token-version revocation, webhook token checks, log redaction, and local smoke scripts.
- Existing roadmap plans from 2026-05-20 already cover broad security, API, frontend, deployment, admin observability, and payment/booking phases.

Current verification gap:
- `tests/smoke/api_smoke.ps1` could not connect to `http://restaurant.test`, so HTTP runtime behavior is not confirmed in this audit pass.

Primary remaining risks:
- Several Markdown/PHP comment strings show mojibake Vietnamese text, which can leak into documentation, logs, and some API responses.
- API endpoints still mix `ResponseService`, `apiSuccess/apiError`, and manual `echo json_encode`, making frontend error handling and test assertions inconsistent.
- Payment and booking flows need explicit idempotency and transition tests around duplicate webhooks, voucher usage, and booking deposit status.
- CSP allows inline scripts because many admin/customer pages still use inline handlers and inline script blocks.
- Runtime smoke tests depend on local host availability but do not fail with a friendly setup diagnostic.

---

## File Structure

- Modify: `README.md` - add a short local host verification step before smoke commands.
- Modify: `TESTING_CHECKLIST.md` - add encoding, smoke-host, and payment/booking regression checks.
- Modify: `ARCHITECTURE.md` - repair mojibake and document the remaining inline-script/CSP tradeoff.
- Modify: `Database/migrations/2026_04_29_120000_add_token_version_to_users.php` - repair comments only; do not change migration behavior.
- Modify: `.htaccess` - update CSP only after inline handlers are moved out of HTML/PHP pages.
- Modify: `api/base.php` - keep compatibility helpers, but make all response helpers call `ResponseService`.
- Modify: `api/services/response_service.php` - keep the canonical API response shape.
- Modify: `api/services/validation_service.php` - add reusable money and date-time validation helpers if missing when implementing flow tests.
- Modify: `api/auth/*.php`, `api/user/*.php`, `api/public/*.php`, `api/payment/*.php`, `api/admin/*.php` - convert remaining manual JSON responses in small batches.
- Modify: `api/services/OrderService.php` - tighten order paid/voucher idempotency only if tests expose a failing transition.
- Modify: `api/payment/webhook.php` - add explicit duplicate webhook assertions and consistent successful no-op responses.
- Modify: `api/user/book_table.php` - add explicit booking conflict and deposit transition coverage.
- Modify: `js/utils.js` - centralize API error parsing and escaped DOM rendering helpers.
- Modify: `js/profile.js`, `js/cart.js`, `js/payment.js`, `js/booking.js` - use shared helpers where touched.
- Modify: `admin/*.php`, `*.html` - remove inline handlers incrementally before tightening CSP.
- Modify: `tests/smoke/*.ps1` - add better preflight and flow-specific assertions.

---

### Task 1: Restore Runtime Smoke Baseline

**Files:**
- Modify: `README.md`
- Modify: `TESTING_CHECKLIST.md`
- Modify: `tests/smoke/api_smoke.ps1`

- [ ] **Step 1: Add host preflight to API smoke script**

At the top of `tests/smoke/api_smoke.ps1`, after `$baseUrl` is assigned, add:

```powershell
try {
    $healthResponse = Invoke-WebRequest -Uri $baseUrl -TimeoutSec 5 -UseBasicParsing
    if ($healthResponse.StatusCode -lt 200 -or $healthResponse.StatusCode -ge 500) {
        throw "Unexpected status code $($healthResponse.StatusCode)"
    }
} catch {
    Write-Error "Cannot reach $baseUrl. Start Laragon/Apache and confirm the virtual host before running smoke tests."
}
```

- [ ] **Step 2: Run smoke test before app changes**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\smoke\api_smoke.ps1
```

Expected when host is down:

```text
Cannot reach http://restaurant.test. Start Laragon/Apache and confirm the virtual host before running smoke tests.
```

Expected when host is up:

```text
PASS csrf
PASS menu
PASS tables
PASS featured_reviews
PASS booked_tables
PASS admin_login_and_stats
```

- [ ] **Step 3: Document the preflight**

Add this section to `README.md` under `Local Verification`:

````markdown
Before running HTTP smoke tests, open `http://restaurant.test` in a browser or run:

```powershell
Invoke-WebRequest http://restaurant.test -UseBasicParsing
```

If it cannot connect, start Laragon/Apache and confirm the virtual host points to this project root.
````

- [ ] **Step 4: Verify syntax**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_js_check.ps1
```

Expected:

```text
PHP lint passed
JS syntax check passed
```

- [ ] **Step 5: Commit**

```bash
git add README.md TESTING_CHECKLIST.md tests/smoke/api_smoke.ps1
git commit -m "test: add smoke host preflight"
```

---

### Task 2: Repair Encoding and Message Consistency

**Files:**
- Modify: `ARCHITECTURE.md`
- Modify: `Database/migrations/2026_04_29_120000_add_token_version_to_users.php`
- Modify: `api/services/OrderService.php`
- Modify: `api/services/csrf_service.php`
- Modify: `api/services/rate_limit_service.php`
- Modify: `config/db.php`
- Modify: `.htaccess`

- [ ] **Step 1: Find mojibake candidates**

Run:

```powershell
Select-String -Path ARCHITECTURE.md,Database\*.php,api\*.php,api\services\*.php,config\*.php,.htaccess,README.md,TESTING_CHECKLIST.md -Pattern '[\u00C3\u00C2\u00C6\u00E1\u00C4]' -AllMatches
```

Expected:

```text
Matches are listed for files that need text-only encoding repair.
```

- [ ] **Step 2: Repair comments and user-facing strings**

Convert mojibake text to readable Vietnamese with UTF-8 encoding. Do not change SQL, PHP logic, table names, function names, constants, or routes.

Example replacements:

```text
Loi ket noi he thong. Vui long thu lai sau.
Loi ket noi he thong. Vui long thu lai sau.

Tim ID ban bang ten ban hoac ID.
Tim ID ban bang ten ban hoac ID.

Chan truy cap vao /logs/ tu web
Chan truy cap vao /logs/ tu web
```

- [ ] **Step 3: Verify no mojibake remains in edited files**

Run:

```powershell
Select-String -Path ARCHITECTURE.md,Database\*.php,api\*.php,api\services\*.php,config\*.php,.htaccess -Pattern '[\u00C3\u00C2\u00C6\u00E1\u00C4]' -AllMatches
```

Expected:

```text
No output
```

- [ ] **Step 4: Verify syntax**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1
```

Expected:

```text
PHP lint passed
```

- [ ] **Step 5: Commit**

```bash
git add ARCHITECTURE.md Database/migrations/2026_04_29_120000_add_token_version_to_users.php api/services config/db.php .htaccess
git commit -m "docs: repair utf8 Vietnamese text"
```

---

### Task 3: Finish API Response Standardization

**Files:**
- Modify: `api/base.php`
- Modify: `api/services/response_service.php`
- Modify: `api/public/get_featured_reviews.php`
- Modify: `api/public/get_booked_tables.php`
- Modify: `api/public/check_voucher.php`
- Modify: `api/user/upload_avatar.php`
- Modify: `api/user/get_user_history.php`
- Modify: `api/user/get_order_details.php`
- Modify: `api/user/submit_review.php`
- Modify: `api/auth/send_otp.php`
- Modify: `api/auth/verify_otp.php`
- Modify: `api/auth/reset_password.php`
- Modify: `api/admin/auth_check_api.php`
- Modify: selected `api/admin/*.php` endpoints that still manually set status and echo JSON

- [ ] **Step 1: Search remaining manual responses**

Run:

```powershell
rg -n "echo json_encode|http_response_code|header\\('Content-Type: application/json" api
```

Expected:

```text
Only ResponseService and intentional file/export endpoints should remain after this task.
```

- [ ] **Step 2: Update `auth_check_api.php` to use `ResponseService`**

Use this shape:

```php
require_once ROOT_PATH . '/api/services/response_service.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    ResponseService::error('Unauthorized Access', 401);
}
```

- [ ] **Step 3: Convert public/user/auth endpoints in batches**

For each endpoint, replace manual status/header/JSON blocks with:

```php
ResponseService::success([
    'message' => 'Review submitted successfully.',
]);
```

or:

```php
ResponseService::error('Login required', 401);
```

Keep existing field names such as `reviews`, `booked`, `details`, `orders`, `bookings`, and `data` when frontend code already reads them.

- [ ] **Step 4: Run syntax and public smoke**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\api_smoke.ps1
```

Expected:

```text
PHP lint passed
PASS csrf
PASS menu
PASS tables
PASS featured_reviews
PASS booked_tables
PASS admin_login_and_stats
```

- [ ] **Step 5: Commit**

```bash
git add api
git commit -m "refactor: finish api response standardization"
```

---

### Task 4: Add Payment and Booking Transition Tests

**Files:**
- Modify: `tests/smoke/payment_booking_smoke.ps1`
- Modify: `api/services/OrderService.php`
- Modify: `api/payment/webhook.php`
- Modify: `api/user/book_table.php`
- Modify: `api/services/payment_state_service.php`

- [ ] **Step 1: Extend smoke assertions**

Add tests that verify:

```text
PASS empty_cart_rejected
PASS invalid_table_rejected_before_order_insert
PASS past_booking_rejected
PASS empty_preorder_rejected
PASS duplicate_order_webhook_is_idempotent
PASS duplicate_booking_webhook_is_idempotent
PASS insufficient_order_payment_rejected
PASS insufficient_booking_deposit_rejected
```

- [ ] **Step 2: Confirm order webhook duplicate behavior**

Expected behavior for duplicate order webhook:

```json
{
  "success": true,
  "message": "Order already paid"
}
```

The second webhook must not increment `vouchers.used_count`.

- [ ] **Step 3: Confirm booking webhook duplicate behavior**

Expected behavior for duplicate booking webhook:

```json
{
  "success": true,
  "message": "Booking deposit already paid"
}
```

The second webhook must keep `bookings.payment_status` and `bookings.status` unchanged.

- [ ] **Step 4: Fix only failing transition logic**

If duplicate order webhook increments voucher usage, update `OrderService::markOrderPaid()` so it checks the locked order status before voucher update:

```php
if (PaymentStateService::isPaidOrderStatus((string)$order['status'])) {
    return [
        'success' => true,
        'message' => 'Order already paid',
        'already_paid' => true,
        'order' => $order,
    ];
}
```

If booking duplicate handling is not protected by a locked read, update `api/payment/webhook.php` to use `SELECT ... FOR UPDATE` inside a transaction before changing booking status.

- [ ] **Step 5: Run verification**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\payment_booking_smoke.ps1
```

Expected:

```text
PHP lint passed
PASS duplicate_order_webhook_is_idempotent
PASS duplicate_booking_webhook_is_idempotent
```

- [ ] **Step 6: Commit**

```bash
git add api tests/smoke/payment_booking_smoke.ps1
git commit -m "test: cover payment and booking transitions"
```

---

### Task 5: Reduce Inline Script Dependency Before Tightening CSP

**Files:**
- Modify: `.htaccess`
- Modify: `admin/dashboard.php`
- Modify: `admin/users.php`
- Modify: `admin/vouchers.php`
- Modify: `admin/orders.php`
- Modify: `admin/bookings.php`
- Modify: `admin/menu.php`
- Modify: `admin/logs.php`
- Modify: `admin/sidebar.php`
- Modify: `profile.html`
- Modify: `js/profile.js`
- Modify: `js/utils.js`

- [ ] **Step 1: Inventory inline handlers**

Run:

```powershell
rg -n "onclick=|onchange=|oninput=|<script>" admin *.html js
```

Expected:

```text
Matches are listed for pages that need event-listener migration.
```

- [ ] **Step 2: Move handlers to JavaScript**

Replace inline handlers with stable IDs or `data-action` attributes.

Example HTML:

```html
<button type="button" class="btn-add" data-action="open-user-modal">Add user</button>
```

Example JavaScript:

```javascript
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-action="open-user-modal"]');
    if (!button) {
        return;
    }
    openModal();
});
```

- [ ] **Step 3: Tighten CSP only after handlers are gone**

In `.htaccess`, change:

```apache
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; media-src 'self' data:; connect-src 'self' https://qr.sepay.vn https://cdn.jsdelivr.net; frame-ancestors 'none'; object-src 'none';"
```

to:

```apache
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; media-src 'self' data:; connect-src 'self' https://qr.sepay.vn https://cdn.jsdelivr.net; frame-ancestors 'none'; object-src 'none';"
```

- [ ] **Step 4: Verify browser flows**

Open:

```text
http://restaurant.test/
http://restaurant.test/profile.html
http://restaurant.test/admin/
http://restaurant.test/admin/bookings.php
http://restaurant.test/admin/logs.php
```

Expected:

```text
No console errors caused by blocked inline scripts.
Buttons and filters still work.
```

- [ ] **Step 5: Run syntax checks**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_js_check.ps1
```

Expected:

```text
PHP lint passed
JS syntax check passed
```

- [ ] **Step 6: Commit**

```bash
git add .htaccess admin profile.html js
git commit -m "security: remove inline handlers before csp tightening"
```

---

### Task 6: Final Verification and Handoff

**Files:**
- Modify: `README.md`
- Modify: `TESTING_CHECKLIST.md`
- Modify: `docs/operations.md`

- [ ] **Step 1: Run all local checks**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_js_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\api_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\security_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\payment_booking_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\admin_observability_smoke.ps1
```

Expected:

```text
PHP lint passed
JS syntax check passed
PASS admin_login_and_stats
PASS webhook_requires_authorization
PASS empty_cart_rejected
PASS admin_health
PASS direct_log_access_blocked
```

- [ ] **Step 2: Check migration status**

Run:

```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe Database\migrate.php status
```

Expected:

```text
The token_version migration is applied.
No pending migration is required for this audit pass unless Task 4 introduced one.
```

- [ ] **Step 3: Capture manual acceptance**

Update `TESTING_CHECKLIST.md` with checked results for:

```markdown
- Homepage loads menu and reviews.
- Login/logout rotates CSRF and session state.
- Cart checkout rejects invalid input.
- Booking rejects past dates and conflicting table windows.
- SePay webhook rejects missing token.
- Admin dashboard, logs, users, bookings, orders, menu, and vouchers pages load.
- Direct `/logs/` access is blocked.
```

- [ ] **Step 4: Final commit**

```bash
git add README.md TESTING_CHECKLIST.md docs/operations.md
git commit -m "docs: record source audit verification"
```

---

## Self-Review

Spec coverage:
- Full-source audit findings are mapped to runtime baseline, encoding, API consistency, payment/booking correctness, CSP/frontend safety, and final handoff.
- Existing 2026-05-20 plans remain valid; this plan focuses on the remaining gaps found on 2026-05-21.

Placeholder scan:
- No vague task remains.
- Each task lists exact files, exact commands, and expected outputs.

Type and behavior consistency:
- API examples use the existing `{ "success": boolean, "message": string }` shape.
- Payment and booking examples use current order codes `DH{id}` and booking codes `BKG{id}`.
- CSP changes are sequenced after inline handler migration to avoid breaking existing pages.
