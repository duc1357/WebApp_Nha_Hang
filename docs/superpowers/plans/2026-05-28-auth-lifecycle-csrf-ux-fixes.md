# Auth Lifecycle, CSRF Race, and UX Validation Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the remaining audit findings: stale sessions/JWTs after admin role/password/delete changes, public auth CSRF race conditions, and frontend/backend validation mismatches.

**Architecture:** Keep the existing PHP session + JWT architecture, but add a small auth-state validation layer that checks `users.deleted_at`, `users.role`, and `users.token_version` against the active session/JWT. Reuse the current CSRF helpers in frontend scripts by making auth requests wait for token initialization.

**Tech Stack:** PHP 8, MySQL/PDO, vanilla JavaScript, PowerShell test/smoke scripts.

---

## Findings Being Fixed

- **High:** Admin update/delete user does not revoke existing sessions/JWTs. A demoted, password-reset, or soft-deleted account can keep using an old authenticated state until logout/expiry.
- **Medium:** `js/login.js`, `js/admin-login.js`, and `js/forgot-password.js` can submit before the CSRF token finishes loading, causing intermittent first-request 403s.
- **Low:** Registration UI says email is optional, but `api/auth/register.php` requires a valid email.
- **Low:** Forgot-password frontend checks only 6 characters, while `api/services/password_policy.php` requires at least 8 chars, uppercase, and digit.

## Implementation Steps

- [x] Create a focused auth lifecycle smoke test before changing behavior.
  - Add `tests/smoke/auth_lifecycle_smoke.ps1`.
  - Use the same request/session helper style as existing smoke scripts.
  - Test flow:
    - Login as admin.
    - Create a temporary user through the existing admin API.
    - Login as that temporary user and capture session/JWT state where the API exposes it.
    - Change that user through `api/admin/update_user.php`.
    - Assert old authenticated access is rejected after `token_version` changes.
    - Soft-delete that user through `api/admin/delete_user.php`.
    - Assert deleted account can no longer authenticate or refresh a token.
    - Clean up only the temporary test user created by the script.
  - Expected first result before implementation: at least one stale-auth assertion fails.

- [x] Add central PHP session auth-state validation.
  - Add `api/services/auth_state_service.php`.
  - Implement methods:
    - `getActiveUser(PDO $pdo, int $userId): ?array`
      - Select `id`, `role`, `token_version`, `deleted_at`.
      - Return `null` if missing or `deleted_at IS NOT NULL`.
    - `validateSession(PDO $pdo, ?string $requiredRole = null): array`
      - Require `$_SESSION['user_id']`.
      - Load active user.
      - Reject if deleted/missing.
      - Reject if `$requiredRole` is set and DB role does not match.
      - Reject if `$_SESSION['token_version']` exists and differs from DB `token_version`.
      - Sync `$_SESSION['role']` and `$_SESSION['token_version']` from DB after validation.
    - `clearSession(): void`
      - Clear local auth session fields consistently.
  - Keep response behavior compatible with existing APIs: 401 for unauthenticated/revoked sessions, 403 for wrong role where the current endpoint already distinguishes it.

- [x] Store `token_version` at login time.
  - Update `api/auth/login.php`.
  - Update `api/admin/login.php`.
  - Ensure successful login writes:
    - `$_SESSION['user_id']`
    - `$_SESSION['role']`
    - `$_SESSION['token_version']`
  - If login queries do not currently select `token_version`, add it.

- [x] Apply session auth-state validation to admin entry points.
  - Update `admin/auth_check.php` to validate the current user against DB instead of trusting only `$_SESSION['role']`.
  - Update `api/admin/auth_check_api.php` similarly.
  - Verify admin pages and admin API routes still return the same JSON/redirect shape expected by the smoke tests.

- [x] Apply session auth-state validation to customer/user API entry points.
  - Update `api/base.php` `requireAuth()` if present and used by protected endpoints.
  - Update protected session endpoints that manually check `$_SESSION['user_id']`, especially:
    - `api/user/get_user_history.php`
    - `api/user/get_order_details.php`
    - `api/user/update_profile.php`
    - `api/user/change_password.php`
    - `api/user/upload_avatar.php`
    - `api/user/book_table.php`
    - `api/user/submit_review.php`
    - `api/payment/create_payment.php`
    - `api/payment/check_status.php`
    - `api/payment/check_status_booking.php`
  - Preserve each endpoint's existing success payload and error envelope conventions.

