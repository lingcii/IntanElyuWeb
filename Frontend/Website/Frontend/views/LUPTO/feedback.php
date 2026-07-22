<?php
require_once __DIR__ . '/../../session-bridge.php';
require_once __DIR__ . '/../../laravel-api-bridge.php';

if ($_SESSION['user_role'] !== 'lupto') {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'LUPTO - Feedback Management';

ob_start();
?>
<link rel="stylesheet" href="../../css/feedback.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<?php
$extraHeadContent = ob_get_clean();

ob_start();
?>
<div class="feedback-container">


    <!-- Dashboard KPI Cards -->
    <div class="feedback-kpi-grid">
        <div class="feedback-kpi-card">
            <div class="feedback-kpi-info">
                <h4>Spots Reviewed</h4>
                <span class="feedback-kpi-value" id="kpi-total-reviewed"><i class="fas fa-spinner fa-spin"></i></span>
                <span class="feedback-kpi-subtext"><i class="fas fa-map-marker-alt"></i> Province-wide</span>
            </div>
            <div class="feedback-kpi-icon bg-blue"><i class="fas fa-map-location-dot"></i></div>
        </div>
        <div class="feedback-kpi-card">
            <div class="feedback-kpi-info">
                <h4>Total Feedback</h4>
                <span class="feedback-kpi-value" id="kpi-total-feedback"><i class="fas fa-spinner fa-spin"></i></span>
                <span class="feedback-kpi-subtext"><i class="fas fa-comments"></i> Submissions</span>
            </div>
            <div class="feedback-kpi-icon bg-purple"><i class="fas fa-comment-dots"></i></div>
        </div>
        <div class="feedback-kpi-card">
            <div class="feedback-kpi-info">
                <h4>Average Rating</h4>
                <span class="feedback-kpi-value" id="kpi-avg-rating"><i class="fas fa-spinner fa-spin"></i></span>
                <span class="feedback-kpi-subtext"><i class="fas fa-star" style="color:#f59e0b;"></i> Overall Score</span>
            </div>
            <div class="feedback-kpi-icon bg-yellow"><i class="fas fa-star"></i></div>
        </div>
        <div class="feedback-kpi-card">
            <div class="feedback-kpi-info">
                <h4>5-Star Reviews</h4>
                <span class="feedback-kpi-value" id="kpi-5star-reviews"><i class="fas fa-spinner fa-spin"></i></span>
                <span class="feedback-kpi-subtext"><i class="fas fa-crown" style="color:#16a34a;"></i> Excellent</span>
            </div>
            <div class="feedback-kpi-icon bg-green"><i class="fas fa-award"></i></div>
        </div>
    </div>


    <!-- Search, Filter & Sort Toolbar -->
    <div class="feedback-toolbar">
        <div class="toolbar-search">
            <i class="fas fa-search"></i>
            <input type="text" id="feedback-search-input" placeholder="Search spot name, municipality, user, comment...">
        </div>
        <div class="toolbar-filters">
            <select class="filter-select" id="filter-municipality">
                <option value="">All Municipalities</option>
                <option value="1">San Juan</option>
                <option value="2">San Fernando City</option>
                <option value="3">Bauang</option>
                <option value="4">Agoo</option>
                <option value="5">Luna</option>
                <option value="6">San Gabriel</option>
                <option value="7">Balaoan</option>
                <option value="8">Aringay</option>
                <option value="9">Rosario</option>
                <option value="10">Bacnotan</option>
                <option value="11">Naguilian</option>
                <option value="12">Tubao</option>
                <option value="13">Pugo</option>
                <option value="14">Caba</option>
                <option value="15">Santo Tomas</option>
                <option value="16">Bangar</option>
                <option value="17">Burgos</option>
                <option value="18">Bagulin</option>
                <option value="19">Santol</option>
                <option value="20">Sudipen</option>
            </select>

            <select class="filter-select" id="filter-category">
                <option value="">All Categories</option>
                <option value="Beach">Beach</option>
                <option value="Waterfalls">Waterfalls</option>
                <option value="Historical">Historical</option>
                <option value="Cultural Heritage">Cultural Heritage</option>
                <option value="Eco-Tourism">Eco-Tourism</option>
                <option value="Nature Park">Nature Park</option>
                <option value="Mountain">Mountain</option>
                <option value="Resort">Resort</option>
            </select>

            <div class="view-switcher-toggle">
                <button class="view-btn active" id="btn-view-gallery" type="button">
                    <i class="fas fa-grip-vertical"></i> Spot Gallery View
                </button>
                <button class="view-btn" id="btn-view-table" type="button">
                    <i class="fas fa-table-list"></i> Table View
                </button>
            </div>

            <button class="btn-reset-filters" id="btn-reset-filters" type="button">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </div>

    <!-- Gallery View Section -->
    <div id="gallery-view-section">
        <div class="feedback-gallery-grid" id="gallery-cards-container">
            <!-- Dynamic Gallery Cards -->
        </div>
        <div id="gallery-pagination"></div>
    </div>

    <!-- Table View Section -->
    <div id="table-view-section" style="display: none;">
        <div class="feedback-table-wrapper">
            <table class="feedback-data-table">
                <thead>
                    <tr>
                        <th>Tourist Spot</th>
                        <th>Municipality</th>
                        <th>Rating</th>
                        <th>Feedback Comment</th>
                        <th>Tourist / User</th>
                        <th>Date Submitted</th>
                    </tr>
                </thead>
                <tbody id="feedback-table-tbody">
                    <!-- Dynamic Table Rows -->
                </tbody>
            </table>
        </div>
        <div id="table-pagination"></div>
    </div>
</div>

<!-- Spot Detail Modal -->
<div class="feedback-modal-overlay" id="spot-modal-overlay">
    <div class="feedback-modal-card">
        <button class="modal-close-btn" id="spot-modal-close" type="button"><i class="fas fa-times"></i></button>
        <div id="spot-modal-content">
            <!-- Loaded dynamically by FeedbackApp.js -->
        </div>
    </div>
</div>

<script src="../../scripts/components/FeedbackApp.js"></script>

<?php
$pageContent = ob_get_clean();

if (is_ajax_request()) {
    if (isset($extraHeadContent)) {
        echo $extraHeadContent;
    }
    echo $pageContent;
    exit;
}

require_once __DIR__ . '/../../components/sections.php';
?>
