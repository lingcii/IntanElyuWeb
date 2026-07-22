<?php
require_once __DIR__ . '/../../session-bridge.php';
if ($_SESSION['user_role'] !== 'picto' && $_SESSION['user_role'] !== 'pitco') {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'PICTO – Leaderboard';

ob_start();
?>
<link rel="stylesheet" href="../../css/PICTO/leaderboard.css">
<?php
$extraHeadContent = ob_get_clean();

ob_start();
?>

<!-- Header Panel -->
<div class="lb-header-panel">
    <div class="lb-header-left">
        <h1>Leaderboard</h1>
        <p class="lb-header-sub">Top tourists ranked by points and activity</p>
    </div>
    <div class="lb-header-stats">
        <div class="lb-header-stat">
            <span class="lb-header-stat-label">Total Tourists</span>
            <span class="lb-header-stat-value" id="kpiUsers">&#8212;</span>
        </div>
        <div class="lb-header-stat">
            <span class="lb-header-stat-label">Highest Points</span>
            <span class="lb-header-stat-value" id="kpiHighest">&#8212;</span>
        </div>
        <div class="lb-header-stat">
            <span class="lb-header-stat-label">Total Activities</span>
            <span class="lb-header-stat-value" id="kpiActivities">&#8212;</span>
        </div>
    </div>
</div>

<!-- Controls Bar -->
<div class="lb-controls-bar">
    <div class="lb-search-wrap">
        <i class="fas fa-search"></i>
        <input
            type="text"
            id="searchInput"
            class="lb-search-input"
            placeholder="Search by name or User ID&hellip;"
            oninput="debouncedSearch()"
            aria-label="Search users">
    </div>

    <div class="lb-filter-group">
        <label for="showFilter" class="lb-filter-label">Show</label>
        <select class="lb-filter-select" id="showFilter" onchange="applyFilters()" aria-label="Number of results">
            <option value="20" selected>Top 20</option>
            <option value="50">Top 50</option>
            <option value="100">Top 100</option>
            <option value="all">All</option>
        </select>
    </div>

    <select class="lb-filter-select" id="sortSelect" onchange="applyFilters()" aria-label="Sort by">
        <option value="points_desc">Highest Points</option>
        <option value="points_asc">Lowest Points</option>
        <option value="activities_desc">Most Activities</option>
        <option value="name_asc">Name A&rarr;Z</option>
    </select>

    <button class="lb-btn-clear" onclick="clearSearch()" title="Reset all filters">
        <i class="fas fa-times"></i> Clear
    </button>
    <button class="lb-btn-refresh" onclick="refreshAll()" title="Refresh data">
        <i class="fas fa-sync-alt" id="refreshIcon"></i> Refresh
    </button>
</div>

<!-- Table Card -->
<div class="lb-table-card">
    <div class="lb-table-wrapper">
        <table class="lb-table" id="leaderboardTable">
            <thead>
                <tr>
                    <th class="lb-col-rank">Rank</th>
                    <th class="lb-col-user">User</th>
                    <th class="lb-col-municipality">Municipality</th>
                    <th class="lb-col-points">Total Points</th>
                    <th class="lb-col-activities">Activities</th>
                    <th class="lb-col-link"></th>
                </tr>
            </thead>
            <tbody id="leaderboardBody">
                <tr>
                    <td colspan="6" class="lb-empty">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading leaderboard&hellip;</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="lb-pagination" id="paginationBar">
        <span class="lb-pagination-info" id="paginationInfo"></span>
        <div class="lb-page-btns" id="paginationBtns"></div>
    </div>
</div>

<!-- User Detail Modal -->
<div class="lb-modal-overlay" id="lbTouristModal" style="display:none;" onclick="if(event.target===this)closeTouristModal()">
    <div class="lb-modal-card">
        <div class="lb-modal-head">
            <h3>User Details</h3>
            <button class="lb-modal-close" onclick="closeTouristModal()">&times;</button>
        </div>
        <div class="lb-modal-body" id="lbModalBody">
            <div class="lb-empty">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading&hellip;</p>
            </div>
        </div>
    </div>
</div>

<script>
    window.__LB_CURRENT_USER__ = {
        id: <?= (int)($_SESSION['user_id'] ?? 0) ?>,
        role: '<?= addslashes($_SESSION['user_role'] ?? '') ?>'
    };
</script>
<script src="../../scripts/functions/PITCO/leaderboard-api.js"></script>

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
