<?php

// Shared Report Generation view — PICTO, LUPTO, and Municipal Tourist Office roles.
// Displays interactive report filters, dynamic report preview, KPI summaries, Chart.js visual graphs, paginated data table, and multi-format exports (PDF, Excel, CSV).

require_once __DIR__ . '/../session-bridge.php';
require_once __DIR__ . '/../laravel-api-bridge.php';

$allowedRoles = ['lupto', 'picto', 'municipal'];
require_once __DIR__ . '/_role_guard.php';

$pageTitle = strtoupper($userRole) . ' Report Generation';

ob_start();
?>
<link rel="stylesheet" href="../css/report-generator.css?v=<?= time() ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<?php
$extraHeadContent = ob_get_clean();

ob_start();
?>

<div class="rg-container">
    <!-- Key KPI Summary Statistics Grid (At top, above Filter card) -->
    <div id="rg-kpi-container" style="margin-bottom: 20px;">
        <div class="rg-kpi-grid">
            <div class="rg-kpi-card">
                <div class="rg-kpi-icon blue"><i class="fas fa-folder-open"></i></div>
                <div class="rg-kpi-info">
                    <h4>Total Records</h4>
                    <div class="rg-kpi-value">—</div>
                </div>
            </div>
            <div class="rg-kpi-card">
                <div class="rg-kpi-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="rg-kpi-info">
                    <h4>Total Approved / Active</h4>
                    <div class="rg-kpi-value">—</div>
                </div>
            </div>
            <div class="rg-kpi-card">
                <div class="rg-kpi-icon amber"><i class="fas fa-clock"></i></div>
                <div class="rg-kpi-info">
                    <h4>Total Pending</h4>
                    <div class="rg-kpi-value">—</div>
                </div>
            </div>
            <div class="rg-kpi-card">
                <div class="rg-kpi-icon red"><i class="fas fa-times-circle"></i></div>
                <div class="rg-kpi-info">
                    <h4>Total Rejected</h4>
                    <div class="rg-kpi-value">—</div>
                </div>
            </div>
            <div class="rg-kpi-card">
                <div class="rg-kpi-icon purple"><i class="fas fa-star"></i></div>
                <div class="rg-kpi-info">
                    <h4>Average Rating</h4>
                    <div class="rg-kpi-value">—</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Filters Card (Located Below KPI Grid) -->
    <div class="rg-card">
        <div class="rg-card-header">
            <h3 class="rg-card-title"><i class="fas fa-sliders"></i> Report Filter Options</h3>
        </div>
        <div class="rg-card-body">
            <form id="rg-filter-form" onsubmit="return false;">
                <div class="rg-filter-grid">
                    <!-- Report Type -->
                    <div class="rg-form-group">
                        <label for="rg-report-type"><i class="fas fa-list-check"></i> Report Type</label>
                        <select id="rg-report-type" class="rg-select">
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
                    <div class="rg-form-group">
                        <label for="rg-municipality"><i class="fas fa-location-dot"></i> Municipality</label>
                        <select id="rg-municipality" class="rg-select">
                            <option value="all">All Municipalities</option>
                            <option value="Agoo">Agoo</option>
                            <option value="Aringay">Aringay</option>
                            <option value="Bacnotan">Bacnotan</option>
                            <option value="Bagulin">Bagulin</option>
                            <option value="Balaoan">Balaoan</option>
                            <option value="Bangar">Bangar</option>
                            <option value="Bauang">Bauang</option>
                            <option value="Burgos">Burgos</option>
                            <option value="Caba">Caba</option>
                            <option value="Luna">Luna</option>
                            <option value="Naguilian">Naguilian</option>
                            <option value="Pugo">Pugo</option>
                            <option value="Rosario">Rosario</option>
                            <option value="San Fernando">San Fernando</option>
                            <option value="San Gabriel">San Gabriel</option>
                            <option value="San Juan">San Juan</option>
                            <option value="Santo Tomas">Santo Tomas</option>
                            <option value="Santol">Santol</option>
                            <option value="Sudipen">Sudipen</option>
                            <option value="Tubao">Tubao</option>
                        </select>
                    </div>

                    <!-- Date Range: Start Date -->
                    <div class="rg-form-group">
                        <label for="rg-start-date"><i class="fas fa-calendar-alt"></i> Start Date</label>
                        <input type="date" id="rg-start-date" class="rg-input">
                    </div>

                    <!-- Date Range: End Date -->
                    <div class="rg-form-group">
                        <label for="rg-end-date"><i class="fas fa-calendar-check"></i> End Date</label>
                        <input type="date" id="rg-end-date" class="rg-input">
                    </div>

                    <!-- Export Format & Download Action -->
                    <div class="rg-form-group">
                        <label for="rg-export-format"><i class="fas fa-file-export"></i> Export Format</label>
                        <div style="display:flex; gap:8px;">
                            <select id="rg-export-format" class="rg-select">
                                <option value="pdf">PDF (.pdf)</option>
                                <option value="excel">Excel (.xlsx)</option>
                                <option value="csv">CSV (.csv)</option>
                                <option value="print">Print Report</option>
                            </select>
                            <button type="button" id="rg-btn-download-now" class="rg-btn rg-btn-primary" style="white-space:nowrap; padding:9px 16px;">
                                <i class="fas fa-download"></i> Download
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Live Report Preview Section (Contains Tab Switcher for Report Data Table & Recent Downloads) -->
    <div id="rg-preview-container" style="margin-top:20px;"></div>

    <!-- Export Confirmation Modal -->
    <div id="rg-confirm-modal" class="rg-modal-overlay">
        <div class="rg-modal">
            <div class="rg-modal-icon"><i class="fas fa-file-download"></i></div>
            <h3 class="rg-modal-title">Confirm Download</h3>
            <p id="rg-confirm-msg" class="rg-modal-msg">Are you sure you want to download this report?</p>
            <div class="rg-modal-actions">
                <button type="button" id="rg-modal-btn-cancel" class="rg-modal-btn rg-modal-btn-cancel">No</button>
                <button type="button" id="rg-modal-btn-confirm" class="rg-modal-btn rg-modal-btn-confirm">Yes</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="rg-toast-container"></div>
</div>

<!-- Dynamically load report generator JS script -->
<script>
(function () {
    if (!window.__reportGeneratorScriptInjected) {
        window.__reportGeneratorScriptInjected = true;
        const s = document.createElement('script');
        s.src = '../scripts/functions/report-generator.js?v=<?= time() ?>';
        s.onload = function () {
            if (typeof window.initReportGeneratorModule === 'function') {
                window.initReportGeneratorModule();
            }
        };
        document.body.appendChild(s);
    } else if (typeof window.initReportGeneratorModule === 'function') {
        window.initReportGeneratorModule();
    }
})();
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
