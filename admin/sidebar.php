<script src="../js/theme.js"></script>
<link rel="stylesheet" href="../css/pages/admin_page.css">
<div class="sidebar">
<div class="brand">
<img src="../photo/favicon.png" alt="Logo">
Dượng Bầu
</div>
<?php
$sidebarMenuItems = require dirname(__DIR__) . '/config/sidebar_menu.php';
$currentFile = basename($_SERVER['PHP_SELF']);
foreach ($sidebarMenuItems as $item):
$isActive = ($currentFile == $item['link']) ? 'active' : '';
?>
<a href="<?php echo htmlspecialchars($item['link']); ?>" class="nav-item <?php echo $isActive; ?>">
<span><?php echo $item['icon']; ?></span> <?php echo htmlspecialchars($item['title']); ?>
</a>
<?php endforeach; ?>
<a href="#" class="nav-item theme-toggle-btn">
<span class="sun-icon">☀️</span>
<span class="moon-icon">🌙</span>
<span>Chế độ sáng/tối</span>
</a>
<a href="#" data-action="admin-logout" class="nav-item">
<span>🚪</span> Đăng xuất
</a>
</div>
<script src="../js/admin-common.js" defer></script>
