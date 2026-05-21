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
                        const id = Number(u.id) || 0;
                        
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>#${id}</td>
                            <td><strong>${escapeHtml(u.name)}</strong></td>
                            <td>${escapeHtml(u.phone)}</td>
                            <td>${escapeHtml(u.email || '-')}</td>
                            <td><span class="badge ${roleClass}">${roleText}</span></td>
                            <td>${escapeHtml(u.created_at)}</td>
                            <td style="text-align:center;">
                                <button type="button" class="btn-action btn-edit js-edit-user" title="Sửa">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                </button>
                                ${u.role !== 'admin' ? 
                                `<button type="button" class="btn-action btn-delete js-delete-user" title="Xóa">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                </button>` : ''}
                            </td>
                        `;
                        tr.querySelector('.js-edit-user').addEventListener('click', () => editUser(id, u.name || '', u.phone || '', u.email || '', u.role || 'user'));
                        const deleteBtn = tr.querySelector('.js-delete-user');
                        if (deleteBtn) deleteBtn.addEventListener('click', () => deleteUser(id));
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
            if(!id && !password) { alert('Vui lòng nhập mật khẩu'); return; }
            if(password && (password.length < 8 || !/[A-Z]/.test(password) || !/\d/.test(password))) {
                alert('Mật khẩu cần tối thiểu 8 ký tự, có chữ hoa và số.');
                return;
            }

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


document.addEventListener('click', (event) => {
    const action = event.target.closest('[data-action]')?.dataset.action;
    if (action === 'open-modal') openModal();
    if (action === 'load-users') loadUsers(1);
    if (action === 'close-modal') closeModal();
    if (action === 'save-user') saveUser();
});
