# Smoke Runtime Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the remaining audit gaps by making smoke tests fail clearly when the host is down, proving payment/booking webhook idempotency at runtime, and escaping booking email HTML.

**Architecture:** Keep the current zero-dependency PHP/MySQL/PowerShell smoke-test setup. Add a small shared PowerShell helper for HTTP smoke preflight, add a PHP fixture helper only for test data setup/cleanup, and keep production code changes narrowly scoped to output escaping.

**Tech Stack:** PHP 8.3, MySQL/mysqli, Apache/Laragon, PowerShell smoke scripts, vanilla PHP services.

---

## File Structure

- Create: `tests/smoke/_helpers.ps1` - shared base URL resolution, host preflight, and HTTP status assertion helpers.
- Create: `tests/smoke/payment_webhook_fixture.php` - CLI-only fixture setup/status/cleanup for runtime webhook idempotency tests.
- Modify: `tests/smoke/api_smoke.ps1` - use shared preflight helper instead of local duplicated code.
- Modify: `tests/smoke/security_smoke.ps1` - use shared preflight and shared `Assert-Status`.
- Modify: `tests/smoke/payment_booking_smoke.ps1` - replace source regex checks with real duplicate webhook HTTP calls and database state assertions.
- Modify: `tests/smoke/admin_observability_smoke.ps1` - use shared preflight and shared `Assert-Status`.
- Modify: `api/user/book_table.php` - escape dynamic values before composing booking confirmation email HTML.
- Modify: `TESTING_CHECKLIST.md` - record that payment/booking idempotency is now runtime verified.

---

### Task 1: Share Smoke Host Preflight

**Files:**
- Create: `tests/smoke/_helpers.ps1`
- Modify: `tests/smoke/api_smoke.ps1`
- Modify: `tests/smoke/security_smoke.ps1`
- Modify: `tests/smoke/payment_booking_smoke.ps1`
- Modify: `tests/smoke/admin_observability_smoke.ps1`

- [ ] **Step 1: Create shared helper**

Create `tests/smoke/_helpers.ps1`:

```powershell
$ErrorActionPreference = "Stop"

function Get-RestaurantBaseUrl {
    $baseUrl = $env:RESTAURANT_BASE_URL
    if ([string]::IsNullOrWhiteSpace($baseUrl)) {
        $baseUrl = "http://restaurant.test"
    }
    return $baseUrl.TrimEnd("/")
}

function Assert-RestaurantHostAvailable {
    param([string] $BaseUrl)

    try {
        $healthResponse = Invoke-WebRequest -Uri $BaseUrl -TimeoutSec 5 -UseBasicParsing
        if ($healthResponse.StatusCode -lt 200 -or $healthResponse.StatusCode -ge 500) {
            throw "Unexpected status code $($healthResponse.StatusCode)"
        }
    } catch {
        Write-Error "Cannot reach $BaseUrl. Start Laragon/Apache and confirm the virtual host before running smoke tests."
    }
}

function Assert-Status {
    param(
        [string] $Name,
        [scriptblock] $Request,
        [int[]] $ExpectedStatus
    )

    try {
        & $Request | Out-Null
        throw "$Name unexpectedly succeeded"
    } catch {
        $response = $_.Exception.Response
        if ($null -eq $response) {
            throw
        }

        $status = [int]$response.StatusCode
        if ($ExpectedStatus -notcontains $status) {
            throw "$Name returned $status; expected $($ExpectedStatus -join ', ')"
        }

        Write-Output "PASS $Name"
    }
}
```

- [ ] **Step 2: Update each smoke script header**

Replace the top base URL setup in all four smoke scripts with:

```powershell
$ErrorActionPreference = "Stop"
. "$PSScriptRoot\_helpers.ps1"

$baseUrl = Get-RestaurantBaseUrl
Assert-RestaurantHostAvailable $baseUrl
```

Remove duplicated local `Assert-Status` functions from `security_smoke.ps1` and `payment_booking_smoke.ps1`.

