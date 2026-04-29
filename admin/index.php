<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Admin Login - Dượng Bầu Restaurant</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: #f4f6f9;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .login-card {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .logo {
            font-size: 24px;
            font-weight: 700;
            color: #e67e22;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #7f8c8d;
            margin-bottom: 30px;
            font-size: 14px;
        }
        input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-family: inherit;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #e67e22;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover { background: #d35400; }
        .error-msg {
            color: #e74c3c;
            font-size: 13px;
            margin-top: 10px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo">DB Admin</div>
        <div class="subtitle">Đăng nhập hệ thống quản trị</div>
        
        <form onsubmit="handleLogin(event)">
            <input type="email" id="email" placeholder="Email quản trị viên" required>
            <input type="password" id="password" placeholder="Mật khẩu" required>
            <button type="submit" id="btnLogin">Đăng Nhập</button>
        </form>
        <div class="error-msg" id="errorMsg">Thông tin đăng nhập không đúng</div>
    </div>

    <script>
        let csrfToken = '';
        (async function initCsrf() {
            try {
                const res = await fetch('../api/auth/get_csrf.php');
                const data = await res.json();
                if (data.success) {
                    csrfToken = data.csrf_token;
                }
            } catch (e) { console.error('CSRF Init fail', e); }
        })();

        // Fetch Interceptor
        const originalFetch = window.fetch;
        window.fetch = async function(url, options = {}) {
            if (options.method && ['POST', 'PUT', 'DELETE'].includes(options.method.toUpperCase())) {
                if (!options.headers) options.headers = {};
                if (options.headers instanceof Headers) {
                    options.headers.append('X-CSRF-Token', csrfToken);
                } else {
                    options.headers['X-CSRF-Token'] = csrfToken;
                }
            }
            return originalFetch(url, options);
        };


        async function handleLogin(e) {
            e.preventDefault();
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const btn = document.getElementById('btnLogin');
            const errDiv = document.getElementById('errorMsg');

            btn.disabled = true;
            btn.textContent = "Đang xử lý...";
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
            } catch (err) {
                console.error(err);
                errDiv.textContent = "Lỗi kết nối server";
                errDiv.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.textContent = "Đăng Nhập";
            }
        }
    </script>
</body>
</html>
