# Frontend UX Maintainability Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Improve customer-facing loading, empty, and error states while keeping the vanilla JavaScript architecture and existing page structure.

**Architecture:** Add small shared UI helpers in `js/utils.js`, then adopt them in the main customer modules. Avoid a bundler or page redesign in this phase.

**Tech Stack:** Vanilla JavaScript, existing HTML/CSS, PHP APIs.

---

### Task 1: Shared UI Helpers

- [x] Add `fetchJson`.
- [x] Add `renderState`.
- [x] Add `renderEmptyState`.
- [x] Add `renderErrorState`.

### Task 2: Customer Flow States

- [x] Improve public menu loading/empty/error states.
- [x] Improve booking preorder menu loading/empty/error states.
- [x] Improve featured review empty/error states.
- [x] Improve cart empty state.
- [x] Improve profile history empty/error states.

### Task 3: Verification

- [x] Run JS syntax check.
- [x] Run PHP lint.
- [x] Run API smoke.
- [x] Run security smoke.
- [x] Run payment/booking smoke.
- [x] Run admin observability smoke.

---

## Notes

This phase intentionally avoids a full visual redesign. It focuses on reusable states and safer data rendering in the existing frontend modules.
