// script.js - Nhà Hàng Cơm Quê Dượng Bầu

// script.js - Nhà Hàng Cơm Quê Dượng Bầu

let cart = [];
let appliedVoucher = null; // Store applied voucher info
let csrfToken = ''; // Store Global CSRF Token

// Init CSRF
(async function initCsrf() {
    try {
        const res = await fetch('api/auth/get_csrf.php');
        const data = await res.json();
        if (data.success) {
            csrfToken = data.csrf_token;
            console.log('CSRF Protected');
        }
    } catch (e) { console.error('CSRF Init fail', e); }
})();

// Global Fetch Interceptor to attach CSRF Token
const originalFetch = window.fetch;
window.fetch = async function(url, options = {}) {
    // Only attach to mutating requests
    if (options.method && ['POST', 'PUT', 'DELETE', 'PATCH'].includes(options.method.toUpperCase())) {
        if (!options.headers) {
            options.headers = {}; 
        }
        
        // Handle if Headers object or plain object
        if (options.headers instanceof Headers) {
            options.headers.append('X-CSRF-Token', csrfToken);
        } else {
            // Assume plain object
            options.headers['X-CSRF-Token'] = csrfToken;
        }
    }
    return originalFetch(url, options);
};

/** Load cart from localStorage on init */
try {
    const savedCart = localStorage.getItem('restaurant_cart');
    if (savedCart) {
        cart = JSON.parse(savedCart);
    }
} catch (e) {
    console.error('Lỗi parse cart:', e);
    cart = [];
}

/** User helper */
function getCurrentUser() {
    const userJson = localStorage.getItem('restaurant_user');
    if (!userJson) return null;
    try {
        return JSON.parse(userJson);
    } catch (e) {
        console.error('Lỗi parse user:', e);
        return null;
    }
}

/** Render Header User Info */
function renderUserHeader() {
    const user = getCurrentUser();
    const box = document.getElementById('auth-actions');
    if (!box) return;

    if (user) {
        const firstLetter = user.name ? user.name.charAt(0).toUpperCase() : 'U';
        const avatarInner = (user.avatar && user.avatar.trim() !== '')
            ? `<img src="${user.avatar}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">`
            : firstLetter;

        box.innerHTML = '';

        // Outer wrapper with relative position for dropdown positioning
        const wrapper = document.createElement('div');
        wrapper.style.cssText = 'position: relative;';

        // Trigger button — clicking opens dropdown
        const trigger = document.createElement('div');
        trigger.id = 'user-header-trigger';
        trigger.innerHTML = `
            <div class="uht-greeting">
                <span class="uht-label">Xin chào,</span>
                <span class="uht-name">${user.name}</span>
            </div>
            <div class="uht-avatar">${avatarInner}</div>
        `;

        // Dropdown card — sibling of trigger, not child
        const dropdown = document.createElement('div');
        dropdown.id = 'user-dropdown-menu';
        dropdown.className = 'premium-dropdown';
        dropdown.innerHTML = `
            <div class="pd-header">
                <div class="pd-avatar">${avatarInner}</div>
                <div class="pd-info">
                    <span class="pd-greeting">Xin chào,</span>
                    <span class="pd-name">${user.name}</span>
                </div>
            </div>
            <div class="pd-divider"></div>
            <a href="profile.html" class="pd-item">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>Hồ sơ cá nhân</span>
            </a>
            <a href="#" class="pd-item pd-logout" id="dropdown-logout-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span>Đăng xuất</span>
            </a>
        `;

        wrapper.appendChild(trigger);
        wrapper.appendChild(dropdown);
        box.appendChild(wrapper);

        // Toggle dropdown when clicking trigger
        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = dropdown.classList.contains('open');
            // Close any other open dropdowns first
            dropdown.classList.toggle('open', !isOpen);
        });

        // Clicking inside dropdown: stop propagation so document listener doesn't close it
        dropdown.addEventListener('click', (e) => {
            e.stopPropagation();
        });

        // Logout button
        dropdown.querySelector('#dropdown-logout-btn').addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropdown.classList.remove('open');
            if (typeof window.showLogoutModal === 'function') window.showLogoutModal();
        });

        // Close on outside click
        document.addEventListener('click', () => {
            dropdown.classList.remove('open');
        });

    } else {
        box.innerHTML = `
            <div class="nav-auth">
                <div class="user-avatar-btn" onclick="location.href='login.html'" title="Đăng nhập">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                </div>
            </div>
        `;
    }
}

function saveCartToStorage() {
    localStorage.setItem('restaurant_cart', JSON.stringify(cart));
}

/** VOUCHER LOGIC */
function checkVoucher() {
    const code = document.getElementById('voucher-input').value.trim();
    const msg = document.getElementById('voucher-msg');
    
    if (!code) {
        msg.textContent = 'Vui lòng nhập mã';
        msg.style.color = 'red';
        return;
    }

    const totalOrder = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);

    fetch('api/public/check_voucher.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ code: code, order_value: totalOrder })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            appliedVoucher = data.voucher;
            msg.textContent = `Giảm ${formatCurrency(data.voucher.discount_amount)}`;
            msg.style.color = 'green';
            updateCart(); // Update Total UI
        } else {
            appliedVoucher = null;
            msg.textContent = data.message;
            msg.style.color = 'red';
            updateCart();
        }
    })
    .catch(err => {
        console.error(err);
        msg.textContent = 'Lỗi kiểm tra mã';
    });
}

