// js/profile.js – Trang cá nhân: hiển thị, cập nhật, avatar, lịch sử, đổi mật khẩu, order modal
// Phụ thuộc: utils.js (showToast, setLoading, formatCurrency, getCurrentUser)
//            reviews.js (openReviewModal)

'use strict';

/* =========================================
   PROFILE TABS
   ========================================= */

/**
 * Chuyển tab trong trang profile
 * @param {string} tabName  Tên tab ('info', 'orders', 'bookings', 'security')
 */
window.switchProfileTab = function(tabName) {
    document.querySelectorAll('.sidebar-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`.sidebar-btn[onclick="switchProfileTab('${tabName}')"]`)?.classList.add('active');
    document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
    document.getElementById(`tab-${tabName}`)?.classList.add('active');

    if (tabName === 'orders' || tabName === 'bookings') loadUserHistory();
};

/* =========================================
   LOAD PROFILE INFO
   ========================================= */

/** Điền thông tin user vào form từ localStorage */
window.loadUserProfile = function() {
    const user = getCurrentUser();
    if (!user) return;
    if (document.getElementById('u_name'))  document.getElementById('u_name').value  = user.name  || '';
    if (document.getElementById('u_email')) document.getElementById('u_email').value = user.email || '';
    if (document.getElementById('u_phone')) document.getElementById('u_phone').value = user.phone || '';

    const img = document.getElementById('profile-avatar-img');
    if (img) img.src = user.avatar || 'photo/default-user.png';
};

/* =========================================
   UPDATE PROFILE INFO
   ========================================= */

/** Gửi cập nhật thông tin cá nhân lên server */
window.updateUserInfo = function() {
    const name  = document.getElementById('u_name')?.value;
    const email = document.getElementById('u_email')?.value;
    const phone = document.getElementById('u_phone')?.value;
    const user  = getCurrentUser();
    if (!user) return;

    const btn = document.querySelector('#tab-info button[type="submit"]');
    setLoading(btn, true, 'Đang lưu...');

    fetch('api/user/update_profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: user.id, name, email, phone })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('Cập nhật thông tin thành công!', 'success');
            const newUser = { ...user, name, email, phone };
            localStorage.setItem('restaurant_user', JSON.stringify(newUser));
            const greeting = document.querySelector('.nav-auth span');
            if (greeting) greeting.textContent = `Xin chào, ${name}`;
        } else {
            showToast(data.message || 'Cập nhật thất bại', 'error');
        }
    })
    .catch(err => { console.error(err); showToast('Lỗi kết nối', 'error'); })
    .finally(() => setLoading(btn, false));
};

/* =========================================
   AVATAR UPLOAD
   ========================================= */

