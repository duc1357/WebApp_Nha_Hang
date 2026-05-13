// Shared UI helpers for pages that load the modular frontend scripts.
// Feature logic lives in utils.js, cart.js, menu.js, booking.js, payment.js,
// profile.js, and reviews.js.

(function () {
    function escapeText(value) {
        if (typeof window.escapeHTML === 'function') {
            return window.escapeHTML(value || '');
        }

        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function safeAvatarPath(path) {
        const avatar = String(path || '');
        return /^photo\/[A-Za-z0-9._/-]+$/.test(avatar) ? avatar : '';
    }

    function createIcon(svgPath) {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('width', '18');
        svg.setAttribute('height', '18');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '1.8');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.innerHTML = svgPath;
        return svg;
    }

    function createAvatar(user) {
        const avatar = document.createElement('div');
        avatar.className = 'uht-avatar';

        const path = safeAvatarPath(user.avatar);
        if (path) {
            const img = document.createElement('img');
            img.src = path;
            img.alt = '';
            img.style.cssText = 'width:100%;height:100%;border-radius:50%;object-fit:cover;';
            avatar.appendChild(img);
            return avatar;
        }

        avatar.textContent = user.name ? user.name.charAt(0).toUpperCase() : 'U';
        return avatar;
    }

    function createDropdownAvatar(user) {
        const avatar = createAvatar(user);
        avatar.className = 'pd-avatar';
        return avatar;
    }

    window.renderUserHeader = function renderUserHeader() {
        const getUser = typeof window.getCurrentUser === 'function'
            ? window.getCurrentUser
            : () => null;
        const user = getUser();
        const box = document.getElementById('auth-actions');
        if (!box) return;

        box.textContent = '';

        if (!user) {
            const navAuth = document.createElement('div');
            navAuth.className = 'nav-auth';

            const loginButton = document.createElement('button');
            loginButton.type = 'button';
            loginButton.className = 'user-avatar-btn';
            loginButton.title = 'Đăng nhập';
            loginButton.setAttribute('aria-label', 'Đăng nhập');
            loginButton.appendChild(createIcon('<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>'));
            loginButton.addEventListener('click', () => {
                window.location.href = 'login.html';
            });

            navAuth.appendChild(loginButton);
            box.appendChild(navAuth);
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.style.position = 'relative';

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.id = 'user-header-trigger';
        trigger.setAttribute('aria-haspopup', 'menu');
        trigger.setAttribute('aria-expanded', 'false');

        const greeting = document.createElement('div');
        greeting.className = 'uht-greeting';
        greeting.innerHTML = `
            <span class="uht-label">Xin chào,</span>
            <span class="uht-name">${escapeText(user.name)}</span>
        `;

        trigger.appendChild(greeting);
        trigger.appendChild(createAvatar(user));

        const dropdown = document.createElement('div');
        dropdown.id = 'user-dropdown-menu';
        dropdown.className = 'premium-dropdown';
        dropdown.setAttribute('role', 'menu');

        const header = document.createElement('div');
        header.className = 'pd-header';
        header.appendChild(createDropdownAvatar(user));

        const info = document.createElement('div');
        info.className = 'pd-info';
        info.innerHTML = `
            <span class="pd-greeting">Xin chào,</span>
            <span class="pd-name">${escapeText(user.name)}</span>
        `;
        header.appendChild(info);

        const divider = document.createElement('div');
        divider.className = 'pd-divider';

        const profileLink = document.createElement('a');
        profileLink.href = 'profile.html';
        profileLink.className = 'pd-item';
        profileLink.setAttribute('role', 'menuitem');
        profileLink.appendChild(createIcon('<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>'));
        const profileText = document.createElement('span');
        profileText.textContent = 'Hồ sơ cá nhân';
        profileLink.appendChild(profileText);

        const logoutLink = document.createElement('button');
        logoutLink.type = 'button';
        logoutLink.className = 'pd-item pd-logout';
        logoutLink.id = 'dropdown-logout-btn';
        logoutLink.setAttribute('role', 'menuitem');
        logoutLink.appendChild(createIcon('<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line>'));
        const logoutText = document.createElement('span');
        logoutText.textContent = 'Đăng xuất';
        logoutLink.appendChild(logoutText);

        dropdown.append(header, divider, profileLink, logoutLink);
        wrapper.append(trigger, dropdown);
        box.appendChild(wrapper);

        const setOpen = (isOpen) => {
            dropdown.classList.toggle('open', isOpen);
            trigger.setAttribute('aria-expanded', String(isOpen));
        };

        trigger.addEventListener('click', (event) => {
            event.stopPropagation();
            setOpen(!dropdown.classList.contains('open'));
        });

        dropdown.addEventListener('click', (event) => {
            event.stopPropagation();
        });

        logoutLink.addEventListener('click', () => {
            setOpen(false);
            if (typeof window.showLogoutModal === 'function') {
                window.showLogoutModal();
            }
        });

        document.addEventListener('click', () => setOpen(false));
    };

    window.showLogoutModal = function showLogoutModal() {
        let modal = document.getElementById('logout-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'logout-modal';
            modal.innerHTML = `
                <div class="logout-modal-card" role="dialog" aria-modal="true" aria-labelledby="logout-modal-title">
                    <div class="logout-icon-wrap">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#e74c3c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    </div>
                    <h3 id="logout-modal-title">Xác nhận đăng xuất</h3>
                    <p>Bạn có chắc chắn muốn rời khỏi hệ thống không?</p>
                    <div class="logout-actions">
                        <button type="button" class="btn-cancel-logout" id="btn-cancel-logout">Hủy bỏ</button>
                        <button type="button" class="btn-confirm-logout" id="btn-execute-logout">Đăng xuất</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            document.getElementById('btn-cancel-logout').addEventListener('click', window.hideLogoutModal);
            document.getElementById('btn-execute-logout').addEventListener('click', window.executeLogout);
            modal.addEventListener('click', (event) => {
                if (event.target === modal) window.hideLogoutModal();
            });
        }

        requestAnimationFrame(() => {
            modal.classList.add('modal-visible');
        });
    };

    window.hideLogoutModal = function hideLogoutModal() {
        const modal = document.getElementById('logout-modal');
        if (modal) modal.classList.remove('modal-visible');
    };

    window.executeLogout = function executeLogout() {
        const btn = document.getElementById('btn-execute-logout');
        if (btn) {
            btn.textContent = 'Đang đăng xuất...';
            btn.disabled = true;
        }

        fetch('api/auth/logout.php', { method: 'POST' })
            .finally(() => {
                localStorage.removeItem('restaurant_user');
                window.location.href = 'index.html';
            });
    };
})();
