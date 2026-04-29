// js/cart.js – Giỏ hàng: state, localStorage, render, voucher, checkout
// Phụ thuộc: utils.js (showToast, formatCurrency, setLoading, getCurrentUser)

'use strict';

/* =========================================
   CART STATE
   ========================================= */
window.cart = [];

// Load cart từ localStorage ngay khi script được nạp
try {
    const savedCart = localStorage.getItem('restaurant_cart');
    if (savedCart) window.cart = JSON.parse(savedCart);
} catch (e) {
    console.error('Lỗi parse cart:', e);
    window.cart = [];
}

/* =========================================
   CART CORE FUNCTIONS
   ========================================= */

/** Lưu giỏ hàng vào localStorage */
window.saveCartToStorage = function() {
    localStorage.setItem('restaurant_cart', JSON.stringify(window.cart));
};

/**
 * Thêm món vào giỏ hàng (có animation bay về giỏ)
 * Hỗ trợ cả cú pháp mới: addToCart(event, id, name, price)
 * Và cú pháp cũ: addToCart(id, name, price)
 */
window.addToCart = function(arg1, arg2, arg3, arg4) {
    let event = null;
    let id, name, price;

    if (arg1 instanceof Event || (arg1 && arg1.target)) {
        event = arg1; id = arg2; name = arg3; price = arg4;
    } else {
        id = arg1; name = arg2; price = arg3;
    }

    const user = getCurrentUser();
    if (!user) {
        showToast('Vui lòng đăng nhập!', 'error');
        setTimeout(() => window.location.href = 'login.html', 1000);
        return;
    }

    // Animation: bay ảnh từ menu item về nút giỏ hàng
    if (event) {
        const btn      = event.target;
        const menuItem = btn.closest('.menu-item');
        const cartBtn  = document.getElementById('floating-cart-btn');

        if (menuItem && cartBtn) {
            const img = menuItem.querySelector('img');
            if (img) {
                const imgClone = img.cloneNode(true);
                const rect     = img.getBoundingClientRect();
                const cartRect = cartBtn.getBoundingClientRect();

                Object.assign(imgClone.style, {
                    position: 'fixed', top: rect.top + 'px', left: rect.left + 'px',
                    width: rect.width + 'px', height: rect.height + 'px',
                    zIndex: '9999', borderRadius: '50%', opacity: '0.8',
                    transition: 'all 0.8s cubic-bezier(0.19, 1, 0.22, 1)'
                });
                document.body.appendChild(imgClone);

                requestAnimationFrame(() => {
                    Object.assign(imgClone.style, {
                        top: (cartRect.top + 10) + 'px', left: (cartRect.left + 10) + 'px',
                        width: '30px', height: '30px', opacity: '0'
                    });
                });
                setTimeout(() => {
                    imgClone.remove();
                    cartBtn.style.transform = 'scale(1.2)';
                    setTimeout(() => cartBtn.style.transform = '', 200);
                }, 800);
            }
        }
    }

    id = parseInt(id);
    price = parseInt(price);
    const existing = window.cart.find(item => item.id === id);
    if (existing) {
        existing.quantity += 1;
    } else {
        window.cart.push({ id, name, price, quantity: 1 });
    }
    saveCartToStorage();

    setTimeout(() => {
        updateCart();
        showToast('Đã thêm vào giỏ!', 'success');
    }, 600);
};

/** Xóa món khỏi giỏ hàng */
window.removeFromCart = function(id) {
    id = parseInt(id);
    window.cart = window.cart.filter(item => item.id !== id);
    saveCartToStorage();
    updateCart();
};

/** Cập nhật toàn bộ UI giỏ hàng */
window.updateCart = function() {
    const cartItems = document.getElementById('cart-items');
    const cartCount = document.getElementById('cart-count');
    const cartTotal = document.getElementById('cart-total');
    if (!cartItems || !cartCount || !cartTotal) return;

    cartItems.innerHTML = '';
    let total = 0;
    let count = 0;

    window.cart.forEach(item => {
        total += item.price * item.quantity;
        count += item.quantity;

        const row  = document.createElement('div');
        row.className = 'cart-item';
        const info = document.createElement('span');
        info.textContent = `${item.name} x ${item.quantity}`;
        const btn  = document.createElement('button');
        btn.type   = 'button';
        btn.onclick = () => removeFromCart(item.id);
        btn.textContent = 'Xóa';
        row.appendChild(info);
        row.appendChild(btn);
        cartItems.appendChild(row);
    });

    cartCount.textContent = count;
    let displayTotal  = total;
    let discountHtml  = '';

    if (window.appliedVoucher) {
        let discount = 0;
        if (window.appliedVoucher.discount_type === 'percent') {
            discount = (total * window.appliedVoucher.discount_value) / 100;
        } else {
            discount = window.appliedVoucher.discount_value;
        }
        if (discount > total) discount = total;
        window.appliedVoucher.discount_amount = discount;
        displayTotal = total - discount;
        discountHtml = `<br><span style="color:green;font-size:0.9em;">(Giảm: -${formatCurrency(discount)})</span>`;

        const msg = document.getElementById('voucher-msg');
        if (msg) {
            msg.textContent = `Đã dùng mã ${window.appliedVoucher.code}: -${formatCurrency(discount)}`;
            msg.style.color = 'green';
        }
    }

    cartTotal.innerHTML = 'Tổng cộng: ' + formatCurrency(displayTotal) + ' VNĐ' + discountHtml;
};

