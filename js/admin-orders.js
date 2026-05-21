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


document.addEventListener('click', (event) => {
    if (event.target.closest('[data-action="load-orders"]')) {
        loadOrders(1);
    }
});
