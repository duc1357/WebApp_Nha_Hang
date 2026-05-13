<?php
require_once 'auth_check.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="../photo/favicon.png" type="image/png">
    <meta charset="UTF-8">
    <title>Quản Lý Bàn & Đặt Bàn - Dượng Bầu Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #e67e22; --text-main: #2c3e50; --bg: #f4f6f9; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Outfit', sans-serif; background: var(--bg); color: var(--text-main); display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 250px; background: white; border-right: 1px solid #eee; display: flex; flex-direction: column; padding: 20px; position: fixed; height: 100%; top:0; left:0; z-index: 100;}
        .brand { font-size: 20px; font-weight: 700; color: var(--primary); margin-bottom: 40px; display: flex; align-items: center; gap: 10px; }
        .nav-item { padding: 12px; min-height: 44px; display: flex; align-items: center; margin-bottom: 4px; color: #7f8c8d; text-decoration: none; border-radius: 8px; font-weight: 500; }
        .nav-item:hover, .nav-item.active { background: #fff0e6; color: var(--primary); }
        .main-content { margin-left: 250px; padding: 30px; width: 100%; }

        /* Filters */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .filters { display: flex; gap: 12px; background: white; padding: 16px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
        .form-control { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; outline: none; }
        .btn-search { background: var(--primary); color: white; border: none; padding: 10px 20px; min-height: 44px; border-radius: 6px; cursor: pointer; font-weight: 600; }

        /* Table */
        .table-container { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f1f1f1; }
        th { background: #fafafa; font-weight: 600; color: #7f8c8d; }
        .badge { padding: 4px 10px; border-radius: 99px; font-size: 12px; font-weight: 600; }
        .res-pending { background: #fff3cd; color: #856404; }
        .res-confirmed { background: #d4edda; color: #155724; }
        .res-cancelled { background: #f8d7da; color: #721c24; }

        /* TABLE MAP STYLES */
        .map-section { display: none; margin-top: 20px; }
        .floor-container { background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
        .floor-title { font-size: 18px; font-weight: 700; margin-bottom: 15px; color: var(--text-main); border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .table-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 15px; }
        .table-item { 
            border: 2px solid #ddd; border-radius: 12px; padding: 15px; text-align: center; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s, background-color 0.2s, border-color 0.2s; background: #fff;
            display: flex; flex-direction: column; justify-content: center; align-items: center; height: 110px; position: relative;
        }
        .table-item:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .table-name { font-weight: 700; font-size: 18px; margin-bottom: 8px; }
        .table-cap { font-size: 12px; color: #7f8c8d; }
        
        /* Status Colors */
        .table-item.available { border-color: #27ae60; background: #eafaf1; color: #27ae60; }
        .table-item.occupied { border-color: #e67e22; background: #fdf2e9; color: #e67e22; }

        /* MODALS */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width:100%; height:100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items:center; justify-content:center; }
        .modal-overlay.active { display: flex; }
        .modal-content { background: #fff; border-radius: 12px; padding: 24px; position: relative; max-width: 900px; width: 95%; max-height: 90vh; display: flex; flex-direction: column; }
        .modal-close { position: absolute; right: 20px; top: 20px; font-size: 28px; cursor: pointer; color: #7f8c8d; line-height: 1; }
        
        /* POS Layout */
        .pos-container { display: flex; gap: 20px; margin-top: 15px; height: 60vh; overflow: hidden; }
        .pos-menu { flex: 2; border-right: 1px solid #eee; padding-right: 20px; display: flex; flex-direction: column; }
        .pos-cart { flex: 1.2; display: flex; flex-direction: column; }
        
        .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; overflow-y: auto; padding-right: 5px; flex: 1; align-content: start; }
        .menu-item { border: 1px solid #eee; border-radius: 8px; padding: 10px; cursor: pointer; text-align: center; background: #fdfdfd; transition: border-color 0.1s, box-shadow 0.1s; min-height: 44px; display: flex; flex-direction: column; justify-content: center; }
        .menu-item:hover { border-color: var(--primary);  box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .menu-item-name { font-weight: 600; font-size: 14px; margin-bottom: 5px; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .menu-item-price { color: var(--primary); font-size: 13px; font-weight: bold; }
        
        .cart-items { flex: 1; overflow-y: auto; margin-bottom: 10px; padding-right: 5px; }
        .cart-item { display: flex; flex-direction: column; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px dashed #eee; }
        .cart-item-header { display: flex; justify-content: space-between; font-weight: 600; font-size: 14px; margin-bottom: 5px; }
        .cart-item-actions { display: flex; justify-content: space-between; align-items: center; }
        .cart-qty { display: flex; align-items: center; gap: 8px; }
        .btn-qty { width: 44px; height: 44px; border: 1px solid #ddd; background: #fff; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-qty:hover { background: #eee; }
        
        .checkout-details { margin-top: 10px; border-top: 2px solid #eee; padding-top: 15px; font-size: 20px; font-weight: bold; text-align: right; color: #d35400; }
        .btn-pos { padding: 14px; min-height: 44px; width: 100%; background: var(--primary); color: #fff; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; margin-top: 10px; font-size: 15px; transition: filter 0.2s; }
        .btn-pos:hover { filter: brightness(1.05); }
        .btn-pos.secondary { background: #27ae60; }
        .btn-pos.danger { background: #e74c3c; }
        
        /* Small utilities */
        .loading-spinner { border: 3px solid rgba(0,0,0,0.1); border-left-color: var(--primary); border-radius: 50%; width: 24px; height: 24px; animation: spin 1s linear infinite; margin: 20px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2 style="font-size: 24px;">Quản Lý Bàn (POS) & Đặt Bàn</h2>
            <div>
                <button onclick="toggleView('map')" class="btn-search" style="background:#3498db; margin-right: 8px;">Sơ đồ bàn (POS)</button>
                <button onclick="toggleView('list')" class="btn-search" style="background:#2ecc71;">Danh sách Khách hẹn</button>
            </div>
        </div>

        <!-- MAP VIEW (POS) -->
        <div id="mapView" class="map-section" style="display:block;">
            <div style="margin-bottom:20px; display:flex; gap:10px; align-items:center;">
                <label>Xem sơ đồ bàn ngày:</label>
                <input type="date" id="mapDate" class="form-control" value="<?php echo date('Y-m-d'); ?>" onchange="loadTableMap()">
                <button onclick="loadTableMap()" class="btn-search">Làm mới</button>
                <span id="posLoading" style="display:none; color: #7f8c8d; font-size: 14px; margin-left: 10px;">Đang xử lý...</span>
            </div>
            <div id="mapContent">Đang tải sơ đồ...</div>
        </div>

        <!-- LIST VIEW -->
        <div id="listView" style="display:none;">
            <div class="filters">
                <input type="text" id="searchInput" class="form-control" placeholder="Tên khách, SĐT..." style="width: 250px;">
                <input type="date" id="dateFilter" class="form-control">
                <select id="statusFilter" class="form-control">
                    <option value="">Tất cả trạng thái</option>
                    <option value="pending">Chờ xác nhận</option>
                    <option value="confirmed">Đã xác nhận</option>
                    <option value="cancelled">Đã hủy</option>
                </select>
                <button onclick="loadBookings(1)" class="btn-search">Tìm kiếm</button>
            </div>
            <div class="table-container">
                <table id="bookingsTable">
                    <thead>
                        <tr>
                            <th>ID</th><th>Khách hàng</th><th>SĐT</th><th>Ngày & Giờ</th><th>Bàn</th><th>Khách</th><th>Trạng thái</th><th style="text-align:center;">Hành động</th>
                        </tr>
                    </thead>
                    <tbody><tr><td colspan="8">Đang tải...</td></tr></tbody>
                </table>
                <div class="pagination" id="pagination" style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px;"></div>
            </div>
        </div> <!-- End ListView -->

    </div>

    <!-- POS MODAL -->
    <div class="modal-overlay" id="posModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('posModal')">&times;</span>
            <h2 id="posTableTitle" style="color: var(--primary);">Order - Bàn</h2>
            
            <div class="pos-container">
                <!-- Left: Menu Items -->
                <div class="pos-menu">
                    <input type="text" id="posSearch" class="form-control" placeholder="Tìm món ăn..." onkeyup="filterMenu()" style="margin-bottom: 15px;">
                    <div class="menu-grid" id="posMenuList"></div>
                </div>
                
                <!-- Right: Cart -->
                <div class="pos-cart">
                    <h3 style="font-size: 16px; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 8px;">Danh sách gọi món</h3>
                    <div class="cart-items" id="posCartItems"></div>
                    <div class="checkout-details">
                        Tổng: <span id="posTotal">0đ</span>
                    </div>
                    <!-- Actons -->
                    <button class="btn-pos" onclick="saveOrder()" id="btnSaveOrder">Lưu Order</button>
                    <button class="btn-pos secondary" onclick="openCheckoutModal()">Thanh Toán & Trả Bàn</button>
                    <button class="btn-pos danger" onclick="emptyTable()">Hủy Order & Về Trống</button>
                </div>
            </div>
        </div>
    </div>

    <!-- CHECKOUT MODAL -->
    <div class="modal-overlay" id="checkoutModal">
        <div class="modal-content" style="max-width: 450px; text-align: center;">
            <span class="modal-close" onclick="closeModal('checkoutModal')">&times;</span>
            <h2>Thanh Toán Bàn <span id="checkoutTableName"></span></h2>
            <p style="margin: 20px 0; font-size: 32px; font-weight: bold; color: var(--primary);" id="checkoutTotal">0đ</p>
            
            <div style="text-align: left; margin-bottom: 25px; background: #f9f9f9; padding: 15px; border-radius: 8px;">
                <label style="font-weight: bold; display:block; margin-bottom: 10px;">Phương thức thanh toán:</label>
                <label style="margin-right: 20px; font-size: 16px; cursor: pointer;">
                    <input type="radio" name="paymentMethod" value="cash" checked> Tiền mặt
                </label>
                <label style="font-size: 16px; cursor: pointer;">
                    <input type="radio" name="paymentMethod" value="bank_transfer"> Chuyển khoản QR
                </label>
            </div>
            
            <button class="btn-pos" onclick="processCheckout()" id="btnProcessCheckout" style="font-size: 16px; padding: 16px;">Xác Nhận Thanh Toán</button>
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
    </script>
</body>
</html>
