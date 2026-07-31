<?php

//   Role guard is handled by the calling stub via _role_guard.php.
//   $userRole is available (set by _role_guard.php).
//   Differences per role:
//    - Page title      → set by stub
//     - Analytics CSS   → css/LUPTO/analytics.css (all roles already use this)
//     - Analytics JS    → scripts/functions/shared/analytics-api.js
 

require_once __DIR__ . '/../session-bridge.php';

// Allow the relevant roles — guard normalises $userRole
$allowedRoles  = ['lupto', 'picto', 'municipal'];
$loginRedirect = '../login.php';
require_once __DIR__ . '/_role_guard.php';

// Default page title if stub didn't set one
if (!isset($pageTitle)) {
    $pageTitle = strtoupper($userRole) . ' Analytics & Reports';
}

// Build the correct relative CSS / JS path from views/ → go one level up
$basePath = '../';

ob_start();
?>
<link rel="stylesheet" href="<?= $basePath ?>css/analytics.css">
<?php
$extraHeadContent = ob_get_clean();
ob_start();
?>

<!-- Page Header -->
<div class="pa-page-header">
    <h2><i class="fas fa-chart-line"></i> Analytics &amp; Reports</h2>
    <div class="pa-header-actions">
        <select class="pa-filter-select" id="filterYear" onchange="window.refreshAll?.()" style="font-size:13px; padding:6px 12px; margin:0; height:34px;" aria-label="Year">
            <option value="2026">2026</option>
            <option value="2025">2025</option>
        </select>

        <!-- Download Report dropdown button -->
        <div class="pa-download-dropdown-wrap" id="pa-download-dropdown-wrap">
            <button class="btn-gov btn-gov-primary pa-download-toggle" id="pa-btn-download" title="Download Report" onclick="paToggleDownloadDropdown(event)">
                <i class="fas fa-file-download"></i> Download Report <i class="fas fa-chevron-down" style="margin-left:4px; font-size:0.75rem;"></i>
            </button>
            <div class="pa-download-menu" id="pa-download-menu">
                <button type="button" class="pa-download-item" data-format="pdf" onclick="paSelectDownloadFormat('pdf', event)">
                    <i class="fas fa-file-pdf" style="color:#EF4444; width:16px;"></i> PDF (.pdf)
                </button>
                <button type="button" class="pa-download-item" data-format="csv" onclick="paSelectDownloadFormat('csv', event)">
                    <i class="fas fa-file-csv" style="color:#10B981; width:16px;"></i> CSV (.csv)
                </button>
                <button type="button" class="pa-download-item" data-format="excel" onclick="paSelectDownloadFormat('excel', event)">
                    <i class="fas fa-file-excel" style="color:#16A34A; width:16px;"></i> Excel (.xlsx)
                </button>
            </div>
        </div>

        <button class="btn-gov btn-gov-secondary" onclick="refreshAll(true)" title="Refresh all data">
            <i class="fas fa-sync-alt" id="refreshIcon"></i> Refresh
        </button>
    </div>
</div>

<!-- KPI Summary Cards -->
<div class="pa-kpi-grid">
    <!-- Card 1: Spots -->
    <div class="pa-kpi-card">
        <div class="pa-kpi-trend green" id="kpiSpotsBadge">—</div>
        <div class="pa-kpi-icon green"><i class="fas fa-location-dot"></i></div>
        <div class="pa-kpi-info">
            <h4>Total Tourist Sites</h4>
            <p id="kpiSpots">—</p>
            <small>Across <span id="kpiMunisCount">X</span> municipalities</small>
        </div>
    </div>
    <!-- Card 2: Tourist Users -->
    <div class="pa-kpi-card">
        <div class="pa-kpi-trend blue" id="kpiVisitsBadge">—</div>
        <div class="pa-kpi-icon blue"><i class="fas fa-users"></i></div>
        <div class="pa-kpi-info">
            <h4>Total Tourist Users</h4>
            <p id="kpiVisists">—</p>
            <small>Registered tourist accounts</small>
        </div>
    </div>
    <!-- Card 3: Monthly Visited -->
    <div class="pa-kpi-card">
        <div class="pa-kpi-trend yellow" id="kpiMonthlyVisitedBadge">—</div>
        <div class="pa-kpi-icon yellow"><i class="fas fa-calendar-alt"></i></div>
        <div class="pa-kpi-info">
            <h4>Monthly Visited</h4>
            <p id="kpiMonthlyVisited">—</p>
            <small>Based on selected year</small>
        </div>
    </div>
    <!-- Card 4: Top Category -->
    <div class="pa-kpi-card">
        <div class="pa-kpi-trend purple" id="kpiTopCategoryBadge">—</div>
        <div class="pa-kpi-icon purple"><i class="fas fa-tags"></i></div>
        <div class="pa-kpi-info">
            <h4>Top Category</h4>
            <p id="kpiTopCategory">—</p>
            <small>Most spots category</small>
        </div>
    </div>
