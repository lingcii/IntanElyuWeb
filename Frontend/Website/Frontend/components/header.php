<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

//  * header.php — shared top-header component
//  * Reads live data from $conn (injected by including page).
// ROLE-BASED HEADER CONFIGURATION
$headerConfig = [
    // LUPTO Role
    'lupto' => [
        'title' => 'LUPTO',
        'subtitle' => 'LA UNION PROVINCIAL TOURISM OFFICE (LUPTO)'
    ],
    // PICTO Role
    'picto' => [
        'title' => 'PICTO',
        'subtitle' => 'PROVINCIAL INFORMATION AND COMMUNICATIONS TECHNOLOGY OFFICE (PICTO)'
    ],
    // Municipal/LGU Roles
    'municipal' => [
        'title' => 'MTO',
        'subtitle' => 'MUNICIPAL TOURISM OFFICE (MTO)'
    ],
    'san_juan_mto' => [
        'title' => 'San Juan MTO',
        'subtitle' => 'SAN JUAN MUNICIPAL TOURISM OFFICE'
    ],
    'san_fernando_mto' => [
        'title' => 'San Fernando MTO',
        'subtitle' => 'SAN FERNANDO MUNICIPAL TOURISM OFFICE'
    ],
    'bauang_mto' => [
        'title' => 'Bauang MTO',
        'subtitle' => 'BAUANG MUNICIPAL TOURISM OFFICE'
    ],
    'agoo_mto' => [
        'title' => 'Agoo MTO',
        'subtitle' => 'AGOO MUNICIPAL TOURISM OFFICE'
    ],
    'luna_mto' => [
        'title' => 'Luna MTO',
        'subtitle' => 'LUNA MUNICIPAL TOURISM OFFICE'
    ],
    'san_gabriel_mto' => [
        'title' => 'San Gabriel MTO',
        'subtitle' => 'SAN GABRIEL MUNICIPAL TOURISM OFFICE'
    ],
    'balaoan_mto' => [
        'title' => 'Balaoan MTO',
        'subtitle' => 'BALAOAN MUNICIPAL TOURISM OFFICE'
    ],
    'aringay_mto' => [
        'title' => 'Aringay MTO',
        'subtitle' => 'ARINGAY MUNICIPAL TOURISM OFFICE'
    ],
    'rosario_mto' => [
        'title' => 'Rosario MTO',
        'subtitle' => 'ROSARIO MUNICIPAL TOURISM OFFICE'
    ],
    'bacnotan_mto' => [
        'title' => 'Bacnotan MTO',
        'subtitle' => 'BACNOTAN MUNICIPAL TOURISM OFFICE'
    ],
    'naguilian_mto' => [
        'title' => 'Naguilian MTO',
        'subtitle' => 'NAGUILIAN MUNICIPAL TOURISM OFFICE'
    ],
    'tubao_mto' => [
        'title' => 'Tubao MTO',
        'subtitle' => 'TUBAO MUNICIPAL TOURISM OFFICE'
    ],
    'pugo_mto' => [
        'title' => 'Pugo MTO',
        'subtitle' => 'PUGO MUNICIPAL TOURISM OFFICE'
    ],
    'caba_mto' => [
        'title' => 'Caba MTO',
        'subtitle' => 'CABA MUNICIPAL TOURISM OFFICE'
    ],
    'santo_tomas_mto' => [
        'title' => 'Santo Tomas MTO',
        'subtitle' => 'SANTO TOMAS MUNICIPAL TOURISM OFFICE'
    ],
    'bangar_mto' => [
        'title' => 'Bangar MTO',
        'subtitle' => 'BANGAR MUNICIPAL TOURISM OFFICE'
    ],
    'burgos_mto' => [
        'title' => 'Burgos MTO',
        'subtitle' => 'BURGOS MUNICIPAL TOURISM OFFICE'
    ],
    'bagulin_mto' => [
        'title' => 'Bagulin MTO',
        'subtitle' => 'BAGULIN MUNICIPAL TOURISM OFFICE'
    ],
    'santol_mto' => [
        'title' => 'Santol MTO',
        'subtitle' => 'SANTOL MUNICIPAL TOURISM OFFICE'
    ],
    'sudipen_mto' => [
        'title' => 'Sudipen MTO',
        'subtitle' => 'SUDIPEN MUNICIPAL TOURISM OFFICE'
    ]
];

