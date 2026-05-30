<?php
require_once 'auth_check.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="../photo/favicon.png" type="image/png">
    <meta charset="UTF-8">
    <title>Quản Lý Bàn & Đặt Bàn - Dượng Bầu Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root { --primary: #e67e22; --text-main: #2c3e50; --bg: #f4f6f9; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Outfit', sans-serif; background: var(--bg); color: var(--text-main); display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 250px; background: white; border-right: 1px solid #eee; display: flex; flex-direction: column; padding: 20px; position: fixed; height: 100%; top:0; left:0; z-index: 100;}
        .brand { font-size: 20px; font-weight: 700; color: var(--primary); margin-bottom: 40px; display: flex; align-items: center; gap: 10px; }
        .nav-item { padding: 12px; min-height: 44px; display: flex; align-items: center; margin-bottom: 4px; color: #7f8c8d; text-decoration: none; border-radius: 8px; font-weight: 500; }
        .nav-item:hover, .nav-item.active { background: #fff0e6; color: var(--primary); }
        .main-content { margin-left: 250px; padding: 30px; width: 100%; }

        /* Filters */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .filters { display: flex; gap: 12px; background: white; padding: 16px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
        .form-control { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; outline: none; }
        .btn-search { background: var(--primary); color: white; border: none; padding: 10px 20px; min-height: 44px; border-radius: 6px; cursor: pointer; font-weight: 600; }

        /* Table */
        .table-container { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f1f1f1; }
        th { background: #fafafa; font-weight: 600; color: #7f8c8d; }
        .badge { padding: 4px 10px; border-radius: 99px; font-size: 12px; font-weight: 600; }
        .res-pending { background: #fff3cd; color: #856404; }
        .res-confirmed { background: #d4edda; color: #155724; }
        .res-cancelled { background: #f8d7da; color: #721c24; }

        /* TABLE MAP STYLES */
        .map-section { display: none; margin-top: 20px; }
        .floor-container { background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
        .floor-title { font-size: 18px; font-weight: 700; margin-bottom: 15px; color: var(--text-main); border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .table-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 15px; }
        .table-item { 
            border: 2px solid #ddd; border-radius: 12px; padding: 15px; text-align: center; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s, background-color 0.2s, border-color 0.2s; background: #fff;
            display: flex; flex-direction: column; justify-content: center; align-items: center; height: 110px; position: relative;
        }
        .table-item:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .table-name { font-weight: 700; font-size: 18px; margin-bottom: 8px; }
        .table-cap { font-size: 12px; color: #7f8c8d; }
        
        /* Status Colors */
        .table-item.available { border-color: #27ae60; background: #eafaf1; color: #27ae60; }
        .table-item.occupied { border-color: #e67e22; background: #fdf2e9; color: #e67e22; }

        /* MODALS */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width:100%; height:100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items:center; justify-content:center; }
        .modal-overlay.active { display: flex; }
        .modal-content { background: #fff; border-radius: 12px; padding: 24px; position: relative; max-width: 900px; width: 95%; max-height: 90vh; display: flex; flex-direction: column; }
        .modal-close { position: absolute; right: 20px; top: 20px; font-size: 28px; cursor: pointer; color: #7f8c8d; line-height: 1; }
        
        /* POS Layout */
        .pos-container { display: flex; gap: 20px; margin-top: 15px; height: 60vh; overflow: hidden; }
        .pos-menu { flex: 2; border-right: 1px solid #eee; padding-right: 20px; display: flex; flex-direction: column; }
        .pos-cart { flex: 1.2; display: flex; flex-direction: column; }
        
        .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; overflow-y: auto; padding-right: 5px; flex: 1; align-content: start; }
        .menu-item { border: 1px solid #eee; border-radius: 8px; padding: 10px; cursor: pointer; text-align: center; background: #fdfdfd; transition: border-color 0.1s, box-shadow 0.1s; min-height: 44px; display: flex; flex-direction: column; justify-content: center; }
        .menu-item:hover { border-color: var(--primary);  box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .menu-item-name { font-weight: 600; font-size: 14px; margin-bottom: 5px; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .menu-item-price { color: var(--primary); font-size: 13px; font-weight: bold; }
        
        .cart-items { flex: 1; overflow-y: auto; margin-bottom: 10px; padding-right: 5px; }
        .cart-item { display: flex; flex-direction: column; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px dashed #eee; }
        .cart-item-header { display: flex; justify-content: space-between; font-weight: 600; font-size: 14px; margin-bottom: 5px; }
        .cart-item-actions { display: flex; justify-content: space-between; align-items: center; }
        .cart-qty { display: flex; align-items: center; gap: 8px; }
        .btn-qty { width: 44px; height: 44px; border: 1px solid #ddd; background: #fff; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-qty:hover { background: #eee; }
        
        .checkout-details { margin-top: 10px; border-top: 2px solid #eee; padding-top: 15px; font-size: 20px; font-weight: bold; text-align: right; color: #d35400; }
        .btn-pos { padding: 14px; min-height: 44px; width: 100%; background: var(--primary); color: #fff; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; margin-top: 10px; font-size: 15px; transition: filter 0.2s; }
        .btn-pos:hover { filter: brightness(1.05); }
        .btn-pos.secondary { background: #27ae60; }
        .btn-pos.danger { background: #e74c3c; }
        
        .col-action { min-width: 160px; text-align: center; }
        /* Small utilities */
        .loading-spinner { border: 3px solid rgba(0,0,0,0.1); border-left-color: var(--primary); border-radius: 50%; width: 24px; height: 24px; animation: spin 1s linear infinite; margin: 20px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2 style="font-size: 24px;">Quản Lý Bàn (POS) & Đặt Bàn</h2>
            <div>
                <button data-action="toggle-map" class="btn-search" style="background:#3498db; margin-right: 8px;">Sơ đồ bàn (POS)</button>
                <button data-action="toggle-list" class="btn-search" style="background:#2ecc71;">Danh sách Khách hẹn</button>
            </div>
        </div>

        <!-- MAP VIEW (POS) -->
        <div id="mapView" class="map-section" style="display:block;">
            <div style="margin-bottom:20px; display:flex; gap:10px; align-items:center;">
                <label>Xem sơ đồ bàn ngày:</label>
                <input type="date" id="mapDate" class="form-control" value="<?php echo date('Y-m-d'); ?>" data-action="load-table-map">
                <button data-action="load-table-map" class="btn-search">Làm mới</button>
                <span id="posLoading" style="display:none; color: #7f8c8d; font-size: 14px; margin-left: 10px;">Đang xử lý...</span>
            </div>
            <div id="mapContent">Đang tải sơ đồ...</div>
        </div>

        <!-- LIST VIEW -->
        <div id="listView" style="display:none;">
            <div class="filters">
                <input type="text" id="searchInput" class="form-control" placeholder="Tên khách, SĐT..." style="width: 250px;">
                <input type="date" id="dateFilter" class="form-control">
                <select id="statusFilter" class="form-control">
                    <option value="">Tất cả trạng thái</option>
                    <option value="pending">Chờ xác nhận</option>
                    <option value="confirmed">Đã xác nhận</option>
                    <option value="cancelled">Đã hủy</option>
                </select>
                <button data-action="load-bookings" class="btn-search">Tìm kiếm</button>
            </div>
            <div class="table-container">
                <table id="bookingsTable">
                    <thead>
                        <tr>
                            <th>ID</th><th>Khách hàng</th><th>SĐT</th><th>Ngày & Giờ</th><th>Bàn</th><th>Khách</th><th>Trạng thái</th><th class="col-action">Hành động</th>
                        </tr>
                    </thead>
                    <tbody><tr><td colspan="8">Đang tải...</td></tr></tbody>
                </table>
                <div class="pagination" id="pagination" style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px;"></div>
            </div>
        </div> <!-- End ListView -->

    </div>

    <!-- POS MODAL -->
    <div class="modal-overlay" id="posModal">
        <div class="modal-content">
            <span class="modal-close" data-action="close-pos-modal">&times;</span>
            <h2 id="posTableTitle" style="color: var(--primary);">Order - Bàn</h2>
            
            <div class="pos-container">
                <!-- Left: Menu Items -->
                <div class="pos-menu">
                    <input type="text" id="posSearch" class="form-control" placeholder="Tìm món ăn..." data-action="filter-menu" style="margin-bottom: 15px;">
                    <div class="menu-grid" id="posMenuList"></div>
                </div>
                
                <!-- Right: Cart -->
                <div class="pos-cart">
                    <h3 style="font-size: 16px; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 8px;">Danh sách gọi món</h3>
                    <div class="cart-items" id="posCartItems"></div>
                    <div class="checkout-details">
                        Tổng: <span id="posTotal">0đ</span>
                    </div>
                    <!-- Actons -->
                    <button class="btn-pos" data-action="save-order" id="btnSaveOrder">Lưu Order</button>
                    <button class="btn-pos secondary" data-action="open-checkout-modal">Thanh Toán & Trả Bàn</button>
                    <button class="btn-pos danger" data-action="empty-table">Hủy Order & Về Trống</button>
                </div>
            </div>
        </div>
    </div>

    <!-- CHECKOUT MODAL -->
    <div class="modal-overlay" id="checkoutModal">
        <div class="modal-content" style="max-width: 450px; text-align: center;">
            <span class="modal-close" data-action="close-checkout-modal">&times;</span>
            <h2>Thanh Toán Bàn <span id="checkoutTableName"></span></h2>
            <p style="margin: 20px 0; font-size: 32px; font-weight: bold; color: var(--primary);" id="checkoutTotal">0đ</p>
            
            <div style="text-align: left; margin-bottom: 25px; background: #f9f9f9; padding: 15px; border-radius: 8px;">
                <label style="font-weight: bold; display:block; margin-bottom: 10px;">Phương thức thanh toán:</label>
                <label style="margin-right: 20px; font-size: 16px; cursor: pointer;">
                    <input type="radio" name="paymentMethod" value="cash" checked> Tiền mặt
                </label>
                <label style="font-size: 16px; cursor: pointer;">
                    <input type="radio" name="paymentMethod" value="bank_transfer"> Chuyển khoản QR
                </label>
            </div>
            
            <button class="btn-pos" data-action="process-checkout" id="btnProcessCheckout" style="font-size: 16px; padding: 16px;">Xác Nhận Thanh Toán</button>
        </div>
    </div>

    
    <script src="../js/admin-bookings.js" defer></script>
</body>
</html>



