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


document.addEventListener('click', (event) => {
    const action = event.target.closest('[data-action]')?.dataset.action;
    if (action === 'delete-expired-vouchers') deleteExpiredVouchers();
    if (action === 'open-modal') openModal();
    if (action === 'close-voucher-modal') document.getElementById('voucherModal').style.display = 'none';
    if (action === 'save-voucher') saveVoucher();
});
