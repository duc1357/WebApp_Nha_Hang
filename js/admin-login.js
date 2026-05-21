'use strict';

let csrfToken = '';

(async function initCsrf() {
    try {
        const res = await fetch('../api/auth/get_csrf.php');
        const data = await res.json();
        if (data.success) {
            csrfToken = data.csrf_token;
        }
    } catch (error) {
        console.error('CSRF Init fail', error);
    }
})();

const originalFetch = window.fetch;
window.fetch = async function(url, options = {}) {
    if (options.method && ['POST', 'PUT', 'DELETE'].includes(options.method.toUpperCase())) {
        if (!options.headers) {
            options.headers = {};
        }
        if (options.headers instanceof Headers) {
            options.headers.append('X-CSRF-Token', csrfToken);
        } else {
            options.headers['X-CSRF-Token'] = csrfToken;
        }
    }
    return originalFetch(url, options);
};

async function handleAdminLogin(event) {
    event.preventDefault();

    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const btn = document.getElementById('btnLogin');
    const errDiv = document.getElementById('errorMsg');

    btn.disabled = true;
    btn.textContent = 'Đang xử lý...';
    errDiv.style.display = 'none';

    try {
        const res = await fetch('../api/admin/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        const data = await res.json();

        if (data.success) {
            window.location.href = 'dashboard.php';
        } else {
            errDiv.textContent = data.message;
            errDiv.style.display = 'block';
        }
    } catch (error) {
        console.error(error);
        errDiv.textContent = 'Lỗi kết nối server';
        errDiv.style.display = 'block';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Đăng Nhập';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('adminLoginForm')?.addEventListener('submit', handleAdminLogin);
});
