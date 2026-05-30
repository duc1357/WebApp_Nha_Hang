# Source Score Uplift No UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Raise the code quality, security, maintainability, and production readiness score without changing the visual UI, page layout, CSS appearance, or user-facing design.

**Architecture:** Keep the current zero-dependency PHP/MySQL/vanilla JS architecture. Improve shared boundaries first: configuration validation, trusted client IP handling, JSON output, CSV export guardrails, and admin notification robustness. Add static/smoke tests so these hardening changes stay locked.

**Tech Stack:** PHP 8.3, MySQLi, Apache/Laragon, vanilla JavaScript, PowerShell smoke/static tests.

---

## Non-UI Constraint

Do not change:
- `style.css`
- visible layout structure in `*.html`
- visible layout structure in `admin/*.php`
- colors, spacing, typography, component appearance, icons, or text copy unless the text is an API/log/test message

Allowed:
- PHP services and API endpoint logic
- test scripts under `tests/`
- documentation
- non-visual JavaScript robustness, as long as it does not change UI appearance

---

## File Structure

- Modify: `api/services/rate_limit_service.php`  
  Responsibility: compute the effective client IP safely, trusting forwarded headers only when the direct peer is a configured trusted proxy.

- Modify: `config/constants.php`  
  Responsibility: expose optional trusted proxy configuration from `.env`.

- Modify: `.env.example`  
  Responsibility: document safe placeholders for trusted proxy settings.

- Modify: `api/services/response_service.php`  
  Responsibility: encode JSON consistently with UTF-8 support and fail safely if encoding fails.

- Modify: `api/admin/export_revenue.php`  
  Responsibility: add server-side date filters, max export range, and structured logging for export operations.

- Modify: `js/admin-common.js`  
  Responsibility: make notification audio initialization safe without changing any UI.

- Create: `tests/static_source_quality_check.ps1`  
  Responsibility: verify source-level hardening patterns for trusted proxies, JSON encoding flags, export limits, and safe admin audio.

- Modify: `README.md` and `docs/operations.md`  
  Responsibility: document production hardening expectations and the new verification script.

---

### Task 1: Trusted Proxy Configuration

**Files:**
- Modify: `config/constants.php`
- Modify: `.env.example`

- [ ] **Step 1: Add trusted proxy constants**

In `config/constants.php`, after Redis constants, add:

```php
define('TRUSTED_PROXY_IPS', env('TRUSTED_PROXY_IPS', ''));
define('TRUST_PROXY_HEADERS', filter_var(env('TRUST_PROXY_HEADERS', 'false'), FILTER_VALIDATE_BOOLEAN));
```

- [ ] **Step 2: Document placeholders**

In `.env.example`, add:

```dotenv
# --- Reverse proxy / CDN ---
# Keep false unless Apache receives traffic from a trusted reverse proxy/CDN.
TRUST_PROXY_HEADERS=false
# Comma-separated direct proxy IPs allowed to provide X-Forwarded-For / CF-Connecting-IP.
TRUSTED_PROXY_IPS=
```

- [ ] **Step 3: Verify config still loads**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1
```

Expected: `PHP lint passed for ... file(s)`.

- [ ] **Step 4: Commit**

```bash
git add config/constants.php .env.example
git commit -m "config: add trusted proxy settings"
```

---

### Task 2: Safe Client IP Resolution

**Files:**
- Modify: `api/services/rate_limit_service.php`
- Test: `tests/static_source_quality_check.ps1`

- [ ] **Step 1: Create the failing static check**

Create `tests/static_source_quality_check.ps1`:

```powershell
$ErrorActionPreference = "Stop"

function Assert-Contains {
    param(
        [string]$Name,
        [string]$Path,
        [string]$Pattern
    )
    $content = Get-Content $Path -Raw
    if ($content -notmatch $Pattern) {
        Write-Error "FAIL $Name"
    }
    Write-Host "PASS $Name"
}