- [x] Revoke auth state on admin user mutation.
  - Update `api/admin/update_user.php`.
  - Increment `token_version = token_version + 1` when admin changes user role, password, email, phone, or name. Bumping on any admin edit is acceptable and simpler than conditional revocation.
  - Update `api/admin/delete_user.php`.
  - Soft-delete with `deleted_at = NOW(), token_version = token_version + 1`.
  - Do not hard-delete users in this fix.

- [x] Harden JWT validation and refresh against revoked/deleted users.
  - Update `api/services/jwt_service.php`.
  - When validating token claims, select `token_version`, `role`, and `deleted_at`.
  - Reject if user is missing, soft-deleted, role mismatches claim, or token version mismatches claim.
  - Ensure refresh-token issuance runs the same active-user validation before returning a new token.
  - Keep token payload format backwards-compatible except for stricter rejection.

- [x] Fix CSRF initialization race in public auth JavaScript.
  - Update `js/login.js`.
  - Update `js/admin-login.js`.
  - Update `js/forgot-password.js`.
  - Pattern to use:
    - Create a module-level `csrfReady` promise from `initializeCsrfToken()`.
    - In the fetch interceptor, `await csrfReady` before adding `X-CSRF-Token` for non-GET requests.
    - Disable or safely no-op submit buttons until CSRF is ready if the existing UI pattern supports it.
  - Match the safer pattern already used by `js/utils.js` and `js/admin-common.js`.

- [x] Fix registration email UI mismatch.
  - Update `login.html`.
  - Change the registration email label from optional to required.
  - Add `required` and appropriate validation messaging for the email input.
  - Keep backend behavior unchanged because `api/auth/register.php` already requires a valid email.

- [x] Fix forgot-password password policy mismatch.
  - Update `js/forgot-password.js`.
  - Replace the 6-character-only check with the same effective policy as `api/services/password_policy.php`:
    - At least 8 characters.
    - At least one uppercase letter.
    - At least one digit.
  - Update the visible validation message to match the backend response in Vietnamese.

- [x] Add lightweight source checks for the frontend fixes.
  - Add `tests/static_auth_frontend_check.ps1`.
  - Assert the three auth scripts contain a `csrfReady` await path.
  - Assert `login.html` no longer marks registration email as optional and the input is `required`.
  - Assert forgot-password JS contains the 8-character, uppercase, and digit checks.

- [x] Update test runners and documentation.
  - Add the new smoke/static tests to `README.md`, `TESTING_CHECKLIST.md`, and `docs/operations.md`.
  - Mention that admin user edits intentionally revoke existing sessions/tokens.
  - Do not edit `index.html` unless the implementation directly requires it; it is already modified from before.

## Verification Commands

Run these after implementation:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_php_unit.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_js_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\static_auth_frontend_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\auth_lifecycle_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\api_response_standardization_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\security_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\api_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\page_load_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\payment_booking_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\admin_observability_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\browser_console_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\browser_workflow_smoke.ps1
```

## Manual Review Checklist

- [ ] Demoted admin cannot keep using old admin session.
- [ ] Soft-deleted user cannot keep using old user session.
- [ ] Soft-deleted user cannot refresh JWT.
- [ ] Existing active users can still login normally.
- [ ] Admin pages still load after valid admin login.
- [ ] Customer profile/history/payment flows still work for valid sessions.
- [ ] Fast-clicking login/register/forgot-password submit does not send POST before CSRF token is available.
- [ ] Registration form no longer implies email is optional.
- [ ] Forgot password frontend and backend agree on password policy.

## Rollback Notes

- Reverting `auth_state_service.php` usage would restore old session behavior, so rollback should be avoided unless smoke tests show a compatibility issue.
- If a protected endpoint has a unique response contract, adapt only that endpoint's wrapper response while keeping the central validation logic.
- Database schema changes are not expected because `token_version` and `deleted_at` already exist in the current migrations.