- [ ] **Step 3: Run smoke scripts with host up**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\smoke\api_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\security_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\payment_booking_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\admin_observability_smoke.ps1
```

Expected:

```text
PASS csrf
PASS admin_stats_requires_login
PASS empty_cart_rejected
PASS admin_health
```

- [ ] **Step 4: Commit**

```bash
git add tests/smoke
git commit -m "test: share smoke host preflight"
```

---

### Task 2: Runtime-Test Duplicate Webhooks

**Files:**
- Create: `tests/smoke/payment_webhook_fixture.php`
- Modify: `tests/smoke/payment_booking_smoke.ps1`

- [ ] **Step 1: Add CLI-only fixture helper**

Create `tests/smoke/payment_webhook_fixture.php`:

```php
<?php
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';

$action = $argv[1] ?? '';
$conn = getDbConnection();

function out(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
}

function scalarQuery(mysqli $conn, string $sql, string $types = '', mixed ...$params): mixed {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    return $row[0] ?? null;
}

$prefix = 'SMOKE_WEBHOOK_';

try {
    if ($action === 'setup') {
        $userId = (int)scalarQuery($conn, "SELECT id FROM users WHERE email = 'customer.demo@example.test' LIMIT 1");
        if ($userId <= 0) {
            $userId = (int)scalarQuery($conn, "SELECT id FROM users WHERE role = 'customer' ORDER BY id LIMIT 1");
        }
        if ($userId <= 0) {
            throw new RuntimeException('No customer user exists for webhook smoke fixture.');
        }

        $menuId = (int)scalarQuery($conn, "SELECT id FROM menu_items WHERE is_active = 1 AND deleted_at IS NULL ORDER BY id LIMIT 1");
        if ($menuId <= 0) {
            throw new RuntimeException('No active menu item exists for webhook smoke fixture.');
        }

        $voucherCode = $prefix . bin2hex(random_bytes(4));
        $conn->begin_transaction();

        $stmt = $conn->prepare("INSERT INTO vouchers (code, description, discount_type, discount_value, min_order_value, expire_date, usage_limit, used_count, is_active) VALUES (?, 'Smoke webhook fixture', 'fixed', 1000, 0, DATE_ADD(NOW(), INTERVAL 1 DAY), 5, 0, 1)");
        $stmt->bind_param('s', $voucherCode);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, discount_amount, final_total, payment_method, status, voucher_code, created_at) VALUES (?, 50000, 1000, 49000, 'bank_transfer', 'pending', ?, NOW())");
        $stmt->bind_param('is', $userId, $voucherCode);
        $stmt->execute();
        $orderId = (int)$stmt->insert_id;
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price) VALUES (?, ?, 1, 50000)");
        $stmt->bind_param('ii', $orderId, $menuId);
        $stmt->execute();
        $stmt->close();

        $date = (new DateTimeImmutable('+45 days'))->format('Y-m-d');
        $stmt = $conn->prepare("INSERT INTO bookings (table_id, name, phone, date, time, guests, status, floor, table_number, user_id, has_preorder, total_amount, deposit_amount, payment_status) VALUES (1, 'Smoke Webhook', '0123456789', ?, '18:00:00', 2, 'awaiting_payment', 'Smoke', 1, ?, 1, 90000, 27000, 'pending')");
        $stmt->bind_param('si', $date, $userId);
        $stmt->execute();
        $bookingId = (int)$stmt->insert_id;
        $stmt->close();

        $conn->commit();
        out([
            'order_id' => $orderId,
            'booking_id' => $bookingId,
            'voucher_code' => $voucherCode,
            'order_amount' => 49000,
            'booking_amount' => 27000,
        ]);
    }

    if ($action === 'status') {
        $orderId = (int)($argv[2] ?? 0);
        $bookingId = (int)($argv[3] ?? 0);
        $voucherCode = (string)($argv[4] ?? '');
        out([
            'order_status' => scalarQuery($conn, 'SELECT status FROM orders WHERE id = ?', 'i', $orderId),
            'booking_status' => scalarQuery($conn, 'SELECT status FROM bookings WHERE id = ?', 'i', $bookingId),
            'booking_payment_status' => scalarQuery($conn, 'SELECT payment_status FROM bookings WHERE id = ?', 'i', $bookingId),
            'voucher_used_count' => (int)scalarQuery($conn, 'SELECT used_count FROM vouchers WHERE code = ?', 's', $voucherCode),
        ]);
    }

    if ($action === 'cleanup') {
        $orderId = (int)($argv[2] ?? 0);
        $bookingId = (int)($argv[3] ?? 0);
        $voucherCode = (string)($argv[4] ?? '');
        $conn->begin_transaction();
        $stmt = $conn->prepare('DELETE FROM order_items WHERE order_id = ?');
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare('DELETE FROM orders WHERE id = ?');
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare('DELETE FROM booking_items WHERE booking_id = ?');
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare('DELETE FROM bookings WHERE id = ?');
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare('DELETE FROM vouchers WHERE code = ? AND code LIKE "SMOKE_WEBHOOK_%"');
        $stmt->bind_param('s', $voucherCode);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        out(['success' => true]);
    }

    throw new RuntimeException('Unknown action.');
} catch (Throwable $e) {
    if ($conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }
    }
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($conn instanceof mysqli) {
        $conn->close();
    }
}
```

- [ ] **Step 2: Replace source-regex checks with real webhook calls**

In `tests/smoke/payment_booking_smoke.ps1`, delete the `$webhookSource` and `$orderServiceSource` regex block. Add this block after the validation rejection checks:

```powershell
$php = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"
$fixture = & $php .\tests\smoke\payment_webhook_fixture.php setup | ConvertFrom-Json

