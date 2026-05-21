<div class="sidebar">
    <div class="brand">
        <img src="../photo/favicon.png" alt="Logo" style="width: 36px; height: 36px; object-fit: cover; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
        Dượng Bầu
    </div>
    <a href="dashboard.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">Tổng quan</a>
    <a href="orders.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'orders.php') ? 'active' : ''; ?>">Đơn hàng</a>
    <a href="bookings.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'bookings.php') ? 'active' : ''; ?>">Đặt bàn</a>
    <a href="menu.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'menu.php') ? 'active' : ''; ?>">Thực đơn</a>
    <a href="users.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'users.php') ? 'active' : ''; ?>">Tài khoản</a>
    <a href="vouchers.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'vouchers.php') ? 'active' : ''; ?>">Mã Khuyến Mãi</a>
    <a href="logs.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'logs.php') ? 'active' : ''; ?>">Nhật ký</a>
    <a href="#" data-action="admin-logout" class="nav-item" style="margin-top:auto; color: #c0392b;">Đăng xuất</a>
</div>

<script src="../js/admin-common.js" defer></script>


