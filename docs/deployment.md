# Deployment Guide

This project is designed for PHP/MySQL hosting without Composer or a framework.

## Local Laragon Setup

1. Place the project at:

```text
C:\laragon\www\restaurant
```

2. Import the database dump:

```text
Database/duong_bau_restaurant.sql
```

3. Copy `.env.example` to `.env`.

4. Fill the database settings:

```env
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=duong_bau_restaurant
BASE_URL=http://restaurant.test
```

5. Generate a JWT secret:

```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -r "echo bin2hex(random_bytes(32));"
```

6. Run migrations:

```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe Database\migrate.php
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe Database\migrate.php status
```

7. Open:

```text
http://restaurant.test/
```

## Required Environment Variables

Database:
- `DB_HOST`
- `DB_USER`
- `DB_PASS`
- `DB_NAME`

Application:
- `BASE_URL`

Mail:
- `MAIL_HOST`
- `MAIL_USER`
- `MAIL_PASS`
- `MAIL_PORT`
- `MAIL_FROM_NAME`

SePay:
- `SEPAY_WEBHOOK_TOKEN`
- `SEPAY_VA_ACCOUNT`
- `SEPAY_BANK_NAME`

JWT:
- `JWT_SECRET`
- `JWT_TTL_SECONDS`

## Production Deployment

1. Upload project files to the hosting document root.
2. Do not upload `.env` from local development.
3. Create a production `.env` directly on the server.
4. Import `Database/duong_bau_restaurant.sql`.
5. Run migrations through SSH if available:

```bash
php Database/migrate.php
php Database/migrate.php status
```

6. Confirm Apache honors `.htaccess`.
7. Confirm these direct URLs are blocked:

```text
/config/
/Database/
/logs/
/.env
```

8. Run smoke tests from a trusted local machine by setting:

```powershell
$env:RESTAURANT_BASE_URL = "https://your-domain.example"
```

## SePay Setup

1. Set `SEPAY_WEBHOOK_TOKEN` to the API key expected in:

```text
Authorization: Apikey <token>
```

2. Set `SEPAY_VA_ACCOUNT` to the virtual account identifier.
3. Set `SEPAY_BANK_NAME` to the gateway value SePay sends, for example `MBBank`.
4. Configure SePay webhook URL:

```text
https://your-domain.example/api/payment/webhook.php
```

5. Test with a known order content format:

```text
DH{id}
```

For booking deposits:

```text
BKG{id}
```

## Mail Setup

Use a Gmail app password or an SMTP account intended for transactional mail.

Required settings:

```env
MAIL_HOST=smtp.gmail.com
MAIL_USER=your_email@gmail.com
MAIL_PASS=your_app_password
MAIL_PORT=465
MAIL_FROM_NAME=Nha Hang Duong Bau
```

## Post-Deploy Verification

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_js_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\api_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\security_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\payment_booking_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\admin_observability_smoke.ps1
```

Then manually verify:
- Homepage menu loads.
- Login works for customer and admin.
- Cart checkout creates cash orders.
- QR checkout displays `DH{id}`.
- Booking page loads tables.
- Admin logs page opens.
