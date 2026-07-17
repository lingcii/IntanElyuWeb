<?php
require_once __DIR__ . '/../../session-bridge.php';
if ($_SESSION['user_role'] !== 'lupto') {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'LUPTO Fare Matrix';
ob_start();
?>
<link rel="stylesheet" href="../../css/LUPTO/fare-data.css">

<div class="fd-container">

    <div class="fd-info-banner">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>View-Only Access</strong>
            <p>LUPTO can search, view, and download fare information. Upload and edit actions are managed by PITCO and Municipal Tourism Offices.</p>
        </div>
    </div>

    <div class="fd-search-bar">
        <div class="fd-search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" class="fd-search-input" placeholder="Search by title, region, or creator..." oninput="filterFareGuides()">
        </div>
        <select id="vehicleFilter" class="fd-filter-select" onchange="filterFareGuides()">
            <option value="">All Vehicle Types</option>
            <option value="PUB_Aircon">PUB Aircon</option>
            <option value="PUB_Ordinary">PUB Ordinary</option>
            <option value="PUJ_Aircon">PUJ Aircon</option>
            <option value="PUJ_Ordinary">PUJ Ordinary</option>
            <option value="Tricycle">Tricycle</option>
            <option value="Van">Van</option>
        </select>

    <!-- optional -->
        <button class="fd-btn-refresh" onclick="loadFareGuides()">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
        <span class="fd-guide-count" id="fareGuidesCount"></span>
    </div>

    <div class="fd-cards-grid" id="fareGuidesGrid">
        <div class="fd-loading-spinner" style="grid-column: 1 / -1;">
            <i class="fas fa-circle-notch fa-spin"></i>
            <p>Loading fare guides...</p>
        </div>
    </div>

    <div class="fd-matrix-panel" id="fareMatrixSection">
        <div class="fd-matrix-header">
            <h3 id="fareMatrixTitle"><i class="fas fa-table"></i> Fare Matrix</h3>
            <div class="fd-matrix-actions">
                <button class="fd-btn-export" onclick="exportFareMatrix()" title="Download as CSV">
                    <i class="fas fa-download"></i> Export CSV
                </button>
                <button class="fd-btn-close" onclick="closeFareMatrix()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
        <div class="fd-matrix-body">
            <table class="fd-matrix-table">
                <thead>
                    <tr>
                        <th>Distance (km)</th>
                        <th>Regular Fare</th>
                        <th>Student / Senior / PWD</th>
                        <th>Savings</th>
                    </tr>
                </thead>
                <tbody id="fareMatrixBody">
                    <tr>
                        <td colspan="4" class="fd-loading-spinner" style="padding: 32px;">
                            <i class="fas fa-circle-notch fa-spin"></i>
                            <p style="margin:8px 0 0;font-size:13px;">Loading matrix...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="fd-toast-container" id="fdToastContainer"></div>

</div>

<script src="../../scripts/api-config.js"></script>
<script src="../../scripts/functions/LUPTO/fare-data-api.js"></script>
<?php
$pageContent = ob_get_clean();
if (is_ajax_request()) {
    if (isset($extraHeadContent)) echo $extraHeadContent;
    echo $pageContent;
    exit;
}
include '../../components/sections.php';
