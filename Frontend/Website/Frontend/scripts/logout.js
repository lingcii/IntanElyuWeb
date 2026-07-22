/**
 * logout.js
 * Handles the logout flow: shows a confirmation modal first, then calls the Laravel API to invalidate the session,
 * then redirects to logout.php to destroy the PHP session as well.
 */
(function () {
    'use strict';

    /**
     * Determine the path to login.php relative to the current page.
     * Pages inside views/LUPTO/, views/PICTO/, views/MUNICIPAL/ are two levels deep.
     */
    function getLoginUrl() {
        const path = window.location.pathname;
        if (
            path.includes('/views/PICTO/') ||
            path.includes('/views/LUPTO/') ||
            path.includes('/views/MUNICIPAL/')
        ) {
            return '../../logout.php';
        }
        return 'logout.php';
    }

    /**
     * Perform instant logout: dispatches the API logout request non-blocking with keepalive: true,
     * and instantly redirects to logout.php to destroy the PHP session without waiting for network latency.
     */
    function doLogout() {
        const redirectUrl = getLoginUrl();

        // Non-blocking background API fetch so user does NOT wait for cloud network latency
        try {
            const base = window.API_CONFIG ? window.API_CONFIG.BASE_URL : '';
            if (base) {
                fetch(`${base}/api/auth/logout`, {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Accept': 'application/json' },
                    keepalive: true,
                }).catch(function () {});
            }
        } catch (_) {}

        // Clear local storage / session storage tokens if present
        try {
            sessionStorage.clear();
        } catch (_) {}

        // Instant redirect to clear PHP session and open login.php
        window.location.href = redirectUrl;
    }
    
    // Show logout confirmation modal
function showLogoutConfirmation() {
    const modal = document.getElementById('logoutConfirmModal');
    if (modal) {
        modal.classList.add('active');
    } else {
        // Fallback to native confirm if modal not found
        if (confirm('Are you sure you want to logout?')) {
            doLogout();
        }
    }
}
    // Hide logout confirmation modal
    function hideLogoutConfirmation() {
        const modal = document.getElementById('logoutConfirmModal');
        if (modal) {
            modal.classList.remove('active');
        }
    }
    
    // Bind modal events
    function bindModalEvents() {
        const cancelBtn = document.getElementById('cancelLogoutBtn');
        const confirmBtn = document.getElementById('confirmLogoutBtn');
        const modal = document.getElementById('logoutConfirmModal');
        
        if (cancelBtn) {
            cancelBtn.addEventListener('click', hideLogoutConfirmation);
        }
        
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
                hideLogoutConfirmation();
                doLogout();
            });
        }
        
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target.id === 'logoutConfirmModal') {
                    hideLogoutConfirmation();
                }
            });
        }
    }

    /**
     * Attach the logout handler to every element that links to logout.php.
     * This intercepts sidebar/header logout links to show confirmation first.
     */
    function bindLogoutLinks() {
        document.querySelectorAll('a[href*="logout.php"]').forEach(function (link) {
            // Avoid binding twice
            if (link.dataset.logoutBound) return;
            link.dataset.logoutBound = 'true';

            link.addEventListener('click', function (e) {
                e.preventDefault();
                showLogoutConfirmation();
            });
        });
    }

    // Initialize everything
    function init() {
        bindLogoutLinks();
        bindModalEvents();
    }

    // Bind immediately for links already in the DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Re-bind after SPA tab loads inject new DOM nodes
    document.addEventListener('tabshow', function() {
        bindLogoutLinks();
        bindModalEvents();
    }, true);

    // Expose globally so it can be called directly if needed
    window.doLogout = doLogout;
    window.showLogoutConfirmation = showLogoutConfirmation;
    window.hideLogoutConfirmation = hideLogoutConfirmation;
})();
