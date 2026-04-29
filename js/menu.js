// js/menu.js – Tải và hiển thị thực đơn công khai
// Phụ thuộc: utils.js, cart.js (addToCart)

'use strict';

/**
 * Tải thực đơn từ API và render ra lưới.
 */
window.loadPublicMenu = async function() {
    const grid = document.getElementById('public-menu-grid');
    if (!grid) return;

    try {
        const res  = await fetch('api/menu/get_menu.php');
        const json = await res.json();
        const items = json.data || json.items || [];

        grid.innerHTML = '';

        if (!json.success || items.length === 0) {
            grid.innerHTML = '<p style="text-align:center;width:100%;">Hiện chưa có món ăn nào.</p>';
            return;
        }

        items.forEach(item => {
            if (item.is_active == 0) return;

            const div = document.createElement('div');
            div.className  = 'menu-item';
            div.dataset.id = item.id;

            const imgUrl = (item.photo && item.photo.trim()) ? item.photo : 'photo/default-food.png';

            div.innerHTML = `
                <img src="${imgUrl}" alt="" onerror="this.src='photo/default-food.png'">
                <h3></h3>
                <p></p>
                <div class="price">${parseInt(item.price).toLocaleString('vi-VN')} VNĐ</div>
                <button class="add-to-cart">Thêm vào giỏ</button>
            `;

            // Gán text an toàn (tránh XSS)
            div.querySelector('h3').textContent = item.name;
            div.querySelector('p').textContent  = item.description || '';

            // Bind sự kiện click
            div.querySelector('.add-to-cart')
               .addEventListener('click', e => addToCart(e, item.id, item.name, item.price));

            grid.appendChild(div);
        });

    } catch (err) {
        console.error('Lỗi tải menu:', err);
        grid.innerHTML = '<p style="text-align:center;width:100%;color:red;">Không thể tải thực đơn. Vui lòng thử lại sau.</p>';
    }
};

/**
 * Tải thực đơn dành cho trang đặt bàn (booking preorder)
 */
window.loadBookingMenu = function() {
    fetch('api/menu/get_menu.php')
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            const list = document.getElementById('booking-menu-list');
            if (!list) return;

            list.innerHTML = '';
            if (!data.data || data.data.length === 0) {
                list.innerHTML = '<p class="text-muted">Chưa có món ăn.</p>';
                return;
            }

            data.data.forEach(item => {
                const qtyInCart = window.bookingCart?.find(i => i.menu_item_id == item.id)?.quantity || 0;

                const el = document.createElement('div');
                el.style.cssText = 'display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #eee;';
                el.innerHTML = `
                    <div>
                        <div style="font-weight:600">${item.name}</div>
                        <div style="color:var(--primary);font-size:0.9rem">${formatCurrency(item.price)}</div>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <button type="button" class="btn btn-outline" style="padding:2px 8px;border-radius:4px"
                            onclick="updateBookingItem(${item.id}, '${item.name.replace(/'/g, "\\'")}', ${item.price}, -1)">-</button>
                        <span id="bkg-qty-${item.id}" style="width:20px;text-align:center">${qtyInCart}</span>
                        <button type="button" class="btn btn-primary" style="padding:2px 8px;border-radius:4px"
                            onclick="updateBookingItem(${item.id}, '${item.name.replace(/'/g, "\\'")}', ${item.price}, 1)">+</button>
                    </div>
                `;
                list.appendChild(el);
            });
        })
        .catch(err => console.error('Error load menu for booking:', err));
};

// Khởi tạo khi DOM sẵn sàng
document.addEventListener('DOMContentLoaded', () => {
    const menuGrid = document.getElementById('public-menu-grid');
    if (menuGrid) loadPublicMenu();
});