/** Thêm món vào giỏ (Updated for Animation) */
function addToCart(arg1, arg2, arg3, arg4) {
    // Handle variable arguments (support legacy calls without event)
    let event = null;
    let id, name, price;

    if (arg1 instanceof Event || (arg1 && arg1.target)) {
        event = arg1;
        id = arg2;
        name = arg3;
        price = arg4;
    } else {
        id = arg1;
        name = arg2;
        price = arg3;
    }

    // 2nd Layer Check
    const user = getCurrentUser();
    if (!user) {
        showToast('Vui lòng đăng nhập!', 'error');
        setTimeout(() => window.location.href = 'login.html', 1000);
        return;
    }

    // Animation Logic
    if (event) {
        const btn = event.target;
        const menuItem = btn.closest('.menu-item');
        const cartBtn = document.getElementById('floating-cart-btn');

        if (menuItem && cartBtn) {
            const img = menuItem.querySelector('img');
            if (img) {
                const imgClone = img.cloneNode(true);
                const rect = img.getBoundingClientRect();
                const cartRect = cartBtn.getBoundingClientRect();

                imgClone.style.position = 'fixed';
                imgClone.style.top = rect.top + 'px';
                imgClone.style.left = rect.left + 'px';
                imgClone.style.width = rect.width + 'px';
                imgClone.style.height = rect.height + 'px';
                imgClone.style.zIndex = '9999';
                imgClone.style.borderRadius = '50%';
                imgClone.style.opacity = '0.8';
                imgClone.style.transformOrigin = 'top left';
                // Tối ưu FPS: CHỈ animate transform và opacity để GPU xử lý, KHÔNG animate top/left/width/height vì sẽ gây Reflow toàn trang liên tục
                imgClone.style.transition = 'transform 0.8s cubic-bezier(0.19, 1, 0.22, 1), opacity 0.8s ease'; 

                document.body.appendChild(imgClone);

                // Trigger animation in next frame
                requestAnimationFrame(() => {
                    const targetX = (cartRect.left + 10) - rect.left;
                    const targetY = (cartRect.top + 10) - rect.top;
                    const scaleX = 30 / rect.width;
                    const scaleY = 30 / rect.height;
                    
                    imgClone.style.transform = `translate(${targetX}px, ${targetY}px) scale(${scaleX}, ${scaleY})`;
                    imgClone.style.opacity = '0';
                });

                // Cleanup
                setTimeout(() => {
                    imgClone.remove();
                    // Shake cart
                    cartBtn.style.transform = 'scale(1.2)';
                    setTimeout(() => cartBtn.style.transform = '', 200);
                }, 800);
            }
        }
    }

    id = parseInt(id);
    price = parseInt(price);

    const existing = cart.find(item => item.id === id);
    if (existing) {
        existing.quantity += 1;
    } else {
        cart.push({ id, name, price, quantity: 1 });
    }
    saveCartToStorage();
    
    // Delay update slightly to match animation start if desired, or instant
    setTimeout(() => {
        updateCart();
        showToast('Đã thêm vào giỏ!', 'success');
    }, 600); // Update near end of animation
}

/** Xóa món khỏi giỏ */
function removeFromCart(id) {
    id = parseInt(id);
    cart = cart.filter(item => item.id !== id);
    saveCartToStorage();
    updateCart();
}

/** Cập nhật hiển thị giỏ hàng */
function updateCart() {
    const cartItems = document.getElementById('cart-items');
    const cartCount = document.getElementById('cart-count');
    const cartTotal = document.getElementById('cart-total');

    if (!cartItems || !cartCount || !cartTotal) return;

    cartItems.innerHTML = '';
    let total = 0;
    let count = 0;

    cart.forEach(item => {
        total += item.price * item.quantity;
        count += item.quantity;

        const row = document.createElement('div');
        row.className = 'cart-item';
        
        const info = document.createElement('span');
        info.textContent = `${item.name} x ${item.quantity}`;
        
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.onclick = () => removeFromCart(item.id);
        btn.textContent = 'Xóa';

        row.appendChild(info);
        row.appendChild(btn);
        cartItems.appendChild(row);
    });

    cartCount.textContent = count;
    
    let displayTotal = total;
    let discountHtml = '';

    if (appliedVoucher) {
        // Recalculate discount based on current total (in case cart changed)
        // Ideally verify again, but for UI sync:
        let discount = 0;
        if (appliedVoucher.discount_type === 'percent') {
            discount = (total * appliedVoucher.discount_value) / 100;
        } else {
            discount = appliedVoucher.discount_value;
        }
        if (discount > total) discount = total;
        
        appliedVoucher.discount_amount = discount; // Update stored amount
        displayTotal = total - discount;
        
        discountHtml = `<br><span style="color:green; font-size:0.9em;">(Giảm: -${displayTotal > 0 ? formatCurrency(discount) : formatCurrency(total)})</span>`;
        
        // Update message if visible
        const msg = document.getElementById('voucher-msg');
        if (msg) {
             msg.textContent = `Đã dùng mã ${appliedVoucher.code}: -${formatCurrency(discount)}`;
             msg.style.color = 'green';
        }
    }

    cartTotal.innerHTML = 'Tổng cộng: ' + formatCurrency(displayTotal) + ' VNĐ' + discountHtml;
}



/** Khởi tạo khi load trang */
document.addEventListener('DOMContentLoaded', () => {
    // Nếu có container menu, load menu động
    const menuGrid = document.getElementById('public-menu-grid');
    if (menuGrid) {
        loadPublicMenu();
    } else {
        // Fallback cho các trang khác nếu tái sử dụng script
        // Gắn sự kiện cho nút "Thêm vào giỏ" (nếu có hardcoded)
        bindAddToCartEvents();
    }

    updateCart();
    fillBookingForm();
});

// Load Menu Function
async function loadPublicMenu() {
    const grid = document.getElementById('public-menu-grid');
    if (!grid) return;

    try {
        const res = await fetch('api/menu/get_menu.php');
        const json = await res.json();
        
        // Hỗ trợ cấu trúc data cũ và mới
        const items = json.data || json.items || [];
        
        grid.innerHTML = '';

        if (!json.success || items.length === 0) {
            grid.innerHTML = '<p style="text-align:center;width:100%;">Hiện chưa có món ăn nào.</p>';
            return;
        }

        items.forEach(item => {
            // Chỉ hiện món đang active
            if (item.is_active == 0) return;

            const div = document.createElement('div');
            div.className = 'menu-item';
            div.dataset.id = item.id;
            // div.dataset.name = item.name;
            // div.dataset.price = item.price;
            
            // Image fallback
            const imgUrl = item.photo && item.photo.trim() !== '' 
                ? item.photo 
                : 'photo/default-food.png'; // Cần đảm bảo có ảnh này hoặc dùng placeholder

            div.innerHTML = `
                <img src="${imgUrl}" alt="${item.name.replace(/"/g, '&quot;')}" onerror="this.src='photo/default-food.png'">
                <h3></h3> <!-- Set via textContent below -->
                <p></p>   <!-- Set via textContent below -->
                <div class="price">${parseInt(item.price).toLocaleString('vi-VN')} VNĐ</div>
                <button class="add-to-cart">Thêm vào giỏ</button>
            `;
            
            // Safer text setting
            div.querySelector('h3').textContent = item.name;
            div.querySelector('p').textContent = item.description || '';
            
            // Bind click manually to avoid inline JS quoting hell
            const btn = div.querySelector('.add-to-cart');
            btn.addEventListener('click', (e) => addToCart(e, item.id, item.name, item.price));
            grid.appendChild(div);
        });

    } catch (err) {
        console.error('Lỗi tải menu:', err);
        grid.innerHTML = '<p style="text-align:center;width:100%;color:red;">Không thể tải thực đơn. Vui lòng thử lại sau.</p>';
    }
}

