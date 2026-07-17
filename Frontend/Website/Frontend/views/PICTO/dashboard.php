<?php
require_once __DIR__ . '/../../session-bridge.php';
// Check role
if ($_SESSION['user_role'] !== 'picto') {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'PICTO Dashboard';

// Extra head content for CSS and scripts that need to load in <head>
ob_start();
?>
    <!-- Invalidate SPA session cache after map marker updates -->
    <script>
    (function(){var p=window.location.pathname,r=p.indexOf('/PICTO/')!==-1?'picto':p.indexOf('/LUPTO/')!==-1?'lupto':'municipal',k='spa_state_'+r+'_v2';if(sessionStorage.getItem(k)!=='1'){var pre='spa_state_'+r+'_';Object.keys(sessionStorage).forEach(function(c){if(c.indexOf(pre)===0)sessionStorage.removeItem(c);});sessionStorage.setItem(k,'1');}})();
    </script>
    <!-- PITCO Dashboard CSS -->
    <link rel="stylesheet" href="../../css/LUPTO/dashboard.css?v=<?= time() ?>">
    <!-- Leaflet Map CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <!-- Leaflet MarkerCluster CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">

<?php
$extraHeadContent = ob_get_clean();

ob_start();
?>


    <!-- Summary Cards -->
    <div class="lupto-kpi-grid">
        <div class="lupto-kpi-card" data-kpi="total-tourist-spots">
            <div class="lupto-kpi-info">
                <h4>Total Tourist Spots</h4>
                <span class="lupto-kpi-value"><i class="fas fa-spinner fa-spin" style="font-size:16px;color:#9CA3AF;"></i></span>
                <!-- <span class="lupto-kpi-trend trend-up"><i class="fas fa-arrow-up"></i> +2 this week</span> -->
            </div>
            <div class="lupto-kpi-icon bg-teal"><i class="fas fa-map-location-dot"></i></div>
        </div>
        <div class="lupto-kpi-card" data-kpi="total-fare-matrix">
            <div class="lupto-kpi-info">
                <h4>Total Fare Matrix</h4>
                <span class="lupto-kpi-value"><i class="fas fa-spinner fa-spin" style="font-size:16px;color:#9CA3AF;"></i></span>
                <!-- <span class="lupto-kpi-trend trend-up"><i class="fas fa-arrow-up"></i> +1 today</span> -->
            </div>
            <div class="lupto-kpi-icon bg-green"><i class="fas fa-file-invoice-dollar"></i></div>
        </div>
        <div class="lupto-kpi-card" data-kpi="total-tourist-users">
            <div class="lupto-kpi-info">
                <h4>Total Tourist Users</h4>
                <span class="lupto-kpi-value"><i class="fas fa-spinner fa-spin" style="font-size:16px;color:#9CA3AF;"></i></span>
                <!-- <span class="lupto-kpi-trend trend-up"><i class="fas fa-arrow-up"></i> Active explorers</span> -->
            </div>
            <div class="lupto-kpi-icon bg-blue"><i class="fas fa-users"></i></div>
        </div>
        <div class="lupto-kpi-card" data-kpi="total-points-earned">
            <div class="lupto-kpi-info">
                <h4>Total Points Earned</h4>
                <span class="lupto-kpi-value"><i class="fas fa-spinner fa-spin" style="font-size:16px;color:#9CA3AF;"></i></span>
                <!-- <span class="lupto-kpi-trend trend-up"><i class="fas fa-arrow-up"></i> Across all users</span> -->
            </div>
            <div class="lupto-kpi-icon bg-gold"><i class="fas fa-star"></i></div>
        </div>
        <div class="lupto-kpi-card" data-kpi="total-visits">
            <div class="lupto-kpi-info">
                <h4>Total Monthly Visitors</h4>
                <span class="lupto-kpi-value"><i class="fas fa-spinner fa-spin" style="font-size:16px;color:#9CA3AF;"></i></span>
                <!-- <span class="lupto-kpi-trend trend-up"><i class="fas fa-arrow-up"></i> +12% this month</span> -->
            </div>
            <div class="lupto-kpi-icon bg-purple"><i class="fas fa-chart-line"></i></div>
        </div>
    </div>

    
    <!-- Map & Activities -->
    <div class="lupto-dashboard-main-grid">
        <!-- Map Preview & Details Panel -->
        <div>
            <div class="card" style="padding: 14px;">
                <div class="lupto-map-header-action">
                    <h3 class="card-title" style="font-size: 14px; margin: 0;">
                        <i class="fas fa-map"></i> La Union Interactive LGU Profile Map
                    </h3>
                </div>
                <div id="dashboard-map-filters"></div>
                <div id="dashboard-map" class="lupto-embedded-map"></div>
            </div>


        </div>

        <!-- Recent Activities -->
        <div class="lupto-recent-activities">
            <div class="act-header-row">
                <div class="act-header-info">
                    <h3><i class="fas fa-history"></i> Recent Activities</h3>
                    <span class="act-subtitle">Stay updated with the latest system activities.</span>
                </div>
                <span class="act-count" id="act-total-count">0 Activities</span>
            </div>
            <div class="act-filters" id="act-filters">
                <span class="act-filter-pill active" onclick="window.filterDashboardActivities(this, 'all')">All</span>
                <span class="act-filter-pill" onclick="window.filterDashboardActivities(this, 'user')"><i class="fas fa-user"></i> Users</span>
                <span class="act-filter-pill" onclick="window.filterDashboardActivities(this, 'spot')"><i class="fas fa-map-marker-alt"></i> Spots</span>
                <span class="act-filter-pill" onclick="window.filterDashboardActivities(this, 'municipal')"><i class="fas fa-building"></i> Municipalities</span>
                <span class="act-filter-pill" onclick="window.filterDashboardActivities(this, 'system')"><i class="fas fa-cog"></i> System</span>
            </div>
            <div id="dashboard-activity-feed" class="dash-timeline">
                <div class="dash-timeline-loading"><i class="fas fa-spinner fa-spin"></i> Loading activities...</div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="lupto-charts-grid" style="margin-top: 24px;">
        <div class="lupto-chart-card">
            <div class="lupto-chart-header">
                <h3><i class="fas fa-chart-line"></i> Visitor Trends (Last 12 Months)</h3>
            </div>
            <div class="lupto-chart-container">
                <canvas id="visitorTrendsChart"></canvas>
            </div>
        </div>

        <div class="lupto-chart-card">
            <div class="lupto-chart-header">
                <h3><i class="fas fa-chart-bar"></i> Top 5 Municipalities</h3>
            </div>
            <div class="lupto-chart-container">
                <canvas id="topMunicipalitiesChart"></canvas>
            </div>
        </div>
        <!-- Top Tourist Spots Table -->
        <div class="lupto-chart-card full-width-card" style="grid-column: 1 / -1; margin-top: 20px;">
            <div class="lupto-chart-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; border-bottom: 1px solid var(--border); padding-bottom: 14px;">
                <h3 style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 700; color: #1E293B;">
                    <i class="fas fa-ranking-star" style="color: #D9A441;"></i> Top Tourist Spots
                </h3>
                <!-- Category Filter Pills -->
                <div class="top-spots-filters" style="display: flex; gap: 8px; align-items: center; overflow-x: auto; max-width: 100%; padding-bottom: 4px;">
                    <span class="spot-filter-pill active" data-category="all" style="cursor: pointer; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #10B981; color: #fff; transition: all 0.2s ease;">All</span>
                    <span class="spot-filter-pill" data-category="Beach" style="cursor: pointer; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #F1F5F9; color: #475569; transition: all 0.2s ease;">Beach</span>
                    <span class="spot-filter-pill" data-category="Nature" style="cursor: pointer; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #F1F5F9; color: #475569; transition: all 0.2s ease;">Nature</span>
                    <span class="spot-filter-pill" data-category="Heritage" style="cursor: pointer; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #F1F5F9; color: #475569; transition: all 0.2s ease;">Heritage</span>
                    <span class="spot-filter-pill" data-category="Cultural" style="cursor: pointer; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #F1F5F9; color: #475569; transition: all 0.2s ease;">Cultural</span>
                </div>
            </div>
            <div class="table-responsive" style="overflow-x: auto; width: 100%; margin-top: 15px;">
                <table class="top-spots-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
                    <thead>
                        <tr style="border-bottom: 2px solid #F1F5F9; color: #64748B; font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">
                            <th style="padding: 12px 8px; width: 50px;">#</th>
                            <th style="padding: 12px 8px;">Destination</th>
                            <th style="padding: 12px 8px;">Barangay</th>
                            <th style="padding: 12px 8px;">Municipal</th>
                            <th style="padding: 12px 8px;">Category</th>
                            <th style="padding: 12px 8px; text-align: center; width: 100px;">Visitors</th>
                            <th style="padding: 12px 8px; text-align: center; width: 100px;">Rating</th>
                        </tr>
                    </thead>
                    <tbody id="top-spots-table-body">
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: #94A3B8;">
                                <i class="fas fa-spinner fa-spin" style="margin-right: 8px;"></i> Loading top spots...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Pagination Controls -->
            <div class="top-spots-pagination" style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding-top: 12px; border-top: 1px solid #F1F5F9; flex-wrap: wrap; gap: 12px;">
                <span id="top-spots-page-info" style="font-size: 12px; color: #64748B; font-weight: 500;">Showing 0-0 of 0 spots</span>
                <div style="display: flex; gap: 6px; align-items: center;" id="top-spots-page-buttons">
                    <!-- Prev, page numbers, and Next buttons will be dynamically rendered -->
                </div>
            </div>
        </div>
    </div>

    <!-- Leaflet Map -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Leaflet MarkerCluster -->
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Shared Map Markers Config -->
    <script src="../../scripts/map-markers-config.js?v=<?= time() ?>"></script>
    <!-- Dashboard Scripts -->
    <script src="../../scripts/functions/PITCO/dashboard-api.js?v=<?= time() ?>"></script>
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
