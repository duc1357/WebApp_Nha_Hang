// js/utils.js – Tiện ích dùng chung, CSRF, fetch interceptor
// Nạp file này ĐẦU TIÊN trước tất cả module khác

'use strict';

/* =========================================
   GLOBAL STATE
   ========================================= */
window.csrfToken     = '';
window.appliedVoucher = null;

/* =========================================
   CSRF INIT & FETCH INTERCEPTOR
   ========================================= */
(async function initCsrf() {
    try {
        const res  = await window._originalFetch('api/auth/get_csrf.php');
        const data = await res.json();
        if (data.success) {
            window.csrfToken = data.csrf_token;
        }
    } catch (e) {
        console.error('CSRF Init fail', e);
    }
})();

// Lưu lại fetch gốc trước khi override
window._originalFetch = window.fetch;

// Global Fetch Interceptor: tự động đính kèm CSRF Token vào mọi mutating request
window.fetch = async function(url, options = {}) {
    const mutatingMethods = ['POST', 'PUT', 'DELETE', 'PATCH'];
    if (options.method && mutatingMethods.includes(options.method.toUpperCase())) {
        options.headers = options.headers || {};
        if (options.headers instanceof Headers) {
            options.headers.append('X-CSRF-Token', window.csrfToken);
        } else {
            options.headers['X-CSRF-Token'] = window.csrfToken;
        }
    }
    return window._originalFetch(url, options);
};

/* =========================================
   FORMAT & VALIDATION HELPERS
   ========================================= */

/**
 * Định dạng số tiền theo chuẩn Việt Nam
 * @param {number} amount
 * @returns {string}
 */
window.formatCurrency = function(amount) {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
};

/**
 * Validate số điện thoại Việt Nam (10 số, bắt đầu 0[3|5|7|8|9])
 * @param {string} phone
 * @returns {boolean}
 */
window.validatePhone = function(phone) {
    return /^(0[3|5|7|8|9])+([0-9]{8})$/.test(phone);
};

/**
 * Validate ngày – phải từ hôm nay trở đi
 * @param {string} dateStr
 * @returns {boolean}
 */
window.validateDate = function(dateStr) {
    const selected = new Date(dateStr);
    const today    = new Date();
    today.setHours(0, 0, 0, 0);
    return selected >= today;
};

/* =========================================
   UI HELPERS
   ========================================= */

/**
 * Hiển thị toast notification
 * @param {string} message
 * @param {'info'|'success'|'error'|'warning'} type
 */
window.showToast = function(message, type = 'info') {
    const existing = document.querySelector('.toast-notification');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
};

/**
 * Đặt trạng thái loading cho button
 * @param {HTMLElement|null} btn
 * @param {boolean} isLoading
 * @param {string} text
 */
window.setLoading = function(btn, isLoading, text = 'Đang xử lý...') {
    if (!btn) return;
    if (isLoading) {
        btn.dataset.originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner"></span> ${text}`;
    } else {
        btn.disabled = false;
        btn.innerHTML = btn.dataset.originalText || 'Gửi';
    }
};

/**
 * Sao chép nội dung của element ra clipboard
 * @param {string} elementId
 */
window.copyToClipboard = function(elementId) {
    const text = document.getElementById(elementId)?.textContent || '';
    navigator.clipboard.writeText(text)
        .then(() => showToast('Đã sao chép: ' + text, 'success'))
        .catch(err => console.error('Lỗi sao chép', err));
};

/* =========================================
   AUTH HELPERS
   ========================================= */

/**
 * Lấy thông tin user hiện tại từ localStorage
 * @returns {Object|null}
 */
window.getCurrentUser = function() {
    try {
        const json = localStorage.getItem('restaurant_user');
        return json ? JSON.parse(json) : null;
    } catch (e) {
        console.error('Lỗi parse user:', e);
        return null;
    }
};

/**
 * Render thông tin user trên header
 */
window.renderUserHeader = function() {
    const user = getCurrentUser();
    const box  = document.getElementById('auth-actions');
    if (!box) return;

    if (user) {
        const firstLetter = user.name ? user.name.charAt(0).toUpperCase() : 'U';
        const avatarHtml  = (user.avatar && user.avatar.trim())
            ? `<img src="${user.avatar}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:2px solid #fff;">`
            : `<div class="user-avatar-btn logged-in" style="width:32px;height:32px;">${firstLetter}</div>`;

        const navAuth = document.createElement('div');
        navAuth.className = 'nav-auth';
        navAuth.style.cssText = 'display:flex;align-items:center;gap:10px;';

        const greeting = document.createElement('span');
        greeting.style.cssText = 'color:white;font-weight:600;font-size:15px;line-height:1;';
        greeting.textContent = `Xin chào, ${user.name}`;

        const avatarDiv = document.createElement('div');
        avatarDiv.onclick = () => location.href = 'profile.html';
        avatarDiv.title   = 'Vào trang cá nhân';
        avatarDiv.style.cursor = 'pointer';
        avatarDiv.innerHTML = avatarHtml;

        navAuth.appendChild(greeting);
        navAuth.appendChild(avatarDiv);
        box.innerHTML = '';
        box.appendChild(navAuth);
    } else {
        box.innerHTML = `
            <div class="nav-auth">
                <div class="user-avatar-btn" onclick="location.href='login.html'" title="Đăng nhập">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                </div>
            </div>`;
    }
};

// Khởi tạo header khi DOM sẵn sàng
document.addEventListener('DOMContentLoaded', () => {
    renderUserHeader();
});