function bindAddToCartEvents() {
    document.querySelectorAll('.menu-item .add-to-cart').forEach(button => {
        button.addEventListener('click', () => {
             // ... legacy logic if needed, but onclick is better for dynamic items
             // See addToCart function modification below if needed.
             // Actually, the new dynamic render uses onclick="addToCart(...)", so we don't need to bind listeners manually for them.
             // But for static items (if any remain), we keep this or upgrade them.
        });
    });
}

function fillBookingForm() {
    const user = getCurrentUser();
    if (user) {
        const nameInput = document.getElementById('name');
        const phoneInput = document.getElementById('phone');
        if (nameInput) nameInput.value = user.name || '';
        if (phoneInput) phoneInput.value = user.phone || '';
    }
}

/** Toggle Floating Cart Popup */
function toggleCart() {
    const popup = document.getElementById('floating-cart-popup');
    if (popup) {
        popup.classList.toggle('hidden');
    }
}
/* =========================================
   UTILITIES & TOAST NOTIFICATION
   ========================================= */

// Format currency
const formatCurrency = (amount) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
};

// Toast Notification
function showToast(message, type = 'info') {
    // Remove existing toast
    const existing = document.querySelector('.toast-notification');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.textContent = message;
    
    document.body.appendChild(toast);

    // Trigger animation
    setTimeout(() => toast.classList.add('show'), 10);

    // Auto hide
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Loading State Helper
function setLoading(btn, isLoading, text = 'Đang xử lý...') {
    if (!btn) return;
    if (isLoading) {
        btn.dataset.originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner"></span> ${text}`;
    } else {
        btn.disabled = false;
        btn.innerHTML = btn.dataset.originalText || 'Gửi';
    }
}

// Validation Helpers
function validatePhone(phone) {
    const re = /^(0[3|5|7|8|9])+([0-9]{8})$/;
    return re.test(phone);
}

function validateDate(dateStr) {
    const selected = new Date(dateStr);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return selected >= today;
}

/* =========================================
   CART LOGIC (Updated with Toast)
   ========================================= */
// ... (Cart functions using showToast instead of alert) -> I will update existing functions later.

/* =========================================
   DYNAMIC TABLE BOOKING LOGIC
   ========================================= */
let availableTables = []; // Fetched from API

async function fetchTables() {
    try {
        const res = await fetch('api/tables/read.php');
        const data = await res.json();
        return Array.isArray(data) ? data : [];
    } catch (err) {
        console.error("Lỗi tải bàn:", err);
        return [];
    }
}

// Global state for booking
let selectedTableId = null; 
let allTablesData = [];

// Entry point for checking tables
async function loadTables() {
    const dateInput = document.getElementById('date');
    const timeInput = document.getElementById('time');
    
    if (!dateInput || !timeInput) return;
    const date = dateInput.value;
    const time = timeInput.value;
    
    // Clear previous view if inputs invalid
    if (!date || !time) {
        document.getElementById('table-map-container').innerHTML = '<p class="text-muted text-center" style="padding:20px;">Vui lòng chọn ngày và giờ.</p>';
        return;
    }

    if (!validateDate(date)) {
        showToast('Ngày không hợp lệ (phải từ hôm nay)!', 'error');
        return;
    }

    // Reset selection on new search
    selectedTableId = null;
    clearBookingSelection();

    // Show loading
    const mapContainer = document.getElementById('table-map-container');
    mapContainer.innerHTML = '<div class="spinner"></div> Đang kiểm tra bàn trống...';

    try {
        // 1. Fetch ALL tables structure if not cached
        // (Assuming floors and tables don't change often)
        if (allTablesData.length === 0) {
            allTablesData = await fetchTables(); 
        }

        // 2. Fetch BOOKED tables for this timeslot
        const bookedRes = await fetch(`api/public/get_booked_tables.php?date=${date}&time=${time}`);
        const bookedList = await bookedRes.json();

        // 3. Render
        renderTableMap(allTablesData, bookedList);

    } catch (err) {
        console.error("Lỗi loadTables:", err);
        mapContainer.innerHTML = '<p class="text-danger text-center">Lỗi tải dữ liệu. Vui lòng thử lại.</p>';
    }
}

// Function renderTableMap theo logic user yêu cầu
function renderTableMap(allTables, bookedList) {
    const tabsContainer = document.getElementById('floor-tabs');
    const mapContainer = document.getElementById('table-map-container');

    if (!tabsContainer || !mapContainer) return;

    tabsContainer.innerHTML = '';
    mapContainer.innerHTML = '';

    // Bước 1: Nhóm bàn theo tầng (floor)
    // Create object groups = { "Sảnh Tulip": [...], "Sảnh Rose": [...] }
    const groups = {};
    allTables.forEach(t => {
        // Giả sử t.floor là "Tulip" hoặc "Rose", ta hiển thị "Sảnh ..."
        // Hoặc nếu database lưu "Sảnh Tulip" thì dùng luôn. 
        // Ở code cũ ta lưu "Tulip", "Rose". Ta có thể prefix "Sảnh " nếu muốn đẹp.
        const floorName = t.floor; // "Tulip", "Rose"
        if (!groups[floorName]) {
            groups[floorName] = [];
        }
        groups[floorName].push(t);
    });

    const floorNames = Object.keys(groups).sort();
    if (floorNames.length === 0) {
        mapContainer.innerHTML = '<p>Không tìm thấy dữ liệu bàn.</p>';
        return;
    }

    // Bước 2: Vẽ Tabs
    floorNames.forEach((floorName, index) => {
        const btn = document.createElement('button');
        btn.className = `hall-tab ${index === 0 ? 'active' : ''}`;
        btn.textContent = `Sảnh ${floorName}`;
        btn.onclick = () => switchTab(floorName);
        tabsContainer.appendChild(btn);
    });

    // Bước 3: Vẽ Bàn
    floorNames.forEach((floorName, index) => {
        const div = document.createElement('div');
        div.className = `floor-grid ${index === 0 ? 'active' : ''}`; // Class floor-grid
        div.id = `floor-${floorName}`; // id theo tầng

        const tablesInFloor = groups[floorName];
        
        tablesInFloor.forEach(t => {
            // Updated comparison: backend returns array of IDs [1, 2, 5...]
            // bookedList = [1, 4, 10...]
            const isBooked = bookedList.includes(parseInt(t.id));
            
            // Class logic
            let statusClass = 'available';
            if (isBooked) statusClass = 'booked';
            // Note: 'selected' logic happens on click usually, but if re-rendering we might check selectedTableId
            if (selectedTableId && parseInt(selectedTableId) === parseInt(t.id)) {
                statusClass = 'selected';
            }

            // HTML Structure for Table Box
            const tableBox = document.createElement('div');
            tableBox.className = `table-box ${statusClass}`;
            tableBox.dataset.id = t.id;
            
            // Content
            tableBox.innerHTML = `
                <h3>${t.name}</h3>
                <p>${t.capacity || '4'} người</p>  <!-- Giả sử có field capacity hoặc default -->
            `;

            if (!isBooked) {
                tableBox.onclick = () => onTableClick(tableBox, floorName, t.name, t.id);
            }

            div.appendChild(tableBox);
        });

        mapContainer.appendChild(div);
    });
}

function switchTab(floorName) {
    // 1. Update Tabs
    document.querySelectorAll('.hall-tab').forEach(btn => {
        if (btn.textContent.includes(floorName)) btn.classList.add('active');
        else btn.classList.remove('active');
    });

    // 2. Hide all floors, Show target
    document.querySelectorAll('.floor-grid').forEach(g => g.classList.remove('active'));
    
    const target = document.getElementById(`floor-${floorName}`);
    if (target) target.classList.add('active');
}

function onTableClick(element, floor, tableName, tableId) {
    // If already selected, deselect
    if (element.classList.contains('selected')) {
        element.classList.remove('selected');
        element.classList.add('available');
        clearBookingSelection();
        return;
    }

    // Else, select this one (single selection mode)
    // 1. Clear other selections
    document.querySelectorAll('.table-box.selected').forEach(el => {
        el.classList.remove('selected');
        el.classList.add('available');
    });

    // 2. Highlight this
    element.classList.remove('available');
    element.classList.add('selected');

    // 3. Save state
    selectedTableId = tableId;
    updateBookingForm(floor, tableName, tableId);
}

function updateBookingForm(floor, tableName, tableId) {
    document.getElementById('selected-floor').value = floor;
    const selectedTableEl = document.getElementById('selected-table');
    selectedTableEl.value = tableName;
    selectedTableEl.setAttribute('data-id', tableId);
    // We can use a new hidden input or reuse selected-table for ID if we change logic, 
    // but legacy might expect name. Let's create a global var or hidden input for ID.
    // Ideally we add a hidden input to booking.html for table_id, but here we can just use the global/param.
    
    // Check if table_id input exists, if not create one or assume submitBooking handles it.
    // submitBooking reads: const table  = document.getElementById('selected-table')?.value;
    // We need to send table_id. Let's add a hidden field or update submitBooking logic.
    // Let's rely on a global variable 'selectedTableId' which is already set in onTableClick,
    // OR create a hidden input dynamically if missing? Better to use the global variable in submitBooking.
    
    // Update summary
    const summary = document.getElementById('selection-summary');
    const date = document.getElementById('date').value;
    const time = document.getElementById('time').value;
    
    if (summary) {
        summary.innerHTML = `Đang chọn: <strong style="color:#e67e22">Bàn ${tableName} - Sảnh ${floor}</strong><br>
                             Thời gian: ${time}, Ngày ${date}`;
    }

    const btn = document.getElementById('btn-submit-booking');
    if (btn) {
        btn.disabled = false;
        btn.textContent = "Xác Nhận Đặt Bàn";
    }
}

function clearBookingSelection() {
    selectedTableId = null;
    document.getElementById('selected-floor').value = '';
    document.getElementById('selected-table').value = '';
    
    document.getElementById('selection-summary').textContent = 'Vui lòng chọn bàn...';
    
    const btn = document.getElementById('btn-submit-booking');
    if (btn) {
        btn.disabled = true;
        btn.textContent = "Vui Lòng Chọn Bàn";
    }
}

// [Legacy selectTable removed]

// Override submitBooking
window.submitBooking = function() {
    const name   = document.getElementById('name')?.value.trim();
    const phone  = document.getElementById('phone')?.value.trim();
    const date   = document.getElementById('date')?.value.trim();
    const time   = document.getElementById('time')?.value.trim();
    const guests = document.getElementById('guests')?.value.trim();
    const floor  = document.getElementById('selected-floor')?.value;
    const tableEl = document.getElementById('selected-table');
    const tableId = tableEl ? tableEl.getAttribute('data-id') : '';
    const tableName = tableEl ? tableEl.value : '';

    const user = getCurrentUser();
    if (!user) {
        showToast('Vui lòng đăng nhập để đặt bàn!', 'error');
        setTimeout(() => window.location.href = 'login.html', 1500);
        return;
    }

    // VALIDATION
    if (!name || !phone || !date || !time || !guests) {
        showToast('Vui lòng điền đầy đủ thông tin đặt bàn!', 'warning');
        return;
    }

    if (!validatePhone(phone)) {
        showToast('Số điện thoại không hợp lệ (10 số, bắt đầu bằng 0)!', 'error');
        return;
    }

    if (!validateDate(date)) {
        showToast('Ngày đặt bàn không hợp lệ!', 'error');
        return;
    }

    const btn = document.getElementById('btn-submit-booking') || document.querySelector('button[type="submit"]');
    setLoading(btn, true);

    const body = {
        name, phone, date, time, guests,
        floor: floor || '',
        table_number: tableName || '', 
        table_id: tableId || '',
        has_preorder: window.isPreorderEnabled || false,
        items: window.bookingCart || []
    };

    fetch('api/user/book_table.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Reset form
            document.getElementById('date').value   = '';
            document.getElementById('time').value   = '';
            document.getElementById('guests').value = '';
            document.getElementById('selected-floor').value = '';
            document.getElementById('selected-table').value = '';
            const tableSelectionArea = document.getElementById('table-selection-area');
            if (tableSelectionArea) tableSelectionArea.style.display = 'none';
            // Reload tables
            loadTables();

            if (data.require_payment && data.payUrl) {
                showToast(data.message || 'Vui lòng thanh toán cọc', 'info');
                showQrModal(data.payUrl, data.deposit_amount, "BKG" + data.booking_id);
            } else {
                showToast(data.message || 'Đặt bàn thành công!', 'success');
            }
        } else {
            showToast(data.message || 'Đặt bàn thất bại', 'error');
        }
    })
    .catch(err => {
        console.error('Lỗi đặt bàn:', err);
        showToast('Lỗi kết nối máy chủ.', 'error');
    })
    .finally(() => {
        setLoading(btn, false);
    });
}