Assert-Contains "trusted_proxy_flag" "config\constants.php" "TRUST_PROXY_HEADERS"
Assert-Contains "trusted_proxy_ips" "config\constants.php" "TRUSTED_PROXY_IPS"
Assert-Contains "rate_limit_trusts_proxy_conditionally" "api\services\rate_limit_service.php" "isTrustedProxy"
Assert-Contains "rate_limit_uses_remote_addr_first" "api\services\rate_limit_service.php" "REMOTE_ADDR"
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\static_source_quality_check.ps1
```

Expected before implementation: fails on `rate_limit_trusts_proxy_conditionally`.

- [ ] **Step 3: Implement trusted proxy helpers**

In `api/services/rate_limit_service.php`, replace `getClientIp()` with:

```php
private static function getClientIp(): string {
    $remoteAddr = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));

    if (self::shouldTrustProxyHeaders($remoteAddr)) {
        $forwarded = self::firstValidForwardedIp([
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
            $_SERVER['HTTP_X_REAL_IP'] ?? '',
        ]);

        if ($forwarded !== null) {
            return $forwarded;
        }
    }

    return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '0.0.0.0';
}

private static function shouldTrustProxyHeaders(string $remoteAddr): bool {
    if (!defined('TRUST_PROXY_HEADERS') || !TRUST_PROXY_HEADERS) {
        return false;
    }

    return self::isTrustedProxy($remoteAddr);
}

private static function isTrustedProxy(string $remoteAddr): bool {
    if (!filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
        return false;
    }

    $trusted = defined('TRUSTED_PROXY_IPS') ? TRUSTED_PROXY_IPS : '';
    $ips = array_filter(array_map('trim', explode(',', $trusted)));

    return in_array($remoteAddr, $ips, true);
}

