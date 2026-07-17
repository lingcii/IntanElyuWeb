<?php

$frontendRootPath = strtolower(str_replace('\\', '/', dirname(__DIR__)));
$entryFileDir = strtolower(str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME'])));

$basePath = '';
if (str_starts_with($entryFileDir, $frontendRootPath)) {
    $relativePath = substr($entryFileDir, strlen($frontendRootPath));
    $depth = substr_count($relativePath, '/');
    for ($i = 0; $i < $depth; $i++) {
        $basePath .= '../';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard' ?></title>

    <!-- Silence non-essential console logs to protect sensitive data -->
    <script>
        (function() {
            var noop = function() {};
            var methods = ['log', 'info', 'debug', 'table', 'trace', 'warn', 'dir', 'clear'];
            methods.forEach(function(method) {
                if (window.console && window.console[method]) {
                    window.console[method] = noop;
                }
            });
        })();
    </script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Component CSS -->
    <link rel="stylesheet" href="<?= $basePath ?>css/components/header.css">
    <link rel="stylesheet" href="<?= $basePath ?>css/components/sidebar.css">
    <link rel="stylesheet" href="<?= $basePath ?>css/components/sections.css">
    
    <!-- Role-specific Base CSS -->
    <?php
    $userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'lupto';
    // Determine the correct CSS directory
    if ($userRole === 'picto') {
        $cssDir = 'PICTO';
    } elseif (str_ends_with($userRole, '_mto') || $userRole === 'municipal') {
        $cssDir = 'MUNICIPAL';
    } else {
        $cssDir = 'LUPTO';
    }
    ?>
    <link rel="stylesheet" href="<?= $basePath ?>css/<?= $cssDir ?>/base.css">

    <?php if ($cssDir === 'MUNICIPAL'): ?>
    <!-- Leaflet Map & MarkerCluster CSS (loaded globally for faster SPA transitions) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
    <?php endif; ?>

    <!-- Extra head content from page -->
    <?= isset($extraHeadContent) ? $extraHeadContent : '' ?>

    <!-- api-config.js must be in <head> so it runs before any inline or module scripts in the body -->
    <script src="<?= $basePath ?>scripts/api-config.js?v=<?= time() ?>"></script>
</head>

<body>
    <!-- APPLY SIDEBAR STATE IMMEDIATELY BEFORE ANYTHING ELSE RENDERS -->
    <script>
    (function() {
        var isMobile = window.innerWidth <= 768;
        if (!isMobile) {
            var saved = localStorage.getItem('cpdo_sidebar_collapsed') === 'true';
            // Add/remove collapsed class RIGHT NOW before DOM is fully rendered
            document.documentElement.classList.toggle('sidebar-collapsed-initial', saved);
        }
    })();
    </script>

    <div class="app-wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Header -->
            <?php include __DIR__ . '/header.php'; ?>

            <!-- Page Body -->
            <div class="page-body">
                <?= isset($pageContent) ? $pageContent : '' ?>
            </div>
        </div>
    </div>

    <?php if ($cssDir === 'MUNICIPAL'): ?>
    <!-- Global Map and Chart Libraries (Parallelized for SPA speed) -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <?php endif; ?>

    <!-- Scripts -->
    <script src="<?= $basePath ?>scripts/components/header.js"></script>
    <script src="<?= $basePath ?>scripts/components/sidebar.js"></script>
    <script src="<?= $basePath ?>scripts/map-cache.js"></script>
    <script src="<?= $basePath ?>scripts/spa-router.js"></script>
    <script src="<?= $basePath ?>scripts/logout.js"></script>

<?php if (!empty($_SESSION['must_change_password'])): ?>
    <!-- First-Time Login Required Modal -->
    <div class="lupto-modal-overlay" id="globalFirstTimeLoginModal" style="display:none; z-index: 99999;">
        <div class="lupto-modal-content" style="max-width: 420px; text-align: center;">
            <div class="lupto-modal-header" style="background: #1e3a8a;">
                <h3 class="lupto-modal-title" style="margin: 0; font-size: 16px; font-weight: 700; color: #fff;"><i class="fas fa-lock"></i> First-Time Login Required</h3>
            </div>
            <div class="lupto-modal-body" style="padding: 24px; background: #fff;">
                <i class="fas fa-key" style="font-size: 56px; color: #eab308; display: block; margin: 12px auto 20px;"></i>
                <p style="font-size: 14px; font-weight: 700; color: #1e293b; margin: 0 0 8px;">
                    First-Time Login Required
                </p>
                <p style="font-size: 13px; color: #64748b; line-height: 1.5; margin: 0;">
                    You are currently using the default password assigned to your account.
                    For security reasons, you must change your password before accessing the system.
                </p>
            </div>
            <div class="lupto-modal-footer" style="justify-content: center; gap: 12px; background: #f8fafc; padding: 16px; display: flex; border-top: 1px solid #e2e8f0;">
                <button class="btn-gov btn-gov-secondary" style="min-width: 100px;" onclick="window.location.href='<?= $basePath ?>logout.php'">Cancel</button>
                <button class="btn-gov" style="background: #1e3a8a; border-color: #1e3a8a; min-width: 150px; color: #fff;" onclick="goToSecuritySettings()">Change Password</button>
            </div>
        </div>
    </div>

    <!-- Access Denied Modal -->
    <div class="lupto-modal-overlay" id="globalAccessDeniedModal" style="display:none; z-index: 99999;">
        <div class="lupto-modal-content" style="max-width: 420px; text-align: center;">
            <div class="lupto-modal-header" style="background: #dc2626;">
                <h3 class="lupto-modal-title" style="margin: 0; font-size: 16px; font-weight: 700; color: #fff;"><i class="fas fa-exclamation-triangle"></i> Access Denied</h3>
            </div>
            <div class="lupto-modal-body" style="padding: 24px; background: #fff;">
                <i class="fas fa-ban" style="font-size: 56px; color: #dc2626; display: block; margin: 12px auto 20px;"></i>
                <p style="font-size: 14px; font-weight: 700; color: #1e293b; margin: 0 0 8px;">
                    Access Denied
                </p>
                <p style="font-size: 13px; color: #64748b; line-height: 1.5; margin: 0;">
                    Please change your password first before accessing other modules.
                </p>
            </div>
            <div class="lupto-modal-footer" style="justify-content: center; gap: 12px; background: #f8fafc; padding: 16px; display: flex; border-top: 1px solid #e2e8f0;">
                <button class="btn-gov btn-gov-secondary" style="min-width: 100px;" onclick="window.location.href='<?= $basePath ?>logout.php'">Logout</button>
                <button class="btn-gov" style="background: #dc2626; border-color: #dc2626; min-width: 180px; color: #fff;" onclick="goToSecuritySettings()">Go to Security Settings</button>
            </div>
        </div>
    </div>

    <script>
        window.MUST_CHANGE_PASSWORD = true;
        
        function goToSecuritySettings() {
            window.location.href = 'settings.php';
        }

        (function() {
            const justLoggedIn = <?= json_encode(!empty($_SESSION['just_logged_in'])) ?>;
            const isSettingsPage = window.location.pathname.endsWith('settings.php');
            
            /* Clear just_logged_in flag in PHP session via background sync if it's set */
            if (justLoggedIn) {
                fetch('<?= $basePath ?>sync-session.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ clear_just_logged_in: true })
                }).catch(err => console.error(err));
            }

            setTimeout(() => {
                if (isSettingsPage) {
                    if (justLoggedIn) {
                        const firstTimeModal = document.getElementById('globalFirstTimeLoginModal');
                        if (firstTimeModal) firstTimeModal.style.display = 'flex';
                    }
                } else {
                    if (justLoggedIn) {
                        const firstTimeModal = document.getElementById('globalFirstTimeLoginModal');
                        if (firstTimeModal) firstTimeModal.style.display = 'flex';
                    } else {
                        const deniedModal = document.getElementById('globalAccessDeniedModal');
                        if (deniedModal) deniedModal.style.display = 'flex';
                    }
                }
            }, 200);

            /* Prevent escape key from closing modals */
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    e.stopPropagation();
                }
            }, true);

            /* Block clicks on navigation links */
            document.addEventListener('click', e => {
                const link = e.target.closest('a');
                if (!link) return;
                const href = link.getAttribute('href');
                if (!href || href.startsWith('#') || href.includes('logout.php') || href.includes('sync-session.php')) {
                    return;
                }
                
                e.preventDefault();
                e.stopPropagation();
                
                const deniedModal = document.getElementById('globalAccessDeniedModal');
                if (deniedModal) {
                    deniedModal.style.display = 'flex';
                }
                const firstModal = document.getElementById('globalFirstTimeLoginModal');
                if (firstModal) firstModal.style.display = 'none';
            }, true);
        })();
    </script>
<?php endif; ?>
</body>
</html>
