<!-- Load Google Fonts for all Admin Pages -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Load theme manager immediately to prevent FOUC (Flash of Un-themed Content) -->
<script src="../js/theme.js"></script>

<style>
    /* ==========================================================
       GLOBAL DESIGN SYSTEM VARIABLES (LIGHT MODE DEFAULT)
       ========================================================== */
    :root {
        --primary: #d35400;
        --primary-dark: #b04300;
        --primary-gradient: linear-gradient(135deg, #e67e22, #c0392b);
        --charcoal: #1c1a17;
        --charcoal-light: #2c2823;
        --cream: #fbf9f6;
        --cream-dark: #f3ede2;
        --text-main: #2c2823;
        --text-muted: #7d7265;
        --white: #ffffff;
        --accent-gold: #d4af37;
        --radius-lg: 24px;
        --radius-md: 16px;
        --radius-sm: 10px;
        --shadow-premium: 0 15px 35px rgba(28, 25, 23, 0.04);
        --shadow-premium-hover: 0 25px 50px rgba(28, 25, 23, 0.08);
        --transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        --bg: var(--cream);
        --card-bg: var(--white);
        --border: rgba(211, 84, 0, 0.08);
    }

    /* ==========================================================
       GLOBAL TYPOGRAPHY OVERRIDES FOR ALL ADMIN PAGES
       ========================================================== */
    body {
        font-family: 'Plus Jakarta Sans', 'Outfit', sans-serif !important;
        background-color: var(--bg) !important;
        color: var(--text-main) !important;
        transition: background-color 0.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }

    /* Main Content Layout Adjustments */
    .main-content {
        margin-left: 260px !important;
        padding: 40px !important;
        background-color: var(--bg) !important;
        min-height: 100vh !important;
        transition: background-color 0.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }

    /* Page Titles */
    .page-header h2 {
        font-family: 'Playfair Display', serif !important;
        font-size: 26px !important;
        font-weight: 800 !important;
        color: var(--charcoal) !important;
        letter-spacing: -0.5px !important;
    }

    /* ==========================================================
       MODERN PREMIUM ELEMENT STYLING FOR ALL ADMIN PAGES
       ========================================================== */
    
    /* Filters Panels */
    .filters {
        display: flex !important;
        gap: 16px !important;
        padding: 20px !important;
        border-radius: 16px !important;
        background: var(--card-bg) !important;
        border: 1px solid var(--border) !important;
        box-shadow: var(--shadow-premium) !important;
        margin-bottom: 24px !important;
        align-items: center !important;
        flex-wrap: wrap !important;
    }

    /* Tables Containers */
    .table-container {
        background: var(--card-bg) !important;
        padding: 24px !important;
        border-radius: 20px !important;
        box-shadow: var(--shadow-premium) !important;
        border: 1px solid var(--border) !important;
        transition: var(--transition) !important;
        margin-bottom: 30px !important;
    }

    /* Forms Inputs, Selects & Textareas */
    .form-control,
    input[type="text"],
    input[type="date"],
    input[type="password"],
    input[type="number"],
    select,
    textarea {
        padding: 12px 16px !important;
        border: 1.5px solid var(--border) !important;
        border-radius: 10px !important;
        outline: none !important;
        font-family: inherit !important;
        font-size: 14px !important;
        transition: var(--transition) !important;
        background-color: var(--card-bg) !important;
        color: var(--text-main) !important;
    }
    .form-control:focus,
    input:focus,
    select:focus,
    textarea:focus {
        border-color: var(--primary) !important;
        box-shadow: 0 0 0 3px rgba(211, 84, 0, 0.12) !important;
    }

    /* Premium Buttons Styling */
    .btn-search,
    .btn-add,
    .btn-pos,
    button[data-action="load-bookings"],
    button[data-action="load-bookings"] + button,
    button[data-action="refresh-all"] {
        background: var(--primary-gradient) !important;
        color: white !important;
        border: none !important;
        padding: 12px 24px !important;
        border-radius: 10px !important;
        cursor: pointer !important;
        font-weight: 700 !important;
        transition: var(--transition) !important;
        box-shadow: 0 6px 15px rgba(211, 84, 0, 0.15) !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 8px !important;
        font-size: 14px !important;
    }
    .btn-search:hover,
    .btn-add:hover,
    .btn-pos:hover,
    button[data-action="refresh-all"]:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 10px 25px rgba(211, 84, 0, 0.3) !important;
        filter: brightness(1.05) !important;
    }
    .btn-search:active,
    .btn-add:active,
    .btn-pos:active,
    button[data-action="refresh-all"]:active {
        transform: translateY(0) !important;
    }

    /* Premium Tables Styling */
    table {
        width: 100% !important;
        border-collapse: separate !important;
        border-spacing: 0 !important;
        font-size: 14.5px !important;
    }
    th {
        text-align: left !important;
        padding: 16px !important;
        color: var(--text-muted) !important;
        font-weight: 700 !important;
        font-size: 11px !important;
        text-transform: uppercase !important;
        letter-spacing: 0.8px !important;
        background: var(--cream-dark) !important;
        border-bottom: 1.5px solid var(--border) !important;
    }
    th:first-child {
        border-top-left-radius: 10px !important;
        border-bottom-left-radius: 10px !important;
    }
    th:last-child {
        border-top-right-radius: 10px !important;
        border-bottom-right-radius: 10px !important;
    }
    td {
        padding: 16px !important;
        border-bottom: 1px solid var(--cream-dark) !important;
        vertical-align: middle !important;
        color: var(--text-main) !important;
        transition: background 0.2s ease !important;
        background: transparent !important;
    }
    tr:last-child td {
        border-bottom: none !important;
    }
    tr:hover td {
        background: rgba(243, 237, 226, 0.15) !important;
    }

    /* Pills Badges Styling */
    .badge {
        display: inline-flex !important;
        align-items: center !important;
        padding: 6px 12px !important;
        border-radius: 99px !important;
        font-size: 12px !important;
        font-weight: 700 !important;
        letter-spacing: 0.2px !important;
        border: 1px solid transparent !important;
    }
    .badge.status-active, 
    .badge.res-confirmed, 
    .badge.available {
        background: rgba(39, 174, 96, 0.1) !important;
        color: #27ae60 !important;
        border-color: rgba(39, 174, 96, 0.15) !important;
    }
    .badge.status-expired, 
    .badge.res-cancelled, 
    .badge.occupied {
        background: rgba(192, 57, 43, 0.1) !important;
        color: #c0392b !important;
        border-color: rgba(192, 57, 43, 0.15) !important;
    }
    .badge.res-pending {
        background: rgba(230, 126, 34, 0.1) !important;
        color: #d35400 !important;
        border-color: rgba(230, 126, 34, 0.15) !important;
    }

    .badge.badge-table {
        background: rgba(41, 128, 185, 0.08) !important;
        color: #2980b9 !important;
        border: 1.5px solid rgba(41, 128, 185, 0.15) !important;
    }
    .badge.role-admin {
        background: rgba(41, 128, 185, 0.08) !important;
        color: #2980b9 !important;
        border: 1.5px solid rgba(41, 128, 185, 0.15) !important;
    }
    .badge.role-user {
        background: rgba(44, 40, 35, 0.05) !important;
        color: #7d7265 !important;
        border: 1.5px solid rgba(44, 40, 35, 0.1) !important;
    }

    /* Premium Action Buttons (Edit, Delete, Approve, Cancel) */
    .btn-action {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 34px !important;
        height: 34px !important;
        border-radius: 10px !important;
        border: 1.5px solid transparent !important;
        cursor: pointer !important;
        transition: var(--transition) !important;
    }
    .btn-action:hover {
        transform: translateY(-2px) !important;
    }
    .btn-action:active {
        transform: translateY(0) !important;
    }
    .btn-action.btn-approve,
    .btn-action.btn-toggle-on {
        background: rgba(39, 174, 96, 0.08) !important;
        color: #27ae60 !important;
        border-color: rgba(39, 174, 96, 0.12) !important;
    }
    .btn-action.btn-approve:hover,
    .btn-action.btn-toggle-on:hover {
        background: rgba(39, 174, 96, 0.15) !important;
        box-shadow: 0 4px 10px rgba(39, 174, 96, 0.1) !important;
    }
    .btn-action.btn-cancel,
    .btn-action.btn-delete,
    .btn-action.btn-toggle-off {
        background: rgba(192, 57, 43, 0.08) !important;
        color: #c0392b !important;
        border-color: rgba(192, 57, 43, 0.12) !important;
    }
    .btn-action.btn-cancel:hover,
    .btn-action.btn-delete:hover,
    .btn-action.btn-toggle-off:hover {
        background: rgba(192, 57, 43, 0.15) !important;
        box-shadow: 0 4px 10px rgba(192, 57, 43, 0.1) !important;
    }
    .btn-action.btn-edit {
        background: rgba(41, 128, 185, 0.08) !important;
        color: #2980b9 !important;
        border-color: rgba(41, 128, 185, 0.12) !important;
    }
    .btn-action.btn-edit:hover {
        background: rgba(41, 128, 185, 0.15) !important;
        box-shadow: 0 4px 10px rgba(41, 128, 185, 0.1) !important;
    }

    /* Table Map Items in Bookings */
    .table-item {
        border: 2px solid var(--border) !important;
        border-radius: 16px !important;
        background: var(--card-bg) !important;
        box-shadow: var(--shadow-premium) !important;
        transition: var(--transition) !important;
    }
    .table-item:hover {
        transform: translateY(-4px) !important;
        box-shadow: var(--shadow-premium-hover) !important;
    }
    .table-item.available {
        border-color: #27ae60 !important;
        background: rgba(39, 174, 96, 0.08) !important;
        color: #27ae60 !important;
    }
    .table-item.occupied {
        border-color: #d35400 !important;
        background: rgba(211, 84, 0, 0.08) !important;
        color: #d35400 !important;
    }

    /* POS Menu Items */
    .menu-item {
        border: 1px solid var(--border) !important;
        border-radius: 12px !important;
        background: var(--card-bg) !important;
        transition: var(--transition) !important;
    }
    .menu-item:hover {
        border-color: var(--primary) !important;
        box-shadow: 0 4px 15px rgba(211, 84, 0, 0.08) !important;
        transform: translateY(-2px) !important;
    }

    /* Premium Pagination */
    .page-link {
        padding: 10px 16px !important;
        border: 1px solid var(--border) !important;
        background: var(--card-bg) !important;
        color: var(--text-main) !important;
        cursor: pointer !important;
        border-radius: 8px !important;
        font-weight: 600 !important;
        transition: var(--transition) !important;
    }
    .page-link:hover:not(:disabled) {
        border-color: var(--primary) !important;
        color: var(--primary) !important;
        background: var(--cream) !important;
    }
    .page-link.active {
        background: var(--primary-gradient) !important;
        color: white !important;
        border-color: transparent !important;
        box-shadow: 0 4px 10px rgba(211, 84, 0, 0.2) !important;
    }
    .page-link:disabled {
        background: var(--cream) !important;
        color: var(--text-muted) !important;
        cursor: not-allowed !important;
        opacity: 0.6 !important;
    }

    /* ==========================================================
       SIDEBAR LAYOUT & STYLING OVERRIDES
       ========================================================== */
    .sidebar {
        width: 260px !important;
        background: #12100e !important; /* Deep charcoal dark background matching main website */
        border-right: 1px solid rgba(211, 84, 0, 0.12) !important;
        display: flex !important;
        flex-direction: column !important;
        padding: 30px 20px !important;
        position: fixed !important;
        height: 100% !important;
        top: 0 !important;
        left: 0 !important;
        z-index: 100 !important;
        box-shadow: 10px 0 35px rgba(0, 0, 0, 0.3) !important;
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }

    .sidebar .brand {
        font-family: 'Playfair Display', serif !important;
        font-size: 22px !important;
        font-weight: 800 !important;
        color: #d4af37 !important; /* Gorgeous warm gold color */
        margin-bottom: 45px !important;
        display: flex !important;
        align-items: center !important;
        gap: 14px !important;
        letter-spacing: 0.5px !important;
        border-bottom: 1.5px solid rgba(211, 84, 0, 0.15) !important;
        padding-bottom: 20px !important;
    }

    .sidebar .brand img {
        width: 42px !important;
        height: 42px !important;
        border-radius: 50% !important;
        border: 2px solid #d4af37 !important;
        box-shadow: 0 0 15px rgba(212, 175, 55, 0.3) !important;
        background-color: #ffffff !important;
        padding: 2px !important;
        transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }

    .sidebar .brand:hover img {
        transform: scale(1.08) rotate(360deg) !important;
    }

    .sidebar .nav-item {
        padding: 14px 18px !important;
        margin-bottom: 8px !important;
        color: rgba(243, 237, 226, 0.7) !important; /* Soft warm white text */
        text-decoration: none !important;
        border-radius: 12px !important;
        font-weight: 600 !important;
        font-size: 14.5px !important;
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1) !important;
        border-left: 3px solid transparent !important;
    }

    .sidebar .nav-item:hover {
        background: rgba(211, 84, 0, 0.08) !important;
        color: #f3ede2 !important;
        padding-left: 22px !important;
    }

    .sidebar .nav-item.active {
        background: linear-gradient(135deg, rgba(211, 84, 0, 0.18), rgba(192, 57, 43, 0.1)) !important;
        color: #e67e22 !important; /* Accent orange */
        border-left-color: #d35400 !important;
        box-shadow: 0 4px 12px rgba(211, 84, 0, 0.05) !important;
        font-weight: 700 !important;
    }

    /* Theme Toggle Sun/Moon Icon Controls */
    html[data-theme="dark"] .sidebar .sun-icon {
        display: inline-block !important;
    }
    html[data-theme="dark"] .sidebar .moon-icon {
        display: none !important;
    }

    html:not([data-theme="dark"]) .sidebar .sun-icon {
        display: none !important;
    }
    html:not([data-theme="dark"]) .sidebar .moon-icon {
        display: inline-block !important;
    }

    /* ==========================================================
       GLOBAL SYSTEM VARIABLES & SELECTORS (DARK MODE)
       ========================================================== */
    html[data-theme="dark"] {
        --cream: #12100e;
        --cream-dark: #24201c;
        --text-main: #f3ede2;
        --text-muted: #a09587;
        --white: #1c1916;
        --charcoal: #fbf9f6;
        --charcoal-light: #f3ede2;
        --shadow-premium: 0 20px 50px rgba(0, 0, 0, 0.35);
        --shadow-premium-hover: 0 30px 60px rgba(0, 0, 0, 0.50);
        --border: rgba(211, 84, 0, 0.15);
    }

    html[data-theme="dark"] body {
        background-color: #12100e !important;
        color: #f3ede2 !important;
    }
    
    html[data-theme="dark"] .main-content {
        background-color: #12100e !important;
    }

    /* Panels & Cards */
    html[data-theme="dark"] .card, 
    html[data-theme="dark"] .table-container, 
    html[data-theme="dark"] .filters,
    html[data-theme="dark"] .floor-container,
    html[data-theme="dark"] .pos-menu,
    html[data-theme="dark"] .pos-cart {
        background-color: #1c1916 !important;
        border: 1px solid rgba(211, 84, 0, 0.12) !important;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25) !important;
        color: #f3ede2 !important;
    }

    /* Headings & Texts */
    html[data-theme="dark"] h1,
    html[data-theme="dark"] h2,
    html[data-theme="dark"] h3,
    html[data-theme="dark"] h4,
    html[data-theme="dark"] h5,
    html[data-theme="dark"] h6,
    html[data-theme="dark"] label,
    html[data-theme="dark"] strong {
        color: #ffffff !important;
    }

    html[data-theme="dark"] p,
    html[data-theme="dark"] span,
    html[data-theme="dark"] small,
    html[data-theme="dark"] .table-cap {
        color: #a09587 !important;
    }

    /* Tables */
    html[data-theme="dark"] table {
        color: #f3ede2 !important;
    }
    
    html[data-theme="dark"] th {
        background-color: #24201c !important;
        color: #a09587 !important;
        border-bottom: 1.5px solid rgba(211, 84, 0, 0.15) !important;
    }
    
    html[data-theme="dark"] td {
        background-color: transparent !important;
        color: #f3ede2 !important;
        border-bottom: 1px solid rgba(211, 84, 0, 0.08) !important;
    }

    html[data-theme="dark"] tr:hover td {
        background-color: rgba(243, 237, 226, 0.04) !important;
    }

    /* Inputs & Form Controls */
    html[data-theme="dark"] .form-control,
    html[data-theme="dark"] input[type="text"],
    html[data-theme="dark"] input[type="date"],
    html[data-theme="dark"] input[type="password"],
    html[data-theme="dark"] input[type="number"],
    html[data-theme="dark"] select,
    html[data-theme="dark"] textarea {
        background-color: #12100e !important;
        color: #f3ede2 !important;
        border: 1px solid rgba(211, 84, 0, 0.2) !important;
        border-radius: 8px !important;
    }

    html[data-theme="dark"] .form-control:focus,
    html[data-theme="dark"] input:focus,
    html[data-theme="dark"] select:focus,
    html[data-theme="dark"] textarea:focus {
        border-color: #d35400 !important;
        box-shadow: 0 0 0 3px rgba(211, 84, 0, 0.2) !important;
    }

    /* Badges */
    html[data-theme="dark"] .badge {
        border: 1px solid transparent !important;
    }
    
    html[data-theme="dark"] .badge.cash {
        background: rgba(212, 175, 55, 0.15) !important;
        color: #d4af37 !important;
        border-color: rgba(212, 175, 55, 0.25) !important;
    }

    html[data-theme="dark"] .badge.bank_transfer {
        background: rgba(52, 152, 219, 0.15) !important;
        color: #5dade2 !important;
        border-color: rgba(52, 152, 219, 0.25) !important;
    }

    html[data-theme="dark"] .res-pending {
        background: rgba(230, 126, 34, 0.15) !important;
        color: #e67e22 !important;
        border: 1px solid rgba(230, 126, 34, 0.25) !important;
    }

    html[data-theme="dark"] .res-confirmed {
        background: rgba(39, 174, 96, 0.15) !important;
        color: #2ecc71 !important;
        border: 1px solid rgba(39, 174, 96, 0.25) !important;
    }

    html[data-theme="dark"] .res-cancelled {
        background: rgba(192, 57, 43, 0.15) !important;
        color: #e74c3c !important;
        border: 1px solid rgba(192, 57, 43, 0.25) !important;
    }

    html[data-theme="dark"] .badge.badge-table {
        background: rgba(52, 152, 219, 0.15) !important;
        color: #5dade2 !important;
        border: 1px solid rgba(52, 152, 219, 0.25) !important;
    }
    html[data-theme="dark"] .badge.role-admin {
        background: rgba(52, 152, 219, 0.15) !important;
        color: #5dade2 !important;
        border: 1px solid rgba(52, 152, 219, 0.25) !important;
    }
    html[data-theme="dark"] .badge.role-user {
        background: rgba(243, 237, 226, 0.08) !important;
        color: #a09587 !important;
        border: 1px solid rgba(243, 237, 226, 0.12) !important;
    }

    /* Premium Action Buttons in Dark Mode */
    html[data-theme="dark"] .btn-action {
        border-color: transparent !important;
    }
    html[data-theme="dark"] .btn-action.btn-approve,
    html[data-theme="dark"] .btn-action.btn-toggle-on {
        background: rgba(39, 174, 96, 0.15) !important;
        color: #2ecc71 !important;
        border: 1px solid rgba(39, 174, 96, 0.25) !important;
    }
    html[data-theme="dark"] .btn-action.btn-approve:hover,
    html[data-theme="dark"] .btn-action.btn-toggle-on:hover {
        background: rgba(39, 174, 96, 0.25) !important;
        box-shadow: 0 4px 12px rgba(46, 204, 113, 0.25) !important;
    }
    html[data-theme="dark"] .btn-action.btn-cancel,
    html[data-theme="dark"] .btn-action.btn-delete,
    html[data-theme="dark"] .btn-action.btn-toggle-off {
        background: rgba(192, 57, 43, 0.15) !important;
        color: #e74c3c !important;
        border: 1px solid rgba(192, 57, 43, 0.25) !important;
    }
    html[data-theme="dark"] .btn-action.btn-cancel:hover,
    html[data-theme="dark"] .btn-action.btn-delete:hover,
    html[data-theme="dark"] .btn-action.btn-toggle-off:hover {
        background: rgba(192, 57, 43, 0.25) !important;
        box-shadow: 0 4px 12px rgba(231, 76, 60, 0.25) !important;
    }
    html[data-theme="dark"] .btn-action.btn-edit {
        background: rgba(52, 152, 219, 0.15) !important;
        color: #5dade2 !important;
        border: 1px solid rgba(52, 152, 219, 0.25) !important;
    }
    html[data-theme="dark"] .btn-action.btn-edit:hover {
        background: rgba(52, 152, 219, 0.25) !important;
        box-shadow: 0 4px 12px rgba(52, 152, 219, 0.25) !important;
    }

    /* Buttons & Pagination */
    html[data-theme="dark"] .btn-quick-action {
        background-color: #24201c !important;
        color: #f3ede2 !important;
        border: 1px solid rgba(211, 84, 0, 0.12) !important;
    }
    
    html[data-theme="dark"] .btn-quick-action:hover {
        background-color: #12100e !important;
        border-color: #d35400 !important;
        color: #e67e22 !important;
    }

    html[data-theme="dark"] .page-link {
        background-color: #1c1916 !important;
        color: #f3ede2 !important;
        border: 1px solid rgba(211, 84, 0, 0.15) !important;
    }

    html[data-theme="dark"] .page-link.active {
        background-color: #d35400 !important;
        color: white !important;
        border-color: #d35400 !important;
    }

    html[data-theme="dark"] .page-link:disabled {
        background-color: #12100e !important;
        color: #7d7265 !important;
        border-color: rgba(211, 84, 0, 0.08) !important;
    }

    /* Modals & POS details */
    html[data-theme="dark"] .modal-content,
    html[data-theme="dark"] #voucherModal > div,
    html[data-theme="dark"] #userModal > div,
    html[data-theme="dark"] #menuModal > div {
        background-color: #1c1916 !important;
        color: #f3ede2 !important;
        border: 1px solid rgba(211, 84, 0, 0.2) !important;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4) !important;
    }

    /* Modal Cancel Buttons overrides */
    html[data-theme="dark"] #voucherModal button[data-action="close-voucher-modal"],
    html[data-theme="dark"] #userModal button[onclick*="close"],
    html[data-theme="dark"] #menuModal button[onclick*="close"],
    html[data-theme="dark"] .modal-content button[data-action*="close"] {
        background-color: #24201c !important;
        color: #f3ede2 !important;
        border: 1px solid rgba(211, 84, 0, 0.15) !important;
    }

    html[data-theme="dark"] #voucherModal button[data-action="close-voucher-modal"]:hover,
    html[data-theme="dark"] #userModal button[onclick*="close"]:hover,
    html[data-theme="dark"] #menuModal button[onclick*="close"]:hover,
    html[data-theme="dark"] .modal-content button[data-action*="close"]:hover {
        background-color: #2c2823 !important;
        color: #ffffff !important;
    }

    html[data-theme="dark"] .table-item {
        background-color: #1c1916 !important;
        border-color: rgba(211, 84, 0, 0.2) !important;
        color: #f3ede2 !important;
    }

    html[data-theme="dark"] .table-item.available {
        border-color: #27ae60 !important;
        background-color: rgba(39, 174, 96, 0.1) !important;
        color: #2ecc71 !important;
    }

    html[data-theme="dark"] .table-item.occupied {
        border-color: #d35400 !important;
        background-color: rgba(211, 84, 0, 0.1) !important;
        color: #e67e22 !important;
    }

    html[data-theme="dark"] .menu-item {
        background-color: #24201c !important;
        border-color: rgba(211, 84, 0, 0.1) !important;
        color: #f3ede2 !important;
    }

    html[data-theme="dark"] .menu-item:hover {
        border-color: #d35400 !important;
    }

    html[data-theme="dark"] .cart-item {
        border-bottom-color: rgba(211, 84, 0, 0.15) !important;
    }

    html[data-theme="dark"] .checkout-details {
        border-top-color: rgba(211, 84, 0, 0.2) !important;
        color: #e67e22 !important;
    }

    /* ==========================================================
       SYSTEM LOGS PAGE (logs.php) PREMIUM OVERRIDES & DARK MODE
       ========================================================== */
    .health-grid {
        gap: 16px !important;
        margin-bottom: 30px !important;
    }
    .health-card {
        border: 1.5px solid var(--border) !important;
        border-radius: 16px !important;
        padding: 18px !important;
        background: var(--card-bg) !important;
        box-shadow: var(--shadow-premium) !important;
        transition: var(--transition) !important;
    }
    .health-card:hover {
        transform: translateY(-4px) !important;
        box-shadow: var(--shadow-premium-hover) !important;
        border-color: var(--primary) !important;
    }
    .health-card h3 {
        font-family: 'Plus Jakarta Sans', sans-serif !important;
        font-size: 11px !important;
        letter-spacing: 0.8px !important;
        font-weight: 700 !important;
        color: var(--text-muted) !important;
        margin-bottom: 12px !important;
    }
    .health-card div {
        font-size: 15px !important;
        font-weight: 700 !important;
    }
    .health-ok {
        color: #27ae60 !important;
    }
    .health-bad {
        color: #c0392b !important;
    }
    
    /* Logs Table Pre tags for context */
    pre {
        font-family: 'Consolas', 'Courier New', monospace !important;
        color: var(--text-main) !important;
        font-size: 12.5px !important;
        background: rgba(211, 84, 0, 0.03) !important;
        padding: 8px 12px !important;
        border-radius: 6px !important;
        border-left: 3px solid var(--primary) !important;
    }

    html[data-theme="dark"] .health-card {
        background-color: #1c1916 !important;
        border: 1.5px solid rgba(211, 84, 0, 0.15) !important;
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.25) !important;
    }
    html[data-theme="dark"] .health-ok {
        color: #2ecc71 !important;
    }
    html[data-theme="dark"] .health-bad {
        color: #e74c3c !important;
    }
    html[data-theme="dark"] pre {
        background: rgba(211, 84, 0, 0.06) !important;
        color: #f3ede2 !important;
    }
    html[data-theme="dark"] .level.INFO, 
    html[data-theme="dark"] .level.DEBUG {
        background: rgba(52, 152, 219, 0.15) !important;
        color: #5dade2 !important;
    }
    html[data-theme="dark"] .level.WARNING {
        background: rgba(243, 156, 18, 0.15) !important;
        color: #f39c12 !important;
    }
    html[data-theme="dark"] .level.ERROR, 
    html[data-theme="dark"] .level.CRITICAL {
        background: rgba(192, 57, 43, 0.2) !important;
        color: #e74c3c !important;
    }
