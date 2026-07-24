/* ============================================================
   SIDEBAR — STATIC, NO ANIMATIONS, NO TRANSITIONS
   ============================================================ */
(function () {
    'use strict';

    const sidebar  = document.getElementById('sidebar');
    const toggle   = document.getElementById('sidebarToggle');
    const overlay  = document.getElementById('sidebarOverlay');
    const main     = document.getElementById('mainContent');

    if (!sidebar || !toggle) return;

    const COLLAPSED_KEY = 'cpdo_sidebar_collapsed';
    const isMobile = () => window.innerWidth <= 768;

    // Apply sidebar state IMMEDIATELY, NO DELAYS, NO ANIMATIONS
    function applyState(collapsed) {
        if (isMobile()) {
            sidebar.classList.toggle('mobile-open', !collapsed);
            overlay && overlay.classList.toggle('visible', !collapsed);
        } else {
            sidebar.classList.toggle('collapsed', collapsed);
        }
    }

    // Restore saved state on page load and clean up initial class
    if (!isMobile()) {
        const saved = localStorage.getItem(COLLAPSED_KEY) === 'true';
        applyState(saved);
        // Remove the initial class now that JS has applied the real state
        document.documentElement.classList.remove('sidebar-collapsed-initial');
    }

    toggle.addEventListener('click', function () {
        if (isMobile()) {
            const isOpen = sidebar.classList.contains('mobile-open');
            applyState(isOpen);
        } else {
            const willCollapse = !sidebar.classList.contains('collapsed');
            localStorage.setItem(COLLAPSED_KEY, willCollapse);
            applyState(willCollapse);
        }
    });

    // Close mobile sidebar overlay
    overlay && overlay.addEventListener('click', function () {
        applyState(true);
    });

    // Handle window resize WITHOUT any delays
    window.addEventListener('resize', function () {
        if (!isMobile()) {
            sidebar.classList.remove('mobile-open');
            overlay && overlay.classList.remove('visible');
            const saved = localStorage.getItem(COLLAPSED_KEY) === 'true';
            applyState(saved);
        } else {
            sidebar.classList.remove('collapsed');
        }
    });
}());
