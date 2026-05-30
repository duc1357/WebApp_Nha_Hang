// js/theme.js - Shared Light/Dark theme manager for Nhà Hàng Cơm Quê Dượng Bầu
'use strict';

// 1. Immediately apply saved theme to prevent FOUC (Flash of Un-themed Content)
(function() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
})();

// 2. Add event listeners on DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
    const toggleButtons = document.querySelectorAll('.theme-toggle-btn');
    
    toggleButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            // Dispatch custom event for custom integrations
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: { theme: newTheme } }));
        });
    });
});