// CHECKOUT LOGIC
// Toggle Order Type UI
function toggleOrderType() {
    const type = document.querySelector('input[name="order_type"]:checked').value;
    document.getElementById('delivery_info').style.display = type === 'delivery' ? 'block' : 'none';
    document.getElementById('dine_in_info').style.display = type === 'dine_in' ? 'block' : 'none';
}

function checkout() {
    if (cart.length === 0) {
        showToast('Giỏ hàng trống!', 'warning');
        return;
    }

    const user = getCurrentUser();
    if (!user) {
        showToast('Vui lòng đăng nhập để thanh toán!', 'error');
        setTimeout(() => window.location.href = 'login.html', 1500);
        return;
    }

    // Get Order Context
    const orderType = document.querySelector('input[name="order_type"]:checked')?.value || 'delivery';
    const address = document.getElementById('delivery_address')?.value.trim();
    const tableNum = document.getElementById('table_number_input')?.value.trim();
    const note = document.getElementById('order_note')?.value.trim();

    // Validation
    if (orderType === 'delivery') {
        if (!address) {
            showToast('Vui lòng nhập địa chỉ giao hàng!', 'warning');
            document.getElementById('delivery_address').focus();
            return;
        }
    } else {
        if (!tableNum) {
             showToast('Vui lòng nhập tên bàn!', 'warning');
             document.getElementById('table_number_input').focus();
             return;
        }
    }

    // Get payment method
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked')?.value || 'cash';
    let total = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
    
    // Client-side recalc with voucher (backend will verify)
    if (appliedVoucher) {
        total -= appliedVoucher.discount_amount;
        if (total < 0) total = 0;
    }

    const btn = document.getElementById('checkout-btn');
    setLoading(btn, true, 'Đang thanh toán...');
    
    fetch('api/payment/create_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            items: cart,
            total: total, 
            payment_method: paymentMethod,
            user_id: user ? user.id : null,
            voucher_code: appliedVoucher ? appliedVoucher.code : '',
            // New Context Fields
            address: orderType === 'delivery' ? address : '',
            table_id: orderType === 'dine_in' ? tableNum : '', // Sending as string (Name)
            note: note
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Đặt hàng thành công!', 'success');
            cart = [];
            updateCart(); // Clear UI
            toggleCart(); // Close popup
            
            // If QR code provided
            if (data.payUrl) {
                const content = "DH" + (data.order_id || '...');
                // Append address/table note to QR content if space allows, but usually just OrderID is enough
                showQrModal(data.payUrl, total, content);
            }
        } else {
            showToast(data.message || 'Thanh toán thất bại', 'error');
        }
    })
    .catch(err => {
        console.error('Checkout error:', err);
        showToast('Lỗi kết nối thanh toán', 'error');
    })
    .finally(() => {
        setLoading(btn, false);
    });
}