// Get current user's role from session (with validation)
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;

// Resolve municipality name for municipal/MTO roles
$resolvedMunicipalityName = null;
$isMunicipalRole = ($userRole === 'municipal' || (is_string($userRole) && str_ends_with($userRole, '_mto')));
if ($isMunicipalRole) {
    $resolvedMunicipalityName = $_SESSION['user_municipality_name'] ?? null;
}

// Determine header text dynamically
if ($isMunicipalRole && $resolvedMunicipalityName) {
    $headerText = [
        'title'    => $resolvedMunicipalityName . ' MTO',
        'subtitle' => strtoupper($resolvedMunicipalityName) . ' MUNICIPAL TOURISM OFFICE',
    ];
} elseif ($userRole && isset($headerConfig[$userRole])) {
    $headerText = $headerConfig[$userRole];
} else {
    $headerText = [
        'title'    => 'Dashboard',
        'subtitle' => 'Tourism Monitoring System',
    ];
}

// Logged-in user name (from session)
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;

$roleAccent = match (true) {
    $userRole === 'picto' => '#1E3A8A',
    $userRole === 'lupto' => '#0B5394',
    str_ends_with($userRole, '_mto') || $userRole === 'municipal' => '#D97706',
    default => '#6B7280',
};

// Notification type → icon/color map
$notifMeta = [
    'missing_data'         => ['icon' => 'fa-triangle-exclamation', 'color' => 'var(--danger)'],
    'delayed_program'      => ['icon' => 'fa-clock',                'color' => 'var(--danger)'],
    'low_investment'       => ['icon' => 'fa-chart-line',           'color' => 'var(--warning)'],
    'agricultural_decline' => ['icon' => 'fa-leaf',                 'color' => 'var(--warning)'],
];

// Human-readable time-ago helper
function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff/60)  . ' min ago';
    if ($diff < 86400)  return floor($diff/3600) . ' hr ago';
    return floor($diff/86400) . ' day(s) ago';
}
?>
<header class="top-header" id="topHeader">
    <div class="header-left">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <div class="header-brand">
            <h1 class="brand-title"><?= htmlspecialchars($headerText['subtitle']) ?></h1>
            <span class="brand-subtitle">Tourism Monitoring System <span class="header-role-badge" style="background: <?= $roleAccent ?>"><?= htmlspecialchars($headerText['title']) ?></span></span>
        </div>
    </div>

    <div class="header-controls">

        <div class="date-control">
            <i class="fas fa-calendar-day date-icon"></i>
            <input type="date" class="ctrl-date" id="reportDate" value="<?= date('Y-m-d') ?>">
        </div>

        <div class="notif-control">
            <button class="notif-btn" id="notifBtn" aria-label="Notifications">
                <i class="fas fa-bell"></i>
                <span class="notif-badge" id="notifBadge" style="display:none;">0</span>
            </button>
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header">
                    <div class="notif-header-left">
                        <i class="fas fa-bell"></i> Notifications
                    </div>
                    <div class="notif-header-actions">
                        <button class="mark-all-read" onclick="window.markAllRead()" title="Mark all as read">Mark all read</button>
                        <button class="clear-all" onclick="window.clearAllNotifs()" title="Clear all">Clear</button>
                    </div>
                </div>
                <div class="notif-scroll" id="notifItems">
                    <div class="notif-item">
                        <i class="fas fa-spinner fa-spin" style="color:#94A3B8; margin: 12px auto; font-size: 16px;"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="user-control" id="userControl">
            <div class="user-avatar-icon">
                <i class="fas fa-user-circle"></i>
            </div>
            <span class="user-label"><?= htmlspecialchars($userName) ?></span>
            <i class="fas fa-chevron-down user-caret" id="userCaret"></i>
            <div class="user-dropdown" id="userDropdown">
                <a href="settings.php" class="dd-item">
                    <i class="fas fa-user-pen"></i> My Profile
                </a>
                <a href="settings.php" class="dd-item">
                    <i class="fas fa-sliders"></i> Settings
                </a>
                <div class="dd-divider"></div>
                <a href="<?= $basePath ?>logout.php" class="dd-item dd-danger">
                    <i class="fas fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Logout Confirmation Modal Styles (inline for global availability) -->
