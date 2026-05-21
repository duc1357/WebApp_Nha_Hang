<?php
require_once 'auth_check.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="../photo/favicon.png" type="image/png">
    <meta charset="UTF-8">
    <title>Quản Lý Đơn Hàng - Dượng Bầu Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root { --primary: #e67e22; --text-main: #2c3e50; --bg: #f4f6f9; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Outfit', sans-serif; background: var(--bg); color: var(--text-main); display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 250px; background: white; border-right: 1px solid #eee; display: flex; flex-direction: column; padding: 20px; position: fixed; height: 100%; top:0; left:0; }
        .brand { font-size: 20px; font-weight: 700; color: var(--primary); margin-bottom: 40px; display: flex; align-items: center; gap: 10px; }
        .nav-item { padding: 12px; margin-bottom: 4px; color: #7f8c8d; text-decoration: none; border-radius: 8px; font-weight: 500; display: block; }
        .nav-item:hover, .nav-item.active { background: #fff0e6; color: var(--primary); }
        .main-content { margin-left: 250px; padding: 30px; width: 100%; }

        /* Filters */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .filters { display: flex; gap: 12px; background: white; padding: 16px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
        .form-control { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; outline: none; }
        .btn-search { background: var(--primary); color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; }

        /* Table */
        .table-container { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f1f1f1; }
        th { background: #fafafa; font-weight: 600; color: #7f8c8d; }
        .badge { padding: 4px 10px; border-radius: 99px; font-size: 12px; font-weight: 600; }
        .res-pending { background: #fff3cd; color: #856404; }
        .res-confirmed { background: #d4edda; color: #155724; }
        .res-cancelled { background: #f8d7da; color: #721c24; }

        /* Buttons */
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: 0.2s;
            margin-right: 4px;
        }
        .btn-approve { background: #e8f5e9; color: #2e7d32; }
        .btn-approve:hover { background: #c8e6c9; }
        
        .btn-cancel { background: #ffebee; color: #c62828; }
        .btn-cancel:hover { background: #ffcdd2; }

        .pagination { display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px; }
        .page-link { padding: 6px 12px; border: 1px solid #ddd; background: white; cursor: pointer; border-radius: 4px; }
        .page-link.active { background: var(--primary); color: white; border-color: var(--primary); }
        .page-link:disabled { background: #f9f9f9; color: #ccc; cursor: not-allowed; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2 style="font-size: 24px;">Quản Lý Đơn Hàng</h2>
        </div>

        <div class="filters">
            <input type="text" id="searchInput" class="form-control" placeholder="Tìm tên khách, mã đơn..." style="width: 300px;">
            <select id="statusFilter" class="form-control">
                <option value="">Tất cả trạng thái</option>
                <option value="pending">Chờ xử lý</option>
                <option value="paid">Đã thanh toán (Paid)</option>
                <option value="cancelled">Đã hủy</option>
            </select>
            <button data-action="load-orders" class="btn-search">Tìm kiếm</button>
        </div>

        <div class="table-container">
            <table id="ordersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Khách hàng</th>
                        <th>SĐT</th>
                        <th>Thanh toán</th>
                        <th>Tổng tiền</th>
                        <th>Nơi nhận/Bàn</th>
                        <th>Trạng thái</th>
                        <th style="text-align:center;">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="7">Đang tải...</td></tr>
                </tbody>
            </table>
            <div class="pagination" id="pagination"></div>
        </div>
    </div>

    
    <script src="../js/admin-orders.js" defer></script>
</body>
</html>


