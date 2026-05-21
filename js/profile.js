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
    document.querySelector(`.sidebar-btn[data-tab="${tabName}"]`)?.classList.add('active');
    document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
    document.getElementById(`tab-${tabName}`)?.classList.add('active');

    if (tabName === 'orders' || tabName === 'bookings') loadUserHistory();
};

function syncProfileSummary(user) {
    if (!user) return;

    const displayName = document.getElementById('display-user-name');
    if (displayName) displayName.textContent = user.name || 'Thành viên';

    const role = document.getElementById('profile-role');
    if (role) role.textContent = user.role === 'admin' ? 'Quản trị' : 'Khách hàng';

    const emailSummary = document.getElementById('profile-email-summary');
    if (emailSummary) emailSummary.textContent = user.email || 'Chưa cập nhật';

    const phoneSummary = document.getElementById('profile-phone-summary');
    if (phoneSummary) phoneSummary.textContent = user.phone || 'Chưa cập nhật';
}

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
    if (img) img.src = /^photo\/[A-Za-z0-9._/\-]+$/.test(user.avatar || '') ? user.avatar : 'photo/default-user.png';

    syncProfileSummary(user);
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
            syncProfileSummary(newUser);
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
    if (newPass.length < 8 || !/[A-Z]/.test(newPass) || !/\d/.test(newPass)) {
        showToast('Mật khẩu cần tối thiểu 8 ký tự, có chữ hoa và số.', 'error');
        return;
    }

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

    if (orderList) renderState(orderList, 'Đang tải đơn hàng...', 'empty');
    if (bookingList) renderState(bookingList, 'Đang tải lịch đặt bàn...', 'empty');

    fetchJson(`api/user/get_user_history.php?o_page=${currentOrderPage}&b_page=${currentBookingPage}`)
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
                        <div class="history-item">
                            <div class="history-topline">
                                <strong>Đơn #${o.id}</strong>
                                <span class="badge badge-${statusBadge(o.status)}">${statusLabel(o.status)}</span>
                            </div>
                            <div class="history-meta">
                                <span>Tổng: <strong>${formatCurrency(o.total_amount)}</strong></span>
                                <span>${o.payment_method === 'bank_transfer' ? 'Chuyển khoản' : 'Tiền mặt'} · ${o.created_at}</span>
                            </div>
                            <div class="history-actions">
                                <button class="profile-action-btn primary" data-order-details="${o.id}">Xem chi tiết</button>
                                ${o.status === 'paid'
                                    ? (o.is_reviewed > 0
                                        ? `<button class="profile-action-btn muted" disabled>Đã đánh giá</button>`
                                        : `<button class="profile-action-btn success" data-review-order="${o.id}">Đánh giá</button>`)
                                    : ''}
                            </div>
                        </div>
                    `).join('');

                    let paginationHtml = '';
                    if (data.pagination?.total_pages > 1) {
                        const { current_page, total_pages } = data.pagination;
                        paginationHtml = `
                            <div class="premium-pagination">
                                <button class="btn-page" ${current_page <= 1 ? 'disabled' : ''} data-order-page="${current_page - 1}">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                                </button>
                                <div class="page-info">
                                    <span class="current">${current_page}</span>
                                    <span>/ ${total_pages}</span>
                                </div>
                                <button class="btn-page" ${current_page >= total_pages ? 'disabled' : ''} data-order-page="${current_page + 1}">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                </button>
                            </div>`;
                    }
                    orderList.innerHTML = ordersHtml + paginationHtml;
                } else {
                    renderEmptyState(orderList, 'Chưa có đơn hàng nào.');
                }
            }

            // Render lịch sử đặt bàn
            if (bookingList) {
                if (data.bookings?.length > 0) {
                    const bStatusLabel = (s) => s === 'cancelled' ? 'Đã hủy' : (s === 'confirmed' ? 'Đã xác nhận' : 'Chờ xác nhận');
                    const bStatusBadge = (s) => s === 'cancelled' ? 'danger' : (s === 'confirmed' ? 'success' : 'warning');

                    const bookingsHtml = data.bookings.map(b => `
                        <div class="history-item">
                            <div class="history-topline">
                                <strong>${b.date} - ${b.time.substring(0, 5)}</strong>
                                <span class="badge badge-${bStatusBadge(b.status)}">${bStatusLabel(b.status)}</span>
                            </div>
                            <div class="history-meta">
                                <span>Bàn: <strong>${escapeHTML(b.table_name || b.table_number || 'Chưa xếp')}</strong> - ${escapeHTML(b.floor || '')}</span>
                                <span>Khách: ${b.guests} người</span>
                            </div>
                        </div>
                    `).join('');
                    
                    let bPaginationHtml = '';
                    if (data.booking_pagination?.total_pages > 1) {
                        const { current_page, total_pages } = data.booking_pagination;
                        bPaginationHtml = `
                            <div class="premium-pagination">
                                <button class="btn-page" ${current_page <= 1 ? 'disabled' : ''} data-booking-page="${current_page - 1}">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                                </button>
                                <div class="page-info">
                                    <span class="current">${current_page}</span>
                                    <span>/ ${total_pages}</span>
                                </div>
                                <button class="btn-page" ${current_page >= total_pages ? 'disabled' : ''} data-booking-page="${current_page + 1}">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                </button>
                            </div>`;
                    }
                    bookingList.innerHTML = bookingsHtml + bPaginationHtml;
                } else {
                    renderEmptyState(bookingList, 'Chưa có lịch đặt bàn nào.');
                }
            }
        })
        .catch(err => {
            console.error(err);
            if (orderList) renderErrorState(orderList, 'Lỗi tải dữ liệu. Vui lòng đăng nhập lại.');
            if (bookingList) renderErrorState(bookingList, 'Lỗi tải dữ liệu. Vui lòng đăng nhập lại.');
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
            if (!data.success) { content.innerHTML = `<p style="color:red;">Lỗi: ${escapeHTML(data.message)}</p>`; return; }
            if (!data.data?.length) { content.innerHTML = '<p>Không có món ăn nào trong đơn này.</p>'; return; }

            let total = 0;
            let html  = '<table style="width:100%;border-collapse:collapse;">';
            html += '<tr style="background:#f1f1f1;"><th style="padding:8px;text-align:left;">Món ăn</th><th style="padding:8px;text-align:center;">SL</th><th style="padding:8px;text-align:right;">Đơn giá</th><th style="padding:8px;text-align:right;">Thành tiền</th></tr>';

            data.data.forEach(item => {
                const subtotal = item.quantity * item.unit_price;
                total += subtotal;
                const image = String(item.image || 'photo/default-food.png');
                const safeImage = /^photo\/[A-Za-z0-9._/\-]+$/.test(image) ? image : 'photo/default-food.png';
                html += `
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:8px;display:flex;align-items:center;gap:10px;">
                            <img src="${safeImage}" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">
                            <span>${escapeHTML(item.name)}</span>
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

document.addEventListener('click', (event) => {
    const detailButton = event.target.closest('[data-order-details]');
    if (detailButton) {
        viewOrderDetails(Number(detailButton.dataset.orderDetails));
        return;
    }

    const reviewButton = event.target.closest('[data-review-order]');
    if (reviewButton && typeof openReviewModal === 'function') {
        openReviewModal(Number(reviewButton.dataset.reviewOrder));
        return;
    }

    const orderPageButton = event.target.closest('[data-order-page]');
    if (orderPageButton) {
        loadUserHistory(Number(orderPageButton.dataset.orderPage), undefined);
        return;
    }

    const bookingPageButton = event.target.closest('[data-booking-page]');
    if (bookingPageButton) {
        loadUserHistory(undefined, Number(bookingPageButton.dataset.bookingPage));
    }
});

// Khởi tạo khi DOM sẵn sàng
document.addEventListener('DOMContentLoaded', () => {
    loadUserProfile();
});
