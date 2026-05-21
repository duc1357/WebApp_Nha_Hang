<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="../photo/favicon.png" type="image/png">
    <meta charset="UTF-8">
    <title>Admin Login - Dượng Bầu Restaurant</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        
        <form id="adminLoginForm">
            <input type="email" id="email" placeholder="Email quản trị viên" required>
            <input type="password" id="password" placeholder="Mật khẩu" required>
            <button type="submit" id="btnLogin">Đăng Nhập</button>
        </form>
        <div class="error-msg" id="errorMsg">Thông tin đăng nhập không đúng</div>
    </div>

    <script src="../js/admin-login.js" defer></script>
</body>
</html>
