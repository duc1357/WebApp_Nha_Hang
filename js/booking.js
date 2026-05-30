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

function populateBookingTimeSlots() {
    const timeInput = document.getElementById('time');
    const timeOptions = document.getElementById('booking-time-options');
    if (!timeInput || !timeOptions || timeInput.dataset.slotsReady === 'true') return;

    const fragment = document.createDocumentFragment();
    for (let hour = 8; hour <= 22; hour += 1) {
        const value = `${String(hour).padStart(2, '0')}:00`;
        const option = document.createElement('option');
        option.value = value;
        fragment.appendChild(option);
    }

    timeOptions.appendChild(fragment);
    timeInput.dataset.slotsReady = 'true';
}

function normalizeBookingTimeInput() {
    const timeInput = document.getElementById('time');
    if (!timeInput) return;

    const raw = timeInput.value.trim().replace(/[hH]/g, ':');
    if (!raw) return;

    const match = raw.match(/^(\d{1,2})(?::?(\d{2}))?$/);
    if (!match) return;

    const hour = Number(match[1]);
    const minute = Number(match[2] ?? '00');
    if (hour < 0 || hour > 23 || minute < 0 || minute > 59) return;

    timeInput.value = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
}

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

    // Kiểm tra giờ đặt bàn trong quá khứ nếu chọn ngày hôm nay
    const nowObj = new Date();
    const yearStr = nowObj.getFullYear();
    const monthStr = String(nowObj.getMonth() + 1).padStart(2, '0');
    const dayStr = String(nowObj.getDate()).padStart(2, '0');
    const todayDateStr = `${yearStr}-${monthStr}-${dayStr}`;
    
    if (date === todayDateStr) {
        const currentHourStr = String(nowObj.getHours()).padStart(2, '0');
        const currentMinStr = String(nowObj.getMinutes()).padStart(2, '0');
        const currentTimeStr = `${currentHourStr}:${currentMinStr}`;
        if (time <= currentTimeStr) {
            showToast('Giờ đặt bàn phải lớn hơn giờ hiện tại!', 'error');
            return;
        }
    }

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
        
        // Khởi động đồng bộ UI ban đầu nhưng chưa vẽ summary list để tránh lag
        updateBookingCartUI(false);
    }
};