private static function firstValidForwardedIp(array $headers): ?string {
    foreach ($headers as $header) {
        foreach (explode(',', (string)$header) as $candidate) {
            $ip = trim($candidate);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }

    return null;
}
```

- [ ] **Step 4: Run static check**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\static_source_quality_check.ps1
```

Expected: all checks pass.

- [ ] **Step 5: Run security smoke**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\smoke\security_smoke.ps1
```

Expected: `PASS admin_stats_requires_login`, `PASS post_without_csrf_rejected`, `PASS wrong_password_rejected`, `PASS webhook_requires_authorization`.

- [ ] **Step 6: Commit**

```bash
git add api/services/rate_limit_service.php tests/static_source_quality_check.ps1
git commit -m "security: trust proxy headers conditionally"
```

---

### Task 3: Safer JSON Responses

**Files:**
- Modify: `api/services/response_service.php`
- Test: `tests/static_source_quality_check.ps1`

- [ ] **Step 1: Extend static check**

Append to `tests/static_source_quality_check.ps1`:

```powershell
Assert-Contains "json_unescaped_unicode" "api\services\response_service.php" "JSON_UNESCAPED_UNICODE"
Assert-Contains "json_throw_on_error" "api\services\response_service.php" "JSON_THROW_ON_ERROR"
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\static_source_quality_check.ps1
```

Expected before implementation: fails on JSON response checks.

- [ ] **Step 3: Replace JSON encoding**

In `api/services/response_service.php`, change `json()` to:

```php
public static function json(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');

    try {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        error_log('[ResponseService] JSON encode failed: ' . $e->getMessage());
        http_response_code(500);
        echo '{"success":false,"message":"Loi he thong. Vui long thu lai sau."}';
    }

    exit;
}
```

- [ ] **Step 4: Run checks**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\static_source_quality_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\api_smoke.ps1
```

Expected: static checks pass; API smoke passes.

- [ ] **Step 5: Commit**

```bash
git add api/services/response_service.php tests/static_source_quality_check.ps1
git commit -m "api: harden json response encoding"
```

---

### Task 4: Revenue Export Guardrails

**Files:**
- Modify: `api/admin/export_revenue.php`
- Test: `tests/static_source_quality_check.ps1`

- [ ] **Step 1: Extend static check**

Append to `tests/static_source_quality_check.ps1`:

```powershell
Assert-Contains "export_revenue_date_range" "api\admin\export_revenue.php" "DateTimeImmutable"
Assert-Contains "export_revenue_max_days" "api\admin\export_revenue.php" "maxExportDays"
Assert-Contains "export_revenue_logger" "api\admin\export_revenue.php" "Logger::"
Assert-Contains "export_revenue_prepared_statement" "api\admin\export_revenue.php" "prepare\("
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\static_source_quality_check.ps1
```

Expected before implementation: fails on export guardrail checks.

- [ ] **Step 3: Add date range filtering and logging**

In `api/admin/export_revenue.php`, after auth requires, add:

```php
require_once ROOT_PATH . '/api/services/logger_service.php';

$timezone = new DateTimeZone('Asia/Ho_Chi_Minh');
$today = new DateTimeImmutable('today', $timezone);
$maxExportDays = 93;

$fromInput = trim((string)($_GET['from'] ?? $today->modify('-30 days')->format('Y-m-d')));
$toInput = trim((string)($_GET['to'] ?? $today->format('Y-m-d')));

$fromDate = DateTimeImmutable::createFromFormat('!Y-m-d', $fromInput, $timezone);
$toDate = DateTimeImmutable::createFromFormat('!Y-m-d', $toInput, $timezone);

if (!$fromDate || !$toDate || $fromDate > $toDate) {
    http_response_code(400);
    echo 'Invalid export date range';
    exit;
}

$daySpan = $fromDate->diff($toDate)->days + 1;
if ($daySpan > $maxExportDays) {
    http_response_code(422);
    echo 'Export range cannot exceed ' . $maxExportDays . ' days';
    exit;
}

Logger::app('Admin revenue export requested', [
    'admin_id' => (int)($_SESSION['user_id'] ?? 0),
    'from' => $fromDate->format('Y-m-d'),
    'to' => $toDate->format('Y-m-d'),
]);
```

Replace the export query with:

```php
$sql = "SELECT o.id, u.name as customer_name, u.phone as customer_phone, o.total_amount, o.discount_amount, o.final_total, o.voucher_code, o.status, o.created_at
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        ORDER BY o.created_at DESC";
$stmt = $conn->prepare($sql);
$fromValue = $fromDate->format('Y-m-d');
$toValue = $toDate->format('Y-m-d');
$stmt->bind_param('ss', $fromValue, $toValue);
$stmt->execute();
$result = $stmt->get_result();
```

Before closing the connection, close the statement:

```php
if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    $stmt->close();
}
```

- [ ] **Step 4: Run checks**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\static_source_quality_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\admin_observability_smoke.ps1
```

Expected: all checks pass.

- [ ] **Step 5: Commit**

```bash
git add api/admin/export_revenue.php tests/static_source_quality_check.ps1
git commit -m "admin: limit and log revenue exports"
```

---

### Task 5: Safe Admin Notification Audio

**Files:**
- Modify: `js/admin-common.js`
- Test: `tests/static_source_quality_check.ps1`

- [ ] **Step 1: Extend static check**

Append to `tests/static_source_quality_check.ps1`:

```powershell
Assert-Contains "admin_audio_lazy_init" "js\admin-common.js" "function getAudioContext"
Assert-Contains "admin_audio_guarded" "js\admin-common.js" "try"
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\static_source_quality_check.ps1
```

Expected before implementation: fails on admin audio checks.

- [ ] **Step 3: Replace eager audio initialization**

In `js/admin-common.js`, replace:

```js
const audioContext = new (window.AudioContext || window.webkitAudioContext)();
```

and the existing `playDing()` function with:

```js
let audioContext = null;

function getAudioContext() {
    if (audioContext) return audioContext;
    const AudioCtor = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtor) return null;

    try {
        audioContext = new AudioCtor();
        return audioContext;
    } catch (err) {
        console.warn('Notification audio unavailable:', err);
        return null;
    }
}

