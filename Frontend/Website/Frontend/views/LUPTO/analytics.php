<?php
require_once __DIR__ . '/../../session-bridge.php';
// Check role
if ($_SESSION['user_role'] !== 'lupto') {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'LUPTO Analytics Dashboard';

ob_start();
?>
<link rel="stylesheet" href="../../css/LUPTO/analytics.css">
<?php
$extraHeadContent = ob_get_clean();
ob_start();
?>

<!-- Page Header -->
<div class="pa-page-header">
    <h2><i class="fas fa-chart-line"></i> Tourism Analytics Dashboard</h2>
    <div class="pa-header-actions">
        <select class="pa-filter-select" id="filterYear" onchange="refreshAll()" style="font-size:13px; padding:6px 12px; margin:0; height:34px;" aria-label="Year">
            <option value="2026">2026</option>
            <option value="2025">2025</option>
        </select>
        <div class="pa-export-group">
            <button class="btn-gov btn-gov-secondary" onclick="exportData('csv')" title="Export as CSV">
                <i class="fas fa-file-csv"></i> CSV
            </button>
            <button class="btn-gov btn-gov-secondary" onclick="exportData('pdf')" title="Export as PDF">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
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
                    <select class="pa-filter-select pa-month-filter" id="filterMonth" onchange="onMonthFilterChange()">
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

<!-- Export Modal -->
<div id="exportModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
            <h3><i class="fas fa-file-export"></i> Export Analytics Data</h3>
            <button class="modal-close" onclick="closeExportModal()">&times;</button>
        </div>
        <div class="modal-body" style="padding:20px;">
            <p style="margin-bottom:16px;">Select the data you want to export:</p>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <button class="btn-gov" onclick="triggerExport('csv','summary')"><i class="fas fa-file-csv"></i> Export Summary as CSV</button>
                <button class="btn-gov" onclick="triggerExport('csv','municipalities')"><i class="fas fa-file-csv"></i> Export Municipalities as CSV</button>
                <button class="btn-gov" onclick="triggerExport('csv','spots')"><i class="fas fa-file-csv"></i> Export Spots as CSV</button>
                <button class="btn-gov" onclick="triggerExport('csv','trends')"><i class="fas fa-file-csv"></i> Export Trends as CSV</button>
                <button class="btn-gov" onclick="triggerExport('csv','full')"><i class="fas fa-file-csv"></i> Export All Data as CSV</button>
                <button class="btn-gov btn-gov-secondary" onclick="triggerExport('pdf','full')"><i class="fas fa-file-pdf"></i> Export Full Report as PDF</button>
            </div>
        </div>
    </div>
    <div class="modal-backdrop" onclick="closeExportModal()"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="../../scripts/functions/LUPTO/analytics-api.js"></script>

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
