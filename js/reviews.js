// js/reviews.js – Đánh giá của khách hàng và hiển thị đánh giá nổi bật trang chủ
// Phụ thuộc: utils.js (showToast, getCurrentUser)

'use strict';

/* =========================================
   CUSTOMER REVIEW MODAL
   ========================================= */

/**
 * Mở modal viết đánh giá cho đơn hàng
 * @param {number} orderId
 */
window.openReviewModal = function(orderId) {
    document.getElementById('review-modal').style.display = 'flex';
    document.getElementById('review-order-id').value      = orderId;
    document.getElementById('review-comment').value       = '';
    setRating(5); // Mặc định 5 sao
};

/**
 * Cập nhật UI sao khi user chọn
 * @param {number} rating  Số sao (1-5)
 */
window.setRating = function(rating) {
    document.getElementById('review-rating').value = rating;
    document.querySelectorAll('.star-rating span').forEach(star => {
        star.style.color = parseInt(star.dataset.value) <= rating ? '#f1c40f' : '#ddd';
    });
};

/** Gửi đánh giá lên server */
window.submitReview = function() {
    const orderId = document.getElementById('review-order-id')?.value;
    const rating  = document.getElementById('review-rating')?.value;
    const comment = document.getElementById('review-comment')?.value;
    const user    = getCurrentUser();
    if (!user) return;

    const btn          = document.querySelector('#review-modal button:last-child');
    const originalText = btn?.textContent;
    if (btn) { btn.textContent = 'Đang gửi...'; btn.disabled = true; }

    fetch('api/user/submit_review.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: user.id, order_id: orderId, rating, comment })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            document.getElementById('review-modal').style.display = 'none';
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(err => { console.error(err); showToast('Lỗi kết nối', 'error'); })
    .finally(() => {
        if (btn) { btn.textContent = originalText; btn.disabled = false; }
    });
};

/* =========================================
   FEATURED REVIEWS (Trang chủ)
   ========================================= */

/**
 * Tải và render đánh giá nổi bật trên trang chủ.
 * Chỉ thực thi khi có element #featured-reviews-grid.
 */
window.loadFeaturedReviews = function() {
    const grid = document.getElementById('featured-reviews-grid');
    if (!grid) return;

    fetch('api/public/get_featured_reviews.php')
        .then(res => res.json())
        .then(data => {
            if (data.reviews?.length > 0) {
                grid.innerHTML = data.reviews.map(r => `
                    <div class="review-card">
                        <div class="review-rating">${'★'.repeat(parseInt(r.rating))}</div>
                        <div class="review-comment">"${r.comment}"</div>
                        <div class="review-author">
                            <img src="photo/default-user.png" alt="user" onerror="this.src='photo/default-user.png'">
                            <span>${r.name}</span>
                        </div>
                    </div>
                `).join('');
            } else {
                grid.innerHTML = '<p>Chưa có đánh giá nào.</p>';
            }
        })
        .catch(err => {
            console.error('Lỗi tải đánh giá:', err);
            grid.innerHTML = '<p>Không thể tải đánh giá.</p>';
        });
};

// Tự load khi DOM sẵn sàng (chỉ chạy được trên trang có #featured-reviews-grid)
document.addEventListener('DOMContentLoaded', () => {
    loadFeaturedReviews();
});
