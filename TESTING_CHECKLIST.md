# Testing Checklist

Use this checklist before pushing or demoing the project.

## Setup

- Import `Database/duong_bau_restaurant.sql` into MySQL.
- Copy `.env.example` to `.env` and fill local DB, mail, SePay and JWT values.
- Run `php Database/migrate.php status` and confirm all migrations are `Ran`.
- Open the app through Laragon/Apache, not directly from the filesystem.
- Review `docs/deployment.md` before deploying to a new host.
- Review `docs/operations.md` before demos or production handoff.

## Automated Baseline

- Run `.\tests\run_php_lint.ps1` and confirm all PHP files pass syntax checks.
- Run `.\tests\run_php_unit.ps1` and confirm helper behavior tests pass.
- Run `.\tests\run_js_check.ps1` and confirm all JavaScript files pass syntax checks.
- Run `.\tests\static_auth_frontend_check.ps1` and confirm auth pages wait for CSRF and match backend validation rules.
- Run `.\tests\static_auth_backend_check.ps1` and confirm auth-state revocation rules stay strict.
- Run `.\tests\static_source_quality_check.ps1` and confirm source hardening checks pass.
- Run `.\tests\smoke\auth_lifecycle_smoke.ps1` and confirm admin user edits/deletes revoke stale sessions and JWT refresh.
- Run `.\tests\smoke\api_smoke.ps1` and confirm public API plus admin login/stats smoke checks pass.
- Run `.\tests\smoke\security_smoke.ps1` and confirm auth, CSRF and webhook boundaries reject invalid requests.
- Run `.\tests\smoke\payment_booking_smoke.ps1` and confirm invalid order/booking inputs are rejected before writes.
- Run `.\tests\smoke\admin_observability_smoke.ps1` and confirm admin logs/health are authenticated and direct logs stay blocked.
- Run `.\tests\smoke\page_load_smoke.ps1` and confirm key customer/admin pages return `200` while sensitive paths stay blocked.
- Run `.\tests\smoke\browser_console_smoke.ps1` and confirm key customer/admin pages do not emit JavaScript exceptions or `console.error`.
- Run `.\tests\smoke\browser_workflow_smoke.ps1` and confirm non-destructive UI workflows still respond to clicks and form state changes.
- Before HTTP smoke tests, confirm `http://restaurant.test` is reachable through Laragon/Apache.
- If Windows blocks `.ps1` files, run them with `powershell -ExecutionPolicy Bypass -File <script>`.
- Set `$env:RESTAURANT_BASE_URL` before the smoke test if the local host is not `http://restaurant.test`.
- `payment_booking_smoke.ps1` requires local `.env` values for `SEPAY_WEBHOOK_TOKEN`, `SEPAY_BANK_NAME`, and `SEPAY_VA_ACCOUNT` because it sends real webhook HTTP requests.

## Customer Flows

- Confirm homepage menu shows loading, empty and error states cleanly when APIs are slow or unavailable.
- Register a new customer with valid name, phone, email and password.
- Try duplicate phone/email registration and confirm it is rejected.
- Login as customer, refresh the page, and confirm the session remains valid.
- Add menu items to cart, apply a valid voucher, then place a cash order.
- Add menu items to cart, choose bank transfer, and confirm QR/payment content shows `DH{id}`.
- Poll payment status for the order and confirm unpaid orders stay `pending`.
- Open profile, update name/phone/email, upload a valid avatar under 2 MB.
- Open profile order and booking history; confirm empty/error states are readable.
- Change password, logout, then login with the new password.

## Booking Flows

- Open booking page before choosing date/time and confirm the empty state is readable.
- Book a future table during 08:00-22:00 with guest count under table capacity.
- Try a past date/time and confirm the API rejects it.
- Try guest count above capacity and confirm the API rejects it.
- Try booking the same table within a 2-hour window and confirm conflict handling.
- Book with preorder and confirm QR/payment content shows `BKG{id}`.
- Poll booking payment status and confirm unpaid bookings stay `pending`.

## Admin Flows

- Login as admin and confirm `/api/admin/auth_check_api.php` gates protected endpoints.
- Update or soft-delete a test user and confirm stale sessions/tokens are rejected.
- Open `admin/logs.php` and confirm health checks plus log filters load.
- Create, update, soft-delete menu items; verify deleted items disappear from public menu.
- Create, update, delete vouchers; verify inactive/expired vouchers are rejected.
- Mark a pending order as paid and confirm voucher usage increments once.
- Checkout a dine-in table and confirm the table returns to available.
- Update booking status from pending to confirmed/cancelled/completed.
- Export revenue and confirm date filters return expected rows.

