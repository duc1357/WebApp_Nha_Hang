// js/booking.js – Đặt bàn: load bàn, sơ đồ tầng, submit booking, preorder
// Phụ thuộc: utils.js (showToast, setLoading, validatePhone, validateDate, getCurrentUser)
//            payment.js (showQrModal)
//            menu.js (loadBookingMenu)

'use strict';

/* =========================================
   BOOKING STATE
   ========================================= */
let availableTables = [];
let selectedTableId = null;
let allTablesData   = [];

window.bookingCart      = [];
window.isPreorderEnabled = false;

/* =========================================
   TABLE LOADING & RENDERING
   ========================================= */

/**
 * Lấy danh sách tất cả bàn từ API
 * @returns {Promise<Array>}
 */
async function fetchTables() {
    try {
        const res  = await fetch('api/tables/read.php');
        const data = await res.json();
        return Array.isArray(data) ? data : [];
    } catch (err) {
        console.error('Lỗi tải bàn:', err);
        return [];
    }
}

/**
 * Render hiệu ứng lấp lánh (Shimmer Skeleton) trong lúc tải sơ đồ bàn
 */
window.renderTableShimmer = function(container) {
    if (!container) return;
    container.innerHTML = `
        <div class="table-shimmer-container">
            <div class="table-shimmer-box"></div>
            <div class="table-shimmer-box"></div>
            <div class="table-shimmer-box"></div>
            <div class="table-shimmer-box"></div>
            <div class="table-shimmer-box"></div>
            <div class="table-shimmer-box"></div>
        </div>
    `;
};

/**
 * Entry point: load bàn theo ngày/giờ đã chọn
 */
window.loadTables = async function() {
    const dateInput = document.getElementById('date');
    const timeInput = document.getElementById('time');
    if (!dateInput || !timeInput) return;

    const date = dateInput.value;
    const time = timeInput.value;
    const mapContainer = document.getElementById('table-map-container');

    if (!date || !time) {
        if (mapContainer) renderEmptyState(mapContainer, 'Vui lòng chọn ngày và giờ.');
        return;
    }
    if (!validateDate(date)) { showToast('Ngày không hợp lệ (phải từ hôm nay)!', 'error'); return; }

    selectedTableId = null;
    clearBookingSelection();
    if (mapContainer) renderTableShimmer(mapContainer);

    try {
        if (allTablesData.length === 0) allTablesData = await fetchTables();

        const bookedRes    = await fetch(`api/public/get_booked_tables.php?date=${date}&time=${time}`);
        const responseData = await bookedRes.json();
        const bookedList   = responseData.booked || [];
        const bookedDetails = responseData.details || {};
        
        renderTableMap(allTablesData, bookedList, bookedDetails);
    } catch (err) {
        console.error('Lỗi loadTables:', err);
        if (mapContainer) renderErrorState(mapContainer, 'Lỗi tải dữ liệu. Vui lòng thử lại.');
    }
};

/**
 * Render sơ đồ bàn theo tầng (tab UI)
 * @param {Array} allTables      Tất cả bàn từ API
 * @param {Array} bookedList     Danh sách ID bàn đã được đặt
 * @param {Object} bookedDetails Chi tiết khung giờ bận của từng bàn
 */
window.renderTableMap = function(allTables, bookedList, bookedDetails = {}) {
    const tabsContainer = document.getElementById('floor-tabs');
    const mapContainer  = document.getElementById('table-map-container');
    if (!tabsContainer || !mapContainer) return;

    tabsContainer.innerHTML = '';
    mapContainer.innerHTML  = '';

    // Nhóm bàn theo tầng
    const groups = {};
    allTables.forEach(t => {
        if (!groups[t.floor]) groups[t.floor] = [];
        groups[t.floor].push(t);
    });

    const floorNames = Object.keys(groups).sort();
    if (floorNames.length === 0) {
        renderEmptyState(mapContainer, 'Không tìm thấy dữ liệu bàn.');
        return;
    }

    // Tạo tabs
    floorNames.forEach((floorName, idx) => {
        const btn = document.createElement('button');
        btn.className  = `hall-tab ${idx === 0 ? 'active' : ''}`;
        btn.textContent = `Sảnh ${floorName}`;
        btn.onclick    = () => switchTab(floorName);
        tabsContainer.appendChild(btn);
    });

    // Tạo lưới bàn theo từng tầng
    floorNames.forEach((floorName, idx) => {
        const div = document.createElement('div');
        div.className = `floor-grid ${idx === 0 ? 'active' : ''}`;
        div.id        = `floor-${floorName}`;

        groups[floorName].forEach(t => {
            const isBooked = bookedList.includes(parseInt(t.id));
            let statusClass = 'available';
            if (isBooked) statusClass = 'booked';
            if (selectedTableId && parseInt(selectedTableId) === parseInt(t.id)) statusClass = 'selected';

            const tableBox = document.createElement('div');
            tableBox.className  = `table-box ${statusClass}`;
            tableBox.dataset.id = t.id;
            tableBox.innerHTML  = `<h3>${escapeHTML(t.name)}</h3><p>${escapeHTML(t.capacity || '4')} người</p>`;

            // Gán tooltip bận nếu có
            if (isBooked && bookedDetails[t.id]) {
                tableBox.setAttribute('data-tooltip', bookedDetails[t.id].duration);
            }

            if (!isBooked) {
                tableBox.onclick = () => onTableClick(tableBox, floorName, t.name, t.id);
            }
            div.appendChild(tableBox);
        });

        mapContainer.appendChild(div);
    });
};

