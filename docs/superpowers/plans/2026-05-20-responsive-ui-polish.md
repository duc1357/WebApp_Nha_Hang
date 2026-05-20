# Responsive UI Polish Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the existing restaurant UI look more professional and work reliably on desktop PC, laptop, iPad/tablet, and mobile without changing the current brand identity or core workflows.

**Architecture:** Add a lightweight responsive design foundation in the existing vanilla CSS, then apply it incrementally to customer pages and admin pages. Preserve current PHP/HTML/JS structure, API contracts, payment logic, booking logic, and zero-dependency architecture.

**Tech Stack:** PHP 8, MySQL, Apache/Laragon, vanilla HTML/CSS/JavaScript, existing PowerShell verification scripts.

---

## File Structure

- Modify `style.css`: shared customer UI tokens, responsive foundation, reusable customer/admin-compatible components, customer page polish, booking/cart/payment/profile responsive rules.
- Modify `index.html`: minimal class/markup adjustments for homepage/menu/cart/payment if CSS needs stable hooks.
- Modify `booking.html`: minimal class/markup adjustments for booking layout, preorder panel, booking QR modal, and responsive section hooks.
- Modify `profile.html`: reduce viewport-risk inline modal/layout styles only where needed; add responsive-safe hooks.
- Modify `login.html`: align auth controls with shared responsive form rules without changing auth logic.
- Modify `forgot_password.html`: align password reset controls with shared responsive form rules without changing auth logic.
- Modify `admin/dashboard.php`: align dashboard layout, cards, toolbar controls, and chart containers with admin responsive rules.
- Modify `admin/orders.php`: standardize filter toolbar, table container, badges, and action buttons.
- Modify `admin/bookings.php`: standardize map/list layout, POS modal, table map cards, and checkout modal.
- Modify `admin/menu.php`: standardize filter toolbar, table, action buttons, and menu modal.
- Modify `admin/users.php`: standardize filter toolbar, table, action buttons, and user modal.
- Modify `admin/vouchers.php`: standardize table, action buttons, voucher modal, and destructive action spacing.
- Modify `admin/logs.php`: standardize health cards, toolbar, table, and compact responsive behavior.
- Modify `admin/sidebar.php`: make admin navigation responsive while keeping the existing sidebar concept.
- Test with `tests/run_js_check.ps1`, `tests/run_php_lint.ps1`, and smoke scripts after markup-affecting phases.

## Shared Rules

- Keep current warm orange/navy restaurant identity.
- Do not introduce Bootstrap, Tailwind, shadcn, icon libraries, bundlers, or frameworks.
- Do not change APIs, database schema, authentication, booking behavior, payment behavior, or admin business logic.
- Prefer CSS-only improvements. Touch HTML/PHP only to add classes, wrappers, or remove inline styles that block responsive behavior.
- Do not rewrite large JS modules unless a responsive state class is impossible with CSS alone.
- Do not revert unrelated working-tree changes.

---

### Task 1: Baseline Responsive Audit

**Files:**
- Read: `index.html`
- Read: `booking.html`
- Read: `profile.html`
- Read: `login.html`
- Read: `forgot_password.html`
- Read: `admin/dashboard.php`
- Read: `admin/orders.php`
- Read: `admin/bookings.php`
- Read: `admin/menu.php`
- Read: `admin/users.php`
- Read: `admin/vouchers.php`
- Read: `admin/logs.php`
- Read: `style.css`

- [ ] **Step 1: Inspect current responsive hotspots**

Run:

```powershell
Select-String -Path style.css -Pattern "@media|nav.main-nav|floating-cart|booking-layout|modal-content|profile|table-container"
Select-String -Path admin\*.php -Pattern "style=|table-container|modal|sidebar|filters|main-content"
```

Expected: find duplicated responsive rules, inline styles, admin-local CSS, modal definitions, table containers, and page-specific responsive code.

- [ ] **Step 2: Record implementation notes before editing**

Create a short local checklist in the task notes for:

```text
Customer overflow risks:
- Main nav wrap/collapse
- Floating cart popup on mobile
- QR modal height
- Booking two-column layout
- Profile sidebar/tabs and modals

Admin overflow risks:
- Fixed sidebar on tablet/mobile
- Filter toolbars
- Wide tables
- POS modal menu/cart split
- Form modals
```

Expected: the notes identify exact areas to verify after each phase.

