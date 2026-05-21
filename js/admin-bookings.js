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
        
        // --- POS GLOBAL STATE ---
        let globalMenu = [];
        let currentTableId = null;
        let currentTableName = '';
        let currentBookingId = null;
        let posCart = []; // Array of {id, name, price, quantity}

        document.addEventListener('DOMContentLoaded', () => {
            loadGlobalMenu();
            loadTableMap();
        });

        function toggleView(view) {
            document.getElementById('mapView').style.display = view === 'map' ? 'block' : 'none';
            document.getElementById('listView').style.display = view === 'list' ? 'block' : 'none';
            if (view === 'map') loadTableMap();
            if (view === 'list') loadBookings(1);
        }

        // --- MENU LOGIC ---
        function loadGlobalMenu() {
            fetch('../api/menu/get_menu.php')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Filter active only for POS
                        globalMenu = data.data.filter(item => item.is_active === 1);
                    }
                }).catch(err => console.error(err));
        }

        function formatCurrency(num) {
            return num.toLocaleString('vi-VN') + 'đ';
        }

        // --- MAP LOGIC ---
        function setPosLoading(isLoading) {
            document.getElementById('posLoading').style.display = isLoading ? 'inline' : 'none';
        }

        function loadTableMap() {
            const date = document.getElementById('mapDate').value;
            const container = document.getElementById('mapContent');
            container.innerHTML = '<div class="loading-spinner"></div>';
            
            fetch(`../api/admin/get_table_status.php?date=${date}&t=${Date.now()}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        container.innerHTML = 'Lỗi tải dữ liệu sơ đồ bàn.';
                        return;
                    }
                    container.innerHTML = '';
                    for (const [floor, tables] of Object.entries(data.floors)) {
                        const floorName = floor === 'rose' ? 'Sảnh Rose' : (floor === 'tulip' ? 'Sảnh Tulip' : floor);
                        const floorDiv = document.createElement('div');
                        floorDiv.className = 'floor-container';
                        
                        let gridHtml = '<div class="table-grid">';
                        tables.forEach(t => {
                            const isOccupied = t.status === 'occupied';
                            const statusClass = isOccupied ? 'occupied' : 'available';
                            const statusText = isOccupied ? 'Đang phục vụ' : 'Trống';
                            const details = t.booking_info ? 
                                `<br><span style="font-size:11px; color:#555;">Hẹn: ${escapeHtml(t.booking_info.name)} (${escapeHtml(t.booking_info.time)})</span>` : '';
                            const tableId = escapeHtml(t.id);
                            const tableName = escapeHtml(t.name);
                            const tableStatus = escapeHtml(t.status);
                            
                            gridHtml += `
                                <div class="table-item ${statusClass}" title="${statusText}" data-id="${tableId}" data-name="${tableName}" data-status="${tableStatus}">
                                    <div class="table-name">${tableName}</div>
                                    <div class="table-cap">${escapeHtml(t.capacity)} ghế</div>
                                    ${details}
                                </div>
                            `;
                        });
                        gridHtml += '</div>';
                        floorDiv.innerHTML = `<div class="floor-title">${escapeHtml(floorName)}</div>${gridHtml}`;
                        floorDiv.querySelectorAll('.table-item').forEach(tableEl => {
                            tableEl.addEventListener('click', () => handleTableClick(tableEl.dataset.id, tableEl.dataset.name, tableEl.dataset.status));
                        });
                        container.appendChild(floorDiv);
                    }
                }).catch(console.error);
        }

        function handleTableClick(id, name, status) {
            if (status === 'available') {
                if (confirm(`Khách nhận bàn ${name}? Bàn sẽ chuyển sang trạng thái "Đang phục vụ".`)) {
                    toggleTableStatus(id, 'occupied');
                }
            } else {
                openPosModal(id, name);
            }
        }

        function toggleTableStatus(id, newStatus) {
            setPosLoading(true);
            fetch('../api/admin/update_table_status.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id, status: newStatus})
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) loadTableMap();
                else alert(data.message);
            })
            .catch(console.error)
            .finally(() => setPosLoading(false));
        }

        // --- POS MODAL LOGIC ---
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function openPosModal(id, name) {
            currentTableId = id;
            currentTableName = name;
            currentBookingId = null;
            posCart = [];
            document.getElementById('posTableTitle').textContent = `Order - Bàn ${name}`;
            document.getElementById('posSearch').value = '';
            
            document.getElementById('posCartItems').innerHTML = '<div class="loading-spinner"></div>';
            document.getElementById('posModal').classList.add('active');
            
            renderMenuGrid();

            // Fetch current order if any
            fetch(`../api/admin/get_table_order.php?table_id=${id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.order && data.order.items) {
                        currentBookingId = data.order.booking_id ? parseInt(data.order.booking_id) : null;
                        // Map items to cart
                        posCart = data.order.items.map(i => ({
                            id: parseInt(i.menu_item_id),
                            name: i.name,
                            price: parseInt(i.unit_price),
                            quantity: parseInt(i.quantity)
                        }));
                    } else {
                        posCart = []; // empty
                    }
                    renderCart();
                }).catch(console.error);
        }

        function renderMenuGrid() {
            const search = document.getElementById('posSearch').value.toLowerCase();
            const container = document.getElementById('posMenuList');
            container.innerHTML = '';
            
            globalMenu.forEach(item => {
                if (item.name.toLowerCase().includes(search)) {
                    const div = document.createElement('div');
                    div.className = 'menu-item';
                    div.innerHTML = `
                        <div class="menu-item-name">${escapeHtml(item.name)}</div>
                        <div class="menu-item-price">${formatCurrency(item.price)}</div>
                    `;
                    div.onclick = () => addToCart(item);
                    container.appendChild(div);
                }
            });
        }

        function filterMenu() {
            renderMenuGrid();
        }

        function addToCart(menuItem) {
            const existing = posCart.find(i => i.id === menuItem.id);
            if (existing) {
                existing.quantity++;
            } else {
                posCart.push({
                    id: menuItem.id,
                    name: menuItem.name,
                    price: menuItem.price,
                    quantity: 1
                });
            }
            renderCart();
        }

        function chgQty(id, delta) {
            const item = posCart.find(i => i.id === id);
            if (!item) return;
            item.quantity += delta;
            if (item.quantity <= 0) {
                posCart = posCart.filter(i => i.id !== id);
            }
            renderCart();
        }

        function renderCart() {
            const container = document.getElementById('posCartItems');
            container.innerHTML = '';
            let total = 0;
            
            if(posCart.length === 0) {
                container.innerHTML = '<p style="color:#999; text-align:center; margin-top: 20px;">Bàn chưa gọi món.</p>';
            }
            
            posCart.forEach(item => {
                const itemTotal = item.price * item.quantity;
                total += itemTotal;
                
                const div = document.createElement('div');
                div.className = 'cart-item';
                div.innerHTML = `
                    <div class="cart-item-header">
                        <span>${escapeHtml(item.name)}</span>
                        <span style="color:var(--primary);">${formatCurrency(itemTotal)}</span>
                    </div>
                    <div class="cart-item-actions">
                        <span style="font-size:13px; color:#777;">Đơn giá: ${formatCurrency(item.price)}</span>
                        <div class="cart-qty">
                            <button type="button" class="btn-qty js-qty-minus">-</button>
                            <span>${item.quantity}</span>
                            <button type="button" class="btn-qty js-qty-plus">+</button>
                        </div>
                    </div>
                `;
                div.querySelector('.js-qty-minus').addEventListener('click', () => chgQty(Number(item.id), -1));
                div.querySelector('.js-qty-plus').addEventListener('click', () => chgQty(Number(item.id), 1));
                container.appendChild(div);
            });
            
            document.getElementById('posTotal').textContent = formatCurrency(total);
        }

        function saveOrder() {
            const btn = document.getElementById('btnSaveOrder');
            btn.innerHTML = 'Đang lưu...';
            btn.disabled = true;

            const payload = {
                table_id: currentTableId,
                booking_id: currentBookingId,
                items: posCart.map(i => ({ menu_item_id: i.id, quantity: i.quantity }))
            };

            fetch('../api/admin/save_table_order.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) alert('Đã lưu Order thành công!');
                else alert(data.message);
                loadTableMap();
            })
            .catch(err => { alert('Lỗi kết nối.'); console.error(err); })
            .finally(() => {
                btn.innerHTML = 'Lưu Order';
                btn.disabled = false;
            });
        }

        function emptyTable() {
            if(!confirm('Xác nhận bỏ qua các thay đổi Order và chuyển bàn về TRỐNG?')) return;
            const viewDate = document.getElementById('mapDate').value;
            // Call API to cancel the order properly
            fetch('../api/admin/cancel_table_order.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ table_id: currentTableId, date: viewDate })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Hủy order thành công! Bàn đã trống.');
                    closeModal('posModal');
                    loadTableMap();
                } else {
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(err => { alert('Lỗi kết nối.'); console.error(err); });
        }

        function openCheckoutModal() {
            if (posCart.length === 0) {
                alert("Bàn chưa gọi món nào, không thể thanh toán. Bạn có thể chọn Hủy Order & Về Trống.");
                return;
            }
            let total = posCart.reduce((sum, i) => sum + (i.price * i.quantity), 0);
            
            document.getElementById('checkoutTableName').textContent = currentTableName;
            document.getElementById('checkoutTotal').textContent = formatCurrency(total);
            
            closeModal('posModal');
            document.getElementById('checkoutModal').classList.add('active');
        }

        function processCheckout() {
            const btn = document.getElementById('btnProcessCheckout');
            btn.innerHTML = 'Đang xử lý...';
            btn.disabled = true;

            const method = document.querySelector('input[name="paymentMethod"]:checked').value;
            const viewDate = document.getElementById('mapDate').value;

            fetch('../api/admin/checkout_table.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    table_id: currentTableId,
                    payment_method: method,
                    date: viewDate
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Thanh toán thành công! Bàn đã trống.');
                    closeModal('checkoutModal');
                    loadTableMap();
                } else {
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(err => { alert('Lỗi kết nối.'); console.error(err); })
            .finally(() => {
                btn.innerHTML = 'Xác Nhận Thanh Toán';
                btn.disabled = false;
            });
        }

        // --- BOOKING LIST LOGIC (Retained) ---
        function loadBookings(page) {
            currentPage = page;
            const search = document.getElementById('searchInput').value;
            const status = document.getElementById('statusFilter').value;
            const date = document.getElementById('dateFilter').value;
            
            const tbody = document.querySelector('#bookingsTable tbody');
            tbody.innerHTML = '<tr><td colspan="8">Đang tải...</td></tr>';

            fetch(`../api/admin/get_bookings.php?page=${page}&limit=10&search=${encodeURIComponent(search)}&status=${status}&date=${date}`)
                .then(res => res.json())
                .then(data => {
                    tbody.innerHTML = '';
                    if (!data.success || !data.bookings.length) {
                        tbody.innerHTML = '<tr><td colspan="8">Không tìm thấy lượt đặt bàn nào.</td></tr>';
                        renderPagination(0, 1);
                        return;
                    }

                    data.bookings.forEach(b => {
                        let statusClass = 'res-pending'; let statusText = 'Chờ xác nhận';
                        if (b.status === 'confirmed') { statusClass = 'res-confirmed'; statusText = 'Đã xác nhận'; }
                        if (b.status === 'cancelled') { statusClass = 'res-cancelled'; statusText = 'Đã hủy'; }
                        if (b.status === 'completed') { statusClass = 'res-confirmed'; statusText = 'Hoàn thành'; }

                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>#${escapeHtml(b.id)}</td>
                            <td>${escapeHtml(b.name || 'Khách lẻ')}</td>
                            <td>${escapeHtml(b.phone || '-')}</td>
                            <td>${escapeHtml(b.date)} <br> <span style="font-weight:600;color:#555;">${escapeHtml(b.time)}</span></td>
                            <td><span class="badge" style="background:#e3f2fd;color:#1565c0;">${escapeHtml(b.table_name || 'Chưa xếp')}</span></td>
                            <td>${escapeHtml(b.guests)} người</td>
                            <td><span class="badge ${statusClass}">${statusText}</span></td>
                            <td style="text-align:center;">
                                ${b.status === 'pending' ? 
                                    '<button type="button" data-status="confirmed" class="js-booking-status" style="padding:4px 8px; border:none; background:#2ecc71; color:white; border-radius:4px; cursor:pointer; margin-right:4px;">Nhận</button>' +
                                    '<button type="button" data-status="cancelled" class="js-booking-status" style="padding:4px 8px; border:none; background:#e74c3c; color:white; border-radius:4px; cursor:pointer;">Hủy</button>'
                                    : '<span style="color:#ccc;">-</span>'}
                            </td>
                        `;
                        tr.querySelectorAll('.js-booking-status').forEach(btn => {
                            btn.addEventListener('click', () => updateStatus(Number(b.id), btn.dataset.status));
                        });
                        tbody.appendChild(tr);
                    });

                    renderPagination(data.pagination.total_pages, data.pagination.page);
                }).catch(console.error);
        }

        function renderPagination(totalPages, current) {
            const container = document.getElementById('pagination');
            container.innerHTML = '';
            if(totalPages <= 1) return;
            // Simplified pagination
            for(let i=1; i<=totalPages; i++) {
                const btn = document.createElement('button');
                btn.style.padding = '10px 15px'; btn.style.minHeight = '44px'; btn.style.border = '1px solid #ddd'; btn.style.background = i===current?'#e67e22':'#fff'; btn.style.color = i===current?'#fff':'#333'; btn.style.cursor = 'pointer'; btn.style.borderRadius = '4px'; btn.style.display = 'inline-flex'; btn.style.alignItems = 'center'; btn.style.justifyContent = 'center';
                btn.textContent = i;
                btn.onclick = () => loadBookings(i);
                container.appendChild(btn);
            }
        }

        function updateStatus(id, status) {
            if(!confirm('Bạn chắc chắn thay đổi trạng thái Booking?')) return;
            fetch('../api/admin/update_booking_status.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id, status})
            }).then(res => res.json()).then(d => {
                if(d.success) loadBookings(currentPage);
                else alert(d.message);
            });
        }


document.addEventListener('click', (event) => {
    const action = event.target.closest('[data-action]')?.dataset.action;
    if (action === 'toggle-map') toggleView('map');
    if (action === 'toggle-list') toggleView('list');
    if (action === 'load-table-map') loadTableMap();
    if (action === 'load-bookings') loadBookings(1);
    if (action === 'close-pos-modal') closeModal('posModal');
    if (action === 'save-order') saveOrder();
    if (action === 'open-checkout-modal') openCheckoutModal();
    if (action === 'empty-table') emptyTable();
    if (action === 'close-checkout-modal') closeModal('checkoutModal');
    if (action === 'process-checkout') processCheckout();
});

document.addEventListener('change', (event) => {
    if (event.target.closest('[data-action="load-table-map"]')) {
        loadTableMap();
    }
});

document.addEventListener('keyup', (event) => {
    if (event.target.closest('[data-action="filter-menu"]')) {
        filterMenu();
    }
});
