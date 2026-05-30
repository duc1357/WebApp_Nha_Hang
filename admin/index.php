<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="../photo/favicon.png" type="image/png">
    <meta charset="UTF-8">
    <title>Đăng Nhập Quản Trị - Dượng Bầu Restaurant</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #d35400;
            --primary-gradient: linear-gradient(135deg, #e67e22, #c0392b);
            --charcoal: #12100e;
            --charcoal-light: #1c1916;
            --cream: #fbf9f6;
            --cream-dark: #f3ede2;
            --text-main: #f3ede2;
            --text-muted: #a09587;
            --accent-gold: #d4af37;
            --radius-lg: 24px;
            --radius-md: 16px;
            --radius-sm: 10px;
            --transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', 'Outfit', sans-serif;
            background-color: var(--charcoal);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            overflow: hidden;
            position: relative;
        }

        /* Ambient background glow effects */
        .ambient-glow-1 {
            position: absolute;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(211, 84, 0, 0.15) 0%, rgba(211, 84, 0, 0) 70%);
            top: -10%;
            left: -10%;
            z-index: 1;
            filter: blur(50px);
        }

        .ambient-glow-2 {
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(192, 57, 43, 0.12) 0%, rgba(192, 57, 43, 0) 70%);
            bottom: -15%;
            right: -10%;
            z-index: 1;
            filter: blur(60px);
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
            z-index: 10;
        }

        .login-card {
            background: rgba(28, 25, 22, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(211, 84, 0, 0.15);
            padding: 45px 35px 35px 35px;
            border-radius: var(--radius-lg);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.45);
            text-align: center;
            transition: var(--transition);
        }

        .login-card:hover {
            border-color: rgba(211, 84, 0, 0.25);
            box-shadow: 0 35px 80px rgba(0, 0, 0, 0.55);
        }

        .brand-logo-wrapper {
            margin-bottom: 25px;
            display: inline-block;
        }

        .brand-logo-wrapper img {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            border: 2px solid var(--accent-gold);
            box-shadow: 0 8px 25px rgba(211, 84, 0, 0.3);
            transition: var(--transition);
        }

        .login-card:hover .brand-logo-wrapper img {
            transform: scale(1.05) rotate(360deg);
        }

        .logo {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            font-weight: 800;
            color: var(--accent-gold);
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }

        .subtitle {
            color: var(--text-muted);
            margin-bottom: 35px;
            font-size: 13.5px;
            font-weight: 500;
            letter-spacing: 0.2px;
        }

        .form-group {
            position: relative;
            margin-bottom: 20px;
            text-align: left;
        }

        .form-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            pointer-events: none;
            transition: var(--transition);
        }

        input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            background: rgba(18, 16, 14, 0.6) !important;
            color: var(--text-main) !important;
            border: 1.5px solid rgba(211, 84, 0, 0.15) !important;
            border-radius: var(--radius-sm) !important;
            outline: none !important;
            font-family: inherit;
            font-size: 14.5px;
            transition: var(--transition) !important;
        }

        input::placeholder {
            color: rgba(160, 149, 135, 0.6);
        }

        input:focus {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 3px rgba(211, 84, 0, 0.2) !important;
            background: rgba(18, 16, 14, 0.8) !important;
        }

        input:focus + .form-icon {
            color: var(--primary);
        }

        button {
            width: 100%;
            padding: 15px;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 6px 20px rgba(211, 84, 0, 0.2);
            margin-top: 10px;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(211, 84, 0, 0.45);
            filter: brightness(1.05);
        }

        button:active {
            transform: translateY(0);
        }

        .error-msg {
            color: #e74c3c;
            font-size: 13px;
            font-weight: 600;
            margin-top: 20px;
            padding: 10px;
            background: rgba(192, 57, 43, 0.1);
            border: 1px solid rgba(192, 57, 43, 0.2);
            border-radius: 8px;
            display: none;
            align-items: center;
            justify-content: center;
            gap: 8px;
            animation: shake 0.4s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-6px); }
            75% { transform: translateX(6px); }
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 11px;
            color: rgba(160, 149, 135, 0.5);
            letter-spacing: 0.3px;
        }
    </style>
</head>
<body>
    <!-- Ambient backgrounds glow -->
    <div class="ambient-glow-1"></div>
    <div class="ambient-glow-2"></div>

    <div class="login-container">
        <div class="login-card">
            <div class="brand-logo-wrapper">
                <img src="../photo/favicon.png" alt="Logo Dượng Bầu">
            </div>
            <div class="logo">DƯỢNG BẦU</div>
            <div class="subtitle">Cổng Quản Trị Hệ Thống Nhà Hàng</div>
            
            <form id="adminLoginForm">
                <div class="form-group">
                    <input type="email" id="email" placeholder="Email quản trị viên" required autocomplete="email">
                    <div class="form-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    </div>
                </div>
                
                <div class="form-group">
                    <input type="password" id="password" placeholder="Mật khẩu" required autocomplete="current-password">
                    <div class="form-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    </div>
                </div>
                
                <button type="submit" id="btnLogin">Đăng Nhập Hệ Thống</button>
            </form>
            
            <div class="error-msg" id="errorMsg">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                Thông tin đăng nhập không chính xác
            </div>
        </div>
        
        <div class="footer">
            © 2026 Dượng Bầu Restaurant. Cổng Quản Trị Bảo Mật Cao.
        </div>
    </div>

    <script src="../js/admin-login.js" defer></script>
</body>
</html>