- [ ] **Step 3: Run current syntax baseline**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_js_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1
```

Expected: both scripts pass before UI changes, or any existing failures are recorded before proceeding.

---

### Task 2: Add Shared UI Foundation

**Files:**
- Modify: `style.css`

- [ ] **Step 1: Add token extensions near the existing `:root` block**

Add or merge these variables without deleting the existing brand variables:

```css
:root {
    --primary: #e67e22;
    --primary-dark: #d35400;
    --accent: #2c3e50;
    --text-main: #34495e;
    --text-light: #7f8c8d;
    --bg-body: #f4f6f9;
    --bg-card: #ffffff;
    --success: #27ae60;
    --danger: #c0392b;
    --warning: #f39c12;
    --border-subtle: #e9edf2;
    --surface-muted: #f8fafc;
    --focus-ring: rgba(230, 126, 34, 0.28);
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 18px;
    --radius-xl: 24px;
    --shadow-soft: 0 8px 24px rgba(15, 23, 42, 0.08);
    --shadow-lifted: 0 18px 48px rgba(15, 23, 42, 0.16);
    --space-1: 4px;
    --space-2: 8px;
    --space-3: 12px;
    --space-4: 16px;
    --space-5: 20px;
    --space-6: 24px;
    --space-8: 32px;
    --container-max: 1200px;
    --admin-sidebar-width: 250px;
}
```

Expected: existing colors still work, and new tokens are available for later rules.

- [ ] **Step 2: Add global responsive safety rules after reset/base styles**

Add:

```css
html {
    overflow-x: hidden;
}

body {
    overflow-x: hidden;
}

button,
input,
select,
textarea {
    font: inherit;
}

button,
a,
input,
select,
textarea {
    outline-color: var(--primary);
}

:focus-visible {
    outline: 3px solid var(--focus-ring);
    outline-offset: 2px;
}

.container {
    width: min(100% - 32px, var(--container-max));
}

.ui-scroll-x {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
```

Expected: pages stop creating horizontal body overflow from root-level layout, while table-specific overflow remains possible inside containers.

- [ ] **Step 3: Add shared component classes**

Add reusable rules:

```css
.ui-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--space-3);
}

.ui-control,
.form-control,
.premium-form-group input,
.premium-form-group textarea,
.booking-filter input,
.booking-filter select {
    min-height: 44px;
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-sm);
    background: #fff;
    color: var(--text-main);
}

.ui-button,
.btn-search,
.btn-add,
.btn-save,
.btn-checkout-premium,
.cta-button,
.add-to-cart {
    min-height: 44px;
    border-radius: var(--radius-sm);
    font-weight: 700;
}

.ui-card {
    background: var(--bg-card);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-soft);
}

.ui-modal-panel,
.modal-content {
    max-width: min(92vw, 640px);
    max-height: min(88vh, 760px);
    overflow: auto;
}

.table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

table {
    min-width: 720px;
}
```

Expected: shared controls become more consistent without requiring every page to change markup immediately.

- [ ] **Step 4: Add breakpoint shell rules**

Add these breakpoint sections near the end of shared foundation rules:

```css
@media (max-width: 1023px) {
    .section-box {
        padding: 28px;
        border-radius: var(--radius-lg);
    }

    nav.main-nav {
        width: min(100% - 24px, var(--container-max));
        gap: var(--space-2);
    }
}

@media (max-width: 767px) {
    .container {
        width: min(100% - 24px, var(--container-max));
        margin: 28px auto;
        padding: 0;
    }

    .section-box {
        padding: 22px 16px;
        margin-bottom: 28px;
        border-radius: var(--radius-md);
    }

    nav.main-nav {
        position: sticky;
        top: 8px;
        justify-content: flex-start;
        overflow-x: auto;
        padding: 10px 12px;
        border-radius: 18px;
        gap: 8px;
    }

    nav.main-nav a {
        flex: 0 0 auto;
        min-height: 40px;
        padding: 8px 12px;
        font-size: 0.95rem;
        white-space: nowrap;
    }
}
```

Expected: customer nav and section spacing behave better on tablet/mobile.

- [ ] **Step 5: Run CSS/JS smoke after foundation**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_js_check.ps1
```

Expected: JS syntax still passes because foundation should be CSS-only.

- [ ] **Step 6: Commit foundation**

Run:

```powershell
git add style.css
git commit -m "style: add responsive ui foundation"
```

Expected: commit contains only the shared foundation changes.

---

