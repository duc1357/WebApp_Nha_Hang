# Deployment Operations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Document deployment, operations, verification, backup, and environment setup for the PHP/MySQL restaurant app.

**Architecture:** Keep the current shared-hosting friendly layout. Document exact Laragon/local and production steps, required environment variables, smoke tests, SePay setup, mail setup, logs, and recovery procedures.

**Tech Stack:** PHP 8.3, MySQL, Apache/Laragon, vanilla JS/CSS, custom migrations, file-based logs.

---

### Task 1: Deployment Guide

- [x] Add `docs/deployment.md`.
- [x] Document local Laragon setup.
- [x] Document production/shared-hosting deployment.
- [x] Document required `.env` keys.
- [x] Document DB import and migration order.
- [x] Document SePay and mail setup.

### Task 2: Operations Guide

- [x] Add `docs/operations.md`.
- [x] Document smoke tests.
- [x] Document admin logs and health checks.
- [x] Document backup/restore.
- [x] Document incident checklist.

### Task 3: Root Docs

- [x] Update `README.md`.
- [x] Update `ARCHITECTURE.md`.
- [x] Update `.env.example`.
- [x] Update `TESTING_CHECKLIST.md`.

### Task 4: Verification

- [x] Run PHP lint.
- [x] Run JS syntax check.
- [x] Run all smoke scripts.
- [x] Run migration status.

---

## Notes

This phase is documentation and verification only. It does not change runtime behavior.
