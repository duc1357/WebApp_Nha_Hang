<?php
require_once 'auth_check.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản Lý Thực Đơn - Dượng Bầu Admin</title>
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
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }

        /* Buttons */
        .btn-action { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 6px; border: none; cursor: pointer; margin-right: 4px; }
        .btn-edit { background: #e3f2fd; color: #1565c0; }
        .btn-toggle { background: #e8f5e9; color: #2e7d32; }

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
            <h2 style="font-size: 24px;">Quản Lý Thực Đơn</h2>
            <button class="btn-add" onclick="openModal()">+ Thêm Món</button>
        </div>

        <div class="filters">
            <input type="text" id="searchInput" class="form-control" placeholder="Tìm tên món..." style="width: 300px;">
            <select id="statusFilter" class="form-control">
                <option value="">Tất cả trạng thái</option>
                <option value="1">Đang bán</option>
                <option value="0">Ngưng bán</option>
            </select>
            <button onclick="loadMenu(1)" class="btn-search">Tìm kiếm</button>
        </div>

        <div class="table-container">
            <table id="menuTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Hình ảnh</th>
                        <th>Tên món</th>
                        <th>Mô tả</th>
                        <th>Giá</th>
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

    <!-- MENU MODAL -->
    <div id="menuModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:999;">
        <div style="background:white; padding:24px; border-radius:12px; width:450px; max-width:90%; box-shadow:0 10px 30px rgba(0,0,0,0.1);">
            <h3 id="modalTitle" style="margin-bottom:16px;">Thêm món mới</h3>
            <input type="hidden" id="menuId">
            <div style="margin-bottom:12px;">
                <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;">Tên món:</label>
                <input type="text" id="menuName" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;">
            </div>
            <div style="margin-bottom:12px;">
                <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;">Mô tả:</label>
                <textarea id="menuDesc" rows="3" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;"></textarea>
            </div>
            <div style="margin-bottom:12px;">
                <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;">Giá (VNĐ):</label>
                <input type="number" id="menuPrice" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;">
            </div>
            <div style="margin-bottom:12px;">
                <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;">Hình ảnh:</label>
                <input type="file" id="menuPhotoFile" accept="image/*" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;">
                <div id="imagePreview" style="margin-top:10px; width:120px; display:none;">
                    <img src="" style="width:100%; border-radius:6px;">
                </div>
            </div>
            <div style="text-align:right; gap:8px; display:flex; justify-content:flex-end; margin-top:20px;">
                <button onclick="closeModal()" style="padding:8px 16px; border:1px solid #ddd; background:white; border-radius:6px; cursor:pointer; font-weight:600;">Hủy</button>
                <button onclick="saveMenuItem()" style="padding:8px 16px; border:none; background:var(--primary); color:white; border-radius:6px; cursor:pointer; font-weight:600;">Lưu</button>
            </div>
        </div>
    </div>

    <script>
        function logout() {
            fetch('../api/auth/logout.php', { method: 'POST' }).then(() => window.location.href = '../index.html');
        }

        let currentPage = 1;

        document.addEventListener('DOMContentLoaded', () => loadMenu(1));

        function loadMenu(page) {
            currentPage = page;
            const search = document.getElementById('searchInput').value;
            const status = document.getElementById('statusFilter').value;
            
            const tbody = document.querySelector('#menuTable tbody');
            tbody.innerHTML = '<tr><td colspan="7">Đang tải...</td></tr>';

            fetch(`../api/menu/get_menu_list.php?page=${page}&limit=5&search=${encodeURIComponent(search)}&status=${status}`) // Limit 5 for nicer viewing of images
                .then(res => res.json())
                .then(data => {
                    tbody.innerHTML = '';
                    if (!data.success || !data.items.length) {
                        tbody.innerHTML = '<tr><td colspan="7">Không tìm thấy món ăn nào.</td></tr>';
                        renderPagination(0, 1);
                        return;
                    }

                    data.items.forEach(m => {
                        const statusClass = m.is_active == 1 ? 'status-active' : 'status-inactive';
                        const statusText = m.is_active == 1 ? 'Đang bán' : 'Ngưng bán';
                        const img = m.image_url ? '../' + m.image_url : '../photo/default.jpg';
                        
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>#${m.id}</td>
                            <td><img src="${img}" style="width:50px;height:50px;object-fit:cover;border-radius:4px;"></td>
                            <td><strong>${m.name}</strong></td>
                            <td style="color:#7f8c8d;font-size:13px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${m.description}</td>
                            <td>${Number(m.price).toLocaleString('vi-VN')}</td>
                            <td><span class="badge ${statusClass}">${statusText}</span></td>
                            <td style="text-align:center;">
                                <button onclick="editMenuItem(${m.id}, '${m.name}', '${m.description}', ${m.price}, '${m.image_url}')" class="btn-action btn-edit" title="Sửa">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                </button>
                                <button onclick="toggleStatus(${m.id}, ${m.is_active})" class="btn-action btn-toggle" title="Đổi trạng thái" style="color:${m.is_active==1 ? '#c62828' : '#2e7d32'}; background:${m.is_active==1 ? '#ffebee' : '#e8f5e9'};">
                                    ${m.is_active==1 
                                        ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>' 
                                        : '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>'}
                                </button>
                                <button onclick="deleteItem(${m.id})" class="btn-action btn-delete" title="Xóa" style="color:#c0392b; background:#f9ebea;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                </button>
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
            // ... (Simple pagination logic same as other pages)
             const prevBtn = document.createElement('button');
            prevBtn.className = 'page-link';
            prevBtn.textContent = '«';
            prevBtn.disabled = current === 1;
            prevBtn.onclick = () => loadMenu(current - 1);
            container.appendChild(prevBtn);

            for(let i=1; i<=totalPages; i++) {
                const btn = document.createElement('button');
                btn.className = `page-link ${i === current ? 'active' : ''}`;
                btn.textContent = i;
                btn.onclick = () => loadMenu(i);
                container.appendChild(btn);
            }

            const nextBtn = document.createElement('button');
            nextBtn.className = 'page-link';
            nextBtn.textContent = '»';
            nextBtn.disabled = current === totalPages;
            nextBtn.onclick = () => loadMenu(current + 1);
            container.appendChild(nextBtn);
        }

        // Modal Logic
        const modal = document.getElementById('menuModal');
        const modalTitle = document.getElementById('modalTitle');
        const preview = document.getElementById('imagePreview');

        function openModal() {
            document.getElementById('menuId').value = '';
            document.getElementById('menuName').value = '';
            document.getElementById('menuDesc').value = '';
            document.getElementById('menuPrice').value = '';
            document.getElementById('menuPhotoFile').value = '';
            preview.style.display = 'none';
            modalTitle.textContent = 'Thêm món mới';
            modal.style.display = 'flex';
        }

        function editMenuItem(id, name, desc, price, img) {
            document.getElementById('menuId').value = id;
            document.getElementById('menuName').value = name;
            document.getElementById('menuDesc').value = desc;
            document.getElementById('menuPrice').value = price;
            
            if(img) {
                preview.style.display = 'block';
                preview.querySelector('img').src = '../' + img;
            } else {
                preview.style.display = 'none';
            }
            modalTitle.textContent = 'Chỉnh sửa món ăn';
            modal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        function saveMenuItem() {
            const id = document.getElementById('menuId').value;
            const name = document.getElementById('menuName').value;
            const description = document.getElementById('menuDesc').value;
            const price = document.getElementById('menuPrice').value;
            const fileInput = document.getElementById('menuPhotoFile');

            if(!name || !price) { alert('Vui lòng nhập tên và giá'); return; }

            const formData = new FormData();
            if(id) formData.append('id', id);
            formData.append('name', name);
            formData.append('description', description);
            formData.append('price', price);
            if(fileInput.files[0]) {
                formData.append('photo', fileInput.files[0]);
            }

            // Determine URL (Create or Update?)
            // Assuming we have a unified save logic or separate. 
            // In task 4.2 we updated 'api/menu/create.php'. We might need 'update.php'.
            // For now, let's use create.php for NEW, but we need UPDATE.
            // I'll assume usage of single endpoint or create one if missing.
            // Let's use `api/menu/save_item.php` if I create it, or modify create.php to handle update.
            // I will use `api/menu/create.php` for now and check if it handles ID. If not I will create update logic.
            // Actually, best to use separate or update.
            // checking context... create.php was updated.
            
            const url = id ? '../api/admin/update_menu_item.php' : '../api/admin/add_menu_item.php'; 
            
            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    alert('Lưu thành công!');
                    closeModal();
                    loadMenu(currentPage);
                } else {
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(console.error);
        }

        function toggleStatus(id, currentStatus) {
            // New status is inverse
            const newStatus = currentStatus == 1 ? 0 : 1;
             fetch('../api/admin/update_menu_item.php', { // Use update item to just toggle 
                 method: 'POST',
                 headers: {'Content-Type': 'application/json'},
                 body: JSON.stringify({id: id, is_active: newStatus})
             })
             .then(res=>res.json()).then(d=>{
                 if(d.success) loadMenu(currentPage);
                 else alert(d.message);
             })
             .catch(console.error);
             
        }

        function deleteItem(id) {
            if(!confirm('Bạn có chắc chắn muốn xóa món này? Hành động này sẽ chuyển món vào thùng rác (Soft Delete).')) return;
            
            fetch('../api/admin/delete_menu_item.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id})
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    alert(data.message);
                    loadMenu(currentPage);
                } else {
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(console.error);
        }
    </script>
</body>
</html>