<style>
#logoutConfirmModal, 
#logoutConfirmModal button, 
#logoutConfirmModal p, 
#logoutConfirmModal h1, 
#logoutConfirmModal h2, 
#logoutConfirmModal h3, 
#logoutConfirmModal h4, 
#logoutConfirmModal span:not(.fas):not(.far):not(.fa) {
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
}

#logoutConfirmModal i,
#logoutConfirmModal .fas,
#logoutConfirmModal .far,
#logoutConfirmModal .fa,
#logoutConfirmModal [class*="fa-"] {
  font-family: "Font Awesome 6 Free", "Font Awesome 5 Free", "FontAwesome" !important;
}

#logoutConfirmModal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 10002;
  overflow-y: auto;
  padding: 24px;
  backdrop-filter: blur(2px);
}
#logoutConfirmModal.active {
  display: flex;
  align-items: center;
  justify-content: center;
  animation: fadeIn 0.2s ease;
}
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
#logoutConfirmModal .modal-content {
  background: white;
  border-radius: 16px;
  width: 90%;
  max-width: 420px;
  max-height: 90vh;
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
#logoutConfirmModal .btn {
  padding: 12px 20px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  border: 2px solid #E5E7EB;
  background: white;
  color: #4B5563;
}
#logoutConfirmModal .btn:hover {
  background: #F9FAFB;
}
#logoutConfirmModal .btn.btn-outline {
  border: 2px solid #E5E7EB;
}
#logoutConfirmModal .btn.btn-danger {
  border: none;
  color: white;
}
</style>

<!-- Logout Confirmation Modal -->
<div class="modal" id="logoutConfirmModal">
    <div class="modal-content" style="max-width: 420px; border-radius: 16px; overflow: hidden;">
        <div style="background: #FEE2E2; padding: 28px 28px 16px 28px; text-align: center;">
            <div style="width: 56px; height: 56px; background: #DC2626; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;">
                <i class="fas fa-right-from-bracket" style="color: white; font-size: 22px;"></i>
            </div>
            <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #991B1B;">Logout</h3>
        </div>
        <div style="padding: 20px 28px 28px 28px;">
            <p style="text-align: center; color: #4B5563; margin: 0 0 24px 0; font-size: 14px;">Are you sure you want to logout?</p>
            <div style="display: flex; gap: 12px;">
                <button class="btn btn-outline" id="cancelLogoutBtn" style="flex: 1; justify-content: center;">
                    <i class="fas fa-times" style="margin-right: 6px;"></i> No
                </button>
                <button class="btn btn-danger" id="confirmLogoutBtn" style="flex: 1; justify-content: center; background: #DC2626; border-color: #DC2626;">
                    <i class="fas fa-check" style="margin-right: 6px;"></i> Yes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete All Notifications Confirmation Modal -->
