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
    <a href="#" onclick="logout()" class="nav-item" style="margin-top:auto; color: #c0392b;">Đăng xuất</a>
</div>

<script>
    // Global Poller for Real-time Notifications
    (function() {
        let lastOrderId = 0;
        // Simple "Ding" sound (Base64)
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        
        function playDing() {
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(1000, audioContext.currentTime); // 1000Hz
            gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
            oscillator.start();
            gainNode.gain.exponentialRampToValueAtTime(0.00001, audioContext.currentTime + 0.5);
            oscillator.stop(audioContext.currentTime + 0.5);
        }

        function checkNewOrders() {
            // First run, just get the latest ID so we don't notify for existing orders
            const isFirstRun = lastOrderId === 0;
            
            fetch(`../api/admin/check_new_orders.php?last_id=${lastOrderId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (!isFirstRun && data.new_count > 0) {
                            playDing();
                            showToastNotification(`🔔 Có ${data.new_count} đơn hàng mới!`);
                            if (window.location.href.includes('orders.php') && typeof loadOrders === 'function') loadOrders(1);
                        }
                        if (data.latest_id > lastOrderId) lastOrderId = data.latest_id;
                    }
                })
                .catch(err => console.error('Order Poller error:', err));
        }

        let lastBookingId = 0;
        function checkNewBookings() {
            const isFirstRun = lastBookingId === 0;
            
            fetch(`../api/admin/check_new_bookings.php?last_id=${lastBookingId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (!isFirstRun && data.new_count > 0) {
                            playDing(); // Reuse sound
                            showToastNotification(`📅 Có ${data.new_count} lịch đặt bàn mới!`);
                            
                            // Auto reload if on bookings page
                            if (window.location.href.includes('bookings.php')) {
                                if (typeof loadBookings === 'function') loadBookings(1); // List view reload
                                if (typeof loadTableMap === 'function') loadTableMap(); // Map view reload
                            }
                        }
                        if (data.latest_id > lastBookingId) lastBookingId = data.latest_id;
                    }
                })
                .catch(err => console.error('Booking Poller error:', err));
        }

        // Init: Get current max ID immediately
        Promise.all([
            fetch('../api/admin/check_new_orders.php?last_id=0').then(r => r.json()),
            fetch('../api/admin/check_new_bookings.php?last_id=0').then(r => r.json())
        ]).then(([orderData, bookingData]) => {
            if(orderData.success) lastOrderId = orderData.latest_id;
            if(bookingData.success) lastBookingId = bookingData.latest_id;
            
            // Start polling every 5 seconds
            setInterval(() => {
                checkNewOrders();
                checkNewBookings();
            }, 5000);
        });

        // Helper Toast (Simple version if not exists)
        function showToastNotification(msg) {
            let toast = document.getElementById('global-toast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'global-toast';
                toast.style.cssText = 'position:fixed; top:20px; right:20px; background:#2c3e50; color:white; padding:15px 25px; border-radius:8px; z-index:9999; box-shadow:0 5px 15px rgba(0,0,0,0.2); transition: all 0.3s; transform: translateX(120%);';
                document.body.appendChild(toast);
            }
            toast.textContent = msg;
            toast.style.transform = 'translateX(0)';
            setTimeout(() => {
                toast.style.transform = 'translateX(120%)';
            }, 4000); // Hide after 4s
        }
    })();
</script>
