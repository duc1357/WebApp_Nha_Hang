<?php
require_once 'auth_check.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="../photo/favicon.png" type="image/png">
    <meta charset="UTF-8">
    <title>Quản Lý Tài Khoản - Dượng Bầu Admin</title>
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
        .btn-add { background: #2c3e50; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; }

        /* Table */
        .table-container { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f1f1f1; }
        th { background: #fafafa; font-weight: 600; color: #7f8c8d; }
        .badge { padding: 4px 10px; border-radius: 99px; font-size: 12px; font-weight: 600; }
        .role-admin { background: #e3f2fd; color: #1565c0; }
        .role-user { background: #f3f3f3; color: #333; }

        /* Buttons */
        .btn-action { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 6px; border: none; cursor: pointer; margin-right: 4px; }
        .btn-edit { background: #e3f2fd; color: #1565c0; }
        .btn-delete { background: #ffebee; color: #c62828; }

        /* Pagination */
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
            <h2 style="font-size: 24px;">Quản Lý Tài Khoản</h2>
            <button class="btn-add" data-action="open-modal">+ Thêm Tài Khoản</button>
        </div>

        <div class="filters">
            <input type="text" id="searchInput" class="form-control" placeholder="Tên, Email, SĐT..." style="width: 300px;">
            <select id="roleFilter" class="form-control">
                <option value="">Tất cả vai trò</option>
                <option value="user">User (Khách)</option>
                <option value="admin">Admin (Quản trị)</option>
            </select>
            <button data-action="load-users" class="btn-search">Tìm kiếm</button>
        </div>

        <div class="table-container">
            <table id="usersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Họ tên</th>
                        <th>SĐT</th>
                        <th>Email</th>
                        <th>Vai trò</th>
                        <th>Ngày tạo</th>
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

    <!-- USER MODAL -->
    <div id="userModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:999;">
        <div style="background:white; padding:24px; border-radius:12px; width:400px; max-width:90%; box-shadow:0 10px 30px rgba(0,0,0,0.1);">
            <h3 id="modalTitle" style="margin-bottom:16px;">Thêm tài khoản</h3>
            <input type="hidden" id="userId">
            <div style="margin-bottom:12px;">
                <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;">Họ tên:</label>
                <input type="text" id="userName" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;">
            </div>
            <div style="margin-bottom:12px;">
                <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;">Số điện thoại:</label>
                <input type="text" id="userPhone" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;">
            </div>
            <div style="margin-bottom:12px;">
                <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;">Email:</label>
                <input type="email" id="userEmail" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;">
            </div>
            <div style="margin-bottom:12px;">
                <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;">Mật khẩu <span id="pwdHint" style="font-weight:400;color:#999;font-size:11px;display:none;">(Để trống nếu không đổi)</span>:</label>
                <input type="password" id="userPassword" minlength="8" pattern="(?=.*[A-Z])(?=.*\d).{8,}"
                    placeholder="Tối thiểu 8 ký tự, có chữ hoa và số" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;">
            </div>
            <div style="margin-bottom:12px;">
                <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;">Vai trò:</label>
                <select id="userRole" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;">
                    <option value="user">User (Khách hàng)</option>
                    <option value="admin">Admin (Quản trị)</option>
                </select>
            </div>
            <div style="text-align:right; gap:8px; display:flex; justify-content:flex-end; margin-top:20px;">
                <button data-action="close-modal" style="padding:8px 16px; border:1px solid #ddd; background:white; border-radius:6px; cursor:pointer; font-weight:600;">Hủy</button>
                <button data-action="save-user" style="padding:8px 16px; border:none; background:var(--primary); color:white; border-radius:6px; cursor:pointer; font-weight:600;">Lưu</button>
            </div>
        </div>
    </div>

    
    <script src="../js/admin-users.js" defer></script>
</body>
</html>


