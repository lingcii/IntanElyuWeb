<?php

//   views/shared/notifications.php
 
//   Shared Notification Center — all roles.
//  The JS inside dynamically determines the correct API prefix from the session role.
 
require_once __DIR__ . '/../session-bridge.php';

// Allow all known roles (notifications are universal)
$allowedRoles = ['picto', 'pitco', 'lupto', 'municipal'];
$allowedRoles = array_merge($allowedRoles, array_map(fn($m) => strtolower(str_replace(' ', '_', $m)) . '_mto', [
    'San Juan', 'San Fernando', 'Bauang', 'Agoo', 'Luna', 'San Gabriel',
    'Balaoan', 'Aringay', 'Rosario', 'Bacnotan', 'Naguilian', 'Tubao',
    'Pugo', 'Caba', 'Santo Tomas', 'Bangar', 'Burgos', 'Bagulin', 'Santol', 'Sudipen'
]));

require_once __DIR__ . '/_role_guard.php';

$pageTitle = 'Notification Center';

ob_start();
?>
   <link rel="stylesheet" href="../css/notifications.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../css/activity-logs.css?v=<?= time() ?>">

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
            <button class="btn-clear" onclick="window.clearAllNotifs()"><i class="fas fa-trash"></i> Clear All</button>
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

<script src="../scripts/functions/notifications-api.js"></script>
<?php
$pageContent = ob_get_clean();
if (is_ajax_request()) {
    if (isset($extraHeadContent)) echo $extraHeadContent;
    echo $pageContent;
    exit;
}
include '../components/sections.php';