window.closePreorderDrawer = function() {
    const drawer = document.getElementById('preorder-drawer');
    if (drawer) {
        drawer.classList.remove('active');
        // Đợi kết thúc animation trượt mới ẩn hẳn overlay
        setTimeout(() => drawer.classList.add('hidden'), 400);
        
        // CHỈ RENDER DANH SÁCH SUMMARY LẦN DUY NHẤT KHI ĐÓNG DRAWER!
        updateBookingCartUI(true);
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
    
    // Cập nhật số lượng hiển thị trong Drawer lập tức (cực kỳ nhẹ)
    const qtyEl = document.getElementById(`bkg-qty-${id}`);
    if (qtyEl && qtyEl.innerText !== String(item.quantity)) {
        qtyEl.innerText = item.quantity;
    }
    
    // Chỉ cập nhật dữ liệu và text giỏ hàng Drawer, trì hoãn render danh sách summary bên ngoài
    updateBookingCartUI(false);
};

window.updateBookingCartUI = function(updateSummaryList = false) {
    const total   = window.bookingCart.reduce((s, i) => s + i.unit_price * i.quantity, 0);
    const deposit = Math.ceil(total * 0.3);
    const count   = window.bookingCart.reduce((s, i) => s + i.quantity, 0);

    // 1. Cập nhật tóm tắt trong Drawer (tác vụ nhẹ trên DOM hiện tại)
    const drawerCount = document.getElementById('drawer-selected-count');
    const drawerTotal = document.getElementById('drawer-total-amount');
    if (drawerCount) {
        const newCountText = `${count} món`;
        if (drawerCount.innerText !== newCountText) {
            drawerCount.innerText = newCountText;
        }
    }
    if (drawerTotal) {
        const newTotalText = formatCurrency(total);
        if (drawerTotal.innerText !== newTotalText) {
            drawerTotal.innerText = newTotalText;
        }
    }

    // 2. Cập nhật tóm tắt số liệu trên form chính (tác vụ nhẹ, chỉ cập nhật text)
    const totalEl   = document.getElementById('preorder-total');
    const depositEl = document.getElementById('preorder-deposit');
    if (totalEl) {
        const newTotalText = formatCurrency(total);
        if (totalEl.innerText !== newTotalText) {
            totalEl.innerText = newTotalText;
        }
    }
    if (depositEl) {
        const newDepositText = formatCurrency(deposit);
        if (depositEl.innerText !== newDepositText) {
            depositEl.innerText = newDepositText;
        }
    }

    // 3. CHỈ RENDER DANH SÁCH TÓM TẮT MÓN ĂN KHI CÓ YÊU CẦU (KHI ĐÓNG DRAWER)
    if (updateSummaryList) {
        const summaryList = document.getElementById('booking-selected-summary-list');
        if (summaryList) {
            if (window.bookingCart.length === 0) {
                summaryList.innerHTML = `<p class="text-muted" style="font-size: 0.85rem; margin: 0;">Chưa chọn món nào. Nhấn nút "Chọn món ăn" để bắt đầu.</p>`;
            } else {
                summaryList.innerHTML = window.bookingCart.map(item => `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid var(--cream-dark); font-size: 0.85rem;">
                        <span style="font-weight: 500; color: var(--text-dark);">${escapeHTML(item.name)} <span style="color: var(--primary); font-weight: bold; margin-left: 4px;">x${item.quantity}</span></span>
                        <span style="color: var(--text-dark); font-weight: 600;">${formatCurrency(item.unit_price * item.quantity)}</span>
                    </div>
                `).join('');
            }
        }
    }

    // 4. Đồng bộ nút bấm submit
    const btn = document.getElementById('btn-submit-booking');
    if (btn) {
        const isPreorderChecked = document.getElementById('toggle-preorder')?.checked;
        const newText = (isPreorderChecked && deposit > 0)
            ? `Đặt Bàn & Đặt Cọc Món (${formatCurrency(deposit)})`
            : 'Xác Nhận Đặt Bàn';
        if (btn.textContent !== newText) {
            btn.textContent = newText;
        }
    }
};

// Khởi tạo khi DOM sẵn sàng
document.addEventListener('DOMContentLoaded', () => {
    fillBookingForm();
    populateBookingTimeSlots();

    const now = new Date();
    const dateInput = document.getElementById('date');
    const timeInput = document.getElementById('time');

    if (dateInput) dateInput.valueAsDate = now;
    if (timeInput) {
        now.setHours(now.getHours() + 1);
        const suggestedHour = Math.min(22, Math.max(8, now.getHours()));
        const hours = String(suggestedHour).padStart(2, '0');
        timeInput.value = `${hours}:00`;
    }

    if (typeof loadTables === 'function') {
        loadTables();
    }

    document.getElementById('date')?.addEventListener('change', () => {
        renderTimeSlots();
        loadTables();
    });
    document.getElementById('time')?.addEventListener('change', loadTables);
    document.getElementById('time')?.addEventListener('blur', normalizeBookingTimeInput);
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

// =========================================================================
// CUSTOM PREMIUM DROPDOWNS CONTROL (CSP Compliant)
// =========================================================================
let calendarYear = new Date().getFullYear();
let calendarMonth = new Date().getMonth();

// Định dạng hiển thị ngày trên trigger
function updateDateTriggerText(dateStr) {
    const triggerVal = document.getElementById('date-trigger-value');
    if (!triggerVal) return;

    if (!dateStr) {
        triggerVal.textContent = 'Chọn ngày đặt...';
        return;
    }

    const date = new Date(dateStr);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const compareDate = new Date(date);
    compareDate.setHours(0, 0, 0, 0);

    const daysOfWeek = ['Chủ Nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
    const dayName = daysOfWeek[date.getDay()];
    
    const formattedDate = `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}/${date.getFullYear()}`;

    if (compareDate.getTime() === today.getTime()) {
        triggerVal.textContent = `Hôm nay, ${formattedDate}`;
    } else {
        triggerVal.textContent = `${dayName}, ${formattedDate}`;
    }
}

// Xử lý chuyển đổi tháng
function changeMonth(direction) {
    calendarMonth += direction;
    if (calendarMonth < 0) {
        calendarMonth = 11;
        calendarYear--;
    } else if (calendarMonth > 11) {
        calendarMonth = 0;
        calendarYear++;
    }
    renderCustomCalendar(calendarYear, calendarMonth);
}

// Chọn một ngày trên bộ lịch
function selectCalendarDate(year, month, day) {
    const formattedDate = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    const dateInput = document.getElementById('date');
    if (dateInput) {
        dateInput.value = formattedDate;
        dateInput.dispatchEvent(new Event('change'));
    }
    
    updateDateTriggerText(formattedDate);
    document.getElementById('custom-date-dropdown')?.classList.remove('active');
    renderCustomCalendar(year, month);
}

// Vẽ bộ lịch custom
function renderCustomCalendar(year, month) {
    const calendarMenu = document.querySelector('#custom-date-dropdown .custom-dropdown-menu');
    if (!calendarMenu) return;

    calendarMenu.innerHTML = '';

    const calendarContainer = document.createElement('div');
    calendarContainer.className = 'calendar-container';

    // 1. Header (Month/Year & nav)
    const header = document.createElement('div');
    header.className = 'calendar-header';

    const title = document.createElement('div');
    title.className = 'calendar-title';
    title.textContent = `Tháng ${String(month + 1).padStart(2, '0')} / ${year}`;

    const prevBtn = document.createElement('button');
    prevBtn.type = 'button';
    prevBtn.className = 'calendar-nav-btn';
    prevBtn.innerHTML = '◀';
    prevBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        changeMonth(-1);
    });

    const nextBtn = document.createElement('button');
    nextBtn.type = 'button';
    nextBtn.className = 'calendar-nav-btn';
    nextBtn.innerHTML = '▶';
    nextBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        changeMonth(1);
    });

    header.appendChild(prevBtn);
    header.appendChild(title);
    header.appendChild(nextBtn);
    calendarContainer.appendChild(header);

    // 2. Weekdays Header
    const weekdays = document.createElement('div');
    weekdays.className = 'calendar-weekdays';
    const daysName = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
    daysName.forEach(name => {
        const dayNameDiv = document.createElement('div');
        dayNameDiv.textContent = name;
        weekdays.appendChild(dayNameDiv);
    });
    calendarContainer.appendChild(weekdays);

    // 3. Days Grid
    const daysGrid = document.createElement('div');
    daysGrid.className = 'calendar-days';

    const firstDay = new Date(year, month, 1);
    let startDayIndex = firstDay.getDay() - 1; 
    if (startDayIndex < 0) startDayIndex = 6; // Chủ Nhật là index 6

    const totalDays = new Date(year, month + 1, 0).getDate();
    const prevMonthTotalDays = new Date(year, month, 0).getDate();

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // Render padding days từ tháng trước
    for (let i = startDayIndex - 1; i >= 0; i--) {
        const dayDiv = document.createElement('div');
        dayDiv.className = 'calendar-day other-month disabled';
        dayDiv.textContent = prevMonthTotalDays - i;
        daysGrid.appendChild(dayDiv);
    }

    // Render ngày tháng này
    const dateInput = document.getElementById('date');
    const selectedDateStr = dateInput ? dateInput.value : '';
    let selectedDate = null;
    if (selectedDateStr) {
        selectedDate = new Date(selectedDateStr);
        selectedDate.setHours(0, 0, 0, 0);
    }

    for (let day = 1; day <= totalDays; day++) {
        const dayDiv = document.createElement('div');
        dayDiv.className = 'calendar-day';
        dayDiv.textContent = day;

        const thisDate = new Date(year, month, day);
        thisDate.setHours(0, 0, 0, 0);

        if (thisDate < today) {
            dayDiv.classList.add('disabled');
        } else {
            const now = new Date();
            if (thisDate.getDate() === now.getDate() && 
                thisDate.getMonth() === now.getMonth() && 
                thisDate.getFullYear() === now.getFullYear()) {
                dayDiv.classList.add('today');
            }

            if (selectedDate && thisDate.getTime() === selectedDate.getTime()) {
                dayDiv.classList.add('selected');
            }

            dayDiv.addEventListener('click', (e) => {
                e.stopPropagation();
                selectCalendarDate(year, month, day);
            });
        }

        daysGrid.appendChild(dayDiv);
    }

    calendarContainer.appendChild(daysGrid);
    calendarMenu.appendChild(calendarContainer);
}

