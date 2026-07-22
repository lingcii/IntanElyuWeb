<?php
require_once __DIR__ . '/../../session-bridge.php';
require_once __DIR__ . '/../../laravel-api-bridge.php';
if ($_SESSION['user_role'] !== 'picto' && $_SESSION['user_role'] !== 'pitco') {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'PICTO Feedback';

ob_start();
?>
<link rel="stylesheet" href="../../css/LUPTO/feedback.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<?php
$extraHeadContent = ob_get_clean();

ob_start();
?>

<div class="fb-page-header">
    <div>
        <h1 class="fb-page-title"><i class="fas fa-comments"></i> Tourist Feedback & Ratings</h1>
        <p class="fb-page-subtitle">Monitor tourist reviews and ratings across all municipalities in La Union.</p>
    </div>
</div>

<!-- ── KPI Summary Cards ─────────────────────────────────────────── -->
<div class="fb-kpi-grid">
    <div class="fb-kpi-card">
        <div class="fb-kpi-icon blue"><i class="fas fa-location-dot"></i></div>
        <div class="fb-kpi-info">
            <h4>Spots Reviewed</h4>
            <span class="fb-kpi-value" data-fb-kpi="spots_reviewed"><i class="fas fa-spinner fa-spin" style="font-size:14px;color:#9CA3AF;"></i></span>
            <div class="fb-kpi-label">Tourist spots with reviews</div>
        </div>
    </div>
    <div class="fb-kpi-card">
        <div class="fb-kpi-icon teal"><i class="fas fa-comment-dots"></i></div>
        <div class="fb-kpi-info">
            <h4>Total Feedback</h4>
            <span class="fb-kpi-value" data-fb-kpi="total_feedback"><i class="fas fa-spinner fa-spin" style="font-size:14px;color:#9CA3AF;"></i></span>
            <div class="fb-kpi-label">Reviews submitted</div>
        </div>
    </div>
    <div class="fb-kpi-card">
        <div class="fb-kpi-icon gold"><i class="fas fa-star"></i></div>
        <div class="fb-kpi-info">
            <h4>Average Rating</h4>
            <span class="fb-kpi-value" data-fb-kpi="avg_rating"><i class="fas fa-spinner fa-spin" style="font-size:14px;color:#9CA3AF;"></i></span>
        </div>
    </div>
    <div class="fb-kpi-card">
        <div class="fb-kpi-icon green"><i class="fas fa-star"></i></div>
        <div class="fb-kpi-info">
            <h4>5-Star Reviews</h4>
            <span class="fb-kpi-value" data-fb-kpi="five_star"><i class="fas fa-spinner fa-spin" style="font-size:14px;color:#9CA3AF;"></i></span>
            <div class="fb-kpi-label">Excellent ratings</div>
        </div>
    </div>
    <div class="fb-kpi-card">
        <div class="fb-kpi-icon blue"><i class="fas fa-star-half-stroke"></i></div>
        <div class="fb-kpi-info">
            <h4>4-Star Reviews</h4>
            <span class="fb-kpi-value" data-fb-kpi="four_star"><i class="fas fa-spinner fa-spin" style="font-size:14px;color:#9CA3AF;"></i></span>
            <div class="fb-kpi-label">Great ratings</div>
        </div>
    </div>
</div>



<!-- ── Toolbar ────────────────────────────────────────────────────── -->
<div class="fb-toolbar">
    <div class="fb-search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" id="fb-search-input" class="fb-search-input" placeholder="Search by spot name, municipality, user, or comment…">
    </div>

    <select id="fb-filter-municipality" class="fb-filter-select">
        <option value="">All Municipalities</option>
    </select>

    <select id="fb-filter-category" class="fb-filter-select">
        <option value="">All Categories</option>
    </select>

    <select id="fb-filter-rating" class="fb-filter-select" style="min-width:110px;">
        <option value="">All Ratings</option>
        <option value="5">⭐⭐⭐⭐⭐ 5 Star</option>
        <option value="4">⭐⭐⭐⭐ 4 Star</option>
        <option value="3">⭐⭐⭐ 3 Star</option>
        <option value="2">⭐⭐ 2 Star</option>
        <option value="1">⭐ 1 Star</option>
    </select>

    <select id="fb-sort-select" class="fb-filter-select" style="min-width:150px;">
        <option value="newest" selected>Newest First</option>
        <option value="most_reviewed">Most Reviewed</option>
        <option value="highest_rated">Highest Rated</option>
        <option value="lowest_rated">Lowest Rated</option>
        <option value="oldest">Oldest First</option>
        <option value="alphabetical">A – Z</option>
    </select>

    <!-- Date filters (table view only) -->
    <span id="fb-date-filters" style="display:none; display:flex; gap:6px; align-items:center;">
        <input type="date" id="fb-date-from" class="fb-date-input" title="From date">
        <span style="font-size:12px;color:#94a3b8;">to</span>
        <input type="date" id="fb-date-to" class="fb-date-input" title="To date">
    </span>

    <!-- View toggle -->
    <div class="fb-view-toggle">
        <button id="fb-btn-gallery" class="fb-view-btn active" onclick="window.switchFeedbackView('gallery')" title="Gallery View">
            <i class="fas fa-th-large"></i> Gallery
        </button>
        <button id="fb-btn-table" class="fb-view-btn" onclick="window.switchFeedbackView('table')" title="Table View">
            <i class="fas fa-table"></i> Table
        </button>
    </div>
</div>

<!-- ── Gallery View ───────────────────────────────────────────────── -->
<div id="fb-gallery-section">
    <div class="fb-gallery-grid" id="fb-gallery-grid">
        <!-- Skeleton cards rendered by JS -->
    </div>
    <div id="fb-gallery-pagination"></div>
</div>

<!-- ── Table View ────────────────────────────────────────────────── -->
<div id="fb-table-section" style="display:none;">
    <div class="fb-table-wrap">
        <table class="fb-table">
            <thead>
                <tr>
                    <th>Tourist Spot</th>
                    <th>Municipality</th>
                    <th>Rating</th>
                    <th>Comment</th>
                    <th>Tourist / User</th>
                    <th>Date Submitted</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="fb-table-body">
                <tr><td colspan="7" style="text-align:center;padding:30px;color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i> Loading…</td></tr>
            </tbody>
        </table>
    </div>
    <div id="fb-table-pagination"></div>
</div>

<script>
(function () {
    // Dynamically load the feedback JS module only once
    if (!window.__pitcoFeedbackScriptInjected) {
        window.__pitcoFeedbackScriptInjected = true;
        const s = document.createElement('script');
        s.src = '../../scripts/functions/PITCO/feedback-api.js?v=<?= time() ?>';
        s.onload = function () {
            if (typeof window.initFeedbackModule === 'function') window.initFeedbackModule();
        };
        document.body.appendChild(s);
    } else if (typeof window.initFeedbackModule === 'function') {
        window.initFeedbackModule();
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
include '../../components/sections.php';