</div>

<!-- ── Report Filters Panel ──────────────────────── -->
<div class="pa-report-filter-card" id="pa-report-filter-card">
    <div class="pa-report-filter-header" id="pa-report-filter-header" onclick="paToggleReportFilters()">
        <div style="display:flex; align-items:center; gap:10px;">
            <i class="fas fa-sliders" style="color:#2563EB;"></i>
            <span style="font-weight:600; font-size:15px; color:#0F172A;">Report Filters</span>
            <span class="pa-filter-badge" id="pa-active-filters-badge" style="display:none;">Filters Active</span>
        </div>
        <div style="display:flex; align-items:center; gap:12px;">
            <span class="pa-filter-hint" id="pa-filter-hint-text">Configure report filters</span>
            <i class="fas fa-chevron-down pa-filter-toggle-icon" id="pa-filter-chevron"></i>
        </div>
    </div>
    <div class="pa-report-filter-body" id="pa-report-filter-body">
        <form id="pa-report-filter-form" onsubmit="return false;">
            <div class="pa-filter-grid">
                <!-- Report Type -->
                <div class="pa-form-group">
                    <label for="pa-report-type"><i class="fas fa-list-check"></i> Report Type</label>
                    <select id="pa-report-type" class="pa-filter-select pa-form-select">
                        <option value="all_summary">All Summary (Master Report)</option>
                        <option value="tourist_spots_summary">Tourist Spots Summary</option>
                        <option value="tourist_spots_by_municipality">Tourist Spots by Municipality</option>
                        <option value="visitor_feedback_summary">Visitor Feedback Summary</option>
                        <option value="tourist_spot_ratings">Tourist Spot Ratings</option>
                        <option value="tourism_statistics">Tourism Statistics</option>
                        <option value="user_accounts_summary">User Accounts Summary</option>
                    </select>
                </div>

                <!-- Municipality -->
                <div class="pa-form-group">
                    <label for="pa-report-municipality"><i class="fas fa-location-dot"></i> Municipality</label>
                    <select id="pa-report-municipality" class="pa-filter-select pa-form-select">
                        <option value="all">All Municipalities</option>
                    </select>
                </div>

                <!-- Start Date -->
                <div class="pa-form-group">
                    <label for="pa-report-start-date"><i class="fas fa-calendar-alt"></i> Start Date</label>
                    <input type="date" id="pa-report-start-date" class="pa-report-date-input">
                </div>

                <!-- End Date -->
                <div class="pa-form-group">
                    <label for="pa-report-end-date"><i class="fas fa-calendar-check"></i> End Date</label>
                    <input type="date" id="pa-report-end-date" class="pa-report-date-input">
                </div>
            </div>
        </form>
    </div>
</div>