### Task 3: Polish Homepage, Menu, Cart, And Payment

**Files:**
- Modify: `style.css`
- Modify: `index.html`

- [ ] **Step 1: Stabilize homepage/menu card layout**

In `style.css`, update or add:

```css
.menu-grid,
.reviews-grid {
    align-items: stretch;
}

.menu-item {
    border-radius: var(--radius-lg);
    border-color: var(--border-subtle);
    box-shadow: var(--shadow-soft);
}

.menu-item img {
    aspect-ratio: 4 / 3;
    height: auto;
    min-height: 180px;
    max-height: 220px;
}

.menu-info {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: var(--space-2);
}

.menu-info .add-to-cart {
    margin-top: auto;
}
```

Expected: menu cards keep stable height and image ratios across responsive widths.

- [ ] **Step 2: Make floating cart viewport-safe**

In `style.css`, update or add:

```css
#floating-cart-popup {
    max-height: min(82vh, 760px);
    display: flex;
    flex-direction: column;
}

#floating-cart-popup .cart-body {
    min-height: 80px;
    overflow-y: auto;
}

#floating-cart-popup .cart-footer {
    overflow-y: auto;
}

@media (max-width: 767px) {
    #floating-cart-container {
        right: 14px;
        bottom: 14px;
        left: 14px;
        pointer-events: none;
    }

    #floating-cart-btn,
    #floating-cart-popup {
        pointer-events: auto;
    }

    #floating-cart-btn {
        margin-left: auto;
    }

    #floating-cart-popup {
        position: fixed;
        left: 12px;
        right: 12px;
        bottom: 12px;
        width: auto;
        max-width: none;
        max-height: calc(100vh - 24px);
        border-radius: 18px;
    }
}
```

Expected: the cart does not overflow small mobile screens and remains scrollable.

- [ ] **Step 3: Make QR and thank-you modals mobile-safe**

In `style.css`, update or add:

```css
.modal-overlay {
    padding: 18px;
}

.qr-container {
    max-width: 280px;
    margin-inline: auto;
}

.qr-image {
    width: min(100%, 260px);
    height: auto;
}

@media (max-width: 480px) {
    .modal-overlay {
        align-items: flex-end;
        padding: 10px;
    }

    .modal-content {
        width: 100%;
        max-width: 100%;
        max-height: calc(100vh - 20px);
        border-radius: 18px;
        padding: 20px 16px;
    }

    .payment-details .detail-row {
        align-items: flex-start;
        flex-direction: column;
        gap: 6px;
    }
}
```

Expected: QR content remains visible and scroll-safe on 390px and 320px widths.

- [ ] **Step 4: Add stable hook classes only if needed**

If `index.html` lacks hooks used by the new CSS, add non-behavioral classes only:

```html
<section id="menu" class="section-box customer-menu-section">
<div id="floating-cart-popup" class="hidden customer-cart-panel">
<div id="qr-payment-modal" class="modal-overlay hidden customer-payment-modal">
```

Expected: no JavaScript selector changes are required because IDs remain unchanged.

- [ ] **Step 5: Verify customer syntax**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_js_check.ps1
```

Expected: JS syntax passes.

- [ ] **Step 6: Commit homepage/cart/payment polish**

Run:

```powershell
git add style.css index.html
git commit -m "style: polish customer menu cart and payment"
```

Expected: commit contains CSS and minimal homepage hooks only.

---

### Task 4: Polish Booking Flow

**Files:**
- Modify: `style.css`
- Modify: `booking.html`

- [ ] **Step 1: Improve desktop/tablet booking layout**

In `style.css`, update or add:

```css
.booking-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.65fr);
    gap: var(--space-6);
    align-items: start;
}

.booking-left-col,
.booking-right-col {
    min-width: 0;
}

.booking-right-col {
    position: sticky;
    top: 96px;
}

.booking-filter {
    display: grid;
    grid-template-columns: repeat(3, minmax(160px, 1fr));
    gap: var(--space-3);
}
```

Expected: desktop keeps two columns, while controls align more predictably.

- [ ] **Step 2: Improve booking table grid responsiveness**

In `style.css`, update or add:

```css
.booking-grid,
.table-grid {
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
}

.booking-table-item,
.table-box {
    min-height: 112px;
    border-radius: var(--radius-md);
}

