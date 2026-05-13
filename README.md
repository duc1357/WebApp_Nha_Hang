# Duong Bau Restaurant

Full-stack restaurant ordering and booking system built with PHP, MySQL and vanilla JavaScript.

## Features

- Customer registration/login, profile, password reset via OTP
- Menu browsing, cart, voucher, checkout
- Table booking with preorder deposit
- SePay QR payment webhook
- Admin dashboard for users, menu, orders, bookings, vouchers

## Tech Stack

- PHP 8
- MySQL
- Apache/Laragon
- Vanilla JavaScript
- CSS

## Security Highlights

- Prepared statements
- `password_hash`
- CSRF tokens
- Rate limiting
- Secure upload validation
- Webhook token verification

## Setup

1. Import `Database/duong_bau_restaurant.sql`
2. Copy `.env.example` to `.env`
3. Configure DB, mail, SePay and JWT secret
4. Run migrations in `Database/migrations`

## Demo Accounts

Seed demo accounts locally with non-production credentials before recording or presenting:

| Role | Email | Password | Notes |
| --- | --- | --- | --- |
| Admin | `admin.demo@example.test` | `ChangeMeDemo123!` | Create only in local/staging data. |
| Customer | `customer.demo@example.test` | `ChangeMeDemo123!` | Use for checkout and booking flows. |

Never reuse production passwords or customer data for demos.

## Screenshots

Recommended screenshot set for the portfolio:

- Homepage/menu browsing
- Cart with voucher applied
- Checkout/payment QR modal
- Table booking map with preorder
- Admin dashboard
- Admin voucher management

## Known Limitations

No automated test suite yet; payment requires SePay webhook configuration.