<!-- Row 1: Line Chart + Classification Status -->
<div class="pa-row-flex">
    <!-- Monthly Visitor Trend Panel -->
    <div class="pa-col-main card">
        <div class="card-header">
            <h3 class="pa-section-title"><i class="fas fa-chart-line"></i> Monthly Visitor Trend</h3>
        </div>
        <div class="card-body">
            <div class="pa-trend-stats">
                <div class="pa-trend-stat-item">
                    <span class="pa-trend-stat-label">Monthly Visitors</span>
                    <span class="pa-trend-stat-val" id="statMonthlyVisitors">—</span>
                </div>
                <div class="pa-trend-stat-item">
                    <span class="pa-trend-stat-label">Select Month</span>
                    <select class="pa-filter-select pa-month-filter" id="filterMonth" onchange="window.onMonthFilterChange?.()">
                        <option value="all">All Months</option>
                        <option value="1">January</option>
                        <option value="2">February</option>
                        <option value="3">March</option>
                        <option value="4">April</option>
                        <option value="5">May</option>
                        <option value="6">June</option>
                        <option value="7">July</option>
                        <option value="8">August</option>
                        <option value="9">September</option>
                        <option value="10">October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select>
                </div>
            </div>
            <div class="pa-chart-body" style="height:270px; position:relative;">
                <canvas id="trendChart" role="img" aria-label="Year-on-year monthly tourism visits trend line chart"></canvas>
            </div>
        </div>
    </div>
    <!-- Classification Status sidebar -->
    <div class="pa-col-side card">
        <div class="card-header">
            <h3 class="pa-section-title"><i class="fas fa-tags"></i> Classification Status</h3>
        </div>
        <div class="pa-quality-list" id="classificationList">
            <div class="pa-loading"><i class="fas fa-spinner fa-spin"></i></div>
        </div>
    </div>
</div>

<!-- Row 2: Top Categories + Visitors by Municipality -->
<div class="pa-row-flex">
    <!-- Top Categories -->
    <div class="pa-col-half card">
        <div class="card-header">
            <h3 class="pa-section-title"><i class="fas fa-list-ol"></i> Top Categories</h3>
        </div>
        <div class="pa-cat-progress-list" id="categoryList">
            <div class="pa-loading"><i class="fas fa-spinner fa-spin"></i></div>
        </div>
    </div>
    <!-- Visitors by Municipality -->
    <div class="pa-col-half card">
        <div class="card-header">
            <h3 class="pa-section-title"><i class="fas fa-map-marked-alt"></i> Visitors by Municipality</h3>
        </div>
        <div class="pa-chart-body" style="height:300px; position:relative;">
            <canvas id="muniVisitsChart" role="img" aria-label="Horizontal bar chart comparing total visits by municipality"></canvas>
        </div>
        <div style="text-align: right; padding: 4px 16px 12px;">
            <button class="btn-gov btn-gov-secondary btn-sm" id="toggleMuniChart" onclick="toggleMuniChart()" style="font-size: 11px; padding: 4px 8px; cursor: pointer; display: none;">Show More</button>
        </div>
    </div>
</div>