.floor-tabs-container {
    display: flex;
    gap: var(--space-2);
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
```

Expected: booking floor tabs and table cards remain readable on tablet/mobile.

- [ ] **Step 3: Stack booking flow on tablet/mobile**

In `style.css`, add:

```css
@media (max-width: 1023px) {
    .booking-layout {
        grid-template-columns: 1fr;
    }

    .booking-right-col {
        position: static;
    }
}

@media (max-width: 767px) {
    .booking-filter {
        grid-template-columns: 1fr;
    }

    .booking-confirm-form {
        padding: 18px 16px;
        border-radius: var(--radius-md);
    }

    .booking-confirm-form .form-row {
        grid-template-columns: 1fr;
    }

    #booking-menu-list {
        max-height: 260px;
    }

    #btn-submit-booking {
        width: 100%;
        position: sticky;
        bottom: 10px;
        z-index: 4;
    }
}
```

Expected: booking is readable and action-oriented on mobile.

- [ ] **Step 4: Replace blocking inline styles with classes where needed**

In `booking.html`, convert preorder and QR blocks to class hooks while keeping IDs and handlers:

```html
<div class="preorder-toggle-group booking-preorder-toggle">
<div id="preorder-section" class="booking-preorder-section">
<div id="booking-menu-list" class="booking-menu-list">
<div class="payment-details booking-payment-details">
```

Expected: JavaScript continues to find elements by ID; CSS can control responsive behavior.

- [ ] **Step 5: Verify booking syntax**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_js_check.ps1
```

Expected: JS syntax passes.

- [ ] **Step 6: Commit booking polish**

Run:

```powershell
git add style.css booking.html
git commit -m "style: polish responsive booking flow"
```

Expected: commit contains booking-only UI changes.

---

### Task 5: Polish Auth And Profile

**Files:**
- Modify: `style.css`
- Modify: `login.html`
- Modify: `forgot_password.html`
- Modify: `profile.html`

- [ ] **Step 1: Normalize auth page responsive behavior**

In `style.css`, add:

```css
.auth-wrapper {
    width: min(100% - 32px, 980px);
}

.auth-card {
    max-width: 460px;
}

@media (max-width: 767px) {
    .auth-page {
        min-height: 100vh;
    }

    .auth-wrapper {
        width: min(100% - 24px, 460px);
        padding-block: 24px;
    }

    .auth-brand {
        text-align: center;
    }

    .auth-card {
        width: 100%;
        border-radius: 18px;
    }
}
```

Expected: login/register/forgot password forms remain centered and readable on mobile.

- [ ] **Step 2: Normalize profile layout**

In `style.css`, add:

```css
.profile-container {
    width: min(100% - 32px, 1180px);
}

.profile-content,
.profile-sidebar {
    min-width: 0;
}

@media (max-width: 1023px) {
    .profile-container {
        grid-template-columns: 1fr;
    }

    .profile-sidebar {
        position: static;
    }

    .sidebar-menu {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 767px) {
    .profile-container {
        width: min(100% - 24px, 720px);
    }

    .profile-nav {
        overflow-x: auto;
        justify-content: flex-start;
    }

    .sidebar-menu {
        grid-template-columns: 1fr;
    }

    .profile-summary-grid {
        grid-template-columns: 1fr;
    }
}
```

Expected: profile stops clipping nav/sidebar/content on tablet and mobile.

- [ ] **Step 3: Make profile modals viewport-safe**

In `profile.html`, add stable classes to modal panels without changing IDs:

```html
<div id="review-modal" class="modal profile-review-modal">
<div class="modal-content profile-review-panel">
<div id="order-detail-modal" class="modal profile-order-modal">
<div class="modal-content profile-order-panel">
```

In `style.css`, add:

```css
.profile-review-panel,
.profile-order-panel {
    width: min(92vw, 600px);
    max-height: 88vh;
    overflow: auto;
}

@media (max-width: 480px) {
    .profile-review-panel,
    .profile-order-panel {
        width: calc(100vw - 20px);
        margin: 10px auto;
        padding: 20px 16px;
        border-radius: 18px;
    }
}
```

Expected: review and order detail modals fit mobile viewport.

- [ ] **Step 4: Keep auth/profile JavaScript untouched**

Check that no JS selectors were changed:

```powershell
Select-String -Path profile.html,login.html,forgot_password.html -Pattern "id=\"login-|id=\"reg-|id=\"review-|id=\"order-detail-modal|id=\"tab-"
```

Expected: existing IDs remain present.

