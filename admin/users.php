<?php
require_once 'auth_check.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản Lý Tài Khoản - Dượng Bầu Admin</title>
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
            <button class="btn-add" onclick="openModal()">+ Thêm Tài Khoản</button>
        </div>

        <div class="filters">
            <input type="text" id="searchInput" class="form-control" placeholder="Tên, Email, SĐT..." style="width: 300px;">
            <select id="roleFilter" class="form-control">
                <option value="">Tất cả vai trò</option>
                <option value="user">User (Khách)</option>
                <option value="admin">Admin (Quản trị)</option>
            </select>
            <button onclick="loadUsers(1)" class="btn-search">Tìm kiếm</button>
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
                <input type="password" id="userPassword" placeholder="******" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;">
            </div>
            <div style="margin-bottom:12px;">
                <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;">Vai trò:</label>
                <select id="userRole" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;">
                    <option value="user">User (Khách hàng)</option>
                    <option value="admin">Admin (Quản trị)</option>
                </select>
            </div>
            <div style="text-align:right; gap:8px; display:flex; justify-content:flex-end; margin-top:20px;">
                <button onclick="closeModal()" style="padding:8px 16px; border:1px solid #ddd; background:white; border-radius:6px; cursor:pointer; font-weight:600;">Hủy</button>
                <button onclick="saveUser()" style="padding:8px 16px; border:none; background:var(--primary); color:white; border-radius:6px; cursor:pointer; font-weight:600;">Lưu</button>
            </div>
        </div>
    </div>

    <script>
        function logout() {
            fetch('../api/auth/logout.php', { method: 'POST' }).then(() => window.location.href = '../index.html');
        }

        let currentPage = 1;

        document.addEventListener('DOMContentLoaded', () => loadUsers(1));

        function loadUsers(page) {
            currentPage = page;
            const search = document.getElementById('searchInput').value;
            const role = document.getElementById('roleFilter').value;
            
            const tbody = document.querySelector('#usersTable tbody');
            tbody.innerHTML = '<tr><td colspan="7">Đang tải...</td></tr>';

            fetch(`../api/admin/get_users_list.php?page=${page}&limit=10&search=${encodeURIComponent(search)}&role=${role}`)
                .then(res => res.json())
                .then(data => {
                    tbody.innerHTML = '';
                    if (!data.success || !data.users.length) {
                        tbody.innerHTML = '<tr><td colspan="7">Không tìm thấy tài khoản nào.</td></tr>';
                        renderPagination(0, 1);
                        return;
                    }

                    data.users.forEach(u => {
                        const roleClass = u.role === 'admin' ? 'role-admin' : 'role-user';
                        const roleText = u.role === 'admin' ? 'Quản trị viên' : 'Khách hàng';
                        
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>#${u.id}</td>
                            <td><strong>${u.name}</strong></td>
                            <td>${u.phone}</td>
                            <td>${u.email || '-'}</td>
                            <td><span class="badge ${roleClass}">${roleText}</span></td>
                            <td>${u.created_at}</td>
                            <td style="text-align:center;">
                                <button onclick="editUser(${u.id}, '${u.name}', '${u.phone}', '${u.email}', '${u.role}')" class="btn-action btn-edit" title="Sửa">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                </button>
                                ${u.role !== 'admin' ? 
                                `<button onclick="deleteUser(${u.id})" class="btn-action btn-delete" title="Xóa">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                </button>` : ''}
                            </td>
                        `;
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

            const prevBtn = document.createElement('button');
            prevBtn.className = 'page-link';
            prevBtn.textContent = '«';
            prevBtn.disabled = current === 1;
            prevBtn.onclick = () => loadUsers(current - 1);
            container.appendChild(prevBtn);

            for(let i=1; i<=totalPages; i++) {
                const btn = document.createElement('button');
                btn.className = `page-link ${i === current ? 'active' : ''}`;
                btn.textContent = i;
                btn.onclick = () => loadUsers(i);
                container.appendChild(btn);
            }

            const nextBtn = document.createElement('button');
            nextBtn.className = 'page-link';
            nextBtn.textContent = '»';
            nextBtn.disabled = current === totalPages;
            nextBtn.onclick = () => loadUsers(current + 1);
            container.appendChild(nextBtn);
        }

        // Modal Logic
        const modal = document.getElementById('userModal');
        const modalTitle = document.getElementById('modalTitle');
        const pwdHint = document.getElementById('pwdHint');

        function openModal() {
            document.getElementById('userId').value = '';
            document.getElementById('userName').value = '';
            document.getElementById('userPhone').value = '';
            document.getElementById('userEmail').value = '';
            document.getElementById('userPassword').value = '';
            document.getElementById('userRole').value = 'user';
            
            modalTitle.textContent = 'Thêm tài khoản mới';
            pwdHint.style.display = 'none';
            modal.style.display = 'flex';
        }

        function editUser(id, name, phone, email, role) {
            document.getElementById('userId').value = id;
            document.getElementById('userName').value = name;
            document.getElementById('userPhone').value = phone;
            document.getElementById('userEmail').value = (email === 'null') ? '' : email;
            document.getElementById('userRole').value = role;
            document.getElementById('userPassword').value = '';

            modalTitle.textContent = 'Chỉnh sửa tài khoản';
            pwdHint.style.display = 'inline';
            modal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        function saveUser() {
            const id = document.getElementById('userId').value;
            const name = document.getElementById('userName').value;
            const phone = document.getElementById('userPhone').value;
            const email = document.getElementById('userEmail').value;
            const password = document.getElementById('userPassword').value;
            const role = document.getElementById('userRole').value;

            if(!name || !phone) { alert('Vui lòng nhập tên và số điện thoại'); return; }

            const url = id ? '../api/admin/update_user.php' : '../api/admin/create_user.php';
            const body = { id, name, phone, email, password, role };

            fetch(url, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(body)
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    alert('Thành công!');
                    closeModal();
                    loadUsers(currentPage);
                } else {
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(console.error);
        }

        function deleteUser(id) {
            if(!confirm('Bạn có chắc chắn muốn xóa user này? Hành động này sẽ chuyển user vào thùng rác (Soft Delete).')) return;
            
            fetch('../api/admin/delete_user.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id})
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    alert(data.message);
                    loadUsers(currentPage);
                } else {
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(console.error);
        }
    </script>
</body>
</html>
