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

        function safeImagePath(path) {
            const value = String(path || '');
            return /^[A-Za-z0-9_./-]+$/.test(value) && !value.includes('..') ? '../' + value : '../photo/default-food.png';
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
                        const img = m.image_url ? safeImagePath(m.image_url) : '../photo/default-food.png';
                        const id = Number(m.id) || 0;
                        const price = Number(m.price) || 0;
                        
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>#${id}</td>
                            <td><img src="${escapeHtml(img)}" alt="" style="width:50px;height:50px;object-fit:cover;border-radius:4px;"></td>
                            <td><strong>${escapeHtml(m.name)}</strong></td>
                            <td style="color:#7f8c8d;font-size:13px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHtml(m.description)}</td>
                            <td>${price.toLocaleString('vi-VN')}</td>
                            <td><span class="badge ${statusClass}">${statusText}</span></td>
                            <td style="text-align:center;">
                                <button type="button" class="btn-action btn-edit js-edit-menu" title="Sửa">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                </button>
                                <button type="button" class="btn-action btn-toggle js-toggle-menu ${m.is_active==1 ? 'btn-toggle-off' : 'btn-toggle-on'}" title="Đổi trạng thái">
                                    ${m.is_active==1 
                                        ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>' 
                                        : '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>'}
                                </button>
                                <button type="button" class="btn-action btn-delete js-delete-menu" title="Xóa">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                </button>
                            </td>
                        `;
                        tr.querySelector('.js-edit-menu').addEventListener('click', () => editMenuItem(id, m.name || '', m.description || '', price, m.image_url || ''));
                        tr.querySelector('.js-toggle-menu').addEventListener('click', () => toggleStatus(id, m.is_active));
                        tr.querySelector('.js-delete-menu').addEventListener('click', () => deleteItem(id));
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


document.addEventListener('click', (event) => {
    const action = event.target.closest('[data-action]')?.dataset.action;
    if (action === 'open-modal') openModal();
    if (action === 'load-menu') loadMenu(1);
    if (action === 'close-modal') closeModal();
    if (action === 'save-menu-item') saveMenuItem();
});
