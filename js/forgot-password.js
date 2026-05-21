'use strict';

let csrfToken = '';
let currentEmail = '';
let confirmedOtp = '';
const originalFetch = window.fetch.bind(window);

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

function showMsg(msg, type) {
    const el = document.getElementById('alertMsg');
    el.className = `alert alert-${type}`;
    el.textContent = msg;
    el.style.display = 'block';
}

function showStep(n) {
    document.querySelectorAll('.step').forEach((step) => step.classList.remove('active'));
    document.getElementById('step' + n).classList.add('active');
    document.getElementById('alertMsg').style.display = 'none';
}

function setLoading(btnId, loading) {
    const btn = document.getElementById(btnId);
    if (!btn) return;

    if (loading) {
        btn.dataset.text = btn.textContent;
        btn.textContent = 'Đang xử lý...';
        btn.disabled = true;
    } else {
        btn.textContent = btn.dataset.text || btn.textContent;
        btn.disabled = false;
    }
}

async function sendOtp() {
    const email = document.getElementById('email').value.trim();
    if (!email) {
        showMsg('Vui lòng nhập email', 'danger');
        return;
    }

    setLoading('btnSend', true);

    try {
        const res = await fetch('api/auth/send_otp.php', {
            method: 'POST',
            body: JSON.stringify({ email }),
        });
        const data = await res.json();

        if (data.success) {
            currentEmail = email;
            showStep(2);
        } else {
            showMsg(data.message, 'danger');
        }
    } catch {
        showMsg('Lỗi kết nối server', 'danger');
    } finally {
        setLoading('btnSend', false);
    }
}

async function verifyOtp() {
    const otp = document.getElementById('otp').value.trim();
    if (otp.length < 6) {
        showMsg('Nhập OTP 6 số', 'danger');
        return;
    }

    setLoading('btnVerify', true);

    try {
        const res = await fetch('api/auth/verify_otp.php', {
            method: 'POST',
            body: JSON.stringify({ email: currentEmail, otp }),
        });
        const data = await res.json();

        if (data.success) {
            confirmedOtp = otp;
            showStep(3);
        } else {
            showMsg(data.message, 'danger');
        }
    } catch {
        showMsg('Lỗi kết nối', 'danger');
    } finally {
        setLoading('btnVerify', false);
    }
}

async function resetPassword() {
    const p1 = document.getElementById('newPass').value;
    const p2 = document.getElementById('confirmPass').value;

    if (p1.length < 6) {
        showMsg('Mật khẩu tối thiểu 6 ký tự', 'danger');
        return;
    }
    if (p1 !== p2) {
        showMsg('Mật khẩu không khớp', 'danger');
        return;
    }

    setLoading('btnReset', true);

    try {
        const res = await fetch('api/auth/reset_password.php', {
            method: 'POST',
            body: JSON.stringify({ email: currentEmail, otp: confirmedOtp, password: p1 }),
        });
        const data = await res.json();

        if (data.success) {
            showStep(4);
        } else {
            showMsg(data.message, 'danger');
        }
    } catch {
        showMsg('Lỗi kết nối', 'danger');
    } finally {
        setLoading('btnReset', false);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('btnSend')?.addEventListener('click', sendOtp);
    document.getElementById('btnVerify')?.addEventListener('click', verifyOtp);
    document.getElementById('btnReset')?.addEventListener('click', resetPassword);
    document.getElementById('btnBackToEmail')?.addEventListener('click', (event) => {
        event.preventDefault();
        showStep(1);
    });
    document.getElementById('btnGoLogin')?.addEventListener('click', () => {
        window.location.href = 'login.html';
    });
});
