// js/menu.js - Public menu and booking preorder menu rendering
// Depends on utils.js, cart.js (addToCart), booking.js (updateBookingItem)

'use strict';

document.addEventListener('error', event => {
    const img = event.target;
    if (!(img instanceof HTMLImageElement) || !img.dataset.menuFallback || img.dataset.fallbackApplied === '1') {
        return;
    }

    img.dataset.fallbackApplied = '1';
    img.src = 'photo/default-food.png';
}, true);

window.loadPublicMenu = async function() {
    const grid = document.getElementById('public-menu-grid');
    if (!grid) return;

    renderState(grid, 'Đang tải thực đơn...', 'empty');

    try {
        const json = await fetchJson('api/menu/get_menu.php');
        const items = (json.data || json.items || []).filter(item => item.is_active != 0);

        grid.innerHTML = '';

        if (!json.success || items.length === 0) {
            renderEmptyState(grid, 'Hiện chưa có món ăn nào.');
            return;
        }

        items.forEach(item => {
            const div = document.createElement('div');
            div.className = 'menu-item';
            div.dataset.id = item.id;

            const imgUrl = (item.photo && item.photo.trim()) ? item.photo : 'photo/default-food.png';

            div.innerHTML = `
                <img src="${escapeHTML(imgUrl)}" alt="" data-menu-fallback="1">
                <h3></h3>
                <p></p>
                <div class="price">${parseInt(item.price, 10).toLocaleString('vi-VN')} VNĐ</div>
                <button class="add-to-cart" type="button">Thêm vào giỏ</button>
            `;

            div.querySelector('h3').textContent = item.name || '';
            div.querySelector('p').textContent = item.description || '';
            div.querySelector('.add-to-cart')
                .addEventListener('click', e => addToCart(e, item.id, item.name, item.price));

            grid.appendChild(div);
        });
    } catch (err) {
        console.error('Lỗi tải menu:', err);
        renderErrorState(grid, 'Không thể tải thực đơn. Vui lòng thử lại sau.');
    }
};

window.loadBookingMenu = function() {
    const list = document.getElementById('drawer-menu-list');
    if (!list) return;

    renderState(list, 'Đang tải món đặt trước...', 'empty');

    fetchJson('api/menu/get_menu.php')
        .then(data => {
            const items = (data.data || []).filter(item => item.is_active != 0);
            list.innerHTML = '';

            if (!data.success || items.length === 0) {
                renderEmptyState(list, 'Chưa có món ăn để đặt trước.');
                return;
            }

            items.forEach(item => {
                const qtyInCart = window.bookingCart?.find(i => i.menu_item_id == item.id)?.quantity || 0;
                const safeName = escapeHTML(item.name || '');
                const safeDesc = escapeHTML(item.description || '');
                const imgUrl = (item.photo && item.photo.trim()) ? item.photo : 'photo/default-food.png';

                const el = document.createElement('div');
                el.className = 'drawer-menu-item';
                el.dataset.name = (item.name || '').toLowerCase();
                el.dataset.desc = (item.description || '').toLowerCase();
                el.innerHTML = `
                    <img class="drawer-item-img" src="${escapeHTML(imgUrl)}" alt="${safeName}" data-menu-fallback="1">
                    <div class="drawer-item-info">
                        <div class="drawer-item-name">${safeName}</div>
                        <div class="drawer-item-desc">${safeDesc}</div>
                        <div class="drawer-item-price">${formatCurrency(item.price)}</div>
                    </div>
                    <div class="drawer-item-actions">
                        <button type="button" class="drawer-qty-btn js-bkg-minus">-</button>
                        <span id="bkg-qty-${item.id}" class="drawer-qty-value">${qtyInCart}</span>
                        <button type="button" class="drawer-qty-btn js-bkg-plus">+</button>
                    </div>
                `;

                el.querySelector('.js-bkg-minus').addEventListener('click', () => updateBookingItem(item.id, item.name || '', item.price, -1));
                el.querySelector('.js-bkg-plus').addEventListener('click', () => updateBookingItem(item.id, item.name || '', item.price, 1));
                list.appendChild(el);
            });
        })
        .catch(err => {
            console.error('Error load menu for booking:', err);
            renderErrorState(list, 'Không thể tải món đặt trước.');
        });
};

document.addEventListener('DOMContentLoaded', () => {
    const menuGrid = document.getElementById('public-menu-grid');
    if (menuGrid) loadPublicMenu();
});
