# Admin Observability Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an admin-only operational view for logs and system health without exposing raw log files publicly.

**Architecture:** Keep direct web access to `logs/` blocked by `.htaccess`. Add authenticated admin APIs that read sanitized log entries and basic health checks, then render them in a lightweight admin page using the existing sidebar/admin style.

**Tech Stack:** PHP 8.3, MySQLi, custom NDJSON logger, Apache/Laragon, vanilla JavaScript.

---

### Task 1: Admin APIs

- [x] Add `api/admin/get_logs.php`.
- [x] Add `api/admin/get_system_health.php`.
- [x] Enforce admin session via `auth_check_api.php`.
- [x] Allow only known log channels.
- [x] Redact sensitive keys in context/request data.

### Task 2: Admin Page

- [x] Add `admin/logs.php`.
- [x] Add sidebar navigation link.
- [x] Render health cards and a filterable log table.
- [x] Keep direct log file access blocked by `.htaccess`.

### Task 3: Verification

- [x] Run PHP lint.
- [x] Run JS syntax check.
- [x] Run API smoke.
- [x] Confirm unauthenticated log API returns 401.
- [x] Confirm direct `/logs/auth.log` access is blocked.

---

## Notes

The first version reads from local log files only. It does not add alerting, email notifications, or external log drains.