- [ ] **Step 5: Verify auth/profile syntax**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_js_check.ps1
```

Expected: JS syntax passes.

- [ ] **Step 6: Commit auth/profile polish**

Run:

```powershell
git add style.css login.html forgot_password.html profile.html
git commit -m "style: polish responsive auth and profile pages"
```

Expected: commit contains only auth/profile responsive UI changes.

---

### Task 6: Add Admin Responsive Foundation

**Files:**
- Modify: `style.css`
- Modify: `admin/sidebar.php`
- Modify: `admin/dashboard.php`
- Modify: `admin/orders.php`
- Modify: `admin/bookings.php`
- Modify: `admin/menu.php`
- Modify: `admin/users.php`
- Modify: `admin/vouchers.php`
- Modify: `admin/logs.php`

- [ ] **Step 1: Add admin shared rules to `style.css`**

Add:

```css
.admin-page .main-content,
.main-content {
    min-width: 0;
}

.page-header,
.header-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-3);
}

.filters,
.toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--space-3);
}

.filters .form-control,
.toolbar .form-control,
.filters input,
.filters select,
.toolbar select {
    min-height: 42px;
    max-width: 100%;
}

.btn-action {
    min-width: 36px;
    min-height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-sm);
}

@media (max-width: 1023px) {
    .sidebar {
        position: static;
        width: 100%;
        height: auto;
        flex-direction: row;
        overflow-x: auto;
        gap: 8px;
        padding: 12px;
    }

    .sidebar .brand {
        flex: 0 0 auto;
    }

    .sidebar .nav-item {
        flex: 0 0 auto;
        white-space: nowrap;
    }

    .main-content {
        margin-left: 0 !important;
        padding: 20px !important;
    }
}

@media (max-width: 767px) {
    .page-header,
    .header-row {
        align-items: stretch;
        flex-direction: column;
    }

    .filters,
    .toolbar {
        align-items: stretch;
        flex-direction: column;
    }

    .filters .form-control,
    .toolbar .form-control,
    .filters input,
    .filters select,
    .toolbar select,
    .btn-search,
    .btn-add {
        width: 100% !important;
    }
}
```

Expected: admin pages gain a shared responsive layer even while retaining page-local CSS.

- [ ] **Step 2: Add admin page body class where possible**

In each admin page body tag, use:

```html
<body class="admin-page">
```

Expected: shared admin CSS can target admin pages without affecting customer pages.

- [ ] **Step 3: Ensure admin tables have scroll containers**

For admin pages with existing `.table-container`, keep the wrapper. If a table lacks a `.table-container`, wrap it:

```html
<div class="table-container">
    <table>
        ...
    </table>
</div>
```

Expected: mobile horizontal scroll stays inside the table region.

- [ ] **Step 4: Verify PHP syntax after admin class edits**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1
```

Expected: PHP lint passes.

- [ ] **Step 5: Commit admin foundation**

Run:

```powershell
git add style.css admin/sidebar.php admin/dashboard.php admin/orders.php admin/bookings.php admin/menu.php admin/users.php admin/vouchers.php admin/logs.php
git commit -m "style: add responsive admin foundation"
```

Expected: commit contains shared admin responsive changes only.

---

### Task 7: Polish Admin Dashboard, Tables, And Modals

**Files:**
- Modify: `style.css`
- Modify: `admin/dashboard.php`
- Modify: `admin/orders.php`
- Modify: `admin/bookings.php`
- Modify: `admin/menu.php`
- Modify: `admin/users.php`
- Modify: `admin/vouchers.php`
- Modify: `admin/logs.php`

- [ ] **Step 1: Normalize dashboard grids and chart cards**

In `style.css`, add:

```css
.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: var(--space-4);
}

.dashboard-grid,
.content-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--space-5);
}

.card,
.stat-card,
.health-card {
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-soft);
}

@media (max-width: 1023px) {
    .dashboard-grid,
    .content-grid {
        grid-template-columns: 1fr;
    }

    .card[style*="grid-column"] {
        grid-column: auto !important;
    }
}
```

Expected: dashboard cards and charts stack cleanly on tablet/mobile.

- [ ] **Step 2: Normalize admin modal panels**

In `style.css`, add:

```css
.admin-page .modal-content,
.admin-page [id$="Modal"] > div {
    width: min(94vw, 760px);
    max-height: 88vh;
    overflow: auto;
    border-radius: var(--radius-md);
}

@media (max-width: 480px) {
    .admin-page .modal-content,
    .admin-page [id$="Modal"] > div {
        width: calc(100vw - 20px);
        padding: 18px 14px !important;
    }
}
```