/** Upload ảnh đại diện */
window.uploadAvatar = async function() {
    const input = document.getElementById('avatar-input');
    if (!input?.files?.length) return;

    const user = getCurrentUser();
    if (!user) return;

    const loading = document.getElementById('avatar-loading');
    if (loading) loading.style.display = 'flex';

    const formData = new FormData();
    formData.append('avatar', input.files[0]);
    formData.append('user_id', user.id); // Ghi chú: server bỏ qua, dùng session

    try {
        const res  = await fetch('api/user/upload_avatar.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            showToast('Cập nhật ảnh đại diện thành công!', 'success');
            user.avatar = data.avatar_url;
            localStorage.setItem('restaurant_user', JSON.stringify(user));
            const img = document.getElementById('profile-avatar-img');
            if (img) img.src = data.avatar_url + '?t=' + Date.now();
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
};

/* =========================================
   CHANGE PASSWORD
   ========================================= */

/** Đổi mật khẩu */
window.changePassword = function() {
    const oldPass     = document.getElementById('old_pass')?.value;
    const newPass     = document.getElementById('new_pass')?.value;
    const confirmPass = document.getElementById('confirm_pass')?.value;

    if (newPass !== confirmPass) { showToast('Mật khẩu xác nhận không khớp!', 'error'); return; }
    if (newPass.length < 6)      { showToast('Mật khẩu mới phải có ít nhất 6 ký tự!', 'error'); return; }

    const submitBtn = document.querySelector('form[onsubmit*="changePassword"] button');
    setLoading(submitBtn, true, 'Đang đổi...');

    fetch('api/auth/change_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ old_password: oldPass, new_password: newPass })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            ['old_pass','new_pass','confirm_pass'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(err => { showToast('Lỗi kết nối server', 'error'); console.error(err); })
    .finally(() => setLoading(submitBtn, false));
};

/* =========================================
   ORDER & BOOKING HISTORY
   ========================================= */

let currentOrderPage = 1;
let currentBookingPage = 1;

/**
 * Tải lịch sử đơn hàng và đặt bàn
 * @param {number} o_page  Trang đơn hàng (undefined thì lấy trang hiện tại)
 * @param {number} b_page  Trang đặt bàn (undefined thì lấy trang hiện tại)
 */
window.loadUserHistory = function(o_page, b_page) {
    if (o_page !== undefined) currentOrderPage = o_page;
    if (b_page !== undefined) currentBookingPage = b_page;

    const user = getCurrentUser();
    if (!user) return;

    const orderList   = document.getElementById('order-history-list');
    const bookingList = document.getElementById('booking-history-list');

    if (orderList)   orderList.innerHTML   = '<div class="spinner"></div> Đang tải đơn hàng...';
    if (bookingList) bookingList.innerHTML = '<div class="spinner"></div> Đang tải lịch đặt bàn...';

    fetch(`api/user/get_user_history.php?o_page=${currentOrderPage}&b_page=${currentBookingPage}`)
        .then(res => res.json())
        .then(data => {
            // Xử lý session expired
            if (data.error === 'Unauthorized') {
                showToast('Phiên đăng nhập hết hạn. Vui lòng đăng nhập lại.', 'error');
                localStorage.removeItem('restaurant_user');
                setTimeout(() => window.location.href = 'login.html', 1500);
                return;
            }

            // Render đơn hàng
            if (orderList) {
                if (data.orders?.length > 0) {
                    const statusLabel = (s) => s === 'paid' ? 'Đã thanh toán' : (s === 'cancelled' ? 'Đã hủy' : 'Chờ thanh toán');
                    const statusBadge = (s) => s === 'paid' ? 'success' : (s === 'cancelled' ? 'danger' : 'warning');

                    const ordersHtml = data.orders.map(o => `
                        <div class="history-item" style="border:1px solid #eee;padding:10px;margin-bottom:10px;border-radius:8px;">
                            <div style="display:flex;justify-content:space-between;">
                                <strong>Đơn #${o.id}</strong>
                                <span class="badge badge-${statusBadge(o.status)}">${statusLabel(o.status)}</span>
                            </div>
                            <p>Tổng: ${formatCurrency(o.total_amount)} - ${o.payment_method === 'bank_transfer' ? 'Chuyển khoản' : 'Tiền mặt'}</p>
                            <small class="text-muted">${o.created_at}</small>
                            <div style="margin-top:8px;text-align:right;">
                                <button onclick="viewOrderDetails(${o.id})" style="background:#e67e22;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;font-size:12px;">Xem chi tiết</button>
                                ${o.status === 'paid'
                                    ? (o.is_reviewed > 0
                                        ? `<button disabled style="background:#95a5a6;color:white;border:none;padding:5px 10px;border-radius:4px;font-size:12px;margin-left:5px;cursor:default;">Đã đánh giá</button>`
                                        : `<button onclick="openReviewModal(${o.id})" style="background:#2ecc71;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;font-size:12px;margin-left:5px;">Đánh giá</button>`)
                                    : ''}
                            </div>
                        </div>
                    `).join('');

                    let paginationHtml = '';
                    if (data.pagination?.total_pages > 1) {
                        const { current_page, total_pages } = data.pagination;
                        paginationHtml = `
                            <div class="premium-pagination">
                                <button class="btn-page" ${current_page <= 1 ? 'disabled' : ''} onclick="loadUserHistory(${current_page - 1}, undefined)">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                                </button>
                                <div class="page-info">
                                    <span class="current">${current_page}</span>
                                    <span>/ ${total_pages}</span>
                                </div>
                                <button class="btn-page" ${current_page >= total_pages ? 'disabled' : ''} onclick="loadUserHistory(${current_page + 1}, undefined)">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                </button>
                            </div>`;
                    }
                    orderList.innerHTML = ordersHtml + paginationHtml;
                } else {
                    orderList.innerHTML = '<p>Chưa có đơn hàng nào.</p>';
                }
            }

            // Render lịch sử đặt bàn
            if (bookingList) {
                if (data.bookings?.length > 0) {
                    const bStatusLabel = (s) => s === 'cancelled' ? 'Đã hủy' : (s === 'confirmed' ? 'Đã xác nhận' : 'Chờ xác nhận');
                    const bStatusBadge = (s) => s === 'cancelled' ? 'danger' : (s === 'confirmed' ? 'success' : 'warning');

                    const bookingsHtml = data.bookings.map(b => `
                        <div class="history-item" style="border:1px solid #eee;padding:10px;margin-bottom:10px;border-radius:8px;">
                            <div style="display:flex;justify-content:space-between;">
                                <strong>${b.date} - ${b.time.substring(0, 5)}</strong>
                                <span class="badge badge-${bStatusBadge(b.status)}">${bStatusLabel(b.status)}</span>
                            </div>
                            <p>Bàn: <strong>${b.table_name || b.table_number || 'Chưa xếp'}</strong> - ${b.floor || ''}</p>
                            <p>Khách: ${b.guests} người</p>
                        </div>
                    `).join('');
                    
                    let bPaginationHtml = '';
                    if (data.booking_pagination?.total_pages > 1) {
                        const { current_page, total_pages } = data.booking_pagination;
                        bPaginationHtml = `
                            <div class="premium-pagination">
                                <button class="btn-page" ${current_page <= 1 ? 'disabled' : ''} onclick="loadUserHistory(undefined, ${current_page - 1})">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                                </button>
                                <div class="page-info">
                                    <span class="current">${current_page}</span>
                                    <span>/ ${total_pages}</span>
                                </div>
                                <button class="btn-page" ${current_page >= total_pages ? 'disabled' : ''} onclick="loadUserHistory(undefined, ${current_page + 1})">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                </button>
                            </div>`;
                    }
                    bookingList.innerHTML = bookingsHtml + bPaginationHtml;
                } else {
                    bookingList.innerHTML = '<p>Chưa có lịch đặt bàn nào.</p>';
                }
            }
        })
        .catch(err => {
            console.error(err);
            if (orderList) orderList.innerHTML = 'Lỗi tải dữ liệu. (Vui lòng đăng nhập lại)';
        });
};

/* =========================================
   ORDER DETAIL MODAL
   ========================================= */

/** Xem chi tiết đơn hàng */
window.viewOrderDetails = function(orderId) {
    const modal   = document.getElementById('order-detail-modal');
    const content = document.getElementById('modal-order-items');
    const titleId = document.getElementById('modal-order-id');

    if (modal)   modal.style.display   = 'block';
    if (titleId) titleId.textContent   = orderId;
    if (content) content.innerHTML     = '<div class="spinner"></div> Đang tải chi tiết...';

    fetch(`api/user/get_order_details.php?order_id=${orderId}`)
        .then(res => res.json())
        .then(data => {
            if (!content) return;
            if (!data.success) { content.innerHTML = `<p style="color:red;">Lỗi: ${data.message}</p>`; return; }
            if (!data.data?.length) { content.innerHTML = '<p>Không có món ăn nào trong đơn này.</p>'; return; }

            let total = 0;
            let html  = '<table style="width:100%;border-collapse:collapse;">';
            html += '<tr style="background:#f1f1f1;"><th style="padding:8px;text-align:left;">Món ăn</th><th style="padding:8px;text-align:center;">SL</th><th style="padding:8px;text-align:right;">Đơn giá</th><th style="padding:8px;text-align:right;">Thành tiền</th></tr>';

            data.data.forEach(item => {
                const subtotal = item.quantity * item.unit_price;
                total += subtotal;
                html += `
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:8px;display:flex;align-items:center;gap:10px;">
                            <img src="${item.image || 'photo/default-food.png'}" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">
                            <span>${item.name}</span>
                        </td>
                        <td style="padding:8px;text-align:center;">${item.quantity}</td>
                        <td style="padding:8px;text-align:right;">${formatCurrency(item.unit_price)}</td>
                        <td style="padding:8px;text-align:right;font-weight:bold;">${formatCurrency(subtotal)}</td>
                    </tr>`;
            });
            html += `<tr><td colspan="3" style="padding:10px;text-align:right;font-weight:bold;">TỔNG CỘNG:</td><td style="padding:10px;text-align:right;font-weight:bold;color:#e67e22;font-size:1.1em;">${formatCurrency(total)}</td></tr>`;
            html += '</table>';
            content.innerHTML = html;
        })
        .catch(err => {
            console.error(err);
            if (content) content.innerHTML = '<p style="color:red;">Lỗi kết nối server.</p>';
        });
};

/** Đóng modal chi tiết đơn hàng */
window.closeOrderModal = function() {
    const modal = document.getElementById('order-detail-modal');
    if (modal) modal.style.display = 'none';
};

// Khởi tạo khi DOM sẵn sàng
document.addEventListener('DOMContentLoaded', () => {
    loadUserProfile();
});
