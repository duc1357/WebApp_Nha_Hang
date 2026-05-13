<?php
require_once 'auth_check.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="../photo/favicon.png" type="image/png">
    <meta charset="UTF-8">
    <title>Quản Lý Voucher - Dượng Bầu Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');
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
        .btn-add { background: #2c3e50; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; }

        /* Table */
        .table-container { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f1f1f1; }
        th { background: #fafafa; font-weight: 600; color: #7f8c8d; }
        .badge { padding: 4px 10px; border-radius: 99px; font-size: 12px; font-weight: 600; }
        .status-active { background: #e8f5e9; color: #2e7d32; }
        .status-expired { background: #fbe9e7; color: #c62828; }

        /* Actions */
        .btn-action { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; color: white; font-size: 12px; margin-right: 4px; }
        .btn-edit { background: #3498db; }
        .btn-delete { background: #e74c3c; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2 style="font-size: 24px;">Quản Lý Mã Giảm Giá</h2>
            <div style="display:flex; gap:10px;">
                <button class="btn-add" style="background:#e74c3c;" onclick="deleteExpiredVouchers()">Xóa Mã Hết Hạn</button>
                <button class="btn-add" onclick="openModal()">+ Tạo Mã Mới</button>
            </div>
        </div>

        <div class="table-container">
            <table id="voucherTable">
                <thead>
                    <tr>
                        <th>Mã Code</th>
                        <th>Loại Giảm</th>
                        <th>Giá Trị</th>
                        <th>Đơn Hàng Tối Thiểu</th>
                        <th>Lượt Dùng</th>
                        <th>Hết Hạn</th>
                        <th>Trạng Thái</th>
                        <th>Hành Động</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="8">Đang tải...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- VOUCHER MODAL -->
    <div id="voucherModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:999;">
        <div style="background:white; padding:24px; border-radius:12px; width:450px; max-width:90%; box-shadow:0 10px 30px rgba(0,0,0,0.1);">
            <h3 id="modalTitle" style="margin-bottom:16px;">Tạo Mã Giảm Giá</h3>
            <input type="hidden" id="vId">
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                <div style="margin-bottom:12px;">
                    <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;">Mã Code:</label>
                    <input type="text" id="vCode" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;text-transform:uppercase;" placeholder="VD: SALE50">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;">Hết hạn:</label>
                    <input type="date" id="vExpire" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                <div style="margin-bottom:12px;">
                    <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;">Loại giảm:</label>
                    <select id="vType" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;">
                        <option value="percent">Phần trăm (%)</option>
                        <option value="fixed">Số tiền cố định (VNĐ)</option>
                    </select>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;">Giá trị giảm:</label>
                    <input type="number" id="vValue" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;" placeholder="VD: 10">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                <div style="margin-bottom:12px;">
                    <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;">Đơn tối thiểu:</label>
                    <input type="number" id="vMin" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;" value="0">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;">Giới hạn lượt dùng:</label>
                    <input type="number" id="vLimit" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;" value="100">
                </div>
            </div>

            <div style="text-align:right; margin-top:20px;">
                <button onclick="document.getElementById('voucherModal').style.display='none'" style="padding:10px 20px;border:none;background:#eee;border-radius:6px;cursor:pointer;margin-right:8px;">Hủy</button>
                <button id="btnSave" onclick="saveVoucher()" style="padding:10px 20px;border:none;background:var(--primary);color:white;border-radius:6px;cursor:pointer;font-weight:600;">Tạo Mã</button>
            </div>
        </div>
    </div>

    <script>
        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function loadVouchers() {
            fetch('../api/admin/get_vouchers.php')
                .then(res => res.json())
                .then(data => {
                    const tbody = document.querySelector('#voucherTable tbody');
                    tbody.innerHTML = '';
                    
                    if (!data.success || !data.vouchers.length) {
                        tbody.innerHTML = '<tr><td colspan="7">Chưa có mã giảm giá nào.</td></tr>';
                        return;
                    }

                    data.vouchers.forEach(v => {
                        const isExpired = new Date(v.expire_date) < new Date();
                        const isLimitReached = v.used_count >= v.usage_limit;
                        let status = (isExpired || isLimitReached) ? '<span class="badge status-expired">Ngưng hđ</span>' : '<span class="badge status-active">Hoạt động</span>';
                        if (isExpired) status = '<span class="badge status-expired">Hết hạn</span>';
                        const id = Number(v.id) || 0;
                        const discountValue = Number(v.discount_value) || 0;
                        const minOrderValue = Number(v.min_order_value) || 0;
                        const usageLimit = Number(v.usage_limit) || 0;
                        const usedCount = Number(v.used_count) || 0;

                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td><strong style="color:var(--primary)">${escapeHtml(v.code)}</strong></td>
                            <td>${v.discount_type === 'percent' ? 'Phần trăm' : 'Tiền mặt'}</td>
                            <td>${v.discount_type === 'percent' ? discountValue + '%' : new Intl.NumberFormat('vi-VN').format(discountValue) + 'đ'}</td>
                            <td>${new Intl.NumberFormat('vi-VN').format(minOrderValue)}đ</td>
                            <td>${usedCount} / ${usageLimit}</td>
                            <td>${escapeHtml(v.expire_date)}</td>
                            <td>${status}</td>
                            <td>
                                <button type="button" class="btn-action btn-edit js-edit-voucher">Sửa</button>
                                <button type="button" class="btn-action btn-delete js-delete-voucher">Xóa</button>
                            </td>
                        `;
                        tr.querySelector('.js-edit-voucher').addEventListener('click', () => editVoucher(id, v.code || '', v.discount_type || 'fixed', discountValue, minOrderValue, usageLimit, v.expire_date || ''));
                        tr.querySelector('.js-delete-voucher').addEventListener('click', () => deleteVoucher(id));
                        tbody.appendChild(tr);
                    });
                })
                .catch(err => console.error(err));
        }

        function deleteExpiredVouchers() {
            if (!confirm('Bạn có chắc muốn xóa tất cả mã giảm giá đã hết hạn? Hành động này không thể hoàn tác.')) return;

            fetch('../api/admin/delete_expired_vouchers.php', {
                method: 'POST'
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    loadVouchers();
                } else {
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(console.error);
        }

        function openModal() {
            document.getElementById('vId').value = '';
            document.getElementById('vCode').value = '';
            document.getElementById('vValue').value = '';
            document.getElementById('vMin').value = '0';
            document.getElementById('vLimit').value = '100';
            document.getElementById('vExpire').value = '';
            
            document.getElementById('modalTitle').textContent = 'Tạo Mã Giảm Giá';
            document.getElementById('btnSave').textContent = 'Tạo Mã'; // Update button text
            document.getElementById('voucherModal').style.display = 'flex';
        }

        function editVoucher(id, code, type, value, min, limit, expire) {
            document.getElementById('vId').value = id;
            document.getElementById('vCode').value = code;
            document.getElementById('vType').value = type;
            document.getElementById('vValue').value = value;
            document.getElementById('vMin').value = min;
            document.getElementById('vLimit').value = limit;
            document.getElementById('vExpire').value = expire.split(' ')[0];
            
            document.getElementById('modalTitle').textContent = 'Cập Nhật Voucher';
            document.getElementById('btnSave').textContent = 'Lưu Lại'; // Update button text
            document.getElementById('voucherModal').style.display = 'flex';
        }

        function saveVoucher() {
            const id = document.getElementById('vId').value;
            const code = document.getElementById('vCode').value.trim();
            const type = document.getElementById('vType').value;
            const value = document.getElementById('vValue').value;
            const min = document.getElementById('vMin').value;
            const limit = document.getElementById('vLimit').value;
            const expire = document.getElementById('vExpire').value;

            if (!code || !value || !expire) {
                alert('Vui lòng nhập đầy đủ thông tin!');
                return;
            }

            const url = id ? '../api/admin/update_voucher.php' : '../api/admin/create_voucher.php';
            const body = {
                id: id,
                code: code,
                discount_type: type,
                discount_value: value,
                min_order_value: min,
                usage_limit: limit,
                expire_date: expire + ' 23:59:59'
            };

            fetch(url, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(body)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(id ? 'Cập nhật thành công!' : 'Tạo mã thành công!');
                    document.getElementById('voucherModal').style.display='none';
                    loadVouchers();
                } else {
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(console.error);
        }

        function deleteVoucher(id) {
            if (!confirm('Bạn có chắc muốn xóa mã này? Nếu mã đã được sử dụng, nó sẽ chỉ bị vô hiệu hóa.')) return;

            fetch('../api/admin/delete_voucher.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id})
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    loadVouchers();
                } else {
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(console.error);
        }

        // Init
        document.addEventListener('DOMContentLoaded', loadVouchers);

        function logout() {
            fetch('../api/auth/logout.php', { method: 'POST' }).finally(() => window.location.href = '../index.html');
        }
    </script>
</body>
</html>