<!-- Row 3: Top Tourist Spots Table -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="pa-section-header">
            <h3 class="pa-section-title"><i class="fas fa-map-location-dot"></i> Top Tourist Sites</h3>
            <div class="pa-cat-tabs" id="categoryTabs" style="display:flex; align-items:center; gap:8px; flex-wrap:nowrap;">
                <button class="pa-cat-tab active" data-category="all" onclick="filterTableCategory('all')">All</button>
                <button class="pa-cat-tab" data-category="Beach" onclick="filterTableCategory('Beach')">Beach</button>
                <button class="pa-cat-tab" data-category="Nature" onclick="filterTableCategory('Nature')">Nature</button>
                <button class="pa-cat-tab" data-category="Heritage" onclick="filterTableCategory('Heritage')">Heritage</button>
                <button class="pa-cat-tab" data-category="Cultural" onclick="filterTableCategory('Cultural')">Cultural</button>

                <div id="extraCategoriesPanel" style="display:inline-flex; align-items:center; gap:8px; overflow:hidden; width:0; transition:width 0.4s cubic-bezier(0.4, 0, 0.2, 1); white-space:nowrap;">
                    <button class="pa-cat-tab" data-category="Scenic" onclick="filterTableCategory('Scenic')">Scenic</button>
                    <button class="pa-cat-tab" data-category="Mountain" onclick="filterTableCategory('Mountain')">Mountain</button>
                    <button class="pa-cat-tab" data-category="Historical" onclick="filterTableCategory('Historical')">Historical</button>
                    <button class="pa-cat-tab" data-category="Waterfalls" onclick="filterTableCategory('Waterfalls')">Waterfalls</button>
                    <button class="pa-cat-tab" data-category="Adventure" onclick="filterTableCategory('Adventure')">Adventure</button>
                    <button class="pa-cat-tab" data-category="Farm" onclick="filterTableCategory('Farm')">Farm</button>
                    <button class="pa-cat-tab" data-category="Religious" onclick="filterTableCategory('Religious')">Religious</button>
                    <button class="pa-cat-tab" data-category="Other" onclick="filterTableCategory('Other')">Other</button>
                </div>

                <button id="toggleCategoriesBtn" onclick="toggleExtraCategories()" style="background:none; border:none; color:#10b981; font-size:14px; cursor:pointer; padding:6px 10px; border-radius:50%; display:flex; align-items:center; justify-content:center; transition:background-color 0.3s; margin-left:4px; outline:none;" title="Show more categories">
                    <i class="fas fa-chevron-right" id="toggleCategoriesIcon"></i>
                </button>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="pa-rank-table-wrap">
            <table class="data-table" id="spotTable">
                <thead>
                    <tr>
                        <th style="width:50px; text-align:center;">#</th>
                        <th>Destination</th>
                        <th>Barangay</th>
                        <th>Municipal</th>
                        <th>Category</th>
                        <th>Visitors</th>
                        <th>Rating</th>
                    </tr>
                </thead>
                <tbody id="spotTableBody">
                    <tr><td colspan="7" class="pa-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>



<!-- ── Export Confirmation Modal ── -->
<div id="pa-confirm-modal" class="pa-modal-overlay">
    <div class="pa-modal">
        <div class="pa-modal-icon"><i class="fas fa-file-download"></i></div>
        <h3 class="pa-modal-title">Confirm Download</h3>
        <p id="pa-confirm-msg" class="pa-modal-msg">Are you sure you want to download this report?</p>
        <div class="pa-modal-actions">
            <button type="button" id="pa-modal-btn-cancel" class="pa-modal-btn pa-modal-btn-cancel">No, Cancel</button>
            <button type="button" id="pa-modal-btn-confirm" class="pa-modal-btn pa-modal-btn-confirm">Yes, Download</button>
        </div>
    </div>
</div>

<!-- ── Toast Notification Container ── -->
<div id="rg-toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="../scripts/functions/analytics-api.js"></script>

<script>
// ── Analytics page download dropdown helpers ──────────────────────
function paToggleDownloadDropdown(e) {
    if (e) { e.preventDefault(); e.stopPropagation(); }
    var menu = document.getElementById('pa-download-menu');
    if (menu) menu.classList.toggle('show');
}
function paSelectDownloadFormat(format, e) {
    if (e) { e.preventDefault(); e.stopPropagation(); }
    var menu = document.getElementById('pa-download-menu');
    if (menu) menu.classList.remove('show');
    paHandleReportExport(format);
}
// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    var menu = document.getElementById('pa-download-menu');
    if (menu && !e.target.closest('#pa-download-dropdown-wrap')) {
        menu.classList.remove('show');
    }
});


// ── Report filter panel toggle ────────────────────────────────────
function paToggleReportFilters() {
    var body = document.getElementById('pa-report-filter-body');
    var icon = document.getElementById('pa-filter-chevron');
    if (!body) return;
    var isOpen = body.classList.contains('open');
    body.classList.toggle('open', !isOpen);
    if (icon) icon.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
}
</script>

<?php
$pageContent = ob_get_clean();
if (is_ajax_request()) {
    if (isset($extraHeadContent)) {
        echo $extraHeadContent;
    }
    echo $pageContent;
    exit;
}
include '../components/sections.php';
