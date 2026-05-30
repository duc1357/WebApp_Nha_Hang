# Source Quality Uplift Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Raise the source quality score by closing the remaining auth lifecycle and test hygiene gaps found after the latest audit.

**Architecture:** Keep the existing session/JWT model and harden it at the shared auth-state boundary. Add regression coverage before changing behavior, then make admin self-updates and smoke-test cleanup deterministic.

**Tech Stack:** PHP 8, MySQLi, vanilla JavaScript, PowerShell smoke/static tests, Laragon PHP CLI.

---

## Target Outcome

After this plan, the codebase should move from roughly **8.3/10** to about **8.8-9.0/10** for this project scale because the remaining medium-risk auth and test-data issues will be closed.

## Files To Modify

- `api/services/auth_state_service.php`
  - Enforce strict rejection for sessions missing `token_version`.
  - Add a helper to sync the current session after intentional self-mutation.
- `api/admin/update_user.php`
  - Keep revocation for normal admin edits.
  - Sync current session `token_version` only when an admin updates their own admin account successfully.
- `tests/smoke/auth_lifecycle_smoke.ps1`
  - Add `try/finally` cleanup for temporary smoke users.
  - Add self-update regression coverage.
- `tests/static_auth_backend_check.ps1`
  - Add lightweight static checks for strict session behavior and self-update sync.
- `README.md`, `TESTING_CHECKLIST.md`, `docs/operations.md`
  - Add the backend static check to verification docs.
  - Document strict session revocation after deployment.

## Task 1: Strict Session Version Enforcement

**Files:**
- Modify: `api/services/auth_state_service.php`
- Test: `tests/static_auth_backend_check.ps1`

- [x] **Step 1: Write the failing static backend check**

Create `tests/static_auth_backend_check.ps1`:

```powershell
$ErrorActionPreference = "Stop"

function Assert-Contains {
    param(
        [string] $Name,
        [string] $Path,
        [string] $Pattern
    )

    $content = Get-Content $Path -Raw
    if ($content -notmatch $Pattern) {
        throw "$Name failed: $Path does not match $Pattern"
    }
    Write-Output "PASS $Name"
}

Assert-Contains "session_missing_token_version_rejected" "api\services\auth_state_service.php" "!\s*isset\(\$_SESSION\['token_version'\]\)"
Assert-Contains "session_token_version_mismatch_rejected" "api\services\auth_state_service.php" "\(int\)\$_SESSION\['token_version'\]\s*!==\s*\$dbTokenVersion"
Assert-Contains "session_sync_helper_exists" "api\services\auth_state_service.php" "function\s+syncCurrentSessionVersion"
Assert-Contains "admin_self_update_syncs_session" "api\admin\update_user.php" "AuthStateService::syncCurrentSessionVersion"
```

