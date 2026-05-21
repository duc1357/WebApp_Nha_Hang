'use strict';

let csrfToken = '';
const originalFetch = window.fetch.bind(window);

(function redirectIfLoggedIn() {
    const userJson = localStorage.getItem('restaurant_user');
    if (userJson) {
        window.location.href = 'index.html';
    }
})();

(async function initCsrf() {
    try {
        const res = await originalFetch('api/auth/get_csrf.php');
        const data = await res.json();
        if (data.success) {
            csrfToken = data.csrf_token;
        }
    } catch (e) {
        console.error('CSRF Init fail', e);
    }
})();

window.fetch = async function(url, options = {}) {
    if (options.method && ['POST', 'PUT', 'DELETE'].includes(options.method.toUpperCase())) {
        options.headers = options.headers || {};
        if (options.headers instanceof Headers) {
            options.headers.append('X-CSRF-Token', csrfToken);
        } else {
            options.headers['X-CSRF-Token'] = csrfToken;
        }
    }
    return originalFetch(url, options);
};

function setMessage(el, text, type) {
    el.textContent = text || '';
    el.classList.remove('error', 'success');
    if (type) el.classList.add(type);
}

function saveUser(data) {
    localStorage.setItem('restaurant_user', JSON.stringify(data));
}

function isStrongPassword(password) {
    return password.length >= 8 && /[A-Z]/.test(password) && /\d/.test(password);
}

function handleLogin() {
    const msg = document.getElementById('login-message');
    const btn = document.getElementById('btn-login');
    setMessage(msg, '', null);

    const identifier = document.getElementById('login-identifier').value.trim();
    const password = document.getElementById('login-password').value.trim();

    if (!identifier || !password) {
        setMessage(msg, 'Vui lòng nhập đầy đủ thông tin.', 'error');
        return;
    }

    btn.disabled = true;
    const oldText = btn.textContent;
    btn.textContent = 'Đang đăng nhập...';

    fetch('api/auth/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ identifier, password }),
    })
        .then((res) => res.json())
        .then((data) => {
            if (!data.success) {
                setMessage(msg, data.message || 'Đăng nhập thất bại.', 'error');
                return;
            }

            saveUser(data.user || { identifier });
            setMessage(msg, 'Đăng nhập thành công! Đang chuyển trang...', 'success');

            const role = data.user && data.user.role ? data.user.role : 'user';
            setTimeout(() => {
                window.location.href = role === 'admin' ? 'admin/dashboard.php' : 'index.html';
            }, 600);
        })
        .catch((err) => {
            console.error(err);
            setMessage(msg, 'Lỗi kết nối máy chủ.', 'error');
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = oldText;
        });
}

function handleRegister() {
    const msg = document.getElementById('register-message');
    const btn = document.getElementById('btn-register');
    setMessage(msg, '', null);

    const user = {
        name: document.getElementById('reg-name').value.trim(),
        phone: document.getElementById('reg-phone').value.trim(),
        email: document.getElementById('reg-email').value.trim(),
        password: document.getElementById('reg-password').value.trim(),
    };

    if (!user.name || !user.phone || !user.password) {
        setMessage(msg, 'Vui lòng điền đủ Họ tên, SĐT và Mật khẩu.', 'error');
        return;
    }

    if (!isStrongPassword(user.password)) {
        setMessage(msg, 'Mật khẩu cần tối thiểu 8 ký tự, có chữ hoa và số.', 'error');
        return;
    }

    btn.disabled = true;
    const oldText = btn.textContent;
    btn.textContent = 'Đang đăng ký...';

    fetch('api/auth/register.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(user),
    })
        .then((res) => res.json())
        .then((data) => {
            if (!data.success) {
                setMessage(msg, data.message || 'Đăng ký thất bại.', 'error');
                return;
            }
            setMessage(msg, 'Đăng ký thành công! Bạn hãy chuyển sang tab Đăng nhập.', 'success');
        })
        .catch(() => {
            setMessage(msg, 'Lỗi kết nối máy chủ!', 'error');
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = oldText;
        });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.auth-tab').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.auth-tab').forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');

            const tab = btn.dataset.tab;
            document.querySelectorAll('.auth-pane').forEach((p) => p.classList.remove('active'));
            document.getElementById('tab-' + tab).classList.add('active');

            document.querySelectorAll('.form-message').forEach((m) => {
                m.textContent = '';
                m.classList.remove('error', 'success');
            });
        });
    });

    document.getElementById('btn-login')?.addEventListener('click', handleLogin);
    document.getElementById('btn-register')?.addEventListener('click', handleRegister);
    document.getElementById('login-password')?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            handleLogin();
        }
    });
});