/* =========================================
   USER PROFILE LOGIC
   ========================================= */

function switchProfileTab(tabName) {
    // 1. Remove active class from buttons
    document.querySelectorAll('.sidebar-btn').forEach(btn => btn.classList.remove('active'));
    // 2. Add active to clicked button
    const activeBtn = document.querySelector(`.sidebar-btn[onclick="switchProfileTab('${tabName}')"]`);
    if (activeBtn) activeBtn.classList.add('active');

    // 3. Hide all tab panes
    document.querySelectorAll('.tab-pane').forEach(pane => {
        pane.classList.remove('active');
        // display: none handled by CSS
    });

    // 4. Show selected tab pane
    const targetPane = document.getElementById(`tab-${tabName}`);
    if (targetPane) {
        targetPane.classList.add('active');
    }

    // Load data if needed
    if (tabName === 'orders' || tabName === 'bookings') {
        loadUserHistory();
    }
}

// function updateUserInfo... (keeping as is, but inserting updated loadUserProfile above)

function loadUserProfile() {
    const user = getCurrentUser();
    if (user) {
        if (document.getElementById('u_name')) document.getElementById('u_name').value = user.name || '';
        if (document.getElementById('u_email')) document.getElementById('u_email').value = user.email || '';
        if (document.getElementById('u_phone')) document.getElementById('u_phone').value = user.phone || '';
        
        // Load Avatar
        const img = document.getElementById('profile-avatar-img');
        if (img) {
            img.src = user.avatar ? user.avatar : 'photo/default-user.png';
        }
    }
}

async function uploadAvatar() {
    const input = document.getElementById('avatar-input');
    if (!input || !input.files || input.files.length === 0) return;

    const file = input.files[0];
    const user = getCurrentUser();
    if (!user) return;

    // Show Loading
    const loading = document.getElementById('avatar-loading');
    if (loading) loading.style.display = 'flex';

    const formData = new FormData();
    formData.append('avatar', file);
    formData.append('user_id', user.id);

    try {
        const res = await fetch('api/user/upload_avatar.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            showToast('Cập nhật ảnh đại diện thành công!', 'success');
            // Update local storage
            user.avatar = data.avatar_url;
            localStorage.setItem('restaurant_user', JSON.stringify(user));
            
            // Update UI
            const img = document.getElementById('profile-avatar-img');
            if (img) img.src = data.avatar_url + '?t=' + new Date().getTime(); // Prevent cache
            
            // Reload to update header if needed
             setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Lỗi upload ảnh', 'error');
        }
    } catch (err) {
        console.error(err);
        showToast('Lỗi kết nối server', 'error');
    } finally {
        if (loading) loading.style.display = 'none';
    }
}

function updateUserInfo() {
    const name = document.getElementById('u_name').value;
    const email = document.getElementById('u_email').value;
    const phone = document.getElementById('u_phone').value;
    const user = getCurrentUser();

    if (!user) return;

    const btn = document.querySelector('#tab-info button[type="submit"]');
    setLoading(btn, true, 'Đang lưu...');

    fetch('api/user/update_profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: user.id, // Assuming user object has ID
            name, email, phone
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('Cập nhật thông tin thành công!', 'success');
            // Update local storage
            const newUser = { ...user, name, email, phone };
            localStorage.setItem('restaurant_user', JSON.stringify(newUser));
            // Update header greetings if on home page
            const greeting = document.querySelector('.nav-auth span');
            if (greeting) greeting.textContent = `Xin chào, ${name}`;
        } else {
            showToast(data.message || 'Cập nhật thất bại', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Lỗi kết nối', 'error');
    })
    .finally(() => setLoading(btn, false));
}