- [x] **Step 2: Run test to verify it fails**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\static_auth_backend_check.ps1
```

Expected: FAIL on `session_missing_token_version_rejected`.

- [x] **Step 3: Implement strict rejection**

In `api/services/auth_state_service.php`, replace the current version check:

```php
if (isset($_SESSION['token_version']) && (int)$_SESSION['token_version'] !== $dbTokenVersion) {
```

with:

```php
if (!isset($_SESSION['token_version']) || (int)$_SESSION['token_version'] !== $dbTokenVersion) {
```

Keep the existing `clearSession()` and 401 response payload.

- [x] **Step 4: Run test to verify partial pass/fail**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\static_auth_backend_check.ps1
```

Expected: strict version checks pass, helper/self-update checks still fail until Task 2.

## Task 2: Admin Self-Update Session Sync

**Files:**
- Modify: `api/services/auth_state_service.php`
- Modify: `api/admin/update_user.php`
- Test: `tests/smoke/auth_lifecycle_smoke.ps1`
- Test: `tests/static_auth_backend_check.ps1`

- [x] **Step 1: Add a failing self-update smoke assertion**

Append this flow to `tests/smoke/auth_lifecycle_smoke.ps1` after admin login and before creating the temporary customer:

```powershell
$selfUsers = Invoke-RestMethod -Uri "$baseUrl/api/admin/get_users_list.php?search=admin.demo@example.test&limit=1" -WebSession $adminSession -TimeoutSec 10
$selfAdmin = @($selfUsers.users | Where-Object { $_.email -eq "admin.demo@example.test" }) | Select-Object -First 1
if ($null -eq $selfAdmin) {
    throw "admin self user was not found"
}

$adminCsrf = Get-CsrfToken $adminSession
$selfUpdate = Invoke-JsonPost `
    -Uri "$baseUrl/api/admin/update_user.php" `
    -Body @{
        id = [int]$selfAdmin.id
        name = $selfAdmin.name
        phone = $selfAdmin.phone
        email = $selfAdmin.email
        password = ""
        role = "admin"
    } `
    -Session $adminSession `
    -Csrf $adminCsrf

if ($selfUpdate.success -ne $true) {
    throw "admin self update failed"
}

$postSelfUpdateStats = Invoke-RestMethod -Uri "$baseUrl/api/admin/get_stats.php" -WebSession $adminSession -TimeoutSec 10
if ($postSelfUpdateStats.success -ne $true) {
    throw "admin session was not synced after self update"
}

Write-Output "PASS admin_self_update_session_synced"
```

- [x] **Step 2: Run smoke to verify it fails**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\smoke\auth_lifecycle_smoke.ps1
```

Expected: FAIL with admin session rejected after self-update.

- [x] **Step 3: Add session sync helper**

Add this method to `api/services/auth_state_service.php` before `clearSession()`:

```php
public static function syncCurrentSessionVersion(int $userId, int $tokenVersion, string $role): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if ((int)($_SESSION['user_id'] ?? 0) !== $userId) {
        return;
    }

    $_SESSION['role'] = $role;
    $_SESSION['token_version'] = $tokenVersion;
}
```

- [x] **Step 4: Sync self-update only after successful admin update**

In `api/admin/update_user.php`, add:

```php
require_once ROOT_PATH . '/api/services/auth_state_service.php';
```

After `$stmt->execute()` succeeds and before returning JSON, add:

```php
if ($id === (int)$_SESSION['user_id']) {
    $versionStmt = $conn->prepare('SELECT token_version, role FROM users WHERE id = ? LIMIT 1');
    $versionStmt->bind_param('i', $id);
    $versionStmt->execute();
    $versionRow = $versionStmt->get_result()->fetch_assoc();
    $versionStmt->close();

    if ($versionRow) {
        AuthStateService::syncCurrentSessionVersion($id, (int)$versionRow['token_version'], (string)$versionRow['role']);
    }
}
```

- [x] **Step 5: Run tests to verify pass**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\static_auth_backend_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\auth_lifecycle_smoke.ps1
```

Expected: both pass.

## Task 3: Smoke Test Cleanup Safety

**Files:**
- Modify: `tests/smoke/auth_lifecycle_smoke.ps1`

- [x] **Step 1: Wrap temporary user flow in `try/finally`**

After `$userId` is known, wrap the update/delete/assertion flow in:

```powershell
$testUserDeleted = $false
try {
    # existing update, stale session, stale JWT, and delete assertions stay here
} finally {
    if (-not $testUserDeleted -and $userId -gt 0) {
        try {
            $cleanupCsrf = Get-CsrfToken $adminSession
            Invoke-JsonPost `
                -Uri "$baseUrl/api/admin/delete_user.php" `
                -Body @{ id = $userId } `
                -Session $adminSession `
                -Csrf $cleanupCsrf | Out-Null
            Write-Output "PASS auth_lifecycle_cleanup"
        } catch {
            Write-Warning "auth lifecycle cleanup failed for user id $userId: $($_.Exception.Message)"
        }
    }
}
```

Inside the normal delete-success branch, set:

```powershell
$testUserDeleted = $true
```

- [x] **Step 2: Clean local leftover test data**

Run this once locally to soft-delete old active smoke leftovers:

```powershell
@'
<?php
require 'config/db.php';
$conn = getDbConnection();
$stmt = $conn->prepare("UPDATE users SET deleted_at = COALESCE(deleted_at, NOW()), token_version = token_version + 1 WHERE email LIKE 'auth-smoke-%@example.test' AND deleted_at IS NULL");
$stmt->execute();
echo "Cleaned active auth smoke users: " . $stmt->affected_rows . PHP_EOL;
$stmt->close();
$conn->close();
'@ | C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe
```

Expected: `Cleaned active auth smoke users: 0` or a small positive number.

- [x] **Step 3: Run smoke twice**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\smoke\auth_lifecycle_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\auth_lifecycle_smoke.ps1
```

Expected: both runs pass and create no active `auth-smoke-*` user.

## Task 4: Documentation And Verification

**Files:**
- Modify: `README.md`
- Modify: `TESTING_CHECKLIST.md`
- Modify: `docs/operations.md`

- [x] **Step 1: Add backend static check to docs**

Add this command next to the frontend static check in all three docs:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\static_auth_backend_check.ps1
```

- [x] **Step 2: Document strict session behavior**

Add this operational note to `docs/operations.md` under login/auth troubleshooting:

```markdown
- Sessions without `token_version` are intentionally rejected after the auth hardening update; ask users to log in again after deploy if they hit a 401.
```

- [x] **Step 3: Run full verification**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_php_unit.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_js_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\static_auth_frontend_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\static_auth_backend_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\auth_lifecycle_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\api_response_standardization_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\security_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\api_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\payment_booking_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\admin_observability_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\page_load_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\browser_console_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\browser_workflow_smoke.ps1
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe Database\migrate.php status
```

Expected:
- PHP lint passes for all PHP files.
- Unit helper tests pass.
- JS syntax passes for all JS files.
- Both static auth checks pass.
- All smoke scripts pass.
- Migration status shows `2026_04_29_120000_add_token_version_to_users` as `Ran`.

## Final Review Checklist

- [x] Old sessions missing `token_version` are rejected.
- [x] Admin self-update does not accidentally log out the current admin.
- [x] Admin update/delete of another user still revokes that user's stale session and JWT refresh.
- [x] `auth_lifecycle_smoke.ps1` cleans up temporary users even when assertions fail.
- [x] No active `auth-smoke-*` local users remain.
- [x] `index.html` remains untouched unless separately requested.
- [x] Existing upload index removals remain respected and are not reverted.
