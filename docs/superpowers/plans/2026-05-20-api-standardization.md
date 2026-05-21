# API Standardization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Standardize JSON response, request parsing, and validation helpers for a pilot set of API endpoints without changing the current frontend contracts.

**Architecture:** Add three small services under `api/services` and keep `api/base.php` as a backwards-compatible function wrapper. Convert four pilot endpoints first: auth login, auth register, payment create, and table booking.

**Tech Stack:** PHP 8.3, MySQLi, custom session/CSRF/rate-limit services, no Composer/framework dependency.

---

## Pilot Scope

Files created:
- `api/services/response_service.php`
- `api/services/request_service.php`
- `api/services/validation_service.php`

Files modified:
- `api/base.php`
- `api/auth/login.php`
- `api/auth/register.php`
- `api/payment/create_payment.php`
- `api/user/book_table.php`

Contracts to preserve:
- JSON responses keep top-level `success`.
- Existing frontend fields such as `message`, `token`, `expires_in`, `user`, `order_id`, `final_total`, `payUrl`, `booking_id`, and `require_payment` remain top-level.
- CSRF behavior remains owned by `CsrfService::validateRequest()`.

---

### Task 1: Add Shared API Services

- [x] Create `ResponseService` with `success`, `error`, and `json`.
- [x] Create `RequestService` with `json`, `input`, and `contentType`.
- [x] Create `ValidationService` helpers for required strings, email, phone, date, time, int range, and enum values.
- [x] Update `api/base.php` wrappers to delegate to the new services.

### Task 2: Convert Pilot Endpoints

- [x] Convert `api/auth/login.php`.
- [x] Convert `api/auth/register.php`.
- [x] Convert `api/payment/create_payment.php`.
- [x] Convert `api/user/book_table.php`.

### Task 3: Verification

- [x] Run `powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1`.
- [x] Run `powershell -ExecutionPolicy Bypass -File .\tests\smoke\api_smoke.ps1`.
- [x] Confirm public response shapes stay compatible.

---

## Notes

This phase intentionally avoids converting every endpoint. Later phases can migrate endpoints incrementally once the pilot pattern is stable.