function playDing() {
    const ctx = getAudioContext();
    if (!ctx) return;

    try {
        const oscillator = ctx.createOscillator();
        const gainNode = ctx.createGain();
        oscillator.connect(gainNode);
        gainNode.connect(ctx.destination);
        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(1000, ctx.currentTime);
        gainNode.gain.setValueAtTime(0.1, ctx.currentTime);
        oscillator.start();
        gainNode.gain.exponentialRampToValueAtTime(0.00001, ctx.currentTime + 0.5);
        oscillator.stop(ctx.currentTime + 0.5);
    } catch (err) {
        console.warn('Notification audio failed:', err);
    }
}
```

- [ ] **Step 4: Run JS and static checks**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_js_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\static_source_quality_check.ps1
```

Expected: JS syntax and static checks pass.

- [ ] **Step 5: Commit**

```bash
git add js/admin-common.js tests/static_source_quality_check.ps1
git commit -m "admin: guard notification audio initialization"
```

---

### Task 6: Documentation and Verification Checklist

**Files:**
- Modify: `README.md`
- Modify: `docs/operations.md`
- Modify: `TESTING_CHECKLIST.md`

- [ ] **Step 1: Add source quality check command to README**

In `README.md`, under Local Verification, add:

```powershell
.\tests\static_source_quality_check.ps1
```

- [ ] **Step 2: Add production hardening notes**

In `docs/operations.md`, add:

```markdown
## Production Hardening Notes

- Rotate `SEPAY_WEBHOOK_TOKEN` and `JWT_SECRET` before any public deployment.
- Keep `TRUST_PROXY_HEADERS=false` unless Apache receives traffic only from a trusted proxy/CDN.
- If enabling proxy headers, set `TRUSTED_PROXY_IPS` to the direct proxy IPs that connect to Apache.
- Revenue exports are intentionally date-limited; use `from=YYYY-MM-DD&to=YYYY-MM-DD` for bounded exports.
```

- [ ] **Step 3: Add checklist entry**

In `TESTING_CHECKLIST.md`, add:

```markdown
- [ ] Static source quality check: `powershell -ExecutionPolicy Bypass -File .\tests\static_source_quality_check.ps1`
```

- [ ] **Step 4: Run full non-browser verification**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_php_unit.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_js_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\static_auth_backend_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\static_auth_frontend_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\static_source_quality_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\api_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\security_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\page_load_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\admin_observability_smoke.ps1
```

Expected: every command exits 0 with PASS output.

- [ ] **Step 5: Commit**

```bash
git add README.md docs/operations.md TESTING_CHECKLIST.md
git commit -m "docs: document source hardening checks"
```

---

## Final Verification

Run:

```powershell
git status --short
powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_php_unit.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_js_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\static_auth_backend_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\static_auth_frontend_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\static_source_quality_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\api_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\security_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\page_load_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\admin_observability_smoke.ps1
```

Expected:
- The only git changes are intentional files from this plan.
- All lint/static/unit/smoke commands pass.
- No CSS, layout HTML, or visual UI files were changed except documentation references.

---

## Self-Review

Spec coverage:
- No UI changes: covered by Non-UI Constraint and Final Verification.
- Security score uplift: trusted proxy rate limiting, secret guidance, export bounds.
- Maintainability score uplift: shared response handling and static source quality check.
- Reliability score uplift: safe admin audio initialization and full verification commands.

Placeholder scan:
- No `TBD`, `TODO`, or unspecified implementation steps remain.

Type consistency:
- `TRUST_PROXY_HEADERS`, `TRUSTED_PROXY_IPS`, `isTrustedProxy()`, `getAudioContext()`, and `maxExportDays` are consistently named across implementation and tests.
