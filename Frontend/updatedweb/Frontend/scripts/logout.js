/**
 * logout.js
 * Handles the logout flow: shows a confirmation modal first, then calls the Laravel API to invalidate the session,
 * then redirects to logout.php to destroy the PHP session as well.
 */
(function () {
    'use strict';

    // Returns the correct logout.php path depending on the current page location.
    // Pages inside views/ need to go one level up.
    function getLoginUrl() {
        const path = window.location.pathname;
        if (path.includes('/views/')) {
            return '../logout.php';
        }
        return 'logout.php';
    }

    // Perform instant logout: dispatches the API logout request non-blocking with keepalive: true,
    // and instantly redirects to logout.php to destroy the PHP session without waiting for network latency.
    function doLogout() {
        const redirectUrl = getLoginUrl();

        // Close any active SSE streaming connections instantly to free TCP sockets
        try {
            if (window._headerSSE) { window._headerSSE.close(); }
            if (window._activityLogSSE) { window._activityLogSSE.close(); }
        } catch (_) {}

        // Clear local & session storage instantly
        try {
            sessionStorage.clear();
            localStorage.clear();
        } catch (_) {}

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

        // Instant navigation to clear PHP session and load login page
        window.location.replace(redirectUrl);
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

    
    //   Attach the logout handler to every element that links to logout.php.
    //   This intercepts sidebar/header logout links to show confirmation first.
     
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
