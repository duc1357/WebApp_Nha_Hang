// js/utils.js – Tiện ích dùng chung, CSRF, fetch interceptor
// Nạp file này ĐẦU TIÊN trước tất cả module khác

'use strict';

/* =========================================
   GLOBAL STATE
   ========================================= */
window.csrfToken     = '';
window.appliedVoucher = null;

// Lưu lại fetch gốc trước khi override
window._originalFetch = window.fetch;

/* =========================================
   CSRF INIT & FETCH INTERCEPTOR
   ========================================= */
window.csrfReady = (async function initCsrf() {
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

// Global Fetch Interceptor: tự động đính kèm CSRF Token vào mọi mutating request
window.fetch = async function(url, options = {}) {
    const mutatingMethods = ['POST', 'PUT', 'DELETE', 'PATCH'];
    if (options.method && mutatingMethods.includes(options.method.toUpperCase())) {
        if (!window.csrfToken && window.csrfReady) {
            await window.csrfReady;
        }
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
 * Sanitize chuỗi để tránh XSS
 * @param {string} str
 * @returns {string}
 */
window.escapeHTML = function(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};

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
    return /^0[35789][0-9]{8}$/.test(phone);
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
    if (existing) {
        existing.classList.remove('show');
        setTimeout(() => existing.remove(), 200);
    }

    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    
    let icon = '';
    let title = 'Thông báo';
    
    if (type === 'success') {
        title = 'Thành công';
        icon = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>`;
    } else if (type === 'error') {
        title = 'Lỗi';
        icon = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>`;
    } else if (type === 'warning') {
        title = 'Cảnh báo';
        icon = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>`;
    } else {
        title = 'Thông tin';
        icon = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>`;
    }

    toast.innerHTML = `
        <div class="toast-accent-bar"></div>
        <div class="toast-icon-wrapper">${icon}</div>
        <div class="toast-body">
            <span class="toast-title">${title}</span>
            <span class="toast-message">${escapeHTML(message)}</span>
        </div>
        <button class="toast-close-btn" aria-label="Đóng thông báo">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
    `;

    document.body.appendChild(toast);

    // Gắn sự kiện click trực tiếp bằng JS cho nút đóng (khắc phục lỗi inline onclick)
    const closeBtn = toast.querySelector('.toast-close-btn');
    if (closeBtn) {
        closeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            toast.classList.remove('show');
            setTimeout(() => {
                if (toast.parentNode) toast.remove();
            }, 400);
        });
    }

    setTimeout(() => toast.classList.add('show'), 50);

    let autoClose = setTimeout(() => {
        if (toast.parentNode) {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 400);
        }
    }, 4000);

    toast.addEventListener('mouseenter', () => clearTimeout(autoClose));
    toast.addEventListener('mouseleave', () => {
        autoClose = setTimeout(() => {
            if (toast.parentNode) {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 400);
            }
        }, 1500);
    });
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

window.fetchJson = async function(url, options = {}) {
    const res = await fetch(url, options);
    const data = await res.json().catch(() => null);
    if (!data) {
        throw new Error('Phản hồi không hợp lệ từ máy chủ');
    }
    return data;
};

window.renderState = function(container, message, type = 'empty') {
    if (!container) return;
    const className = type === 'error' ? 'state-message state-error' : 'state-message state-empty';
    container.innerHTML = `<div class="${className}" role="status">${escapeHTML(message)}</div>`;
};

window.renderEmptyState = function(container, message = 'Chưa có dữ liệu.') {
    renderState(container, message, 'empty');
};

window.renderErrorState = function(container, message = 'Không thể tải dữ liệu. Vui lòng thử lại.') {
    renderState(container, message, 'error');
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
