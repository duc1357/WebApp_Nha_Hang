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

    <style>
        :root {
            --primary: #e67e22;
            --primary-dark: #d35400;
            --text-main: #2c3e50;
            --bg: #f4f6f9;
            --card-bg: #ffffff;
            --border: #eee;
            --radius: 12px;
            --shadow: 0 4px 12px rgba(0,0,0,0.03);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */
        .sidebar {
            width: 250px;
            background: white;
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            padding: 24px;
            position: fixed;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 100;
        }
        .brand {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 40px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .nav-item {
            padding: 12px 16px;
            margin-bottom: 4px;
            color: #7f8c8d;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            display: block;
            transition: 0.2s;
        }
        .nav-item:hover, .nav-item.active {
            background: #fff0e6;
            color: var(--primary);
        }

        /* MAIN CONTENT */
        .main-content {
            margin-left: 250px;
            padding: 40px;
            width: 100%;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .stat-card h3 {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #7f8c8d;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 4px;
        }
        .stat-note { font-size: 12px; color: #95a5a6; }

        .card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f9f9f9;
        }
        .card-header h3 { font-size: 16px; font-weight: 700; }

        /* TABLES */
        table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 14px; }
        th { text-align: left; padding: 10px; color: #95a5a6; font-weight: 600; font-size: 12px; text-transform: uppercase; }
        td { padding: 12px 10px; border-bottom: 1px solid #f4f4f4; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .res-pending { background: #fff3cd; color: #856404; }
        .res-confirmed { background: #d4edda; color: #155724; }
        .res-cancelled { background: #f8d7da; color: #721c24; }
        .admin-role { background: #e3f2fd; color: #1565c0; }
        
        @media (max-width: 1024px) {
            .stat-grid { grid-template-columns: repeat(2, 1fr); }
            div[style*="grid-template-columns"] { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <!-- HEADER -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
            <div>
                <h2 style="font-size:24px; font-weight:700; color:var(--text-main);">Xin chào, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?> 👋</h2>
                <p style="color:#7f8c8d; margin-top:5px;">Đây là tổng quan tình hình kinh doanh hôm nay.</p>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
                <a href="../api/admin/export_revenue.php" target="_blank" style="font-size:14px; color:white; background:#27ae60; padding:8px 16px; border-radius:8px; text-decoration:none; font-weight:600; border:none; cursor:pointer;">
                    📥 Xuất Excel
                </a>
                <div style="font-size:14px; color:#7f8c8d; background:white; padding:8px 16px; border-radius:8px; border:1px solid #eee;">
                    <?php echo date('d/m/Y'); ?>
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
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <a href="menu.php" style="display:block; padding:12px; background:#f8f9fa; border-radius:8px; text-decoration:none; color:#2c3e50; font-weight:600; border:1px solid #eee; transition:0.2s;">
                            🍽️ Quản lý Thực đơn
                        </a>
                        <a href="users.php" style="display:block; padding:12px; background:#f8f9fa; border-radius:8px; text-decoration:none; color:#2c3e50; font-weight:600; border:1px solid #eee; transition:0.2s;">
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


