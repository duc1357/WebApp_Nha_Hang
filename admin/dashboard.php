<?php
// admin/dashboard.php
require_once 'auth_check.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="../photo/favicon.png" type="image/png">
    <meta charset="UTF-8">
    <title>Admin Dashboard - Nhà Hàng Cơm Quê Dượng Bầu</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');

        :root {
            --primary: #e67e22;
            --primary-dark: #d35400;
            --text-main: #2c3e50;
            --bg: #f4f6f9;
            --card-bg: #ffffff;
            --border: #eee;
            --radius: 12px;
            --shadow: 0 4px 12px rgba(0,0,0,0.03);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */
        .sidebar {
            width: 250px;
            background: white;
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            padding: 24px;
            position: fixed;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 100;
        }
        .brand {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 40px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .nav-item {
            padding: 12px 16px;
            margin-bottom: 4px;
            color: #7f8c8d;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            display: block;
            transition: 0.2s;
        }
        .nav-item:hover, .nav-item.active {
            background: #fff0e6;
            color: var(--primary);
        }

        /* MAIN CONTENT */
        .main-content {
            margin-left: 250px;
            padding: 40px;
            width: 100%;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .stat-card h3 {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #7f8c8d;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 4px;
        }
        .stat-note { font-size: 12px; color: #95a5a6; }

        .card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f9f9f9;
        }
        .card-header h3 { font-size: 16px; font-weight: 700; }

        /* TABLES */
        table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 14px; }
        th { text-align: left; padding: 10px; color: #95a5a6; font-weight: 600; font-size: 12px; text-transform: uppercase; }
        td { padding: 12px 10px; border-bottom: 1px solid #f4f4f4; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .res-pending { background: #fff3cd; color: #856404; }
        .res-confirmed { background: #d4edda; color: #155724; }
        .res-cancelled { background: #f8d7da; color: #721c24; }
        .admin-role { background: #e3f2fd; color: #1565c0; }
        
        @media (max-width: 1024px) {
            .stat-grid { grid-template-columns: repeat(2, 1fr); }
            div[style*="grid-template-columns"] { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <!-- HEADER -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
            <div>
                <h2 style="font-size:24px; font-weight:700; color:var(--text-main);">Xin chào, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?> 👋</h2>
                <p style="color:#7f8c8d; margin-top:5px;">Đây là tổng quan tình hình kinh doanh hôm nay.</p>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
                <a href="../api/admin/export_revenue.php" target="_blank" style="font-size:14px; color:white; background:#27ae60; padding:8px 16px; border-radius:8px; text-decoration:none; font-weight:600; border:none; cursor:pointer;">
                    📥 Xuất Excel
                </a>
                <div style="font-size:14px; color:#7f8c8d; background:white; padding:8px 16px; border-radius:8px; border:1px solid #eee;">
                    <?php echo date('d/m/Y'); ?>
                </div>
            </div>
        </div>

        <!-- STATISTICS -->
        <div class="stat-grid">
            <div class="stat-card">
                <h3>Doanh thu hôm nay</h3>
                <div class="stat-value" id="todayRevenue">0 VNĐ</div>
                <div class="stat-note">Đã thanh toán</div>
            </div>
            <div class="stat-card">
                <h3>Đơn hàng hôm nay</h3>
                <div class="stat-value" id="todayOrders">0</div>
                <div class="stat-note">Tất cả trạng thái</div>
            </div>
            <div class="stat-card">
                <h3>Đặt bàn hôm nay</h3>
                <div class="stat-value" id="todayBookings">0</div>
                <div class="stat-note">Khách đặt trước</div>
            </div>
            <div class="stat-card">
                <h3>Đơn đang chờ</h3>
                <div class="stat-value" id="pendingOrders" style="color:#e67e22;">0</div>
                <div class="stat-note">Cần xử lý ngay</div>
            </div>
        </div>

        <!-- REVENUE CHART -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h3>Biểu đồ doanh thu (7 ngày)</h3>
            </div>
            <div style="height: 300px;">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:24px;">
            <!-- LEFT COLUMN -->
            <div style="display:flex; flex-direction:column; gap:24px;">
                
                <!-- RECENT ORDERS -->
                <div class="card" style="grid-column: span 2;">
                    <div class="card-header">
                        <h3>Đơn hàng mới nhất</h3>
                        <a href="orders.php" style="font-size:13px; font-weight:600; color:var(--primary); text-decoration:none;">Xem tất cả &rarr;</a>
                    </div>
                    <table id="recentOrdersTable">
                        <thead>
                        <tr>
                            <th>Mã đơn</th>
                            <th>Khách hàng</th>
                            <th>Thanh toán</th>
                            <th>Tiền</th>
                            <th>Trạng thái</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr><td colspan="5">Đang tải...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- RIGHT COLUMN -->
            <div style="display:flex; flex-direction:column; gap:24px;">
                
                <!-- TODAY BOOKINGS -->
                <div class="card">
                    <div class="card-header">
                        <h3>Đặt bàn hôm nay</h3>
                        <a href="bookings.php" style="font-size:13px; font-weight:600; color:var(--primary); text-decoration:none;">Chi tiết &rarr;</a>
                    </div>
                    <div id="miniBookingList" style="display:flex; flex-direction:column; gap:12px;">
                        <!-- JS renders bookings here -->
                        <p style="color:#999; font-size:13px;">Đang tải...</p>
                    </div>
                </div>

                <!-- QUICK ACTIONS -->
                <div class="card">
                    <div class="card-header">
                        <h3>Truy cập nhanh</h3>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <a href="menu.php" style="display:block; padding:12px; background:#f8f9fa; border-radius:8px; text-decoration:none; color:#2c3e50; font-weight:600; border:1px solid #eee; transition:0.2s;">
                            🍽️ Quản lý Thực đơn
                        </a>
                        <a href="users.php" style="display:block; padding:12px; background:#f8f9fa; border-radius:8px; text-decoration:none; color:#2c3e50; font-weight:600; border:1px solid #eee; transition:0.2s;">
                            👥 Quản lý Tài khoản
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>


<script>
let previousPending = 0;

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// (RESTORED) ======= Logout =======
function logout() {
    fetch('../api/auth/logout.php', { method: 'POST' }).finally(() => window.location.href = '../index.html');
}

// ======= Load Statistics =======
function loadStats() {
    fetch('../api/admin/get_stats.php')
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            const fmt = n => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(n);
            
            document.getElementById('todayRevenue').textContent = fmt(data.today_revenue || 0);
            document.getElementById('todayOrders').textContent = data.today_orders || 0;
            document.getElementById('todayBookings').textContent = data.today_bookings || 0;
            document.getElementById('pendingOrders').textContent = data.pending_orders || 0;

            // Notification Sound Logic
            const sound = document.getElementById('notif-sound');
            const currentPending = parseInt(data.pending_orders || 0);
            
            // Only play if pending count increases (new order)
            if (currentPending > previousPending && sound) {
                sound.play().catch(() => console.log('Autoplay blocked'));
            }
            previousPending = currentPending;
        })
        .catch(console.error);
}

// ======= Load Mini Bookings (Right Column) =======
function loadBookings() {
    // Just fetch today's bookings for the dashboard widget
    const today = new Date().toISOString().split('T')[0];
    fetch(`../api/admin/get_bookings.php?date=${today}&limit=5`)
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById('miniBookingList');
            container.innerHTML = '';
            
            if (!data.success || !data.bookings.length) {
                container.innerHTML = '<p style="color:#95a5a6; font-size:13px; font-style:italic;">Hôm nay chưa có đặt bàn.</p>';
                return;
            }

            data.bookings.forEach(b => {
                const div = document.createElement('div');
                div.style.cssText = 'display:flex; justify-content:space-between; align-items:center; padding:10px; border:1px solid #f5f5f5; border-radius:8px; background:white;';
                
                let statusColor = '#f39c12';
                let statusText = 'Chờ xác nhận';
                if(b.status === 'confirmed') { statusColor = '#27ae60'; statusText = 'Đã xác nhận'; }
                if(b.status === 'cancelled') { statusColor = '#c0392b'; statusText = 'Đã hủy'; }

                div.innerHTML = `
                    <div style="display:flex; flex-direction:column;">
                        <span style="font-weight:600; font-size:13px;">${escapeHtml(b.name)}</span>
                        <span style="font-size:12px; color:#7f8c8d;">${escapeHtml(b.time)} - ${escapeHtml(b.guests)} khách</span>
                    </div>
                    <div style="text-align:right;">
                         <span style="font-size:11px; font-weight:600; color:${statusColor}; display:block;">${statusText}</span>
                         <span style="font-size:11px; color:#95a5a6;">${escapeHtml(b.phone)}</span>
                    </div>
                `;
                container.appendChild(div);
            });
        })
        .catch(console.error);
}

// ======= Load Recent Orders (Compact) =======
function loadRecentOrders() {
    fetch('../api/admin/get_orders.php?limit=5')
        .then(res => res.json())
        .then(data => {
            const tbody = document.querySelector('#recentOrdersTable tbody');
            tbody.innerHTML = '';
            if (!data.success || !data.orders.length) {
                tbody.innerHTML = '<tr><td colspan="5">Chưa có đơn hàng.</td></tr>';
                return;
            }
            data.orders.forEach(o => {
                let sttColor = '#f39c12';
                let sttText = 'Chờ xử lý';
                if(o.status === 'paid') { sttColor = '#27ae60'; sttText = 'Đã thanh toán'; }
                if(o.status === 'cancelled') { sttColor = '#c0392b'; sttText = 'Đã hủy'; }

                let payText = 'Tiền mặt';
                let payClass = 'cash';
                if(o.payment_method === 'bank_transfer') { payText = 'Chuyển khoản'; payClass = 'bank_transfer'; }

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>#${escapeHtml(o.id)}</strong></td>
                    <td>${escapeHtml(o.customer_name || 'Khách lẻ')}</td>
                    <td><span class="badge tag ${payClass}" style="font-weight:500;">${payText}</span></td>
                    <td style="font-weight:700;">${Number(o.total_amount).toLocaleString('vi-VN')}</td>
                    <td><span style="color:${sttColor}; font-weight:600; font-size:12px;">${sttText}</span></td>
                `;
                tbody.appendChild(tr);
            });
        })
        .catch(console.error);
}

function updateOrderStatus(id, status) {
    if (!confirm('Bạn có chắc chắn muốn thay đổi trạng thái đơn hàng?')) return;
    
    fetch('../api/admin/update_order_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, status })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadRecentOrders(); // Reload list
            loadStats(); // Reload stats
        } else {
            alert('Lỗi: ' + (data.message || 'Không thể cập nhật'));
        }
    })
    .catch(console.error);
}

// (RESTORED) ======= Load Menu Items =======
function loadMenuItems() {
    fetch('../api/menu/get_menu.php')
        .then(res => res.json())
        .then(data => {
            const tbody = document.querySelector('#menuItemsTable tbody');
            tbody.innerHTML = '';

            // Handle API wrapping
            const list = data.data || data.items || [];
            if (!data.success || !list.length) {
                tbody.innerHTML = '<tr><td colspan="6">Chưa có món nào trong thực đơn.</td></tr>';
                return;
            }

            list.forEach(m => {
                const tr = document.createElement('tr');
                const isActive = m.is_active == 1;
                const activeText = isActive ? 'Đang bán' : 'Hết hàng';
                const activeColor = isActive ? 'color:#27ae60;' : 'color:#c0392b;';
                
                // Toggle Button Logic
                const toggleTitle = isActive ? 'Báo hết hàng' : 'Mở bán lại';
                const toggleIcon = isActive 
                    ? '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>' // Ban/Stop
                    : '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>';// Check

                tr.innerHTML = `
                    <td>${escapeHtml(m.id)}</td>
                    <td>${escapeHtml(m.name)}</td>
                    <td>${Number(m.price).toLocaleString('vi-VN')} VNĐ</td>
                    <td style="${activeColor}font-weight:600;">${activeText}</td>
                    <td>
                        <button onclick="editMenuItem(${m.id}, '${m.name}', '${m.description || ''}', ${m.price}, '${m.photo || ''}')" style="margin-right:8px;background:none;border:none;cursor:pointer;color:#3498db;" title="Sửa">
                             <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        </button>
                        <button onclick="toggleMenuStatus(${m.id}, ${m.is_active})" style="margin-right:8px;background:none;border:none;cursor:pointer;color:${isActive ? '#e67e22' : '#27ae60'};" title="${toggleTitle}">
                           ${toggleIcon}
                        </button>
                        <button onclick="deleteMenuItem(${m.id})" style="background:none;border:none;cursor:pointer;color:#c0392b;" title="Xóa">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </button>
                    </td>
                    <td>${m.created_at || ''}</td>
                `;
                tbody.appendChild(tr);
            });
        })
        .catch(err => console.error(err));
}

// === MENU ACTIONS (WITH FILE UPLOAD) ===
function openMenuModal() {
    document.getElementById('menuId').value = '';
    document.getElementById('menuName').value = '';
    document.getElementById('menuDesc').value = '';
    document.getElementById('menuPrice').value = '';
    
    // Reset File Input
    document.getElementById('menuPhotoFile').value = ''; 
    document.getElementById('currentPhotoUrl').value = '';
    document.getElementById('imagePreview').style.display = 'none';

    document.getElementById('modalTitle').textContent = 'Thêm món mới';
    document.getElementById('menuModal').style.display = 'flex';
}

function closeMenuModal() {
    document.getElementById('menuModal').style.display = 'none';
}

function editMenuItem(id, name, desc, price, photo) {
    document.getElementById('menuId').value = id;
    document.getElementById('menuName').value = name;
    document.getElementById('menuDesc').value = desc;
    document.getElementById('menuPrice').value = price;
    
    // Handle Photo
    document.getElementById('menuPhotoFile').value = ''; // Clear file input
    document.getElementById('currentPhotoUrl').value = photo;
    
    const preview = document.getElementById('imagePreview');
    if (photo) {
        preview.style.display = 'block';
        preview.querySelector('img').src = '../' + photo;
    } else {
        preview.style.display = 'none';
    }

    document.getElementById('modalTitle').textContent = 'Cập nhật món';
    document.getElementById('menuModal').style.display = 'flex';
}

function saveMenuItem() {
    const id = document.getElementById('menuId').value;
    const name = document.getElementById('menuName').value;
    const description = document.getElementById('menuDesc').value;
    const price = document.getElementById('menuPrice').value;
    const fileInput = document.getElementById('menuPhotoFile');
    const updatedPhoto = document.getElementById('currentPhotoUrl').value;

    const url = id 
        ? '../api/admin/update_menu_item.php'
        : '../api/admin/add_menu_item.php';

    // Use FormData instead of JSON
    const formData = new FormData();
    if (id) formData.append('id', id);
    formData.append('name', name);
    formData.append('description', description);
    formData.append('price', price);
    
    // If file selected, append it
    if (fileInput.files.length > 0) {
        formData.append('photo', fileInput.files[0]);
    } else if (updatedPhoto) {
        formData.append('photo', updatedPhoto);
    }

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeMenuModal();
            loadMenuItems();
        } else {
            alert('Lỗi: ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Lỗi kết nối server');
    });
}

function toggleMenuStatus(id, currentStatus) {
    const newStatus = currentStatus == 1 ? 0 : 1;
    fetch('../api/admin/update_menu_item.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, is_active: newStatus })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) loadMenuItems();
        else alert('Lỗi trạng thái: ' + data.message);
    });
}

function deleteMenuItem(id) {
    if(!confirm('Bạn chắc chắn muốn xóa món này?')) return;
    fetch('../api/admin/delete_menu_item.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) loadMenuItems();
        else alert('Lỗi xóa: ' + data.message);
    });
}

// ======= Load Users =======
function loadUsers() {
    fetch('../api/admin/get_users.php')
        .then(res => res.json())
        .then(data => {
            const tbody = document.querySelector('#usersTable tbody');
            tbody.innerHTML = '';

            if (!data.success || !data.users || !data.users.length) {
                tbody.innerHTML = '<tr><td colspan="7">Chưa có người dùng.</td></tr>';
                return;
            }

            data.users.forEach(u => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHtml(u.id)}</td>
                    <td>${escapeHtml(u.name || '')}</td>
                    <td>${escapeHtml(u.phone || '')}</td>
                    <td>${escapeHtml(u.email || '')}</td>
                    <td><span class="badge ${u.role === 'admin' ? 'res-confirmed' : 'res-pending'}">${escapeHtml(u.role || 'user')}</span></td>
                    <td>
                        <button onclick="editUser(${u.id}, '${u.name||''}', '${u.phone||''}', '${u.email||''}', '${u.role||'user'}')" style="margin-right:8px;background:none;border:none;cursor:pointer;color:#3498db;" title="Sửa">
                             <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        </button>
                        <button onclick="deleteUser(${u.id})" style="background:none;border:none;cursor:pointer;color:#c0392b;" title="Xóa">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </button>
                    </td>
                    <td>${u.created_at || ''}</td>
                `;
                tbody.appendChild(tr);
            });
        })
        .catch(err => {
            console.error('Lỗi load users:', err);
            const tbody = document.querySelector('#usersTable tbody');
            tbody.innerHTML = '<tr><td colspan="7">Lỗi tải danh sách người dùng.</td></tr>';
        });
}

// === USER ACTIONS ===
function openUserModal() {
    document.getElementById('userId').value = '';
    document.getElementById('userName').value = '';
    document.getElementById('userPhone').value = '';
    document.getElementById('userEmail').value = '';
    document.getElementById('userPassword').value = '';
    document.getElementById('userRole').value = 'user';
    document.getElementById('userModalTitle').textContent = 'Thêm tài khoản';
    document.getElementById('userModal').style.display = 'flex';
}

function closeUserModal() {
    document.getElementById('userModal').style.display = 'none';
}

function editUser(id, name, phone, email, role) {
    document.getElementById('userId').value = id;
    document.getElementById('userName').value = name;
    document.getElementById('userPhone').value = phone;
    document.getElementById('userEmail').value = email;
    document.getElementById('userPassword').value = '';
    document.getElementById('userRole').value = role;
    document.getElementById('userModalTitle').textContent = 'Cập nhật tài khoản';
    document.getElementById('userModal').style.display = 'flex';
}

function saveUser() {
    const id = document.getElementById('userId').value;
    const name = document.getElementById('userName').value;
    const phone = document.getElementById('userPhone').value;
    const email = document.getElementById('userEmail').value;
    const password = document.getElementById('userPassword').value;
    const role = document.getElementById('userRole').value;

    const url = id 
        ? '../api/admin/update_user.php'
        : '../api/admin/add_user.php';

    const body = { name, phone, email, role, password };
    if (id) body.id = id;

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeUserModal();
            loadUsers();
        } else {
            alert('Lỗi: ' + data.message);
        }
    });
}

function deleteUser(id) {
    if(!confirm('Cảnh báo: Xóa người dùng này có thể ảnh hưởng đến lịch sử đặt bàn/đơn hàng. Bạn có chắc không?')) return;
    
    fetch('../api/admin/delete_user.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) loadUsers();
        else alert('Lỗi xóa: ' + data.message);
    });
}


function loadRevenueChart() {
    fetch('../api/admin/get_revenue_stats.php')
    .then(res => res.json())
    .then(data => {
        if(data.success && data.stats) {
            const ctx = document.getElementById('revenueChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.stats.labels,
                    datasets: [{
                        label: 'Doanh thu (VNĐ)',
                        data: data.stats.data,
                        borderColor: '#e67e22',
                        backgroundColor: 'rgba(230, 126, 34, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return new Intl.NumberFormat('vi-VN').format(value) + ' đ';
                                }
                            }
                        }
                    }
                }
            });
        }
    })
    .catch(console.error);
}

// ======= Khởi động =======
loadRevenueChart();
loadStats();
loadBookings();
loadRecentOrders();
loadMenuItems();
loadUsers();
</script>

    <!-- NOTIFICATION SOUND (Base64 "Ding" for simplicity) -->
    <audio id="notif-sound" src="data:audio/mp3;base64,SUQzBAAAAAAAI1RTS1MAAAAPAAADTGF2ZjU4LjI5LjEwMAAAAAAAAAAAAAAA//uQZAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAWgAAAA0AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAADwAAAA8AAAAAAAAAAAAAAD//7kGQAA/0U1V/ywAAAAANVV8sAAAAA1VfywAAAAADVV/LAAAA//+5BkAAAAAAAAABAAAAADEAAAAA00b80QAAAAADTRvzRAAA//+5BkAAAAAAAAD7AAAAAEwAAAAA00b80QAAAAADTRvzRAAA//+5BkAAAAAAAAAAAAAAAABzAAAAANNG/NEAAAAAA00b80QAAA=="></audio>
    <!-- Note: Short beep placeholder. User should replace with real mp3 if needed. -->

    <script>
        // Init
        // Functions called above at line 709+
        
        // Poll for real-time updates
        setInterval(loadStats, 10000);
    </script>
</body>
</html>
