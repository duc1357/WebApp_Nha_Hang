// Init logic for profile page
        document.addEventListener('DOMContentLoaded', () => {
            const user = getCurrentUser();
            if (!user) {
                showToast('Bạn chưa đăng nhập!', 'error');
                setTimeout(() => window.location.href = 'index.html', 1500);
                return;
            }

            // Sync user name to the new hero section
            const displayUserName = document.getElementById('display-user-name');
            if (displayUserName) {
                displayUserName.textContent = user.name || 'Thành Viên';
            }

            // Render Header User Info
            renderUserHeader();

            // Load tabs based on URL param
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || 'info';
            switchProfileTab(tab);

            // Initial Load
            loadUserProfile();
        });

        function logoutUser() {
            if (typeof window.showLogoutModal === 'function') {
                window.showLogoutModal();
            }
        }


document.addEventListener('click', (event) => {
    const tabButton = event.target.closest('[data-tab]');
    if (tabButton) {
        switchProfileTab(tabButton.dataset.tab);
        return;
    }

    const action = event.target.closest('[data-action]')?.dataset.action;
    if (action === 'choose-avatar') document.getElementById('avatar-input')?.click();
    if (action === 'logout-user') logoutUser();
    if (action === 'close-review-modal') document.getElementById('review-modal').style.display = 'none';
    if (action === 'submit-review') submitReview();
    if (action === 'close-order-modal') closeOrderModal();

    const rating = event.target.closest('[data-rating]')?.dataset.rating;
    if (rating) setRating(Number(rating));
});

        document.addEventListener('change', (event) => {
            if (event.target.closest('[data-action="upload-avatar"]')) {
                uploadAvatar();
            }
        });

        document.addEventListener('submit', (event) => {
            const action = event.target.closest('[data-action]')?.dataset.action;
            if (action === 'update-user-info') {
                event.preventDefault();
                updateUserInfo();
            }
            if (action === 'change-password') {
                event.preventDefault();
                changePassword();
            }
        });

        document.getElementById('profile-avatar-img')?.addEventListener('error', (event) => {
            event.target.src = 'photo/default-user.png';
        });
