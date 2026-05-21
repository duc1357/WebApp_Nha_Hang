# Order Booking Payment Robustness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make checkout, table booking, payment polling, and webhook state handling more predictable without changing the database schema.

**Architecture:** Centralize status names in a small state service, harden service-level calculations, standardize status polling endpoints, and add smoke checks for invalid inputs that must fail before writes.

**Tech Stack:** PHP 8.3, MySQLi, vanilla JS polling, SePay QR/webhook, custom API services.

---

## Status Model

Orders:
- `pending`: order created, not paid yet.
- `paid`: payment accepted or admin marks cash payment paid.
- `cancelled`: order cancelled.

Bookings:
- `pending`: booking created without preorder payment requirement.
- `awaiting_payment`: booking created with preorder deposit required.
- `confirmed`: deposit paid or admin confirmed.
- `arrived`: customer arrived.
- `completed`: booking completed.
- `cancelled`: booking cancelled.

Booking payment:
- `pending`: no deposit/final payment recorded.
- `partial`: deposit paid.
- `paid`: fully paid.

---

### Task 1: Add Robustness Smoke Coverage

- [x] Add `tests/smoke/payment_booking_smoke.ps1`.
- [x] Verify empty cart is rejected.
- [x] Verify invalid dine-in table is rejected before order insert.
- [x] Verify past booking is rejected.
- [x] Verify preorder without valid items is rejected.

### Task 2: Harden Services and Endpoints

- [x] Add `PaymentStateService` for allowed statuses.
- [x] Harden `OrderService` item quantity and voucher calculations.
- [x] Standardize `check_status.php`.
- [x] Standardize `check_status_booking.php`.
- [x] Reject preorder bookings without valid preorder items.

### Task 3: Frontend Polling Robustness

- [x] Escape polling IDs.
- [x] Stop polling on not-found or unauthorized responses.
- [x] Keep modal close behavior clearing the interval.

### Task 4: Verification

- [x] Run PHP lint.
- [x] Run JS syntax check.
- [x] Run API smoke.
- [x] Run security smoke.
- [x] Run payment/booking smoke.

---

## Notes

This phase intentionally avoids schema changes and avoids creating paid orders/bookings during smoke tests.