/** Chuyển tab tầng */
window.switchTab = function(floorName) {
    document.querySelectorAll('.hall-tab').forEach(btn => {
        btn.classList.toggle('active', btn.textContent.includes(floorName));
    });
    document.querySelectorAll('.floor-grid').forEach(g => g.classList.remove('active'));
    document.getElementById(`floor-${floorName}`)?.classList.add('active');
};

/**
 * Xử lý click chọn/bỏ chọn bàn
 */
window.onTableClick = function(element, floor, tableName, tableId) {
    if (element.classList.contains('selected')) {
        element.classList.replace('selected', 'available');
        clearBookingSelection();
        return;
    }
    document.querySelectorAll('.table-box.selected').forEach(el => {
        el.classList.replace('selected', 'available');
    });
    element.classList.replace('available', 'selected');
    selectedTableId = tableId;
    updateBookingForm(floor, tableName, tableId);
};

/** Cập nhật form khi chọn bàn */
window.updateBookingForm = function(floor, tableName, tableId) {
    document.getElementById('selected-floor').value = floor;
    const tableEl = document.getElementById('selected-table');
    tableEl.value = tableName;
    tableEl.setAttribute('data-id', tableId);

    const date    = document.getElementById('date')?.value;
    const time    = document.getElementById('time')?.value;
    const summary = document.getElementById('selection-summary');
    if (summary) {
        summary.innerHTML = `Đang chọn: <strong style="color:#e67e22">Bàn ${escapeHTML(tableName)} - Sảnh ${escapeHTML(floor)}</strong><br>
                             Thời gian: ${escapeHTML(time)}, Ngày ${escapeHTML(date)}`;
    }
    const btn = document.getElementById('btn-submit-booking');
    if (btn) { btn.disabled = false; btn.textContent = 'Xác Nhận Đặt Bàn'; }
};

/** Reset chọn bàn */
window.clearBookingSelection = function() {
    selectedTableId = null;
    const floorEl = document.getElementById('selected-floor');
    const tableEl = document.getElementById('selected-table');
    if (floorEl) floorEl.value = '';
    if (tableEl) tableEl.value = '';
    const summary = document.getElementById('selection-summary');
    if (summary) summary.textContent = 'Vui lòng chọn bàn...';
    const btn = document.getElementById('btn-submit-booking');
    if (btn) { btn.disabled = true; btn.textContent = 'Vui Lòng Chọn Bàn'; }
};

/* =========================================
   FILL FORM FROM USER INFO
   ========================================= */
window.fillBookingForm = function() {
    const user = getCurrentUser();
    if (!user) return;
    const nameInput  = document.getElementById('name');
    const phoneInput = document.getElementById('phone');
    if (nameInput)  nameInput.value  = user.name  || '';
    if (phoneInput) phoneInput.value = user.phone || '';
};

/* =========================================
   SUBMIT BOOKING
   ========================================= */
