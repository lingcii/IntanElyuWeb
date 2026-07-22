<?php
require_once __DIR__ . '/../../session-bridge.php';
// Check role
if ($_SESSION['user_role'] !== 'picto' && $_SESSION['user_role'] !== 'pitco') {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'PICTO – Archive Management';

ob_start();
?>
<link rel="stylesheet" href="../../css/PICTO/archive-management.css">
<?php
$extraHeadContent = ob_get_clean();
ob_start();
?>

<!-- ── Page Header ─────────────────────────────────────────────── -->
<div class="am-page-header">
    <div class="am-header-left">
        <h2><i class="fas fa-box-archive"></i> Archive Management</h2>
        <p class="am-header-sub">Archived records are preserved and can be restored or permanently deleted.</p>
    </div>
    <div class="am-header-actions">
        <button class="btn-gov btn-gov-secondary" onclick="am_refresh()" title="Refresh">
            <i class="fas fa-sync-alt" id="amRefreshIcon"></i> Refresh
        </button>
    </div>
</div>

<!-- ── Stats Strip ──────────────────────────────────────────────── -->
<div class="am-stats-grid" id="amStatsGrid">
    <div class="am-stat-card">
        <div class="am-stat-icon orange"><i class="fas fa-box-archive"></i></div>
        <div class="am-stat-info"><h4>Total Archived</h4><p id="amStatTotal">—</p></div>
    </div>
    <div class="am-stat-card">
        <div class="am-stat-icon blue"><i class="fas fa-calendar-days"></i></div>
        <div class="am-stat-info"><h4>Archived This Month</h4><p id="amStatMonth">—</p></div>
    </div>
    <div class="am-stat-card">
        <div class="am-stat-icon teal"><i class="fas fa-bus"></i></div>
        <div class="am-stat-info"><h4>Transportation Fare</h4><p id="amStatFare">—</p></div>
    </div>
    <div class="am-stat-card am-stat-future">
        <div class="am-stat-icon muted"><i class="fas fa-layer-group"></i></div>
        <div class="am-stat-info"><h4>More Modules</h4><p>Coming Soon</p></div>
    </div>
</div>

<!-- ── Module Tabs ──────────────────────────────────────────────── -->
<div class="am-tabs" role="tablist">
    <button class="am-tab-btn active" id="amTab-fare" onclick="am_switchTab('fare')" role="tab">
        <i class="fas fa-bus"></i> Transportation Fare
    </button>
    <!-- Future module tabs go here -->
</div>

<!-- ══════════════════════════════════════════════════════════════
     TAB: TRANSPORTATION FARE ARCHIVES
════════════════════════════════════════════════════════════════ -->
<div class="am-tab-panel active" id="amPanel-fare">

    <!-- Toolbar -->
    <div class="am-toolbar">
        <div class="am-search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="amSearchInput" class="am-search-input"
                   placeholder="Search by title or region…"
                   oninput="am_debouncedLoad()"
                   aria-label="Search archived fare guides">
        </div>
        <select class="am-filter-select" id="amVehicleFilter" onchange="am_loadFares()" aria-label="Vehicle type">
            <option value="">All Vehicle Types</option>
            <option value="PUB_Aircon">PUB Aircon</option>
            <option value="PUB_Ordinary">PUB Ordinary</option>
            <option value="PUJ_Aircon">PUJ Aircon</option>
            <option value="PUJ_Ordinary">PUJ Ordinary</option>
            <option value="Tricycle">Tricycle</option>
            <option value="Van">Van</option>
        </select>
        <select class="am-filter-select" id="amSortSelect" onchange="am_loadFares()" aria-label="Sort">
            <option value="newest">Newest Archived</option>
            <option value="oldest">Oldest Archived</option>
            <option value="title">Title A–Z</option>
        </select>
        <button class="btn-gov btn-gov-secondary" onclick="am_clearFilters()">
            <i class="fas fa-times"></i> Clear
        </button>
    </div>

    <!-- Table -->
    <div class="am-table-wrap card">
        <table class="am-table" id="amFareTable">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Title / Vehicle Type</th>
                    <th>Region</th>
                    <th>Effective Date</th>
                    <th>Fare Range</th>
                    <th>Archived Date</th>
                    <th>Archived By</th>
                    <th style="width:180px;text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody id="amFareTbody">
                <tr><td colspan="8" class="am-loading-row"><i class="fas fa-spinner fa-spin"></i> Loading…</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="am-pagination" id="amPagination" style="display:none;"></div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     DETAIL / VIEW MODAL
════════════════════════════════════════════════════════════════ -->
<div class="am-modal-overlay" id="amViewModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="amViewTitle">
    <div class="am-modal am-modal-wide">
        <div class="am-modal-header">
            <h3 id="amViewTitle"><i class="fas fa-eye"></i> Fare Guide Details</h3>
            <button class="am-modal-close" onclick="am_closeView()" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="am-modal-body" id="amViewBody" style="max-height:70vh;overflow-y:auto;padding:0;"></div>
        <div class="am-modal-footer">
            <button class="btn-gov btn-gov-secondary" onclick="am_closeView()">Close</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     RESTORE CONFIRM MODAL
════════════════════════════════════════════════════════════════ -->
<div class="am-modal-overlay" id="amRestoreModal" style="display:none;" role="dialog" aria-modal="true">
    <div class="am-modal" style="max-width:440px;">
        <div class="am-modal-header">
            <h3><i class="fas fa-rotate-left" style="color:#15803d;"></i> Restore Fare Guide</h3>
            <button class="am-modal-close" onclick="am_closeRestore()" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="am-modal-body" style="text-align:center;padding:28px 24px;">
            <div style="font-size:52px;margin-bottom:16px;">♻️</div>
            <p style="font-size:14px;color:var(--text-secondary);margin:0 0 8px;" id="amRestoreText">
                Restore this fare guide?
            </p>
            <p style="font-size:12px;color:var(--text-muted);margin:0;">
                It will be moved back to Transportation Fare as a <strong>Draft</strong>. You can activate it from there.
            </p>
        </div>
        <div class="am-modal-footer">
            <button class="btn-gov btn-gov-secondary" onclick="am_closeRestore()">Cancel</button>
            <button class="btn-gov" id="amRestoreOkBtn" style="background:#15803d;border-color:#15803d;" onclick="am_doRestore()">
                <i class="fas fa-rotate-left"></i> Restore
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     PERMANENT DELETE CONFIRM MODAL
════════════════════════════════════════════════════════════════ -->
<div class="am-modal-overlay" id="amDeleteModal" style="display:none;" role="dialog" aria-modal="true">
    <div class="am-modal" style="max-width:460px;">
        <div class="am-modal-header" style="border-bottom-color:#fee2e2;">
            <h3 style="color:#dc2626;"><i class="fas fa-triangle-exclamation"></i> Permanent Delete</h3>
            <button class="am-modal-close" onclick="am_closeDelete()" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="am-modal-body" style="text-align:center;padding:28px 24px;">
            <div style="font-size:52px;margin-bottom:16px;">🗑️</div>
            <p style="font-size:14px;color:var(--text-secondary);margin:0 0 12px;" id="amDeleteText">
                Are you sure you want to permanently delete this fare guide?
            </p>
            <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:10px 14px;font-size:12px;color:#991b1b;text-align:left;">
                <i class="fas fa-exclamation-circle"></i>
                <strong>This cannot be undone.</strong> All fare matrix data, upload records, and associated files will be permanently removed.
            </div>
        </div>
        <div class="am-modal-footer">
            <button class="btn-gov btn-gov-secondary" onclick="am_closeDelete()">Cancel</button>
            <button class="btn-gov" id="amDeleteOkBtn"
                style="background:#dc2626;border-color:#dc2626;"
                onclick="am_doDelete()">
                <i class="fas fa-trash"></i> Delete Permanently
            </button>
        </div>
    </div>
</div>

<!-- Toast container -->
<div id="amToastContainer"></div>

<script src="../../scripts/functions/PITCO/archive-management-api.js"></script>

<?php
$pageContent = ob_get_clean();
if (is_ajax_request()) {
    if (isset($extraHeadContent)) {
        echo $extraHeadContent;
    }
    echo $pageContent;
    exit;
}
include '../../components/sections.php';