// Vẽ/cập nhật danh sách các khung giờ đặt bàn, tự động ẩn mờ các giờ trong quá khứ nếu chọn ngày hôm nay
function renderTimeSlots() {
    const timeMenu = document.querySelector('#custom-time-dropdown .custom-dropdown-menu');
    const timeInput = document.getElementById('time-input');
    const rawTimeHidden = document.getElementById('time');
    const dateInput = document.getElementById('date');
    if (!timeMenu) return;

    timeMenu.innerHTML = '';

    const selectedDateStr = dateInput ? dateInput.value : '';
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const todayStr = `${year}-${month}-${day}`;

    const isToday = selectedDateStr === todayStr;
    const currentHour = now.getHours();
    const currentMinute = now.getMinutes();
    const currentTimeStr = `${String(currentHour).padStart(2, '0')}:${String(currentMinute).padStart(2, '0')}`;

    let currentTime = rawTimeHidden?.value || '18:00';
    let isCurrentTimeValid = true;

    // Kiểm tra xem giờ hiện tại đang chọn có bị quá giờ hay không (nếu là hôm nay)
    if (isToday && currentTime <= currentTimeStr) {
        isCurrentTimeValid = false;
    }

    let firstValidTimeStr = null;

    for (let hour = 8; hour <= 22; hour++) {
        const timeVal = `${String(hour).padStart(2, '0')}:00`;
        const item = document.createElement('div');
        
        let isDisabled = false;
        if (isToday && timeVal <= currentTimeStr) {
            isDisabled = true;
        }

        if (!isDisabled && !firstValidTimeStr) {
            firstValidTimeStr = timeVal;
        }

        if (isDisabled) {
            item.className = 'custom-dropdown-item disabled';
            item.textContent = timeVal;
            // Thuộc tính style tăng độ an toàn trực quan
            item.style.opacity = '0.4';
            item.style.pointerEvents = 'none';
            item.style.cursor = 'not-allowed';
        } else {
            item.className = `custom-dropdown-item ${timeVal === currentTime ? 'selected' : ''}`;
            item.dataset.value = timeVal;
            item.textContent = timeVal;
        }
        timeMenu.appendChild(item);
    }

    // Nếu giờ đang chọn bị quá giờ, tự động đổi sang khung giờ hợp lệ đầu tiên tiếp theo
    if (isToday && !isCurrentTimeValid) {
        const newTime = firstValidTimeStr || '22:00'; // Fallback nếu đã hết giờ đặt bàn trong ngày
        if (rawTimeHidden) {
            rawTimeHidden.value = newTime;
            rawTimeHidden.dispatchEvent(new Event('change'));
        }
        if (timeInput) {
            timeInput.value = newTime;
        }
        
        // Cập nhật lại class selected
        const items = timeMenu.querySelectorAll('.custom-dropdown-item');
        items.forEach(item => {
            if (item.dataset.value === newTime) {
                item.classList.add('selected');
            } else {
                item.classList.remove('selected');
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // 1. Khởi tạo danh sách giờ trong dropdown Giờ đặt
    const timeInput = document.getElementById('time-input');
    const rawTimeHidden = document.getElementById('time');

    // 2. Quản lý việc Đóng/Mở các Custom Dropdowns
    const dropdowns = document.querySelectorAll('.custom-dropdown');
    dropdowns.forEach(dropdown => {
        const trigger = dropdown.querySelector('.custom-dropdown-trigger');
        trigger.addEventListener('click', (e) => {
            // Nếu click vào chính input giờ thì để sự kiện click của input xử lý riêng
            if (e.target.id === 'time-input') return;
            
            e.stopPropagation();
            dropdowns.forEach(other => {
                if (other !== dropdown) other.classList.remove('active');
            });
            dropdown.classList.toggle('active');
        });
    });

    document.addEventListener('click', () => {
        let hasActive = false;
        for (let i = 0; i < dropdowns.length; i++) {
            if (dropdowns[i].classList.contains('active')) {
                hasActive = true;
                break;
            }
        }
        if (hasActive) {
            dropdowns.forEach(dropdown => dropdown.classList.remove('active'));
        }
    });

    // 3. Xử lý sự kiện click chọn giờ trên dropdown Giờ đặt
    document.querySelector('#custom-time-dropdown')?.addEventListener('click', (e) => {
        const item = e.target.closest('.custom-dropdown-item');
        if (!item) return;

        const val = item.dataset.value;
        document.querySelector('#custom-time-dropdown .custom-dropdown-item.selected')?.classList.remove('selected');
        item.classList.add('selected');
        
        if (timeInput) timeInput.value = val;
        if (rawTimeHidden) {
            rawTimeHidden.value = val;
            rawTimeHidden.dispatchEvent(new Event('change'));
        }
        
        document.getElementById('custom-time-dropdown')?.classList.remove('active');
    });

    // 4. Lập trình logic gõ nhập tay và kiểm tra hợp lệ cho ô nhập Giờ đặt
    if (timeInput) {
        timeInput.addEventListener('click', (e) => {
            e.stopPropagation();
            const parentDropdown = timeInput.closest('.custom-dropdown');
            dropdowns.forEach(other => {
                if (other !== parentDropdown) other.classList.remove('active');
            });
            parentDropdown.classList.add('active');
        });

        timeInput.addEventListener('input', () => {
            let val = timeInput.value.trim();
            
            // Tự động thêm dấu hai chấm khi gõ 2 số đầu tiên
            if (val.length === 2 && !val.includes(':') && /^\d+$/.test(val)) {
                timeInput.value = val + ':';
                val = timeInput.value;
            }

            // Regex kiểm tra định dạng giờ hợp lệ: HH:MM hoặc H:MM
            const timeRegex = /^([01]?[0-9]|2[0-3]):[0-5][0-9]$/;
            if (timeRegex.test(val)) {
                // Chuẩn hóa ví dụ 8:30 thành 08:30
                if (val.length === 4 && val.charAt(1) === ':') {
                    val = '0' + val;
                    timeInput.value = val;
                }
                
                if (rawTimeHidden && rawTimeHidden.value !== val) {
                    rawTimeHidden.value = val;
                    rawTimeHidden.dispatchEvent(new Event('change'));
                }

                // Cập nhật highlight trong dropdown
                document.querySelectorAll('#custom-time-dropdown .custom-dropdown-item').forEach(item => {
                    item.classList.toggle('selected', item.dataset.value === val);
                });
            }
        });

        timeInput.addEventListener('blur', () => {
            const val = timeInput.value.trim();
            const timeRegex = /^([01]?[0-9]|2[0-3]):[0-5][0-9]$/;
            if (!timeRegex.test(val)) {
                // Nếu sai định dạng, khôi phục lại giá trị hợp lệ gần nhất trong trường ẩn
                timeInput.value = rawTimeHidden?.value || '18:00';
            } else {
                let finalVal = val;
                if (finalVal.length === 4 && finalVal.charAt(1) === ':') {
                    finalVal = '0' + finalVal;
                }
                timeInput.value = finalVal;
                if (rawTimeHidden && rawTimeHidden.value !== finalVal) {
                    rawTimeHidden.value = finalVal;
                    rawTimeHidden.dispatchEvent(new Event('change'));
                }
            }
        });
    }

    // 5. Xử lý sự kiện click chọn số khách trên dropdown Số khách
    document.querySelector('#custom-guests-dropdown')?.addEventListener('click', (e) => {
        const item = e.target.closest('.custom-dropdown-item');
        if (!item) return;

        const val = item.dataset.value;
        document.querySelector('#custom-guests-dropdown .custom-dropdown-item.selected')?.classList.remove('selected');
        item.classList.add('selected');
        
        const triggerVal = document.getElementById('guests-trigger-value');
        const textSpan = item.querySelector('.guest-text');
        if (triggerVal && textSpan) {
            triggerVal.textContent = textSpan.textContent;
        }
        
        const rawSelect = document.getElementById('guests');
        if (rawSelect) {
            rawSelect.value = val;
            rawSelect.dispatchEvent(new Event('change'));
        }
        
        document.getElementById('custom-guests-dropdown')?.classList.remove('active');
    });

    // 6. Đồng bộ hóa trạng thái ban đầu khi tải trang
    // A. Đồng bộ bộ chọn Ngày
    const initialDate = document.getElementById('date')?.value;
    if (initialDate) {
        updateDateTriggerText(initialDate);
        const parsedDate = new Date(initialDate);
        if (!isNaN(parsedDate.getTime())) {
            calendarYear = parsedDate.getFullYear();
            calendarMonth = parsedDate.getMonth();
        }
    } else {
        const todayStr = new Date().toISOString().split('T')[0];
        const dateInput = document.getElementById('date');
        if (dateInput) {
            dateInput.value = todayStr;
            dateInput.dispatchEvent(new Event('change'));
        }
        updateDateTriggerText(todayStr);
    }
    renderCustomCalendar(calendarYear, calendarMonth);

    // B. Đồng bộ bộ chọn Giờ
    const initialTime = rawTimeHidden?.value || '18:00';
    if (timeInput) timeInput.value = initialTime;
    document.querySelectorAll('#custom-time-dropdown .custom-dropdown-item').forEach(item => {
        item.classList.toggle('selected', item.dataset.value === initialTime);
    });

    // C. Đồng bộ bộ chọn Số khách
    const initialGuests = document.getElementById('guests')?.value || '2';
    document.querySelectorAll('#custom-guests-dropdown .custom-dropdown-item').forEach(item => {
        item.classList.toggle('selected', item.dataset.value === initialGuests);
        if (item.classList.contains('selected')) {
            const triggerVal = document.getElementById('guests-trigger-value');
            const textSpan = item.querySelector('.guest-text');
            if (triggerVal && textSpan) triggerVal.textContent = textSpan.textContent;
        }
    });

    // 7. Đồng bộ hóa và cập nhật các khung giờ đặt bàn hợp lệ (loại bỏ giờ trong quá khứ)
    renderTimeSlots();
});