window.submitBooking = function() {
    const name      = document.getElementById('name')?.value.trim();
    const phone     = document.getElementById('phone')?.value.trim();
    const date      = document.getElementById('date')?.value.trim();
    const time      = document.getElementById('time')?.value.trim();
    const guests    = document.getElementById('guests')?.value.trim();
    const floor     = document.getElementById('selected-floor')?.value;
    const tableEl   = document.getElementById('selected-table');
    const tableId   = tableEl?.getAttribute('data-id') || '';
    const tableName = tableEl?.value || '';

    const user = getCurrentUser();
    if (!user) {
        showToast('Vui lòng đăng nhập để đặt bàn!', 'error');
        setTimeout(() => window.location.href = 'login.html', 1500);
        return;
    }
    if (!name || !phone || !date || !time || !guests) {
        showToast('Vui lòng điền đầy đủ thông tin đặt bàn!', 'warning');
        return;
    }
    if (!validatePhone(phone)) { showToast('Số điện thoại không hợp lệ!', 'error'); return; }
    if (!validateDate(date))   { showToast('Ngày đặt bàn không hợp lệ!', 'error'); return; }

    const btn = document.getElementById('btn-submit-booking') || document.querySelector('button[type="submit"]');
    setLoading(btn, true);

    // Gửi giỏ hàng rỗng nếu tắt đặt trước (Phương án B)
    const isPreorderChecked = document.getElementById('toggle-preorder')?.checked;
    
    fetch('api/user/book_table.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            name, phone, date, time, guests,
            floor: floor || '',
            table_number: tableName || '',
            table_id: tableId || '',
            has_preorder: isPreorderChecked || false,
            items: isPreorderChecked ? (window.bookingCart || []) : []
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            ['date','time','guests','selected-floor','selected-table'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            document.getElementById('table-selection-area')?.setAttribute('style', 'display:none');
            loadTables();
            if (data.require_payment && data.payUrl) {
                showToast(data.message || 'Vui lòng thanh toán cọc', 'info');
                showQrModal(data.payUrl, data.deposit_amount, 'BKG' + data.booking_id);
            } else {
                showToast(data.message || 'Đặt bàn thành công!', 'success');
            }
        } else {
            showToast(data.message || 'Đặt bàn thất bại', 'error');
        }
    })
    .catch(err => { console.error('Lỗi đặt bàn:', err); showToast('Lỗi kết nối máy chủ.', 'error'); })
    .finally(() => setLoading(btn, false));
};

/* =========================================
   PREORDER LOGIC WITH DRAWER UX (Phương án B)
   ========================================= */
window.togglePreorderSection = function() {
    const isChecked = document.getElementById('toggle-preorder')?.checked;
    window.isPreorderEnabled = isChecked;
    const section = document.getElementById('preorder-section');
    if (!section) return;

    if (isChecked) {
        section.style.display = 'block';
        const drawerList = document.getElementById('drawer-menu-list');
        // Nạp sẵn thực đơn vào Drawer nếu chưa nạp
        if (drawerList && drawerList.innerHTML.includes('Đang tải')) {
            loadBookingMenu();
        }
    } else {
        section.style.display = 'none';
    }
    updateBookingCartUI();
};

window.openPreorderDrawer = function() {
    const drawer = document.getElementById('preorder-drawer');
    if (drawer) {
        drawer.classList.remove('hidden');
        // Kích hoạt animation mượt mà
        setTimeout(() => drawer.classList.add('active'), 10);
        
        // Đảm bảo dữ liệu mới nhất được nạp
        const drawerList = document.getElementById('drawer-menu-list');
        if (drawerList && drawerList.innerHTML.includes('Đang tải')) {
            loadBookingMenu();
        } else {
            // Đồng bộ số lượng hiện tại nếu giỏ hàng thay đổi bên ngoài
            syncDrawerQuantities();
        }
    }
};

window.closePreorderDrawer = function() {
    const drawer = document.getElementById('preorder-drawer');
    if (drawer) {
        drawer.classList.remove('active');
        // Đợi kết thúc animation trượt mới ẩn hẳn overlay
        setTimeout(() => drawer.classList.add('hidden'), 400);
    }
};

