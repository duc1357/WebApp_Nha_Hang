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
        if (mapContainer) mapContainer.innerHTML = '<p class="text-muted text-center" style="padding:20px;">Vui lòng chọn ngày và giờ.</p>';
        return;
    }
    if (!validateDate(date)) { showToast('Ngày không hợp lệ (phải từ hôm nay)!', 'error'); return; }

    selectedTableId = null;
    clearBookingSelection();
    if (mapContainer) mapContainer.innerHTML = '<div class="spinner"></div> Đang kiểm tra bàn trống...';

    try {
        if (allTablesData.length === 0) allTablesData = await fetchTables();

        const bookedRes  = await fetch(`api/public/get_booked_tables.php?date=${date}&time=${time}`);
        const bookedList = await bookedRes.json();
        renderTableMap(allTablesData, bookedList);
    } catch (err) {
        console.error('Lỗi loadTables:', err);
        if (mapContainer) mapContainer.innerHTML = '<p class="text-danger text-center">Lỗi tải dữ liệu. Vui lòng thử lại.</p>';
    }
};

/**
 * Render sơ đồ bàn theo tầng (tab UI)
 * @param {Array} allTables   Tất cả bàn từ API
 * @param {Array} bookedList  Danh sách ID bàn đã được đặt
 */
window.renderTableMap = function(allTables, bookedList) {
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
        mapContainer.innerHTML = '<p>Không tìm thấy dữ liệu bàn.</p>';
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

    fetch('api/user/book_table.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            name, phone, date, time, guests,
            floor: floor || '',
            table_number: tableName || '',
            table_id: tableId || '',
            has_preorder: window.isPreorderEnabled || false,
            items: window.bookingCart || []
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
   PREORDER LOGIC
   ========================================= */
window.togglePreorderSection = function() {
    const isChecked = document.getElementById('toggle-preorder')?.checked;
    window.isPreorderEnabled = isChecked;
    const section = document.getElementById('preorder-section');
    if (!section) return;

    if (isChecked) {
        section.style.display = 'block';
        const menuList = document.getElementById('booking-menu-list');
        if (menuList && menuList.innerHTML.includes('Đang tải')) loadBookingMenu();
    } else {
        section.style.display = 'none';
        window.bookingCart = [];
        updateBookingCartUI();
    }
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
    const qtyEl = document.getElementById(`bkg-qty-${id}`);
    if (qtyEl) qtyEl.innerText = item.quantity;
    updateBookingCartUI();
};

window.updateBookingCartUI = function() {
    const total   = window.bookingCart.reduce((s, i) => s + i.unit_price * i.quantity, 0);
    const deposit = Math.ceil(total * 0.3);
    const totalEl   = document.getElementById('preorder-total');
    const depositEl = document.getElementById('preorder-deposit');
    if (totalEl)   totalEl.innerText   = formatCurrency(total);
    if (depositEl) depositEl.innerText = formatCurrency(deposit);

    const btn = document.getElementById('btn-submit-booking');
    if (btn && !btn.disabled) {
        btn.innerText = (window.isPreorderEnabled && deposit > 0)
            ? 'Xác Nhận & Thanh Toán Cọc'
            : 'Xác Nhận Đặt Bàn';
    }
};

// Khởi tạo khi DOM sẵn sàng
document.addEventListener('DOMContentLoaded', () => fillBookingForm());
