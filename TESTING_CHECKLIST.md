# Testing Checklist

Use this checklist before pushing or demoing the project.

## Setup

- Import `Database/duong_bau_restaurant.sql` into MySQL.
- Copy `.env.example` to `.env` and fill local DB, mail, SePay and JWT values.
- Run `php Database/migrate.php status` and confirm all migrations are `Ran`.
- Open the app through Laragon/Apache, not directly from the filesystem.

## Customer Flows

- Register a new customer with valid name, phone, email and password.
- Try duplicate phone/email registration and confirm it is rejected.
- Login as customer, refresh the page, and confirm the session remains valid.
- Add menu items to cart, apply a valid voucher, then place a cash order.
- Add menu items to cart, choose bank transfer, and confirm QR/payment content shows `DH{id}`.
- Poll payment status for the order and confirm unpaid orders stay `pending`.
- Open profile, update name/phone/email, upload a valid avatar under 2 MB.
- Change password, logout, then login with the new password.

## Booking Flows

- Book a future table during 08:00-22:00 with guest count under table capacity.
- Try a past date/time and confirm the API rejects it.
- Try guest count above capacity and confirm the API rejects it.
- Try booking the same table within a 2-hour window and confirm conflict handling.
- Book with preorder and confirm QR/payment content shows `BKG{id}`.
- Poll booking payment status and confirm unpaid bookings stay `pending`.

## Admin Flows

- Login as admin and confirm `/api/admin/auth_check_api.php` gates protected endpoints.
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

## Regression Checks

- Run PHP lint over all `.php` files.
- Run `php Database/migrate.php status` from CLI and confirm there are no session warnings.
- Open `index.html`, `booking.html`, `profile.html`, `login.html`, `forgot_password.html`.
- Check browser console for JavaScript errors in customer and admin pages.