try {
    $token = $env:SEPAY_WEBHOOK_TOKEN
    if ([string]::IsNullOrWhiteSpace($token)) {
        $token = "dev-sepay-webhook-token-change-me"
    }

    $orderWebhookBody = @{
        gateway = "MBBank"
        transferType = "in"
        transferAmount = [int]$fixture.order_amount
        content = "Thanh toan DH$($fixture.order_id)"
    } | ConvertTo-Json

    $firstOrder = Invoke-RestMethod `
        -Uri "$baseUrl/api/payment/webhook.php" `
        -Method Post `
        -Body $orderWebhookBody `
        -ContentType "application/json; charset=utf-8" `
        -Headers @{ Authorization = "Apikey $token" } `
        -TimeoutSec 10

    $secondOrder = Invoke-RestMethod `
        -Uri "$baseUrl/api/payment/webhook.php" `
        -Method Post `
        -Body $orderWebhookBody `
        -ContentType "application/json; charset=utf-8" `
        -Headers @{ Authorization = "Apikey $token" } `
        -TimeoutSec 10

    $bookingWebhookBody = @{
        gateway = "MBBank"
        transferType = "in"
        transferAmount = [int]$fixture.booking_amount
        content = "Thanh toan BKG$($fixture.booking_id)"
    } | ConvertTo-Json

    $firstBooking = Invoke-RestMethod `
        -Uri "$baseUrl/api/payment/webhook.php" `
        -Method Post `
        -Body $bookingWebhookBody `
        -ContentType "application/json; charset=utf-8" `
        -Headers @{ Authorization = "Apikey $token" } `
        -TimeoutSec 10

    $secondBooking = Invoke-RestMethod `
        -Uri "$baseUrl/api/payment/webhook.php" `
        -Method Post `
        -Body $bookingWebhookBody `
        -ContentType "application/json; charset=utf-8" `
        -Headers @{ Authorization = "Apikey $token" } `
        -TimeoutSec 10

    $status = & $php .\tests\smoke\payment_webhook_fixture.php status $fixture.order_id $fixture.booking_id $fixture.voucher_code | ConvertFrom-Json

    if ($firstOrder.success -ne $true -or $secondOrder.message -ne "Order already paid") {
        throw "duplicate order webhook did not return idempotent success"
    }
    if ($status.order_status -ne "paid" -or [int]$status.voucher_used_count -ne 1) {
        throw "duplicate order webhook changed final state incorrectly"
    }
    Write-Output "PASS duplicate_order_webhook_is_idempotent"

    if ($firstBooking.success -ne $true -or $secondBooking.message -ne "Booking deposit already paid") {
        throw "duplicate booking webhook did not return idempotent success"
    }
    if ($status.booking_status -ne "confirmed" -or $status.booking_payment_status -ne "partial") {
        throw "duplicate booking webhook changed final state incorrectly"
    }
    Write-Output "PASS duplicate_booking_webhook_is_idempotent"
} finally {
    if ($fixture) {
        & $php .\tests\smoke\payment_webhook_fixture.php cleanup $fixture.order_id $fixture.booking_id $fixture.voucher_code | Out-Null
    }
}
```

- [ ] **Step 3: Run payment smoke**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\smoke\payment_booking_smoke.ps1
```