## Payment Webhook

- Call webhook without `Authorization: Apikey ...` and confirm `401`.
- Temporarily remove `SEPAY_WEBHOOK_TOKEN` locally and confirm webhook returns `503`.
- Send a valid order payment payload with exact/greater amount and content `DH{id}`.
- Send an insufficient amount and confirm it is rejected.
- Send unexpected account/gateway values and confirm they are rejected.
- Re-send the same valid webhook and confirm voucher usage does not increment twice.
- Duplicate SePay order and booking webhooks are verified through real HTTP webhook calls; voucher usage remains incremented exactly once.

## Regression Checks

- Run `.\tests\run_php_lint.ps1`.
- Run `.\tests\run_php_unit.ps1`.
- Run `.\tests\run_js_check.ps1`.
- Run `.\tests\static_auth_frontend_check.ps1`.
- Run `.\tests\static_auth_backend_check.ps1`.
- Run `.\tests\smoke\auth_lifecycle_smoke.ps1`.
- Run `.\tests\smoke\api_smoke.ps1`.
- Run `.\tests\smoke\security_smoke.ps1`.
- Run `.\tests\smoke\payment_booking_smoke.ps1`.
- Run `.\tests\smoke\admin_observability_smoke.ps1`.
- Run `.\tests\smoke\page_load_smoke.ps1`.
- Run `.\tests\smoke\browser_console_smoke.ps1`.
- Run `.\tests\smoke\browser_workflow_smoke.ps1`.
- Run `php Database/migrate.php status` from CLI and confirm there are no session warnings.
- Open `index.html`, `booking.html`, `profile.html`, `login.html`, `forgot_password.html`.
- Confirm `photo/default-food.png`, `photo/default-user.png`, CSS and JS assets load without 404s.
- Confirm direct access to `/config/`, `/Database/`, `/logs/`, and `/.env` is blocked.
- Check browser console for JavaScript errors in customer and admin pages.

## Source Audit Verification - 2026-05-21

Automated checks run from `C:\laragon\www\restaurant`:

- [x] PHP lint: `PHP lint passed for 92 file(s)`.
- [x] JavaScript syntax: `JS syntax check passed for 20 file(s)`.
- [x] API smoke: `csrf`, `menu`, `tables`, `featured_reviews`, `booked_tables`, `admin_login_and_stats`.
- [x] Security smoke: `admin_stats_requires_login`, `post_without_csrf_rejected`, `wrong_password_rejected`, `webhook_requires_authorization`.
- [x] Payment/booking smoke: `empty_cart_rejected`, `invalid_table_rejected_before_order_insert`, `past_booking_rejected`, `empty_preorder_rejected`, duplicate webhook idempotency, insufficient payment/deposit rejection.
- [x] Admin observability smoke: `admin_health`, `admin_logs`, `admin_logs_requires_login`, `direct_log_access_blocked`.
- [x] Page-load smoke: `home_page`, `booking_page`, `profile_page`, `login_page`, `forgot_password_page`, `admin_login_page`, `admin_bookings_page`, `admin_logs_page`, and sensitive path blocks.
- [x] Browser console smoke: key customer/admin pages load in headless Chrome without JavaScript exceptions or `console.error`.
- [x] Browser workflow smoke: `home_cart_toggle`, `booking_preorder_drawer`, `login_tabs`, `forgot_password_back_link`, `admin_login_page`, `admin_logs_controls`.
- [x] Migration status: `2026_04_29_120000_add_token_version_to_users` is `Ran`.
- [x] HTTP page-load check: `/`, `/profile.html`, `/admin/`, `/admin/bookings.php`, and `/admin/logs.php` return `200`.

Acceptance coverage captured by automated checks:

- [x] Homepage loads menu and reviews.
- [x] Login/logout rotates CSRF and session state.
- [x] Cart checkout rejects invalid input.
- [x] Booking rejects past dates and conflicting table windows.
- [x] SePay webhook rejects missing token.
- [x] Admin dashboard, logs, users, bookings, orders, menu, and vouchers checks are covered by admin smoke and page-load checks.
- [x] Direct `/logs/` access is blocked.

Manual browser console verification was not run in this Codex session because the browser automation tool required by the available Browser skill was not exposed.