/** Toggle hiển thị popup giỏ hàng */
window.toggleCart = function() {
    document.getElementById('floating-cart-popup')?.classList.toggle('hidden');
};

/* =========================================
   VOUCHER LOGIC
   ========================================= */
window.checkVoucher = function() {
    const code = document.getElementById('voucher-input')?.value.trim();
    const msg  = document.getElementById('voucher-msg');
    if (!code) { if (msg) { msg.textContent = 'Vui lòng nhập mã'; msg.style.color = 'red'; } return; }

    const totalOrder = window.cart.reduce((sum, item) => sum + item.price * item.quantity, 0);

    fetch('api/public/check_voucher.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code, order_value: totalOrder })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.appliedVoucher = data.voucher;
            if (msg) { msg.textContent = `Giảm ${formatCurrency(data.voucher.discount_amount)}`; msg.style.color = 'green'; }
        } else {
            window.appliedVoucher = null;
            if (msg) { msg.textContent = data.message; msg.style.color = 'red'; }
        }
        updateCart();
    })
    .catch(err => { console.error(err); if (msg) msg.textContent = 'Lỗi kiểm tra mã'; });
};

/* =========================================
   CHECKOUT LOGIC
   ========================================= */
window.toggleOrderType = function() {
    const type = document.querySelector('input[name="order_type"]:checked')?.value;
    if (!type) return;
    document.getElementById('delivery_info').style.display  = type === 'delivery' ? 'block' : 'none';
    document.getElementById('dine_in_info').style.display   = type === 'dine_in'  ? 'block' : 'none';
};

window.checkout = function() {
    if (window.cart.length === 0) { showToast('Giỏ hàng trống!', 'warning'); return; }

    const user = getCurrentUser();
    if (!user) {
        showToast('Vui lòng đăng nhập để thanh toán!', 'error');
        setTimeout(() => window.location.href = 'login.html', 1500);
        return;
    }

    const orderType     = document.querySelector('input[name="order_type"]:checked')?.value || 'delivery';
    const address       = document.getElementById('delivery_address')?.value.trim();
    const tableNum      = document.getElementById('table_number_input')?.value.trim();
    const note          = document.getElementById('order_note')?.value.trim();
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked')?.value || 'cash';

    if (orderType === 'delivery' && !address) {
        showToast('Vui lòng nhập địa chỉ giao hàng!', 'warning');
        document.getElementById('delivery_address')?.focus();
        return;
    }
    if (orderType === 'dine_in' && !tableNum) {
        showToast('Vui lòng nhập tên bàn!', 'warning');
        document.getElementById('table_number_input')?.focus();
        return;
    }

    let total = window.cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
    if (window.appliedVoucher) {
        total -= window.appliedVoucher.discount_amount;
        if (total < 0) total = 0;
    }

    const btn = document.getElementById('checkout-btn');
    setLoading(btn, true, 'Đang thanh toán...');

    fetch('api/payment/create_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            items: window.cart,
            total,
            payment_method: paymentMethod,
            user_id: user.id,
            voucher_code: window.appliedVoucher?.code || '',
            address: orderType === 'delivery' ? address : '',
            table_id: orderType === 'dine_in' ? tableNum : '',
            note
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Đặt hàng thành công!', 'success');
            window.cart = [];
            updateCart();
            toggleCart();
            saveCartToStorage();
            if (data.payUrl) {
                showQrModal(data.payUrl, total, 'DH' + (data.order_id || ''));
            }
        } else {
            showToast(data.message || 'Thanh toán thất bại', 'error');
        }
    })
    .catch(err => { console.error('Checkout error:', err); showToast('Lỗi kết nối thanh toán', 'error'); })
    .finally(() => setLoading(btn, false));
};

// Khởi tạo khi DOM sẵn sàng
document.addEventListener('DOMContentLoaded', () => updateCart());