Expected:

```text
PASS empty_cart_rejected
PASS invalid_table_rejected_before_order_insert
PASS past_booking_rejected
PASS empty_preorder_rejected
PASS insufficient_order_payment_rejected
PASS insufficient_booking_deposit_rejected
PASS duplicate_order_webhook_is_idempotent
PASS duplicate_booking_webhook_is_idempotent
```

- [ ] **Step 4: Commit**

```bash
git add tests/smoke/payment_booking_smoke.ps1 tests/smoke/payment_webhook_fixture.php
git commit -m "test: verify duplicate webhooks at runtime"
```

---

### Task 3: Escape Booking Confirmation Email HTML

**Files:**
- Modify: `api/user/book_table.php`

- [ ] **Step 1: Add escaped variables before `$body`**

In `api/user/book_table.php`, before `$body = "<h2>...`, add:

```php
$safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$safeDate = htmlspecialchars($date, ENT_QUOTES, 'UTF-8');
$safeTime = htmlspecialchars($time, ENT_QUOTES, 'UTF-8');
$safeFloor = htmlspecialchars($floor, ENT_QUOTES, 'UTF-8');
$safePreorderText = htmlspecialchars($preorderText, ENT_QUOTES, 'UTF-8');
```

- [ ] **Step 2: Replace dynamic email variables**

Change the email body interpolation to:

```php
$body = "<h2>Cam on ban da yeu cau dat ban!</h2>
         <p>Xin chao <strong>$safeName</strong>,</p>
         <p>Yeu cau dat ban cua ban da duoc ghi nhan:</p>
         <ul>
         <li><strong>Ngay:</strong> $safeDate</li>
         <li><strong>Gio:</strong> $safeTime</li>
         <li><strong>So khach:</strong> $guestsInt</li>
         <li><strong>Ban:</strong> $tableNumberInt (Sanh $safeFloor)</li>
         </ul>
         <p>$safePreorderText</p>
         <p>Tran trong,<br>Nha Hang Com Que Duong Bau</p>";
```

- [ ] **Step 3: Run syntax check**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1
```

Expected:

```text
PHP lint passed
```

- [ ] **Step 4: Commit**

```bash
git add api/user/book_table.php
git commit -m "security: escape booking email html"
```

---

### Task 4: Document Runtime Coverage

**Files:**
- Modify: `TESTING_CHECKLIST.md`
- Modify: `README.md` if the smoke command list needs the new helper behavior mentioned.

- [ ] **Step 1: Update checklist payment section**

Add this line to `TESTING_CHECKLIST.md` under payment/booking smoke coverage:

```markdown
- [ ] Duplicate SePay order and booking webhooks are verified through real HTTP webhook calls; voucher usage remains incremented exactly once.
```

- [ ] **Step 2: Run full verification**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_js_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\api_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\security_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\payment_booking_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\admin_observability_smoke.ps1
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe Database\migrate.php status
```

Expected:

```text
PHP lint passed
JS syntax check passed
PASS csrf
PASS admin_stats_requires_login
PASS duplicate_order_webhook_is_idempotent
PASS duplicate_booking_webhook_is_idempotent
PASS admin_health
2026_04_29_120000_add_token_version_to_users       Ran
```

- [ ] **Step 3: Commit**

```bash
git add TESTING_CHECKLIST.md README.md
git commit -m "docs: record runtime webhook smoke coverage"
```

---

## Self-Review

Spec coverage:
- Host-down smoke failures are addressed by Task 1.
- Payment/booking idempotency false confidence is addressed by Task 2 with real HTTP webhook calls and DB state checks.
- Booking email HTML injection risk is addressed by Task 3.
- Handoff documentation is addressed by Task 4.

Placeholder scan:
- No TBD/TODO/fill-later placeholders remain.
- Code-changing steps include exact snippets and exact commands.

Type consistency:
- PowerShell uses the existing `$baseUrl`, `Invoke-RestMethod`, and `Assert-Status` patterns.
- PHP fixture uses existing `config/constants.php`, `getDbConnection()`, and schema columns from `Database/duong_bau_restaurant.sql`.
- Expected webhook messages match current `api/payment/webhook.php` and `api/services/OrderService.php`.