function loadUserHistory(page = 1) {
    const user = getCurrentUser();
    if (!user) return;

    const orderList = document.getElementById('order-history-list');
    const bookingList = document.getElementById('booking-history-list');

    // Show loading
    if (orderList) orderList.innerHTML = '<div class="spinner"></div> Đang tải...';
    // Only refresh bookings on first load to avoid flicker (or refresh if needed, keeping simple)
    if (bookingList && page === 1) bookingList.innerHTML = '<div class="spinner"></div> Đang tải...';

    fetch(`api/user/get_user_history.php?page=${page}`)
        .then(res => res.json())
        .then(data => {
            // Check Auth Failure
            if (data.error === 'Unauthorized') {
                showToast('Phiên đăng nhập hết hạn. Vui lòng đăng nhập lại.', 'error');
                localStorage.removeItem('restaurant_user');
                setTimeout(() => window.location.href = 'login.html', 1500);
                return;
            }

            // Render Orders
            if (orderList) {
                if (data.orders && data.orders.length > 0) {
                    const html = data.orders.map(o => `
                        <div class="history-item" style="border:1px solid #eee; padding:10px; margin-bottom:10px; border-radius:8px;">
                            <div style="display:flex; justify-content:space-between;">
                                <strong>Đơn #${o.id}</strong>
                                <span class="badge badge-${o.status === 'paid' ? 'success' : (o.status === 'cancelled' ? 'danger' : 'warning')}">
                                    ${o.status === 'paid' ? 'Đã thanh toán' : (o.status === 'cancelled' ? 'Đã hủy' : 'Chờ thanh toán')}
                                </span>
                            </div>
                            <p>Tổng: ${formatCurrency(o.total_amount)} - ${o.payment_method === 'bank_transfer' ? 'Chuyển khoản' : 'Tiền mặt'}</p>
                            <small class="text-muted">${o.created_at}</small>
                            <div style="margin-top:8px; text-align:right;">
                                <button onclick="viewOrderDetails(${o.id})" style="background:#e67e22; color:white; border:none; padding:5px 10px; border-radius:4px; cursor:pointer; font-size:12px;">Xem chi tiết</button>
                                ${o.status === 'paid' ? 
                                    (o.is_reviewed > 0 
                                        ? `<button disabled style="background:#95a5a6; color:white; border:none; padding:5px 10px; border-radius:4px; font-size:12px; margin-left:5px; cursor:default;">Đã đánh giá</button>`
                                        : `<button onclick="openReviewModal(${o.id})" style="background:#2ecc71; color:white; border:none; padding:5px 10px; border-radius:4px; cursor:pointer; font-size:12px; margin-left:5px;">Đánh giá</button>`
                                    ) 
                                : ''}
                            </div>
                        </div>
                    `).join('');

                    // Pagination Control
                    let paginationHtml = '';
                    if (data.pagination && data.pagination.total_pages > 1) {
                        const { current_page, total_pages } = data.pagination;
                        paginationHtml = `
                            <div class="pagination-controls">
                                <button class="page-btn" ${current_page <= 1 ? 'disabled' : ''} onclick="loadUserHistory(${current_page - 1})">« Trước</button>
                                <span style="line-height:30px; font-weight:500;">Trang ${current_page} / ${total_pages}</span>
                                <button class="page-btn" ${current_page >= total_pages ? 'disabled' : ''} onclick="loadUserHistory(${current_page + 1})">Sau »</button>
                            </div>
                        `;
                    }
                    orderList.innerHTML = html + paginationHtml;

                } else {
                    orderList.innerHTML = '<p>Chưa có đơn hàng nào.</p>';
                }
            }

            // Render Bookings (Only render if data exists, and maybe only on page 1?)
            if (bookingList && page === 1) {
                if (data.bookings && data.bookings.length > 0) {
                    bookingList.innerHTML = data.bookings.map(b => `
                        <div class="history-item" style="border:1px solid #eee; padding:10px; margin-bottom:10px; border-radius:8px;">
                            <div style="display:flex; justify-content:space-between;">
                                <strong>${b.date} - ${b.time.substring(0, 5)}</strong>
                                <span class="badge badge-${b.status==='cancelled'?'danger':(b.status==='confirmed'?'success':'warning')}">
                                    ${b.status==='cancelled'?'Đã hủy':(b.status==='confirmed'?'Đã xác nhận':'Chờ xác nhận')}
                                </span>
                            </div>
                            <p>Bàn: <strong>${b.table_name || b.table_number || 'Chưa xếp'}</strong> - ${b.floor || ''}</p>
                            <p>Khách: ${b.guests} người</p>
                        </div>
                    `).join('');
                } else {
                    bookingList.innerHTML = '<p>Chưa có lịch đặt bàn nào.</p>';
                }
            }
        })
        .catch(err => {
            console.error(err);
            if (orderList) orderList.innerHTML = 'Lỗi tải dữ liệu. (Vui lòng đăng nhập lại)';
        });
}



/* =========================================
   QR MODAL LOGIC
   ========================================= */

let paymentCheckInterval = null;

function showQrModal(qrUrl, amount, content) {
    const modal = document.getElementById('qr-payment-modal');
    if (!modal) return;

    // Set Data
    document.getElementById('qr-code-img').src = qrUrl;
    document.getElementById('qr-amount').textContent = formatCurrency(amount);
    document.getElementById('qr-content').textContent = content;

    // Show
    modal.classList.remove('hidden');
    // Small delay for CSS transition
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);

    // Extract Order ID or Booking ID from content for checking 
    const matchesDH = content.match(/DH(\d+)/i);
    if (matchesDH && matchesDH[1]) {
        startPaymentPolling(matchesDH[1]);
    }
    
    const matchesBKG = content.match(/BKG(\d+)/i);
    if (matchesBKG && matchesBKG[1]) {
        startBookingPaymentPolling(matchesBKG[1]);
    }
}