</style>

<div class="sidebar">
    <div class="brand">
        <img src="../photo/favicon.png" alt="Logo">
        Dượng Bầu
    </div>
    <a href="dashboard.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
        <span>📊</span> Tổng quan
    </a>
    <a href="orders.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'orders.php') ? 'active' : ''; ?>">
        <span>🛒</span> Đơn hàng
    </a>
    <a href="bookings.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'bookings.php') ? 'active' : ''; ?>">
        <span>📅</span> Đặt bàn
    </a>
    <a href="menu.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'menu.php') ? 'active' : ''; ?>">
        <span>🍽️</span> Thực đơn
    </a>
    <a href="users.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'users.php') ? 'active' : ''; ?>">
        <span>👥</span> Tài khoản
    </a>
    <a href="vouchers.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'vouchers.php') ? 'active' : ''; ?>">
        <span>🎟️</span> Mã Khuyến Mãi
    </a>
    <a href="logs.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'logs.php') ? 'active' : ''; ?>">
        <span>📋</span> Nhật ký
    </a>
    
    <!-- Theme Toggle Switcher Button -->
    <a href="#" class="nav-item theme-toggle-btn" style="margin-top: auto; cursor: pointer;">
        <span class="sun-icon">☀️</span>
        <span class="moon-icon">🌙</span>
        <span>Chế độ sáng/tối</span>
    </a>
    
    <a href="#" data-action="admin-logout" class="nav-item" style="color: #e74c3c !important;">
        <span>🚪</span> Đăng xuất
    </a>
</div>

<script src="../js/admin-common.js" defer></script>