Expected: menu/user/voucher/POS/checkout modals remain usable on mobile.

- [ ] **Step 3: Improve POS modal responsiveness**

In `style.css`, add:

```css
.pos-container {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(280px, 0.8fr);
    gap: var(--space-4);
    min-height: 0;
}

.pos-menu,
.pos-cart {
    min-width: 0;
}

@media (max-width: 900px) {
    .pos-container {
        grid-template-columns: 1fr;
    }
}
```

Expected: POS menu and cart stack cleanly on tablet/mobile.

- [ ] **Step 4: Standardize table minimum widths by page type**

In `style.css`, add:

```css
#ordersTable,
#bookingsTable {
    min-width: 860px;
}

#menuTable,
#usersTable,
#voucherTable {
    min-width: 760px;
}

.card table {
    min-width: 680px;
}
```

Expected: admin tables remain readable and scroll inside wrappers.

- [ ] **Step 5: Verify admin syntax**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_js_check.ps1
```

Expected: PHP lint and JS syntax pass.

- [ ] **Step 6: Commit admin polish**

Run:

```powershell
git add style.css admin/dashboard.php admin/orders.php admin/bookings.php admin/menu.php admin/users.php admin/vouchers.php admin/logs.php
git commit -m "style: polish responsive admin screens"
```

Expected: commit contains admin polish only.

---

### Task 8: Visual And Smoke Verification

**Files:**
- Read: `TESTING_CHECKLIST.md`
- Read: `tests/README.md`
- Modify: no source files unless verification finds issues.

- [ ] **Step 1: Run full local verification scripts**

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_php_lint.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_js_check.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\api_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\security_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\payment_booking_smoke.ps1
powershell -ExecutionPolicy Bypass -File .\tests\smoke\admin_observability_smoke.ps1
```

Expected: all scripts pass, or environment-specific failures are documented with exact command output.

- [ ] **Step 2: Start or confirm local site URL**

Use the Laragon site URL already expected by the test suite:

```text
http://restaurant.test
```

Expected: homepage loads locally.

- [ ] **Step 3: Browser-check viewport widths**

Check these widths:

```text
1440 x 900
1366 x 768
1024 x 768
768 x 1024
430 x 932
390 x 844
320 x 720
```

Pages to inspect:

```text
/index.html
/booking.html
/login.html
/forgot_password.html
/profile.html
/admin/dashboard.php
/admin/orders.php
/admin/bookings.php
/admin/menu.php
/admin/users.php
/admin/vouchers.php
/admin/logs.php
```

Expected: no horizontal body overflow, no overlapping nav/actions, no clipped modal content, and table overflow is contained within table wrappers.

- [ ] **Step 4: Capture portfolio screenshot list**

Capture or record screenshot readiness for:

```text
Homepage hero and menu
Floating cart with items
QR payment modal
Booking table map
Booking confirmation/preorder section
Profile dashboard/history
Admin dashboard
Admin booking POS map/modal
Admin orders table
Admin logs/health view
```

Expected: screenshots look like one consistent product.

- [ ] **Step 5: Fix any verification regressions**

If verification finds an issue, make the smallest CSS/HTML change that fixes that exact issue, then rerun the relevant command or viewport check.

Expected: every fix has a matching verification result.

- [ ] **Step 6: Final commit**

Run:

```powershell
git add style.css index.html booking.html login.html forgot_password.html profile.html admin/dashboard.php admin/orders.php admin/bookings.php admin/menu.php admin/users.php admin/vouchers.php admin/logs.php admin/sidebar.php
git commit -m "style: complete responsive ui polish"
```

Expected: final commit contains verification fixes only, if any.

---

## Plan Self-Review

Spec coverage:

- UI foundation is covered by Task 2.
- Customer homepage/menu/cart/payment is covered by Task 3.
- Booking flow is covered by Task 4.
- Auth/profile is covered by Task 5.
- Admin responsive behavior is covered by Tasks 6 and 7.
- Visual QA and portfolio capture are covered by Task 8.

Placeholder scan:

- This plan contains no TBD, TODO, or deferred implementation placeholders.
- Every task lists exact files and concrete commands.

Scope check:

- The plan intentionally avoids database, API, auth, payment, booking business logic, framework, and dependency changes.
- The work can be implemented incrementally with verification after each major UI surface.
