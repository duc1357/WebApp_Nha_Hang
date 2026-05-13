// js/reviews.js - Customer reviews and featured review rendering
// Depends on utils.js (showToast, getCurrentUser)

'use strict';

window.openReviewModal = function(orderId) {
    const modal = document.getElementById('review-modal');
    if (!modal) return;

    modal.style.display = 'flex';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    document.getElementById('review-order-id').value = orderId;
    document.getElementById('review-comment').value = '';
    setRating(5);
};

window.setRating = function(rating) {
    const input = document.getElementById('review-rating');
    if (input) input.value = rating;

    document.querySelectorAll('.star-rating span').forEach(star => {
        star.style.color = parseInt(star.dataset.value, 10) <= rating ? '#f1c40f' : '#ddd';
    });
};

window.submitReview = function() {
    const orderId = document.getElementById('review-order-id')?.value;
    const rating = document.getElementById('review-rating')?.value;
    const comment = document.getElementById('review-comment')?.value;
    const user = getCurrentUser();
    if (!user) return;

    const btn = document.querySelector('#review-modal button:last-child');
    const originalText = btn?.textContent;
    if (btn) {
        btn.textContent = 'Đang gửi...';
        btn.disabled = true;
    }

    fetch('api/user/submit_review.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId, rating, comment })
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
    .catch(err => {
        console.error(err);
        showToast('Lỗi kết nối', 'error');
    })
    .finally(() => {
        if (btn) {
            btn.textContent = originalText;
            btn.disabled = false;
        }
    });
};

window.loadFeaturedReviews = function() {
    const grid = document.getElementById('featured-reviews-grid');
    if (!grid) return;

    fetch('api/public/get_featured_reviews.php')
        .then(res => res.json())
        .then(data => {
            grid.innerHTML = '';

            if (!data.reviews?.length) {
                const empty = document.createElement('p');
                empty.textContent = 'Chưa có đánh giá nào.';
                grid.appendChild(empty);
                return;
            }

            data.reviews.forEach(r => {
                const card = document.createElement('div');
                card.className = 'review-card';

                const rating = document.createElement('div');
                rating.className = 'review-rating';
                rating.textContent = '★'.repeat(Math.max(0, Math.min(5, parseInt(r.rating, 10) || 0)));

                const comment = document.createElement('div');
                comment.className = 'review-comment';
                comment.textContent = `"${r.comment || ''}"`;

                const author = document.createElement('div');
                author.className = 'review-author';

                const img = document.createElement('img');
                img.src = 'photo/default-user.png';
                img.alt = 'Ảnh đại diện khách hàng';
                img.onerror = () => { img.src = 'photo/default-user.png'; };

                const name = document.createElement('span');
                name.textContent = r.name || 'Khách hàng';

                author.append(img, name);
                card.append(rating, comment, author);
                grid.appendChild(card);
            });
        })
        .catch(err => {
            console.error('Lỗi tải đánh giá:', err);
            grid.innerHTML = '';
            const error = document.createElement('p');
            error.textContent = 'Không thể tải đánh giá.';
            grid.appendChild(error);
        });
};

document.addEventListener('DOMContentLoaded', () => {
    loadFeaturedReviews();
});
