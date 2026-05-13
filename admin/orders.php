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
            <button onclick="loadOrders(1)" class="btn-search">Tìm kiếm</button>
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

    <script>
        function logout() {
            fetch('../api/auth/logout.php', { method: 'POST' }).then(() => window.location.href = '../index.html');
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        let currentPage = 1;

        document.addEventListener('DOMContentLoaded', () => loadOrders(1));

        function loadOrders(page) {
            currentPage = page;
            const search = document.getElementById('searchInput').value;
            const status = document.getElementById('statusFilter').value;
            
            const tbody = document.querySelector('#ordersTable tbody');
            tbody.innerHTML = '<tr><td colspan="7">Đang tải...</td></tr>';

            fetch(`../api/admin/get_orders.php?page=${page}&limit=10&search=${encodeURIComponent(search)}&status=${status}`)
                .then(res => res.json())
                .then(data => {
                    tbody.innerHTML = '';
                    if (!data.success || !data.orders.length) {
                        tbody.innerHTML = '<tr><td colspan="7">Không tìm thấy đơn hàng nào.</td></tr>';
                        renderPagination(0, 1);
                        return;
                    }

                    data.orders.forEach(o => {
                        let statusClass = 'res-pending';
                        let statusText = 'Chờ xử lý';
                        if (o.status === 'paid') { statusClass = 'res-confirmed'; statusText = 'Đã thanh toán'; }
                        if (o.status === 'cancelled') { statusClass = 'res-cancelled'; statusText = 'Đã hủy'; }
                        const id = Number(o.id) || 0;
                        const finalTotal = Number(o.final_total || o.total_amount || 0);
                        const totalAmount = Number(o.total_amount || 0);
                        const discount = Number(o.discount_amount || 0);

                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>#${id}</td>
                            <td>${escapeHtml(o.customer_name || 'Khách lẻ')}</td>
                            <td>${escapeHtml(o.customer_phone || '-')}</td>
                            <td>${o.payment_method === 'bank_transfer' ? 'Chuyển khoản' : 'Tiền mặt'}</td>
                            <td>
                                <div><strong style="font-size:15px; color:#e67e22;">${finalTotal.toLocaleString('vi-VN')} đ</strong></div>
                                ${discount > 0 ? `<div style="text-decoration:line-through; color:#999; font-size:12px;">${totalAmount.toLocaleString('vi-VN')} đ</div>` : ''}
                                ${discount > 0 ? `<div style="color:green; font-size:11px;">- ${discount.toLocaleString('vi-VN')}</div>` : ''}
                            </td>
                            <td>
                                ${o.table_id ? 
                                    `<span class="badge" style="background:#e3f2fd; color:#0d47a1;">Tại bàn: ${escapeHtml(o.table_id)}</span>` :
                                    (o.address ? `<div style="font-size:13px;">Giao: <b>${escapeHtml(o.address)}</b></div>` : '<span style="color:#999; font-style:italic;">Mua lại quầy</span>')
                                }
                                ${o.note ? `<div style="margin-top:4px; font-size:12px; color:#c0392b; font-style:italic;">"${escapeHtml(o.note)}"</div>` : ''}
                            </td>
                            <td><span class="badge ${statusClass}">${statusText}</span></td>
                            <td style="text-align:center;">
                                ${o.status !== 'paid' && o.status !== 'cancelled' ? 
                                    `<button type="button" data-id="${id}" data-status="paid" class="btn-action btn-approve js-update-status" title="Duyệt đơn">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                     </button>
                                     <button type="button" data-id="${id}" data-status="cancelled" class="btn-action btn-cancel js-update-status" title="Duyệt hủy">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                     </button>` 
                                    : '<span style="color:#ccc;">-</span>'}
                            </td>
                        `;
                        tr.querySelectorAll('.js-update-status').forEach(btn => {
                            btn.addEventListener('click', () => updateStatus(Number(btn.dataset.id), btn.dataset.status));
                        });
                        tbody.appendChild(tr);
                    });

                    renderPagination(data.pagination.total_pages, data.pagination.page);
                })
                .catch(err => console.error(err));
        }

        function renderPagination(totalPages, current) {
            const container = document.getElementById('pagination');
            container.innerHTML = '';
            if(totalPages <= 1) return;

            // Prev
            const prevBtn = document.createElement('button');
            prevBtn.className = 'page-link';
            prevBtn.textContent = '«';
            prevBtn.disabled = current === 1;
            prevBtn.onclick = () => loadOrders(current - 1);
            container.appendChild(prevBtn);

            // Numbers
            for(let i=1; i<=totalPages; i++) {
                const btn = document.createElement('button');
                btn.className = `page-link ${i === current ? 'active' : ''}`;
                btn.textContent = i;
                btn.onclick = () => loadOrders(i);
                container.appendChild(btn);
            }

            // Next
            const nextBtn = document.createElement('button');
            nextBtn.className = 'page-link';
            nextBtn.textContent = '»';
            nextBtn.disabled = current === totalPages;
            nextBtn.onclick = () => loadOrders(current + 1);
            container.appendChild(nextBtn);
        }

        function updateStatus(id, status) {
            if(!confirm('Bạn chắc chắn thay đổi trạng thái?')) return;
            fetch('../api/admin/update_order_status.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id, status})
            }).then(res => res.json()).then(d => {
                if(d.success) loadOrders(currentPage);
                else alert(d.message);
            });
        }
    </script>
</body>
</html>
