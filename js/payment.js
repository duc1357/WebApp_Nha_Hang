// js/payment.js – QR Modal, Payment Polling, Copy to Clipboard
// Phụ thuộc: utils.js (showToast, formatCurrency), cart.js (cart, updateCart, saveCartToStorage, toggleCart)

'use strict';

/* =========================================
   QR MODAL STATE
   ========================================= */
let paymentCheckInterval = null;

/* =========================================
   QR MODAL
   ========================================= */

/**
 * Hiển thị modal QR thanh toán.
 * Tự động bắt đầu polling khi phát hiện prefix DH (đơn hàng) hoặc BKG (đặt bàn).
 *
 * @param {string} qrUrl    URL ảnh QR code từ SePay
 * @param {number} amount   Số tiền cần thanh toán
 * @param {string} content  Nội dung chuyển khoản (VD: DH42, BKG7)
 */
window.showQrModal = function(qrUrl, amount, content) {
    const modal = document.getElementById('qr-payment-modal');
    if (!modal) return;

    document.getElementById('qr-code-img').src       = qrUrl;
    document.getElementById('qr-amount').textContent  = formatCurrency(amount);
    document.getElementById('qr-content').textContent = content;

    modal.classList.remove('hidden');
    setTimeout(() => modal.classList.add('show'), 10);

    // Tự động polling theo loại thanh toán
    const matchDH  = content.match(/DH(\d+)/i);
    const matchBKG = content.match(/BKG(\d+)/i);

    if (matchDH && matchDH[1])   startPaymentPolling(matchDH[1]);
    if (matchBKG && matchBKG[1]) startBookingPaymentPolling(matchBKG[1]);
};

/**
 * Đóng QR modal và dừng polling.
 */
window.closeQrModal = function() {
    const modal = document.getElementById('qr-payment-modal');
    if (!modal) return;

    if (paymentCheckInterval) {
        clearInterval(paymentCheckInterval);
        paymentCheckInterval = null;
    }

    modal.classList.remove('show');
    setTimeout(() => modal.classList.add('hidden'), 300);
};

/* =========================================
   PAYMENT POLLING
   ========================================= */

/**
 * Polling trạng thái đơn hàng mỗi 3 giây.
 * Dừng và hiện toast khi đã thanh toán xong.
 *
 * @param {string|number} orderId  ID đơn hàng
 */
window.startPaymentPolling = function(orderId) {
    if (paymentCheckInterval) clearInterval(paymentCheckInterval);
    const safeOrderId = encodeURIComponent(orderId);

    paymentCheckInterval = setInterval(() => {
        fetch(`api/payment/check_status.php?order_id=${safeOrderId}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success && ['Unauthorized', 'Order not found'].includes(data.message)) {
                    clearInterval(paymentCheckInterval);
                    paymentCheckInterval = null;
                    showToast(data.message || 'Không thể kiểm tra thanh toán.', 'error');
                    return;
                }

                if (data.success && (data.is_paid || data.status === 'paid')) {
                    clearInterval(paymentCheckInterval);
                    paymentCheckInterval = null;
                    closeQrModal();
                    // Xóa giỏ hàng sau khi thanh toán
                    window.cart = [];
                    updateCart();
                    saveCartToStorage();
                    toggleCart();
                    // Hiện popup cảm ơn
                    setTimeout(() => showThankYouModal(), 400);
                }
            })
            .catch(err => console.error('Polling error:', err));
    }, 3000);
};

/**
 * Polling trạng thái đặt cọc booking mỗi 3 giây.
 *
 * @param {string|number} bookingId  ID booking
 */
window.startBookingPaymentPolling = function(bookingId) {
    if (paymentCheckInterval) clearInterval(paymentCheckInterval);
    const safeBookingId = encodeURIComponent(bookingId);

    paymentCheckInterval = setInterval(() => {
        fetch(`api/payment/check_status_booking.php?booking_id=${safeBookingId}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success && ['Unauthorized', 'Booking not found'].includes(data.message)) {
                    clearInterval(paymentCheckInterval);
                    paymentCheckInterval = null;
                    showToast(data.message || 'Không thể kiểm tra thanh toán.', 'error');
                    return;
                }

                if (data.success && (data.is_paid || data.payment_status === 'partial' || data.payment_status === 'paid')) {
                    clearInterval(paymentCheckInterval);
                    paymentCheckInterval = null;
                    showToast('Thanh toán cọc thành công! Đã giữ bàn.', 'success');
                    closeQrModal();
                    // Reset preorder nếu đang bật
                    const preorderToggle = document.getElementById('toggle-preorder');
                    if (preorderToggle) {
                        preorderToggle.checked = false;
                        if (typeof togglePreorderSection === 'function') togglePreorderSection();
                    }
                }
            })
            .catch(err => console.error('Booking polling error:', err));
    }, 3000);
};

/* =========================================
   EVENT LISTENERS
   ========================================= */
// Đóng modal khi click ra ngoài
document.addEventListener('click', function(event) {
    const qrModal     = document.getElementById('qr-payment-modal');
    const orderModal  = document.getElementById('order-detail-modal');
    const reviewModal = document.getElementById('review-modal');

    if (event.target === qrModal) {
        qrModal.classList.remove('show');
        setTimeout(() => qrModal.classList.add('hidden'), 300);
    }
    if (event.target === orderModal) {
        orderModal.style.display = 'none';
    }
    if (event.target === reviewModal) {
        reviewModal.style.display = 'none';
    }
});

/* =========================================
   THANK YOU MODAL
   ========================================= */
window.showThankYouModal = function() {
    const modal = document.getElementById('thank-you-modal');
    if (!modal) {
        showToast('Thanh toán thành công!', 'success');
        return;
    }
    modal.classList.remove('hidden');
    setTimeout(() => modal.classList.add('show'), 10);
};

window.closeThankYouModal = function() {
    const modal = document.getElementById('thank-you-modal');
    if (!modal) return;
    modal.classList.remove('show');
    setTimeout(() => modal.classList.add('hidden'), 300);
};