function startPaymentPolling(orderId) {
    if (paymentCheckInterval) clearInterval(paymentCheckInterval);

    paymentCheckInterval = setInterval(() => {
        fetch(`api/payment/check_status.php?order_id=${orderId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.status === 'paid') {
                clearInterval(paymentCheckInterval);
                showToast('Thanh toán thành công!', 'success');
                closeQrModal();
                // Clear cart if needed
                cart = [];
                updateCart();
                saveCartToStorage();
                toggleCart(); // Ensure cart popup is closed
            }
        })
        .catch(err => console.error("Polling error:", err));
    }, 3000); // Check every 3 seconds
}

function startBookingPaymentPolling(bookingId) {
    if (paymentCheckInterval) clearInterval(paymentCheckInterval);

    paymentCheckInterval = setInterval(() => {
        fetch(`api/payment/check_status_booking.php?booking_id=${bookingId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && (data.payment_status === 'partial' || data.payment_status === 'paid')) {
                clearInterval(paymentCheckInterval);
                showToast('Thanh toán cọc thành công! Đã giữ bàn.', 'success');
                closeQrModal();
                if(document.getElementById('preorder-section')) {
                    document.getElementById('toggle-preorder').checked = false;
                    togglePreorderSection();
                }
            }
        })
        .catch(err => console.error("Polling error:", err));
    }, 3000); // Check every 3 seconds
}

function closeQrModal() {
    const modal = document.getElementById('qr-payment-modal');
    if (!modal) return;

    // Stop Polling
    if (paymentCheckInterval) {
        clearInterval(paymentCheckInterval);
        paymentCheckInterval = null;
    }

    modal.classList.remove('show');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300); // Match CSS transition duration
}

function copyToClipboard(elementId) {
    const text = document.getElementById(elementId).textContent;
    navigator.clipboard.writeText(text).then(() => {
        showToast('Đã sao chép: ' + text, 'success');
    }).catch(err => {
        console.error('Lỗi sao chép', err);
    });
}

// =========================================
// MISSING FUNCTIONS (ADDED FIX)
// =========================================

