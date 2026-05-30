<?php
// admin/dashboard.php
require_once 'auth_check.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="../photo/favicon.png" type="image/png">
    <meta charset="UTF-8">
    <title>Admin Dashboard - Nhà Hàng Cơm Quê Dượng Bầu</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Premium Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
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
            --shadow-premium: 0 20px 50px rgba(28, 25, 23, 0.04);
            --shadow-premium-hover: 0 35px 60px rgba(28, 25, 23, 0.08);
            --transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            --bg: var(--cream);
            --card-bg: var(--white);
            --border: rgba(211, 84, 0, 0.08);
        }

        /* Dark Theme Overrides */
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

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
        }

        /* MAIN CONTENT */
        .main-content {
            margin-left: 260px;
            padding: 40px;
            width: calc(100% - 260px);
            background: var(--bg);
            transition: var(--transition);
        }

        /* WELCOME BANNER */
        .welcome-banner {
            background: linear-gradient(135deg, #1c1a17, #2c2823);
            color: #fbf9f6;
            padding: 32px 40px;
            border-radius: var(--radius-lg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(28, 25, 23, 0.12);
            border: 1px solid rgba(212, 175, 55, 0.12);
        }
        .welcome-banner::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 15% 30%, rgba(211, 84, 0, 0.2), transparent 60%);
            pointer-events: none;
        }
        .welcome-text h2 {
            font-family: 'Playfair Display', serif;
            font-size: 26px;
            font-weight: 700;
            background: linear-gradient(to right, #ffffff, var(--accent-gold));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 6px;
        }
        .welcome-text p {
            color: var(--text-muted);
            font-size: 14.5px;
        }
        .welcome-actions {
            display: flex;
            gap: 12px;
            align-items: center;
            z-index: 2;
        }
        .btn-export {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: white;
            background: var(--primary-gradient);
            padding: 12px 24px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-weight: 700;
            transition: var(--transition);
            box-shadow: 0 8px 20px rgba(211, 84, 0, 0.25);
            border: none;
            cursor: pointer;
        }
        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(211, 84, 0, 0.4);
            filter: brightness(1.05);
        }
        .date-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #fbf9f6;
            background: rgba(255, 255, 255, 0.08);
            padding: 12px 24px;
            border-radius: var(--radius-sm);
            border: 1px solid rgba(255, 255, 255, 0.12);
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        /* STATS GRID */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--radius-md);
            padding: 24px;
            box-shadow: var(--shadow-premium);
            border: 1px solid var(--border);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: transparent;
            transition: background 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-premium-hover);
            border-color: rgba(211, 84, 0, 0.15);
        }
        .stat-card:hover::before {
            background: var(--primary-gradient);
        }
        .stat-card:nth-child(4):hover::before {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }

        .stat-card h3 {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-muted);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--charcoal);
            margin-bottom: 6px;
            font-family: 'Outfit', sans-serif;
            letter-spacing: -0.5px;
        }
        .stat-note { font-size: 13px; color: var(--text-muted); }

        /* CARDS */
        .card {
            background: var(--card-bg);
            border-radius: var(--radius-md);
            padding: 28px;
            box-shadow: var(--shadow-premium);
            border: 1px solid var(--border);
            transition: var(--transition);
        }
        .card:hover {
            box-shadow: var(--shadow-premium-hover);
            border-color: rgba(211, 84, 0, 0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--cream-dark);
        }
        .card-header h3 {
            font-family: 'Playfair Display', serif;
            font-size: 17px;
            font-weight: 700;
            color: var(--charcoal);
        }

        /* TABLES */
        table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 14.5px; }
        th {
            text-align: left;
            padding: 14px 16px;
            color: var(--text-muted);
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            background: var(--cream-dark);
            border-bottom: 1px solid rgba(211, 84, 0, 0.1);
        }
        th:first-child {
            border-top-left-radius: var(--radius-sm);
            border-bottom-left-radius: var(--radius-sm);
        }
        th:last-child {
            border-top-right-radius: var(--radius-sm);
            border-bottom-right-radius: var(--radius-sm);
        }
        td {
            padding: 16px;
            border-bottom: 1px solid var(--cream-dark);
            vertical-align: middle;
            color: var(--charcoal-light);
            transition: background 0.2s ease;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(243, 237, 226, 0.15); }
        
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.2px;
            border: 1px solid transparent;
        }
        .badge.cash {
            background: rgba(212, 175, 55, 0.1);
            color: #b8860b;
            border-color: rgba(212, 175, 55, 0.2);
        }
        .badge.bank_transfer {
            background: rgba(52, 152, 219, 0.1);
            color: #2980b9;
            border-color: rgba(52, 152, 219, 0.2);
        }
        .res-pending {
            background: rgba(230, 126, 34, 0.1) !important;
            color: #d35400 !important;
            border: 1px solid rgba(230, 126, 34, 0.15) !important;
        }
        .res-confirmed {
            background: rgba(39, 174, 96, 0.1) !important;
            color: #27ae60 !important;
            border: 1px solid rgba(39, 174, 96, 0.15) !important;
        }
        .res-cancelled {
            background: rgba(192, 57, 43, 0.1) !important;
            color: #c0392b !important;
            border: 1px solid rgba(192, 57, 43, 0.15) !important;
        }
        
        .btn-quick-action {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: var(--cream);
            border-radius: var(--radius-sm);
            text-decoration: none;
            color: var(--charcoal-light);
            font-weight: 700;
            border: 1px solid var(--cream-dark);
            transition: var(--transition);
            font-size: 14.5px;
        }
        .btn-quick-action:hover {
            background: white;
            border-color: var(--primary);
            transform: translateX(4px);
            box-shadow: 0 6px 15px rgba(211, 84, 0, 0.05);
            color: var(--primary);
        }

        /* Chart Canvas Area */
        canvas {
            filter: drop-shadow(0 4px 10px rgba(0, 0, 0, 0.02));
        }

        @media (max-width: 1024px) {
            .sidebar { width: 80px !important; padding: 20px 10px !important; }
            .sidebar .brand { font-size: 0 !important; border-bottom: none !important; margin-bottom: 20px !important; padding-bottom: 0 !important; }
            .sidebar .nav-item span { margin-right: 0 !important; font-size: 20px !important; }
            .sidebar .nav-item { justify-content: center !important; border-left: none !important; border-bottom: 3px solid transparent !important; }
            .sidebar .nav-item.active { border-bottom-color: #d35400 !important; }
            .main-content { margin-left: 80px !important; width: calc(100% - 80px) !important; padding: 20px !important; }
            .stat-grid { grid-template-columns: repeat(2, 1fr) !important; }
            .welcome-banner { flex-direction: column; align-items: flex-start; gap: 20px; }
            div[style*="grid-template-columns"] { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <!-- WELCOME BANNER HERO -->
        <div class="welcome-banner">
            <div class="welcome-text">
                <h2>Xin chào, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?> 👋</h2>
                <p>Chào mừng trở lại! Đây là tổng quan hoạt động kinh doanh của nhà hàng Dượng Bầu hôm nay.</p>
            </div>
            <div class="welcome-actions">
                <a href="../api/admin/export_revenue.php" target="_blank" class="btn-export">
                    📥 Xuất Excel
                </a>
                <div class="date-badge">
                    📅 <?php echo date('d/m/Y'); ?>
                </div>
            </div>
        </div>

        <!-- STATISTICS -->
        <div class="stat-grid">
            <div class="stat-card">
                <h3>Doanh thu hôm nay</h3>
                <div class="stat-value" id="todayRevenue">0 VNĐ</div>
                <div class="stat-note">Đã thanh toán</div>
            </div>
            <div class="stat-card">
                <h3>Đơn hàng hôm nay</h3>
                <div class="stat-value" id="todayOrders">0</div>
                <div class="stat-note">Tất cả trạng thái</div>
            </div>
            <div class="stat-card">
                <h3>Đặt bàn hôm nay</h3>
                <div class="stat-value" id="todayBookings">0</div>
                <div class="stat-note">Khách đặt trước</div>
            </div>
            <div class="stat-card">
                <h3>Đơn đang chờ</h3>
                <div class="stat-value" id="pendingOrders" style="color:#e67e22;">0</div>
                <div class="stat-note">Cần xử lý ngay</div>
            </div>
        </div>

        <!-- CHARTS SECTION -->
        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:24px; margin-bottom: 24px;">
            <!-- REVENUE CHART -->
            <div class="card">
                <div class="card-header">
                    <h3>Biểu đồ doanh thu</h3>
                    <select id="revenueChartFilter" data-action="load-revenue-chart" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #ccc; font-family: inherit; font-size: 13px; outline:none; cursor:pointer;">
                        <option value="day">7 ngày qua</option>
                        <option value="month">Trong năm nay</option>
                    </select>
                </div>
                <div style="height: 300px;">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <!-- TOP DISHES CHART -->
            <div class="card">
                <div class="card-header">
                    <h3>Top 5 Món bán chạy</h3>
                </div>
                <div style="height: 300px; display:flex; justify-content:center; align-items:center;">
                    <canvas id="topDishesChart"></canvas>
                </div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:24px;">
            <!-- LEFT COLUMN -->
            <div style="display:flex; flex-direction:column; gap:24px;">
                
                <!-- RECENT ORDERS -->
                <div class="card" style="grid-column: span 2;">
                    <div class="card-header">
                        <h3>Đơn hàng mới nhất</h3>
                        <a href="orders.php" style="font-size:13px; font-weight:600; color:var(--primary); text-decoration:none;">Xem tất cả &rarr;</a>
                    </div>
                    <table id="recentOrdersTable">
                        <thead>
                        <tr>
                            <th>Mã đơn</th>
                            <th>Khách hàng</th>
                            <th>Thanh toán</th>
                            <th>Tiền</th>
                            <th>Trạng thái</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr><td colspan="5">Đang tải...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- RIGHT COLUMN -->
            <div style="display:flex; flex-direction:column; gap:24px;">
                
                <!-- TODAY BOOKINGS -->
                <div class="card">
                    <div class="card-header">
                        <h3>Đặt bàn hôm nay</h3>
                        <a href="bookings.php" style="font-size:13px; font-weight:600; color:var(--primary); text-decoration:none;">Chi tiết &rarr;</a>
                    </div>
                    <div id="miniBookingList" style="display:flex; flex-direction:column; gap:12px;">
                        <!-- JS renders bookings here -->
                        <p style="color:#999; font-size:13px;">Đang tải...</p>
                    </div>
                </div>

                <!-- QUICK ACTIONS -->
                <div class="card">
                    <div class="card-header">
                        <h3>Truy cập nhanh</h3>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <a href="menu.php" class="btn-quick-action">
                            🍽️ Quản lý Thực đơn
                        </a>
                        <a href="users.php" class="btn-quick-action">
                            👥 Quản lý Tài khoản
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>




    <!-- NOTIFICATION SOUND (Base64 "Ding" for simplicity) -->
    <audio id="notif-sound" src="data:audio/mp3;base64,SUQzBAAAAAAAI1RTS1MAAAAPAAADTGF2ZjU4LjI5LjEwMAAAAAAAAAAAAAAA//uQZAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAWgAAAA0AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAADwAAAA8AAAAAAAAAAAAAAD//7kGQAA/0U1V/ywAAAAANVV8sAAAAA1VfywAAAAADVV/LAAAA//+5BkAAAAAAAAABAAAAADEAAAAA00b80QAAAAADTRvzRAAA//+5BkAAAAAAAAD7AAAAAEwAAAAA00b80QAAAAADTRvzRAAA//+5BkAAAAAAAAAAAAAAAABzAAAAANNG/NEAAAAAA00b80QAAA=="></audio>
    <!-- Note: Short beep placeholder. User should replace with real mp3 if needed. -->

    
    <script src="../js/admin-dashboard.js" defer></script>
</body>
</html>