window.filterDrawerMenu = function() {
    const query = document.getElementById('drawer-search')?.value.toLowerCase().trim() || '';
    const items = document.querySelectorAll('.drawer-menu-item');
    
    items.forEach(item => {
        const name = item.dataset.name || '';
        const desc = item.dataset.desc || '';
        if (name.includes(query) || desc.includes(query)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
};

window.syncDrawerQuantities = function() {
    // Reset toàn bộ số lượng hiển thị trong Drawer về 0
    document.querySelectorAll('.drawer-qty-value').forEach(el => el.innerText = '0');
    
    // Cập nhật lại số lượng theo giỏ hàng hiện có
    window.bookingCart.forEach(item => {
        const el = document.getElementById(`bkg-qty-${item.menu_item_id}`);
        if (el) el.innerText = item.quantity;
    });
};

window.updateBookingItem = function(id, name, price, change) {
    let item = window.bookingCart.find(i => i.menu_item_id == id);
    if (!item) {
        if (change < 0) return;
        item = { menu_item_id: id, name, unit_price: price, quantity: 1 };
        window.bookingCart.push(item);
    } else {
        item.quantity += change;
        if (item.quantity <= 0) {
            window.bookingCart = window.bookingCart.filter(i => i.menu_item_id != id);
            item.quantity = 0;
        }
    }
    
    // Cập nhật số lượng hiển thị trong Drawer
    const qtyEl = document.getElementById(`bkg-qty-${id}`);
    if (qtyEl) qtyEl.innerText = item.quantity;
    
    updateBookingCartUI();
};

window.updateBookingCartUI = function() {
    const total   = window.bookingCart.reduce((s, i) => s + i.unit_price * i.quantity, 0);
    const deposit = Math.ceil(total * 0.3);
    const count   = window.bookingCart.reduce((s, i) => s + i.quantity, 0);

    // 1. Cập nhật tóm tắt trong Drawer
    const drawerCount = document.getElementById('drawer-selected-count');
    const drawerTotal = document.getElementById('drawer-total-amount');
    if (drawerCount) drawerCount.innerText = `${count} món`;
    if (drawerTotal) drawerTotal.innerText = formatCurrency(total);

    // 2. Cập nhật tóm tắt trên form chính
    const totalEl   = document.getElementById('preorder-total');
    const depositEl = document.getElementById('preorder-deposit');
    if (totalEl)   totalEl.innerText   = formatCurrency(total);
    if (depositEl) depositEl.innerText = formatCurrency(deposit);

    // 3. Render danh sách tóm tắt các món đã chọn trong form chính
    const summaryList = document.getElementById('booking-selected-summary-list');
    if (summaryList) {
        if (window.bookingCart.length === 0) {
            summaryList.innerHTML = `<p class="text-muted" style="font-size: 0.9rem; margin: 0;">Chưa chọn món nào. Nhấn nút "Chọn món ăn" để bắt đầu.</p>`;
        } else {
            summaryList.innerHTML = window.bookingCart.map(item => `
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f3f5; font-size: 0.9rem;">
                    <span style="font-weight: 500; color: #34495e;">${escapeHTML(item.name)} <span style="color: var(--primary); font-weight: bold; margin-left: 4px;">x${item.quantity}</span></span>
                    <span style="color: #2c3e50; font-weight: 600;">${formatCurrency(item.unit_price * item.quantity)}</span>
                </div>
            `).join('');
        }
    }

    // 4. Đồng bộ nút bấm submit
    const btn = document.getElementById('btn-submit-booking');
    if (btn && !btn.disabled) {
        const isPreorderChecked = document.getElementById('toggle-preorder')?.checked;
        btn.innerText = (isPreorderChecked && deposit > 0)
            ? 'Xác Nhận & Thanh Toán Cọc'
            : 'Xác Nhận Đặt Bàn';
    }
};

// Khởi tạo khi DOM sẵn sàng
document.addEventListener('DOMContentLoaded', () => {
    fillBookingForm();

    const now = new Date();
    const dateInput = document.getElementById('date');
    const timeInput = document.getElementById('time');

    if (dateInput) dateInput.valueAsDate = now;
    if (timeInput) {
        now.setHours(now.getHours() + 1);
        const hours = String(now.getHours()).padStart(2, '0');
        timeInput.value = `${hours}:00`;
    }

    if (typeof loadTables === 'function') {
        loadTables();
    }

    document.getElementById('date')?.addEventListener('change', loadTables);
    document.getElementById('time')?.addEventListener('change', loadTables);
    document.getElementById('toggle-preorder')?.addEventListener('change', togglePreorderSection);
    document.getElementById('drawer-search')?.addEventListener('input', filterDrawerMenu);
    document.getElementById('booking-form')?.addEventListener('submit', (event) => {
        event.preventDefault();
        submitBooking();
    });
    document.getElementById('preorder-drawer')?.addEventListener('click', (event) => {
        if (event.target === event.currentTarget) {
            closePreorderDrawer();
        }
    });
});

document.addEventListener('click', (event) => {
    const action = event.target.closest('[data-action]')?.dataset.action;
    if (action === 'open-preorder-drawer') {
        openPreorderDrawer();
    }
    if (action === 'close-preorder-drawer') {
        closePreorderDrawer();
    }
});