// CHANGE PASSWORD FUNCTION
function changePassword() {
    const oldPass = document.getElementById('old_pass').value;
    const newPass = document.getElementById('new_pass').value;
    const confirmPass = document.getElementById('confirm_pass').value;

    if (newPass !== confirmPass) {
        showToast('Mật khẩu xác nhận không khớp!', 'error');
        return;
    }

    if (newPass.length < 6) {
        showToast('Mật khẩu mới phải có ít nhất 6 ký tự!', 'error');
        return;
    }

    // Find submit button inside the form
    const submitBtn = document.querySelector('form[onsubmit="changePassword(); return false;"] button');
    setLoading(submitBtn, true, 'Đang đổi...');

    fetch('api/auth/change_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            old_password: oldPass,
            new_password: newPass
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            // Clear inputs
            document.getElementById('old_pass').value = '';
            document.getElementById('new_pass').value = '';
            document.getElementById('confirm_pass').value = '';
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(err => {
        showToast('Lỗi kết nối server', 'error');
        console.error(err);
    })
    .finally(() => setLoading(submitBtn, false));
}

// ORDER DETAILS MODAL
function viewOrderDetails(orderId) {
    const modal = document.getElementById('order-detail-modal');
    const content = document.getElementById('modal-order-items');
    const titleId = document.getElementById('modal-order-id');

    if (modal) modal.style.display = 'block';
    if (titleId) titleId.textContent = orderId;
    if (content) content.innerHTML = '<div class="spinner"></div> Đang tải chi tiết...';

    fetch(`api/user/get_order_details.php?order_id=${orderId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (data.data.length === 0) {
                    content.innerHTML = '<p>Không có món ăn nào trong đơn này.</p>';
                    return;
                }
                
                let html = '<table style="width:100%; border-collapse:collapse;">';
                html += '<tr style="background:#f1f1f1;"><th style="padding:8px; text-align:left;">Món ăn</th><th style="padding:8px; text-align:center;">SL</th><th style="padding:8px; text-align:right;">Đơn giá</th><th style="padding:8px; text-align:right;">Thành tiền</th></tr>';
                
                let total = 0;
                data.data.forEach(item => {
                    const subtotal = item.quantity * item.unit_price;
                    total += subtotal;
                    const img = item.image || 'photo/default-food.png';
                    html += `
                        <tr style="border-bottom:1px solid #eee;">
                            <td style="padding:8px; display:flex; align-items:center; gap:10px;">
                                <img src="${img}" style="width:40px; height:40px; object-fit:cover; border-radius:4px;">
                                <span>${item.name}</span>
                            </td>
                            <td style="padding:8px; text-align:center;">${item.quantity}</td>
                            <td style="padding:8px; text-align:right;">${formatCurrency(item.unit_price)}</td>
                            <td style="padding:8px; text-align:right; font-weight:bold;">${formatCurrency(subtotal)}</td>
                        </tr>
                    `;
                });
                
                html += `
                    <tr>
                        <td colspan="3" style="padding:10px; text-align:right; font-weight:bold;">TỔNG CỘNG:</td>
                        <td style="padding:10px; text-align:right; font-weight:bold; color:#e67e22; font-size:1.1em;">${formatCurrency(total)}</td>
                    </tr>
                `;
                html += '</table>';
                
                content.innerHTML = html;
            } else {
                content.innerHTML = `<p style="color:red;">Lỗi: ${data.message}</p>`;
            }
        })
        .catch(err => {
            console.error(err);
            content.innerHTML = '<p style="color:red;">Lỗi kết nối server.</p>';
        });
}

function closeOrderModal() {
    const modal = document.getElementById('order-detail-modal');
    if (modal) modal.style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('order-detail-modal');
    const qrModal = document.getElementById('qr-payment-modal');
    const reviewModal = document.getElementById('review-modal');
    if (event.target == modal) {
        modal.style.display = "none";
    }
    if (event.target == qrModal) {
        qrModal.classList.remove('show');
        setTimeout(() => qrModal.classList.add('hidden'), 300);
    }
    if (event.target == reviewModal) {
        reviewModal.style.display = "none";
    }
}

// =========================================
// CUSTOMER REVIEWS
// =========================================

function openReviewModal(orderId) {
    document.getElementById('review-modal').style.display = 'flex';
    document.getElementById('review-order-id').value = orderId;
    setRating(5); // Default 5 stars
    document.getElementById('review-comment').value = '';
}

function setRating(rating) {
    document.getElementById('review-rating').value = rating;
    const stars = document.querySelectorAll('.star-rating span');
    stars.forEach(star => {
        if (parseInt(star.dataset.value) <= rating) {
            star.style.color = '#f1c40f'; // Gold
        } else {
            star.style.color = '#ddd';
        }
    });
}

function submitReview() {
    const orderId = document.getElementById('review-order-id').value;
    const rating = document.getElementById('review-rating').value;
    const comment = document.getElementById('review-comment').value;
    const user = getCurrentUser();

    if (!user) return;

    // Loading state
    const btn = document.querySelector('#review-modal button:last-child');
    const originalText = btn.textContent;
    btn.textContent = 'Đang gửi...';
    btn.disabled = true;

    fetch('api/user/submit_review.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            user_id: user.id,
            order_id: orderId,
            rating: rating,
            comment: comment
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            document.getElementById('review-modal').style.display = 'none';
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Lỗi kết nối', 'error');
    })
    .finally(() => {
        btn.textContent = originalText;
        btn.disabled = false;
    });
}

// =========================================
// FEATURED REVIEWS (Homepage)
// =========================================
function loadFeaturedReviews() {
    const grid = document.getElementById('featured-reviews-grid');
    if (!grid) return; // Not on homepage

    fetch('api/public/get_featured_reviews.php')
        .then(res => res.json())
        .then(data => {
            if (data.reviews && data.reviews.length > 0) {
                grid.innerHTML = data.reviews.map(r => `
                    <div class="review-card">
                        <div class="review-rating">${'★'.repeat(parseInt(r.rating))}</div>
                        <div class="review-comment">"${r.comment}"</div>
                        <div class="review-author">
                            <img src="photo/default-user.png" alt="user" onerror="this.src='photo/default-user.png'">
                            <span>${r.name}</span>
                        </div>
                    </div>
                `).join('');
            } else {
                grid.innerHTML = '<p>Chưa có đánh giá nào.</p>';
            }
        })
        .catch(err => {
            console.error('Lỗi tải đánh giá:', err);
            grid.innerHTML = '<p>Không thể tải đánh giá.</p>';
        });
}

// Auto load if on homepage
document.addEventListener('DOMContentLoaded', () => {
    if(typeof loadFeaturedReviews === 'function') loadFeaturedReviews();
});

/* =========================================
   PRE-ORDER LOGIC IN BOOKING FORM
   ========================================= */

window.bookingCart = [];
window.isPreorderEnabled = false;

window.togglePreorderSection = function() {
    const isChecked = document.getElementById('toggle-preorder').checked;
    window.isPreorderEnabled = isChecked;
    const section = document.getElementById('preorder-section');
    if (isChecked) {
        section.style.display = 'block';
        if (document.getElementById('booking-menu-list').innerHTML.includes('Đang tải')) {
            loadBookingMenu();
        }
    } else {
        section.style.display = 'none';
        window.bookingCart = [];
        updateBookingCartUI();
    }
};

function loadBookingMenu() {
    fetch('api/menu/get_menu.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const list = document.getElementById('booking-menu-list');
                list.innerHTML = '';
                if(data.data.length === 0) {
                    list.innerHTML = '<p class="text-muted">Chưa có món ăn.</p>';
                    return;
                }
                data.data.forEach(item => {
                    const el = document.createElement('div');
                    el.style.display = 'flex';
                    el.style.justifyContent = 'space-between';
                    el.style.alignItems = 'center';
                    el.style.padding = '8px 0';
                    el.style.borderBottom = '1px solid #eee';
                    
                    const qtyInCart = window.bookingCart.find(i => i.menu_item_id == item.id)?.quantity || 0;
                    
                    el.innerHTML = `
                        <div>
                            <div style="font-weight:600">${item.name}</div>
                            <div style="color:var(--primary); font-size:0.9rem">${formatCurrency(item.price)}</div>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <button type="button" class="btn btn-outline" style="padding:2px 8px; border-radius:4px" onclick="updateBookingItem(${item.id}, '${item.name}', ${item.price}, -1)">-</button>
                            <span id="bkg-qty-${item.id}" style="width:20px; text-align:center">${qtyInCart}</span>
                            <button type="button" class="btn btn-primary" style="padding:2px 8px; border-radius:4px" onclick="updateBookingItem(${item.id}, '${item.name}', ${item.price}, 1)">+</button>
                        </div>
                    `;
                    list.appendChild(el);
                });
            }
        })
        .catch(err => console.error('Error load menu for booking:', err));
}

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
    if(qtyEl) qtyEl.innerText = item.quantity;
    updateBookingCartUI();
};

window.updateBookingCartUI = function() {
    let total = window.bookingCart.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
    let deposit = Math.ceil(total * 0.3);
    
    document.getElementById('preorder-total').innerText = formatCurrency(total);
    document.getElementById('preorder-deposit').innerText = formatCurrency(deposit);
    
    // Auto-update submit button text
    const btn = document.getElementById('btn-submit-booking');
    if(btn && !btn.disabled) {
        if(window.isPreorderEnabled && deposit > 0) {
            btn.innerText = "Xác Nhận & Thanh Toán Cọc";
        } else {
            btn.innerText = "Xác Nhận Đặt Bàn";
        }
    }
};

/* =========================================
   GLOBAL LOGOUT MODAL
   ========================================= */
window.showLogoutModal = function() {
    // Remove existing if present
    let modal = document.getElementById('logout-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'logout-modal';
        modal.innerHTML = `
            <div class="logout-modal-card">
                <div class="logout-icon-wrap">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#e74c3c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                </div>
                <h3>Xác nhận Đăng xuất</h3>
                <p>Bạn có chắc chắn muốn rời khỏi hệ thống không?</p>
                <div class="logout-actions">
                    <button class="btn-cancel-logout" id="btn-cancel-logout">Hủy bỏ</button>
                    <button class="btn-confirm-logout" id="btn-execute-logout">Đăng xuất</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        document.getElementById('btn-cancel-logout').addEventListener('click', window.hideLogoutModal);
        document.getElementById('btn-execute-logout').addEventListener('click', window.executeLogout);
        // Click backdrop to close
        modal.addEventListener('click', (e) => { if (e.target === modal) window.hideLogoutModal(); });
    }

    // Trigger transition on next frame
    requestAnimationFrame(() => {
        requestAnimationFrame(() => modal.classList.add('modal-visible'));
    });
}

window.hideLogoutModal = function() {
    const modal = document.getElementById('logout-modal');
    if (!modal) return;
    modal.classList.remove('modal-visible');
}

window.executeLogout = function() {
    const btn = document.getElementById('btn-execute-logout');
    if (btn) {
        btn.innerHTML = '<span class="spinner" style="display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,0.4);border-top-color:white;border-radius:50%;animation:spin 0.7s linear infinite;"></span>';
        btn.disabled = true;
    }
    fetch('api/auth/logout.php', { method: 'POST' })
        .finally(() => {
            localStorage.removeItem('restaurant_user');
            window.location.href = 'index.html';
        });
}

