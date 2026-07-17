<?php
require_once __DIR__ . '/../../session-bridge.php';
$allowedRoles = ['lupto', 'picto'];
if (!in_array($_SESSION['user_role'], $allowedRoles)) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'LUPTO Activity Logs';

ob_start();
?>
    <link rel="stylesheet" href="../../css/PICTO/activity-logs.css?v=<?= time() ?>">
<?php
$extraHeadContent = ob_get_clean();

ob_start();
?>
    <div class="flex-between" style="margin-bottom:16px; align-items: center;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <h2 class="section-title" style="margin: 0;">Activity Logs</h2>
            <div class="live-status-container">
                <span class="live-dot syncing" id="live-dot"></span>
                <span id="live-status-label">Connecting...</span>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 12px;">
            <span class="sync-time-info">
                <button class="btn-action" id="btn-manual-refresh" title="Force Sync" style="background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 14px; padding: 4px; display: inline-flex; align-items: center; justify-content: center; outline: none;">
                    <i class="fas fa-sync-alt sync-icon paused"></i>
                </button>
                Last synced: <span id="last-synced-time">just now</span>
            </span>
            <div class="export-dropdown-wrapper">
                <button class="btn-gov" id="btn-export-logs" style="display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-file-export"></i> Export Logs <i class="fas fa-chevron-down" style="font-size: 10px;"></i>
                </button>
                <div class="export-dropdown-menu" id="export-formats-menu">
                    <button class="export-dropdown-item" data-format="csv"><i class="fas fa-file-csv" style="color: #10b981;"></i> CSV Format</button>
                    <button class="export-dropdown-item" data-format="xlsx"><i class="fas fa-file-excel" style="color: #22c55e;"></i> Excel Format</button>
                    <button class="export-dropdown-item" data-format="pdf"><i class="fas fa-file-pdf" style="color: #ef4444;"></i> Print / PDF</button>
                </div>
            </div>
        </div>
    </div>

    <div class="summary-strip">
        <div class="stat-card blue" id="card-logs-today">
            <div class="stat-card-info">
                <h5>Logs Today</h5>
                <div class="stat-value" id="stat-logs-today">-</div>
            </div>
            <div class="stat-card-icon"><i class="fas fa-clipboard-list"></i></div>
        </div>
        <div class="stat-card green" id="card-approvals">
            <div class="stat-card-info">
                <h5>Approvals</h5>
                <div class="stat-value" id="stat-approvals-today">-</div>
            </div>
            <div class="stat-card-icon"><i class="fas fa-check-circle"></i></div>
        </div>
        <div class="stat-card red" id="card-rejections">
            <div class="stat-card-info">
                <h5>Rejections</h5>
                <div class="stat-value" id="stat-rejections-today">-</div>
            </div>
            <div class="stat-card-icon"><i class="fas fa-times-circle"></i></div>
        </div>
        <div class="stat-card yellow" id="card-active-users">
            <div class="stat-card-info">
                <h5>Active Users (24h)</h5>
                <div class="stat-value" id="stat-active-users">-</div>
            </div>
            <div class="stat-card-icon"><i class="fas fa-users"></i></div>
        </div>
    </div>

    <div class="filter-bar">
        <div class="date-presets">
            <button class="date-preset-pill" data-preset="today">Today</button>
            <button class="date-preset-pill" data-preset="yesterday">Yesterday</button>
            <button class="date-preset-pill" data-preset="last7">Last 7 Days</button>
            <button class="date-preset-pill" data-preset="last30">Last 30 Days</button>
        </div>

        <!-- Custom Dropdown: Action -->
        <div class="custom-dropdown-select" id="dropdown-action">
            <button class="custom-dropdown-trigger" type="button">
                <span>All Actions</span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="custom-dropdown-menu" id="dropdown-action-menu"></div>
        </div>

        <!-- Custom Dropdown: Module -->
        <div class="custom-dropdown-select" id="dropdown-module">
            <button class="custom-dropdown-trigger" type="button">
                <span>All Modules</span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="custom-dropdown-menu" id="dropdown-module-menu"></div>
        </div>

        <!-- Date Pickers -->
        <div class="date-picker-group">
            <input type="date" id="date-from" aria-label="From Date">
            <span style="font-size: 12px; color: var(--text-muted); font-weight: 600;">to</span>
            <input type="date" id="date-to" aria-label="To Date">
        </div>

        <!-- Search Input -->
        <div class="search-input-wrap">
            <i class="fas fa-search search-icon"></i>
            <input type="text" placeholder="Search description, user..." aria-label="Search logs">
        </div>

        <!-- Clear Button -->
        <button class="btn-clear-filters" id="btn-clear-filters" style="display: none;">
            <i class="fas fa-filter-slash"></i> Clear Filters
        </button>
    </div>

    <div class="active-filter-badges" id="filter-badges-container"></div>

    <div class="table-card">
        <div class="table-scroll-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="activity-icon-cell"></th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Description</th>
                        <th>User</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="table-pagination-row" id="table-pagination-container"></div>
    </div>

    <div class="detail-drawer" id="detail-drawer">
        <div class="detail-drawer-header">
            <h3>Activity Log Details</h3>
            <button class="btn-drawer-close" id="btn-drawer-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="detail-drawer-body">
            <div class="drawer-user-header">
                <div class="drawer-avatar blue" id="drawer-user-avatar">--</div>
                <div>
                    <div class="drawer-user-name" id="drawer-user-name">-</div>
                    <div class="drawer-user-meta" id="drawer-user-meta">-</div>
                </div>
            </div>
            <div class="drawer-section">
                <div class="drawer-section-title">General Info</div>
                <div class="drawer-info-grid">
                    <div class="drawer-info-item full-width">
                        <span class="drawer-info-label">Action Performed</span>
                        <span class="drawer-info-value" id="drawer-action" style="font-size:14px; color:var(--navy-blue); font-weight:700;">-</span>
                    </div>
                    <div class="drawer-info-item"><span class="drawer-info-label">Module</span><span class="drawer-info-value" id="drawer-module">-</span></div>
                </div>
            </div>
            <div class="drawer-section">
                <div class="drawer-section-title">Device & Network</div>
                <div class="drawer-info-grid">
                    <div class="drawer-info-item"><span class="drawer-info-label">IP Address</span><span class="drawer-info-value" id="drawer-ip">-</span></div>
                    <div class="drawer-info-item"><span class="drawer-info-label">Timestamp</span><span class="drawer-info-value" id="drawer-timestamp">-</span></div>
                    <div class="drawer-info-item"><span class="drawer-info-label">Device</span><span class="drawer-info-value" id="drawer-device">-</span></div>
                    <div class="drawer-info-item"><span class="drawer-info-label">Browser</span><span class="drawer-info-value" id="drawer-browser">-</span></div>
                    <div class="drawer-info-item"><span class="drawer-info-label">OS</span><span class="drawer-info-value" id="drawer-os">-</span></div>
                </div>
            </div>
            <div class="drawer-section">
                <div class="drawer-section-title">Data Changes</div>
                <div style="margin-bottom: 12px;">
                    <span class="drawer-info-label">Old Value</span>
                    <pre class="diff-viewer" id="drawer-old-val">None</pre>
                </div>
                <div>
                    <span class="drawer-info-label">New Value</span>
                    <pre class="diff-viewer" id="drawer-new-val">None</pre>
                </div>
            </div>
        </div>
    </div>
    <div class="detail-drawer-overlay" id="detail-drawer-overlay"></div>

    <div class="floating-toast-alert" id="floating-toast">
        <i class="fas fa-arrow-up"></i> <span>0 new activity logs available</span>
    </div>

    <script src="../../scripts/functions/PITCO/activity-logs.js?v=<?= time() ?>"></script>
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
