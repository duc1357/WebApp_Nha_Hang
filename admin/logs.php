<?php
require_once 'auth_check.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="../photo/favicon.png" type="image/png">
    <meta charset="UTF-8">
    <title>Admin Logs - Duong Bau Restaurant</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root { --primary:#e67e22; --text-main:#2c3e50; --bg:#f4f6f9; --card-bg:#fff; --border:#eee; --radius:8px; --shadow:0 4px 12px rgba(0,0,0,0.03); }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text-main); display:flex; min-height:100vh; }
        .sidebar { width:250px; background:white; border-right:1px solid var(--border); display:flex; flex-direction:column; padding:24px; position:fixed; height:100%; top:0; left:0; z-index:100; }
        .brand { font-size:20px; font-weight:700; color:var(--primary); margin-bottom:40px; display:flex; align-items:center; gap:12px; }
        .nav-item { padding:12px 16px; margin-bottom:4px; color:#7f8c8d; text-decoration:none; border-radius:8px; font-weight:500; display:block; transition:0.2s; }
        .nav-item:hover, .nav-item.active { background:#fff0e6; color:var(--primary); }
        .main-content { margin-left:250px; padding:40px; width:100%; }
        .header-row { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:24px; }
        .card { background:var(--card-bg); border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow); padding:20px; }
        .health-grid { display:grid; grid-template-columns:repeat(5, minmax(140px, 1fr)); gap:12px; margin-bottom:24px; }
        .health-card { border:1px solid var(--border); border-radius:8px; padding:14px; background:white; min-height:92px; }
        .health-card h3 { font-size:12px; color:#7f8c8d; text-transform:uppercase; margin-bottom:8px; }
        .health-ok { color:#1e8449; font-weight:700; }
        .health-bad { color:#c0392b; font-weight:700; }
        .toolbar { display:flex; align-items:center; gap:12px; margin-bottom:16px; }
        select, button { font-family:inherit; border:1px solid #ddd; border-radius:6px; padding:8px 10px; background:white; }
        button { background:var(--primary); border-color:var(--primary); color:white; cursor:pointer; font-weight:600; }
        table { width:100%; border-collapse:separate; border-spacing:0; font-size:13px; }
        th { text-align:left; padding:10px; color:#7f8c8d; border-bottom:1px solid var(--border); text-transform:uppercase; font-size:11px; }
        td { padding:10px; border-bottom:1px solid #f5f5f5; vertical-align:top; }
        .level { display:inline-block; border-radius:4px; padding:3px 8px; font-weight:700; font-size:11px; }
        .level.INFO, .level.DEBUG { background:#eaf2f8; color:#2874a6; }
        .level.WARNING { background:#fff3cd; color:#856404; }
        .level.ERROR, .level.CRITICAL { background:#f8d7da; color:#721c24; }
        pre { white-space:pre-wrap; word-break:break-word; margin:0; font-family:Consolas,monospace; font-size:12px; color:#34495e; }
        @media (max-width:1100px) { .health-grid { grid-template-columns:repeat(2, 1fr); } .main-content { padding:24px; } }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <div class="header-row">
            <div>
                <h1 style="font-size:24px; margin-bottom:6px;">Nhật ký hệ thống</h1>
                <p style="color:#7f8c8d;">Theo dõi log và tình trạng vận hành của ứng dụng.</p>
            </div>
            <button type="button" data-action="refresh-all">Làm mới</button>
        </div>
        <section class="health-grid" id="healthGrid">
            <div class="health-card"><h3>Đang tải</h3><div>...</div></div>
        </section>
        <section class="card">
            <div class="toolbar">
                <label for="channelSelect">Kênh</label>
                <select id="channelSelect" data-action="load-logs">
                    <option value="auth">Auth</option>
                    <option value="payment">Payment</option>
                    <option value="security">Security</option>
                    <option value="app">App</option>
                </select>
                <label for="limitSelect">Số dòng</label>
                <select id="limitSelect" data-action="load-logs">
                    <option value="50">50</option>
                    <option value="100" selected>100</option>
                    <option value="200">200</option>
                </select>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Thời gian</th>
                        <th>Level</th>
                        <th>Message</th>
                        <th>Context</th>
                        <th>Request</th>
                    </tr>
                </thead>
                <tbody id="logRows"><tr><td colspan="5">Đang tải...</td></tr></tbody>
            </table>
        </section>
    </main>
    
    <script src="../js/admin-logs.js" defer></script>
</body>
</html>