<div class="modal" id="clearNotifsConfirmModal" style="display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5); z-index: 10500; align-items: center; justify-content: center; backdrop-filter: blur(2px); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    <div class="modal-content" style="max-width: 420px; width: 90%; border-radius: 16px; overflow: hidden; background: #fff; box-shadow: 0 20px 60px rgba(0,0,0,0.3); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
        <div style="background: #FEE2E2; padding: 28px 28px 16px 28px; text-align: center;">
            <div style="width: 56px; height: 56px; background: #DC2626; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;">
                <i class="fas fa-trash-can" style="color: white; font-size: 22px;"></i>
            </div>
            <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #991B1B; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">Delete All Notifications</h3>
        </div>
        <div style="padding: 20px 28px 28px 28px; text-align: center;">
            <p style="color: #4B5563; margin: 0 0 24px 0; font-size: 14px; line-height: 1.5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">Are you sure you want to delete all notifications? This action cannot be undone.</p>
            <div style="display: flex; gap: 12px;">
                <button type="button" class="btn btn-outline" id="cancelClearNotifsBtn" style="flex: 1; justify-content: center; padding: 12px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; border: 2px solid #E5E7EB; background: white; color: #4B5563; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
                    Cancel
                </button>
                <button type="button" class="btn btn-danger" id="confirmClearNotifsBtn" style="flex: 1; justify-content: center; padding: 12px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; background: #DC2626; border: none; color: white; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
                    Yes, Delete All
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var role = document.querySelector('meta[name="user-role"]') ? '' : '<?= htmlspecialchars($userRole ?? "") ?>';
    var prefixMap = { picto: 'pitco', lupto: 'lupto' };
    var prefix = prefixMap[role] || (role.endsWith && role.endsWith('_mto') || role === 'municipal' ? 'municipal' : null);
    if (!prefix) return;
    var baseUrl = window.API_CONFIG ? window.API_CONFIG.BASE_URL : 'http://localhost:8000';
    var API = baseUrl + '/api/' + prefix + '/notifications';
    var unreadCount = 0, lastNotifId = 0, badgePrev = 0;
    var notifItemsEl = document.getElementById('notifItems');
    var notifBadge = document.getElementById('notifBadge');
    var notifCountEl = document.getElementById('notifCount');
    if (!notifItemsEl) return;

    function showNotifToast(msg, type) {
        if (typeof window.showToast === 'function') {
            window.showToast(msg, type || 'success');
            return;
        }
        var toast = document.createElement('div');
        toast.className = 'notif-toast-pop';
        toast.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#1E293B;color:#fff;padding:12px 20px;border-radius:8px;font-size:14px;box-shadow:0 10px 25px rgba(0,0,0,0.2);z-index:11000;display:flex;align-items:center;gap:10px;animation:fadeIn 0.3s ease;';
        toast.innerHTML = '<i class="fas fa-check-circle" style="color:#10B981;"></i><span>' + esc(msg) + '</span>';
        document.body.appendChild(toast);
        setTimeout(function() { toast.remove(); }, 3500);
    }

    function timeAgo(dateStr) {
        if (!dateStr) return '';
        var diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
        if (diff < 86400) return Math.floor(diff / 3600) + ' hr ago';
        if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
        return new Date(dateStr).toLocaleDateString();
    }
    function esc(str) { return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
    function updateBadge(count) {
        unreadCount = count;
        if (!notifBadge) return;
        notifBadge.textContent = count > 99 ? '99+' : count;
        notifBadge.style.display = count > 0 ? '' : 'none';
        if (count > badgePrev && badgePrev >= 0) {
            notifBadge.classList.remove('pulse'); void notifBadge.offsetWidth; notifBadge.classList.add('pulse');
            var btn = document.getElementById('notifBtn');
            if (btn) { btn.classList.remove('shake'); void btn.offsetWidth; btn.classList.add('shake'); }
        }
        badgePrev = count;
        if (notifCountEl) notifCountEl.textContent = count + ' new';
    }
    function renderNotifications(notifications) {
        var html = '';
        if (!notifications || !notifications.length) {
            html = '<div class="notif-empty"><i class="fas fa-bell-slash"></i><span>No notifications yet</span></div>';
        } else {
            notifications.forEach(function (n) {
                if (n.id > lastNotifId) lastNotifId = n.id;
                var color = n.type_color || 'gray';
                var icon = n.type_icon || 'fa-bell';
                var typeLabel = (n.type || '').replace(/_/g, ' ');
                html += '<div class="notif-item' + (n.is_read ? '' : ' unread') + '" data-id="' + n.id + '" onclick="window.handleNotifClick(this,\'' + esc(n.action_url || '') + '\',' + n.id + ')">' +
                    '<div class="notif-dot"></div>' +
                    '<div class="notif-icon ' + color + '"><i class="fas ' + icon + '"></i></div>' +
                    '<div class="notif-body">' +
                    '<div class="notif-type ' + color + '">' + esc(typeLabel) + '</div>' +
                    '<div class="notif-title">' + esc(n.title || '') + '</div>' +
                    '<div class="notif-msg">' + esc(n.message || '') + '</div>' +
                    '<div class="notif-meta">' +
                    (n.actor_name ? '<span><i class="fas fa-user-circle"></i> ' + esc(n.actor_name) + '</span>' : '') +
                    (n.municipality_name ? '<span><i class="fas fa-map-pin"></i> ' + esc(n.municipality_name) + '</span>' : '') +
                    '<span><i class="far fa-clock"></i> ' + timeAgo(n.created_at) + '</span>' +
                    '</div></div>' +
                    '<button class="notif-delete" title="Delete" onclick="event.stopPropagation();window.deleteNotif(' + n.id + ')"><i class="fas fa-times"></i></button>' +
                    '</div>';
            });
        }
        html += '<div class="notif-footer"><a href="notifications.php"><i class="fas fa-list"></i> View All Notifications <i class="fas fa-arrow-right"></i></a></div>';
        notifItemsEl.innerHTML = html;
    }
    function fetchNotifications() {
        window.API_CONFIG.get(API + '/recent').then(function (data) {
            updateBadge(data.unread_count || 0);
            renderNotifications(data.notifications || []);
        }).catch(function () {
            notifItemsEl.innerHTML = '<div class="notif-empty"><i class="fas fa-bell-slash"></i><span>Unable to load</span></div>';
        });
    }
    window.handleNotifClick = function (el, url, id) {
        el.classList.remove('unread');
        var dot = el.querySelector('.notif-dot');
        if (dot) dot.style.display = 'none';
        window.API_CONFIG.patch(API + '/' + id + '/read', {}).catch(function () {});
        fetchNotifications();
        if (url) {
            var targetPage = url.split('?')[0];
            if (typeof window.switchTab === 'function') {
                window.switchTab(targetPage);
            } else if (url.endsWith('.php') || url.includes('.php')) {
                window.location.href = url;
            }
        }
    };
    window.deleteNotif = function (id) {
        window.API_CONFIG.delete(API + '/' + id).then(function () { fetchNotifications(); }).catch(function () {});
    };
    window.markAllRead = function () {
        unreadCount = 0;
        updateBadge(0);
        if (notifItemsEl) {
            var items = notifItemsEl.querySelectorAll('.notif-item.unread');
            items.forEach(function(item) {
                item.classList.remove('unread');
                var dot = item.querySelector('.notif-dot');
                if (dot) dot.style.display = 'none';
            });
        }
        window.API_CONFIG.patch(API + '/read-all', {}).then(function () {
            fetchNotifications();
        }).catch(function () {});
    };
    window.clearAllNotifs = function () {
        var clearModal = document.getElementById('clearNotifsConfirmModal');
        if (clearModal) {
            clearModal.style.display = 'flex';
        }
    };

    document.addEventListener('click', function(e) {
        if (e.target && e.target.id === 'cancelClearNotifsBtn') {
            var clearModal = document.getElementById('clearNotifsConfirmModal');
            if (clearModal) clearModal.style.display = 'none';
        }
        if (e.target && e.target.id === 'confirmClearNotifsBtn') {
            var clearModal = document.getElementById('clearNotifsConfirmModal');
            if (clearModal) clearModal.style.display = 'none';
            window.API_CONFIG.delete(API + '/clear-all').then(function () {
                updateBadge(0);
                fetchNotifications();
                showNotifToast('All notifications have been deleted successfully.', 'success');
            }).catch(function (err) {
                console.error('Failed to clear notifications:', err);
            });
        }
    });
    function startSSE() {
        try {
            var es = new EventSource(API + '/stream?last_id=' + lastNotifId, { withCredentials: true });
            es.addEventListener('notification', function (e) {
                fetchNotifications();
                try {
                    var notif = JSON.parse(e.data);
                    void 0;
                    
                    if (notif.module === 'Dashboard' || notif.type === 'spot_approved' || notif.type === 'spot_rejected' || notif.type === 'spot_pending') {
                        if (typeof window.softRefreshDashboard === 'function') {
                            window.softRefreshDashboard();
                        }
                        if (typeof window.softRefreshTouristSpots === 'function') {
                            window.softRefreshTouristSpots();
                        }
                    } else if (notif.module === 'Users' || notif.type === 'user_created' || notif.type === 'user_updated') {
                        if (typeof window.softRefreshDashboard === 'function') {
                            window.softRefreshDashboard();
                        }
                        if (typeof window.refreshTable === 'function') {
                            window.refreshTable();
                        }
                    } else if (notif.module === 'FareData' || notif.type === 'fare_updated') {
                        if (typeof window.softRefreshDashboard === 'function') {
                            window.softRefreshDashboard();
                        }
                        if (typeof window.softRefreshFareData === 'function') {
                            window.softRefreshFareData();
                        }
                    }
                } catch (err) {
                    console.error('Error parsing notification data:', err);
                }
            });
            es.addEventListener('count', function (e) { try { var d = JSON.parse(e.data); updateBadge(d.unread_count || 0); } catch (er) {} });
            es.onerror = function () { es.close(); };
        } catch (err) {}
    }
    fetchNotifications();
    setTimeout(startSSE, 2000);
    setInterval(fetchNotifications, 60000);
})();
</script>