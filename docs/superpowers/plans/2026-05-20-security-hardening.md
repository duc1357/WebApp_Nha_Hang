# Security Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Harden authentication, CSRF, JWT, rate-limit, upload, and webhook boundaries while preserving the current PHP/MySQL architecture.

**Architecture:** Keep the existing session/JWT hybrid auth model. Standardize security error responses through `ResponseService`, add security smoke tests, and make secret/token boundaries fail closed.

**Tech Stack:** PHP 8.3, MySQLi, custom session config, custom CSRF/JWT/rate-limit services, SePay webhook.

---

### Task 1: Security Smoke Coverage

- [x] Add `tests/smoke/security_smoke.ps1`.
- [x] Verify unauthenticated admin stats returns 401.
- [x] Verify mutating POST without CSRF returns 403.
- [x] Verify wrong-password login returns 401.
- [x] Verify webhook without authorization returns 401 or 503 if intentionally unconfigured.

### Task 2: Harden Shared Security Services

- [x] Standardize CSRF failure response through `ResponseService`.
- [x] Add stronger rate-limit key normalization.
- [x] Validate JWT secret length before signing/verifying.
- [x] Standardize JWT auth failure responses.

### Task 3: Harden Auth and Webhook Endpoints

- [x] Standardize logout response and method checks.
- [x] Standardize refresh-token responses.
- [x] Standardize change-password responses.
- [x] Keep upload avatar MIME/size checks intact.
- [x] Standardize SePay webhook responses and unauthorized boundary.

### Task 4: Verification

- [x] Run PHP lint.
- [x] Run JS syntax check.
- [x] Run API smoke.
- [x] Run security smoke.

---

## Notes

This phase avoids changing password policy, schema, or payment state logic. It focuses on boundaries and repeatable security regression checks.
