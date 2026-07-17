<?php
require_once __DIR__ . '/../../session-bridge.php';

$pageTitle = 'Notification Center';
$allowedRoles = ['picto', 'pitco', 'lupto', 'municipal'];
$allowedRoles = array_merge($allowedRoles, array_map(fn($m) => strtolower(str_replace(' ', '_', $m)) . '_mto', [
    'San Juan', 'San Fernando', 'Bauang', 'Agoo', 'Luna', 'San Gabriel',
    'Balaoan', 'Aringay', 'Rosario', 'Bacnotan', 'Naguilian', 'Tubao',
    'Pugo', 'Caba', 'Santo Tomas', 'Bangar', 'Burgos', 'Bagulin', 'Santol', 'Sudipen'
]));
if (!in_array($_SESSION['user_role'], $allowedRoles)) {
    header('Location: ../../login.php');
    exit;
}

ob_start();
?>
    <link rel="stylesheet" href="../../css/PICTO/activity-logs.css?v=<?= time() ?>">
    <style>
        .nc-page { padding: 20px; }
        .nc-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .nc-header h2 { margin: 0; font-size: 20px; display: flex; align-items: center; gap: 10px; }
        .nc-unread-count { font-size: 13px; color: #3B82F6; background: #EFF6FF; padding: 4px 12px; border-radius: 20px; font-weight: 600; }
        .nc-actions { display: flex; gap: 8px; }
        .nc-actions button { padding: 8px 16px; border-radius: 8px; border: 1.5px solid #E2E8F0; background: #fff; cursor: pointer; font-size: 12.5px; font-weight: 600; color: #475569; transition: all 0.15s; }
        .nc-actions button:hover { background: #F8FAFC; }
        .nc-actions .btn-read-all { color: #2563EB; border-color: #BFDBFE; }
        .nc-actions .btn-read-all:hover { background: #EFF6FF; }
        .nc-actions .btn-clear { color: #DC2626; border-color: #FECACA; }
        .nc-actions .btn-clear:hover { background: #FEF2F2; }
        .nc-filters { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; align-items: center; }
        .nc-filters select, .nc-filters input { padding: 8px 12px; border: 1.5px solid #E2E8F0; border-radius: 8px; font-size: 12.5px; color: #374151; background: #fff; font-family: inherit; }
        .nc-filters select { min-width: 140px; }
        .nc-filters input { min-width: 200px; }
        .nc-filters input:focus, .nc-filters select:focus { outline: none; border-color: #3B82F6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .nc-list { display: flex; flex-direction: column; gap: 6px; }
        .nc-card { display: flex; align-items: flex-start; gap: 14px; padding: 14px 16px; background: #fff; border: 1px solid #E5E7EB; border-radius: 10px; cursor: pointer; transition: all 0.18s; }
        .nc-card:hover { border-color: #D1D5DB; box-shadow: 0 2px 8px rgba(0,0,0,0.06); transform: translateX(3px); }
        .nc-card.unread { background: #F0F7FF; border-color: #BFDBFE; }
        .nc-card .nc-dot { width: 8px; height: 8px; border-radius: 50%; background: #3B82F6; flex-shrink: 0; margin-top: 6px; display: none; }
        .nc-card.unread .nc-dot { display: block; }
        .nc-card .nc-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; flex-shrink: 0; }
        .nc-icon.blue { background: #3B82F6; } .nc-icon.green { background: #10B981; } .nc-icon.red { background: #EF4444; }
        .nc-icon.orange { background: #F59E0B; } .nc-icon.purple { background: #8B5CF6; } .nc-icon.gray { background: #6B7280; } .nc-icon.yellow { background: #EAB308; }
        .nc-card .nc-body { flex: 1; min-width: 0; }
        .nc-card .nc-type { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 2px; color: #64748B; }
        .nc-card .nc-title { font-size: 13.5px; font-weight: 600; color: #1E293B; margin-bottom: 3px; }
        .nc-card .nc-msg { font-size: 12px; color: #64748B; line-height: 1.4; }
        .nc-card .nc-meta { display: flex; flex-wrap: wrap; gap: 10px; font-size: 11px; color: #94A3B8; margin-top: 6px; }
        .nc-card .nc-meta span { display: inline-flex; align-items: center; gap: 3px; }
        .nc-card .nc-actions-cell { display: flex; gap: 4px; flex-shrink: 0; opacity: 0; transition: opacity 0.15s; }
        .nc-card:hover .nc-actions-cell { opacity: 1; }
        .nc-card .nc-actions-cell button { background: none; border: none; cursor: pointer; color: #94A3B8; font-size: 13px; padding: 4px 6px; border-radius: 4px; transition: all 0.15s; }
        .nc-card .nc-actions-cell button:hover { color: #DC2626; background: #FEF2F2; }
        .nc-empty { text-align: center; padding: 40px; color: #94A3B8; }
        .nc-empty i { font-size: 36px; margin-bottom: 8px; opacity: 0.3; }
        .nc-pagination { display: flex; justify-content: center; gap: 6px; margin-top: 20px; }
        .nc-pagination button { padding: 8px 14px; border: 1.5px solid #E2E8F0; border-radius: 8px; background: #fff; cursor: pointer; font-size: 12.5px; font-weight: 600; color: #374151; transition: all 0.15s; }
        .nc-pagination button:hover { background: #F8FAFC; border-color: #D1D5DB; }
        .nc-pagination button.active { background: #3B82F6; color: #fff; border-color: #3B82F6; }
        .nc-pagination button:disabled { opacity: 0.4; cursor: default; }
        .nc-time { font-size: 11px; color: #94A3B8; flex-shrink: 0; margin-top: 2px; white-space: nowrap; }
        .toast { position: fixed; bottom: 20px; right: 20px; background: #1E293B; color: #fff; padding: 12px 20px; border-radius: 10px; font-size: 13px; z-index: 9999; opacity: 0; transform: translateY(10px); transition: all 0.3s ease; pointer-events: none; }
        .toast.show { opacity: 1; transform: translateY(0); }
    </style>
<?php
$extraHeadContent = ob_get_clean();

ob_start();
?>
<div class="nc-page">
    <div class="nc-header">
        <div>
            <h2><i class="fas fa-bell" style="color:#64748B;"></i> Notification Center <span class="nc-unread-count" id="nc-unread-badge">0 unread</span></h2>
        </div>
        <div class="nc-actions">
            <button class="btn-read-all" onclick="markAllRead()"><i class="fas fa-check-double"></i> Mark All Read</button>
            <button class="btn-clear" onclick="if(confirm('Clear all notifications permanently?'))clearAll()"><i class="fas fa-trash"></i> Clear All</button>
        </div>
    </div>
    <div class="nc-filters">
        <select id="nc-filter-type" onchange="loadPage(1)">
            <option value="">All Types</option>
            <option value="tourist_spot_added">Tourist Spot Added</option>
            <option value="tourist_spot_updated">Tourist Spot Updated</option>
            <option value="tourist_spot_submitted">Tourist Spot Submitted</option>
            <option value="spot_approved">Tourist Spot Approved</option>
            <option value="spot_rejected">Tourist Spot Rejected</option>
            <option value="user_created">User Created</option>
            <option value="user_updated">User Updated</option>
            <option value="user_deleted">User Deleted</option>
            <option value="user_archived">User Archived</option>
            <option value="user_restored">User Restored</option>
            <option value="municipality_assigned">Municipality Assigned</option>
            <option value="municipality_updated">Municipality Updated</option>
            <option value="system_settings">System Settings</option>
        </select>
        <select id="nc-filter-read" onchange="loadPage(1)">
            <option value="">All Status</option>
            <option value="true">Read</option>
            <option value="false">Unread</option>
        </select>
        <input type="text" id="nc-filter-search" placeholder="Search notifications..." onkeyup="debounceSearch()">
    </div>
    <div class="nc-list" id="nc-list"></div>
    <div class="nc-pagination" id="nc-pagination"></div>
</div>
<div class="toast" id="nc-toast"></div>

<script>
(function () {
    var role = '<?= htmlspecialchars($_SESSION['user_role'] ?? '') ?>';
    var prefixMap = { picto: 'pitco', lupto: 'lupto' };
    var prefix = prefixMap[role] || (role.endsWith && role.endsWith('_mto') || role === 'municipal' ? 'municipal' : null);
    var baseUrl = window.API_CONFIG?.BASE_URL || 'http://localhost:8000';
    var API = baseUrl + '/api/' + prefix + '/notifications';
    var currentPage = 1;
    var perPage = 20;
    var searchTimer = null;

    function timeAgo(d) { if(!d)return'';var s=Math.floor((Date.now()-new Date(d))/1000);if(s<60)return'Just now';if(s<3600)return Math.floor(s/60)+' min ago';if(s<86400)return Math.floor(s/3600)+' hr ago';return Math.floor(s/86400)+' day(s) ago'; }
    function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    function loadPage(page) {
        currentPage = page;
        var type = document.getElementById('nc-filter-type').value;
        var isRead = document.getElementById('nc-filter-read').value;
        var search = document.getElementById('nc-filter-search').value;
        var params = '?per_page=' + perPage + '&page=' + page;
        if (type) params += '&type=' + encodeURIComponent(type);
        if (isRead) params += '&is_read=' + isRead;
        if (search) params += '&search=' + encodeURIComponent(search);

        window.API_CONFIG.get(API + params).then(function (data) {
            var badge = document.getElementById('nc-unread-badge');
            if (badge) badge.textContent = (data.unread_count||0) + ' unread';
            renderList(data.notifications || []);
            renderPagination(data.pagination || {});
        }).catch(function () {
            document.getElementById('nc-list').innerHTML = '<div class="nc-empty"><i class="fas fa-exclamation-circle"></i><p>Failed to load notifications</p></div>';
        });
    }

    function renderList(notifications) {
        var el = document.getElementById('nc-list');
        if (!notifications.length) {
            el.innerHTML = '<div class="nc-empty"><i class="fas fa-bell-slash"></i><p>No notifications found</p></div>';
            return;
        }
        var h = '';
        notifications.forEach(function (n) {
            var color = n.type_color || 'gray';
            var icon = n.type_icon || 'fa-bell';
            var typeLabel = (n.type||'').replace(/_/g,' ');
            h += '<div class="nc-card' + (n.is_read?'':' unread') + '" data-id="' + n.id + '" onclick="cardClick(this,' + n.id + ',\'' + esc(n.action_url||'') + '\')">' +
                '<div class="nc-dot"></div>' +
                '<div class="nc-icon ' + color + '"><i class="fas ' + icon + '"></i></div>' +
                '<div class="nc-body">' +
                '<div class="nc-type" style="color:' + ({blue:'#2563EB',green:'#059669',red:'#DC2626',orange:'#D97706',purple:'#7C3AED',gray:'#64748B',yellow:'#A16207'}[color]||'#64748B') + '">' + esc(typeLabel) + '</div>' +
                '<div class="nc-title">' + esc(n.title||'') + '</div>' +
                '<div class="nc-msg">' + esc(n.message||'') + '</div>' +
                '<div class="nc-meta">' +
                (n.actor_name?'<span><i class="fas fa-user-circle"></i> '+esc(n.actor_name)+'</span>':'') +
                (n.municipality_name?'<span><i class="fas fa-map-pin"></i> '+esc(n.municipality_name)+'</span>':'') +
                (n.spot_name?'<span><i class="fas fa-location-dot"></i> '+esc(n.spot_name)+'</span>':'') +
                '<span><i class="far fa-clock"></i> ' + timeAgo(n.created_at) + '</span>' +
                '</div></div>' +
                '<div class="nc-actions-cell">' +
                '<button title="Delete" onclick="event.stopPropagation();delNotif('+n.id+')"><i class="fas fa-trash"></i></button>' +
                '</div>' +
                '<div class="nc-time">' + timeAgo(n.created_at) + '</div>' +
                '</div>';
        });
        el.innerHTML = h;
    }

    function renderPagination(p) {
        var el = document.getElementById('nc-pagination');
        if (!p || p.last_page <= 1) { el.innerHTML = ''; return; }
        var h = '';
        h += '<button ' + (p.current_page <= 1 ? 'disabled' : 'onclick="loadPage(' + (p.current_page-1) + ')"') + '><i class="fas fa-chevron-left"></i></button>';
        for (var i = 1; i <= p.last_page; i++) {
            h += '<button class="' + (i === p.current_page ? 'active' : '') + '" onclick="loadPage(' + i + ')">' + i + '</button>';
        }
        h += '<button ' + (p.current_page >= p.last_page ? 'disabled' : 'onclick="loadPage(' + (p.current_page+1) + ')"') + '><i class="fas fa-chevron-right"></i></button>';
        el.innerHTML = h;
    }

    window.cardClick = function (el, id, url) {
        el.classList.remove('unread');
        var dot = el.querySelector('.nc-dot');
        if (dot) dot.style.display = 'none';
        window.API_CONFIG.patch(API + '/' + id + '/read', {}).then(function () { loadPage(currentPage); }).catch(function () {});
        if (url && url.endsWith('.php')) { window.location.href = url; }
    };

    window.delNotif = function (id) {
        window.API_CONFIG.delete(API + '/' + id).then(function () { loadPage(currentPage); }).catch(function () {});
    };

    window.markAllRead = function () {
        window.API_CONFIG.patch(API + '/read-all', {}).then(function () {
            showToast('All notifications marked as read');
            loadPage(currentPage);
        }).catch(function () {});
    };

    window.clearAll = function () {
        window.API_CONFIG.delete(API + '/clear-all').then(function () {
            showToast('All notifications cleared');
            loadPage(1);
        }).catch(function () {});
    };

    window.loadPage = loadPage;

    window.debounceSearch = function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { loadPage(1); }, 400);
    };

    window.notifCenterMarkRead = function (id) {
        window.API_CONFIG.patch(API + '/' + id + '/read', {}).then(function () { loadPage(currentPage); }).catch(function () {});
    };

    function showToast(msg) {
        var t = document.getElementById('nc-toast');
        if (!t) return;
        t.textContent = msg;
        t.classList.add('show');
        setTimeout(function () { t.classList.remove('show'); }, 2500);
    }

    loadPage(1);
})();
</script>
<?php
$pageContent = ob_get_clean();
if (is_ajax_request()) {
    if (isset($extraHeadContent)) echo $extraHeadContent;
    echo $pageContent;
    exit;
}
include '../../components/sections.php';
