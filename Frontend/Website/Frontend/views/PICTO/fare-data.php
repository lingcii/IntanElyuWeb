<?php
require_once __DIR__ . '/../../session-bridge.php';
// Check role
if ($_SESSION['user_role'] !== 'picto' && $_SESSION['user_role'] !== 'pitco') {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'PICTO Transportation Fare Management';

ob_start();
?>
<link rel="stylesheet" href="../../css/PICTO/fare-data.css">
<?php
$extraHeadContent = ob_get_clean();
ob_start();
?>

<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="fd-page-header">
    <h2><i class="fas fa-bus"></i> Transportation Fare Management</h2>
    <div class="fd-header-actions">
        <button class="btn-gov btn-gov-secondary" onclick="fd_refreshAll()" title="Refresh">
            <i class="fas fa-sync-alt" id="refreshIcon"></i> Refresh
        </button>
        <button class="btn-gov" onclick="fd_switchTab('upload')">
            <i class="fas fa-cloud-upload-alt"></i> Upload CSV
        </button>
    </div>
</div>



<!-- ── Tabs ───────────────────────────────────────────────── -->
<div class="fd-tabs" role="tablist">
    <button class="fd-tab-btn active" id="tab-browse" onclick="fd_switchTab('browse')" role="tab"><i class="fas fa-table-list"></i> Browse Guides</button>
    <button class="fd-tab-btn" id="tab-upload" onclick="fd_switchTab('upload')" role="tab"><i class="fas fa-cloud-upload-alt"></i> Upload CSV</button>
    <button class="fd-tab-btn"        id="tab-history" onclick="fd_switchTab('history')" role="tab"><i class="fas fa-history"></i> Upload History</button>
</div>

<!-- ════════════════════════════════════════════════════════
     TAB: BROWSE GUIDES
═════════════════════════════════════════════════════════ -->
<div class="fd-tab-panel active" id="panel-browse">

    <!-- Active guide badges -->
    <div class="fd-badge-strip" id="fdBadgeStrip" style="display:none;">
        <span class="fd-info-badge guide"><i class="fas fa-file-alt"></i> <span id="badgeGuideName">—</span></span>
        <span class="fd-info-badge effective"><i class="fas fa-calendar-check"></i> Effective: <span id="badgeEffective">—</span></span>
        <span class="fd-info-badge updated"><i class="fas fa-clock"></i> Updated: <span id="badgeUpdated">—</span></span>
    </div>

    <!-- Statistics Grid -->
    <div class="fd-stats-grid">
        <div class="fd-stat-card">
            <div class="fd-stat-info">
                <h4>Total Fare Matrix</h4>
                <p id="statGuides">—</p>
            </div>
            <div class="fd-stat-icon blue"><i class="fas fa-file-invoice-dollar"></i></div>
        </div>
        <div class="fd-stat-card">
            <div class="fd-stat-info">
                <h4>Active Fare Matrix</h4>
                <p id="statActive">—</p>
            </div>
            <div class="fd-stat-icon green"><i class="fas fa-check-circle"></i></div>
        </div>
        <div class="fd-stat-card">
            <div class="fd-stat-info">
                <h4>Archived Fare Matrix</h4>
                <p id="statArchived">—</p>
            </div>
            <div class="fd-stat-icon orange"><i class="fas fa-archive"></i></div>
        </div>
        <div class="fd-stat-card">
            <div class="fd-stat-info">
                <h4>Transportation Types</h4>
                <p id="statTypes">—</p>
            </div>
            <div class="fd-stat-icon blue"><i class="fas fa-bus-simple"></i></div>
        </div>
        <div class="fd-stat-card">
            <div class="fd-stat-info">
                <h4>Last Updated Count</h4>
                <p id="statLastUpdated">—</p>
            </div>
            <div class="fd-stat-icon red"><i class="fas fa-history"></i></div>
        </div>
    </div>

    <!-- Search & filter -->
    <div class="fd-search-bar">
        <div class="fd-search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="fdSearchInput" class="fd-search-input"
                   placeholder="Search by title, region, vehicle type…"
                   oninput="fd_debouncedFilter()" aria-label="Search fare guides">
        </div>
        <select class="fd-filter-select" id="fdVehicleFilter" onchange="fd_filterGuides()" aria-label="Vehicle type">
            <option value="">All Vehicle Types</option>
            <option value="MPUJ">MPUJ (Modern PUJ)</option>
            <option value="TPUJ">TPUJ (Traditional PUJ)</option>
            <option value="PUB_Aircon">PUB Aircon</option>
            <option value="PUB_Regular">PUB Regular</option>
            <option value="TAXI">TAXI</option>
            <option value="UVE">UVE (UV Express)</option>
            <option value="Tricycle">Tricycle</option>
            <option value="Van">Van</option>
        </select>
        <select class="fd-filter-select" id="fdStatusFilter" onchange="fd_filterGuides()" aria-label="Status">
            <option value="">All Statuses</option>
            <option value="active">Active</option>
            <option value="draft">Draft</option>
            <option value="archived">Archived</option>
        </select>
        <select class="fd-filter-select" id="fdSortSelect" onchange="fd_filterGuides()" aria-label="Sort">
            <option value="newest">Newest First</option>
            <option value="oldest">Oldest First</option>
            <option value="fare_asc">Fare ↑</option>
            <option value="fare_desc">Fare ↓</option>
        </select>
        <button class="btn-gov btn-gov-secondary" onclick="fd_clearFilters()"><i class="fas fa-times"></i> Clear</button>
    </div>

    <!-- Fare guide cards -->
    <div id="fdCardsGrid" class="fd-cards-grid">
        <!-- Skeleton loading placeholders -->
        <div class="fd-skeleton fd-skeleton-card"></div>
        <div class="fd-skeleton fd-skeleton-card"></div>
        <div class="fd-skeleton fd-skeleton-card"></div>
    </div>

    <!-- Matrix viewer panel (shown below cards when a guide is selected) -->
    <div class="fd-matrix-panel" id="fdMatrixPanel" style="display:none;">
        <div class="fd-matrix-header">
            <h3 class="fd-matrix-title"><i class="fas fa-table"></i> <span id="fdMatrixTitle">Fare Matrix</span></h3>
            <div style="display:flex;gap:8px;">
                <button class="fd-matrix-close" onclick="fd_exportMatrix()" title="Export CSV">
                    <i class="fas fa-download"></i> CSV
                </button>
                <button class="fd-matrix-close" onclick="fd_closeMatrix()"><i class="fas fa-times"></i> Close</button>
            </div>
        </div>
        <div class="fd-matrix-table-wrap">
            <table class="fd-matrix-table">
                <thead>
                    <tr>
                        <th>Distance (km)</th>
                        <th>Regular Fare</th>
                        <th>Discounted Fare <small style="text-transform:none;font-weight:400;">(Student / Senior / PWD)</small></th>
                        <th>Savings</th>
                    </tr>
                </thead>
                <tbody id="fdMatrixBody">
                    <tr><td colspan="4" style="text-align:center;padding:32px;color:var(--text-muted);">Select a guide to view the fare matrix.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     TAB: UPLOAD CSV
 ═════════════════════════════════════════════════════════ -->
<div class="fd-tab-panel" id="panel-upload">
    <div class="card">
        <div class="card-header flex-between">
            <h3 class="card-title"><i class="fas fa-cloud-upload-alt"></i> Upload Official Fare Matrix CSV</h3>
            <span style="font-size:11px;padding:3px 10px;background:#e3f0fb;color:#0071c5;border-radius:20px;font-weight:600;">PICTO Admin</span>
        </div>
        <div class="card-body">
            <!-- Info banner -->
            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px 16px;display:flex;gap:10px;align-items:flex-start;margin-bottom:20px;">
                <i class="fas fa-info-circle" style="color:#1d4ed8;margin-top:1px;"></i>
                <div style="font-size:13px;color:#1e40af;">
                    <strong>Automatic Processing:</strong> Upload the official CSV and the system will automatically parse, validate, and extract all fare data, vehicle types, titles, and effective dates from the file content.
                </div>
            </div>

            <!-- Drop zone — input is OUTSIDE the zone to prevent bubble double-trigger -->
            <input type="file" id="fdFileInput" accept=".csv,text/csv" style="display:none;">
            <div class="fd-upload-zone" id="fdUploadZone">
                <i class="fas fa-file-csv fd-upload-icon"></i>
                <h3>Drag &amp; Drop Fare Matrix CSV</h3>
                <p>or click anywhere in this box to browse</p>
                <small>CSV files only · Max 20 MB</small>
            </div>

            <!-- Progress -->
            <div id="fdProgressWrap" style="display:none; margin-top:16px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:13px;font-weight:600;">
                    <span id="fdProgressLabel">Uploading…</span>
                    <span id="fdProgressPct">0%</span>
                </div>
                <div class="fd-progress-bar">
                    <div class="fd-progress-fill" id="fdProgressFill" style="width:0%;"></div>
                </div>
            </div>

            <!-- Upload result -->
            <div id="fdUploadResult" style="display:none; margin-top:16px;"></div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     TAB: UPLOAD HISTORY
═════════════════════════════════════════════════════════ -->
<div class="fd-tab-panel" id="panel-history">
    <div class="card">
        <div class="card-header flex-between">
            <h3 class="card-title"><i class="fas fa-history"></i> Upload History</h3>
            <button class="btn-gov btn-gov-secondary" onclick="fd_loadHistory()"><i class="fas fa-sync-alt"></i> Refresh</button>
        </div>
        <div style="overflow-x:auto;">
            <table class="fd-history-table" id="fdHistoryTable" style="min-width:700px;">
                <thead>
                    <tr>
                        <th>File Name</th>
                        <th>Uploaded By</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th style="text-align:center;">Records</th>
                        <th style="width:120px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="fdHistoryBody">
                    <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Confirm modal ─────────────────────────────────────── -->
<div class="fd-modal-overlay" id="fdConfirmModal" onclick="if(event.target===this)fd_closeConfirm()">
    <div class="fd-modal" style="max-width:440px;">
        <div class="fd-modal-header">
            <h3 id="fdConfirmTitle"><i class="fas fa-exclamation-triangle"></i> Confirm Action</h3>
            <button class="fd-modal-close" onclick="fd_closeConfirm()" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="fd-modal-body" style="text-align:center;padding:32px 24px;">
            <div id="fdConfirmIcon" style="font-size:52px;margin-bottom:16px;">⚠️</div>
            <p id="fdConfirmText" style="font-size:14px;color:var(--text-secondary);margin:0;line-height:1.6;"></p>
        </div>
        <div class="fd-modal-footer">
            <button class="btn-gov btn-gov-secondary" onclick="fd_closeConfirm()">No, Cancel</button>
            <button class="btn-gov" id="fdConfirmOkBtn" onclick="fd_confirmOk()">Confirm</button>
        </div>
    </div>
</div>

<!-- ── Logs modal ─────────────────────────────────────────── -->
<div class="fd-modal-overlay" id="fdLogsModal" onclick="if(event.target===this)fd_closeLogs()">
    <div class="fd-modal" style="max-width:640px;">
        <div class="fd-modal-header">
            <h3 id="fdLogsTitle"><i class="fas fa-list-alt"></i> Logs</h3>
            <button class="fd-modal-close" onclick="fd_closeLogs()"><i class="fas fa-times"></i></button>
        </div>
        <div class="fd-modal-body" id="fdLogsBody" style="max-height:420px;overflow-y:auto;padding:0;"></div>
        <div class="fd-modal-footer">
            <button class="btn-gov btn-gov-secondary" onclick="fd_closeLogs()">Close</button>
        </div>
    </div>
</div>

<!-- ── Add/Edit manual modal ─────────────────────────────── -->
<div class="fd-modal-overlay" id="fdAddEditModal" onclick="if(event.target===this)fd_closeAddEdit()">
    <div class="fd-modal" style="max-width:600px;">
        <div class="fd-modal-header">
            <h3 id="fdAddEditTitle"><i class="fas fa-plus"></i> Add Fare Matrix</h3>
            <button class="fd-modal-close" onclick="fd_closeAddEdit()" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <form id="fdAddEditForm" onsubmit="fd_saveManual(event)">
            <input type="hidden" id="aeGuideId">
            <div class="fd-modal-body" style="padding:20px;">
                <div class="fd-form-group" style="margin-bottom:12px;">
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--text-primary);">Title</label>
                    <input type="text" id="aeTitle" required class="fd-input" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:4px;" placeholder="e.g., General Fare Matrix 2026">
                </div>
                <div style="display:flex;gap:12px;margin-bottom:12px;">
                    <div class="fd-form-group" style="flex:1;">
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--text-primary);">Vehicle Type</label>
                        <select id="aeVehicleType" required class="fd-input" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:4px;">
                            <option value="PUB_Aircon">PUB Aircon</option>
                            <option value="PUB_Ordinary">PUB Ordinary</option>
                            <option value="PUJ_Aircon">PUJ Aircon</option>
                            <option value="PUJ_Ordinary">PUJ Ordinary</option>
                            <option value="Tricycle">Tricycle</option>
                            <option value="Van">Van</option>
                        </select>
                    </div>
                    <div class="fd-form-group" style="flex:1;">
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--text-primary);">Region / Municipality</label>
                        <input type="text" id="aeRegion" required class="fd-input" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:4px;" placeholder="e.g., La Union">
                    </div>
                </div>
                <div class="fd-form-group" style="margin-bottom:16px;">
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--text-primary);">Effective Date</label>
                    <input type="date" id="aeEffectiveDate" required class="fd-input" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:4px;">
                </div>

                <div style="border-top:1px solid var(--border);padding-top:12px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <h4 style="margin:0;font-size:13px;color:var(--text-primary);"><i class="fas fa-list"></i> Fare Matrix Rows</h4>
                        <button type="button" class="btn-gov" style="padding:4px 8px;font-size:11px;" onclick="ae_addRow()">
                            <i class="fas fa-plus"></i> Add Row
                        </button>
                    </div>
                    <div style="max-height:200px;overflow-y:auto;border:1px solid var(--border);border-radius:4px;">
                        <table style="width:100%;border-collapse:collapse;font-size:12px;" id="aeRowsTable">
                            <thead style="background:#f8fafc;position:sticky;top:0;z-index:1;">
                                <tr>
                                    <th style="padding:6px;text-align:left;border-bottom:1px solid var(--border);">Distance (km)</th>
                                    <th style="padding:6px;text-align:left;border-bottom:1px solid var(--border);">Regular Fare (₱)</th>
                                    <th style="padding:6px;text-align:left;border-bottom:1px solid var(--border);">Discounted (₱)</th>
                                    <th style="padding:6px;width:40px;border-bottom:1px solid var(--border);"></th>
                                </tr>
                            </thead>
                            <tbody id="aeRowsBody">
                                <!-- Dynamic rows -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="fd-modal-footer">
                <button type="button" class="btn-gov btn-gov-secondary" onclick="fd_closeAddEdit()">Cancel</button>
                <button type="submit" class="btn-gov" id="aeSaveBtn">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Toast container ───────────────────────────────────── -->
<div id="fdToastContainer"></div>

<script src="../../scripts/functions/PITCO/fare-data-api.js?v=<?php echo filemtime(__DIR__ . '/../../scripts/functions/PITCO/fare-data-api.js'); ?>"></script>

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
