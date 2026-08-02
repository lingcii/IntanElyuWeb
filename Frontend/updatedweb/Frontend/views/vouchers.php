<?php
// Shared Voucher & Rewards page — LUPTO, PICTO, Municipal (MTO).

require_once __DIR__ . '/../session-bridge.php';

$allowedRoles  = ['lupto', 'picto', 'municipal'];
$loginRedirect = '../login.php';
require_once __DIR__ . '/_role_guard.php';

if (!isset($pageTitle)) {
    $pageTitle = strtoupper($userRole) . ' – Voucher & Rewards';
}

ob_start();
?>
<link rel="stylesheet" href="../css/vouchers.css?v=<?= time() ?>">
<?php
$extraHeadContent = ob_get_clean();

ob_start();
?>

<!-- Page Header -->
<div class="vch-page-header">
    <div class="vch-header-left">
        <h2><i class="fas fa-ticket-simple"></i> Voucher & Rewards</h2>
        <p class="vch-header-sub">Manage promotional vouchers, discount offers, and tourist reward redemptions.</p>
    </div>
</div>

<!-- Summary KPI Cards -->
<div class="vch-kpi-grid">
    <div class="vch-kpi-card">
        <div class="vch-kpi-info">
            <h4>Total Vouchers</h4>
            <span class="vch-kpi-value" id="kpiTotalVouchers">&#8212;</span>
        </div>
        <div class="vch-kpi-icon blue"><i class="fas fa-ticket-simple"></i></div>
    </div>
    <div class="vch-kpi-card">
        <div class="vch-kpi-info">
            <h4>Active Vouchers</h4>
            <span class="vch-kpi-value" id="kpiActiveVouchers">&#8212;</span>
        </div>
        <div class="vch-kpi-icon green"><i class="fas fa-check-circle"></i></div>
    </div>
    <div class="vch-kpi-card">
        <div class="vch-kpi-info">
            <h4>Total Redeemed</h4>
            <span class="vch-kpi-value" id="kpiTotalRedeemed">&#8212;</span>
        </div>
        <div class="vch-kpi-icon purple"><i class="fas fa-gift"></i></div>
    </div>
    <div class="vch-kpi-card">
        <div class="vch-kpi-info">
            <h4>Points Redeemed</h4>
            <span class="vch-kpi-value" id="kpiTotalPointsRedeemed">&#8212;</span>
        </div>
        <div class="vch-kpi-icon gold"><i class="fas fa-star"></i></div>
    </div>
    <div class="vch-kpi-card">
        <div class="vch-kpi-info">
            <h4>Expired Vouchers</h4>
            <span class="vch-kpi-value" id="kpiExpiredVouchers">&#8212;</span>
        </div>
        <div class="vch-kpi-icon red"><i class="fas fa-clock"></i></div>
    </div>
</div>

<!-- Navigation Tabs -->
<div class="vch-nav-tabs">
    <button class="vch-tab-btn active" onclick="switchVoucherTab('catalog')" id="tabBtnCatalog">
        <i class="fas fa-list"></i> Voucher Catalog
    </button>
    <button class="vch-tab-btn" onclick="switchVoucherTab('history')" id="tabBtnHistory">
        <i class="fas fa-history"></i> Redemption History
    </button>
    <button class="vch-tab-btn" onclick="switchVoucherTab('analytics')" id="tabBtnAnalytics">
        <i class="fas fa-chart-pie"></i> Analytics & Trends
    </button>
</div>

<!-- TAB 1: VOUCHER CATALOG -->
<div class="vch-tab-content active" id="tabContentCatalog">
    <!-- Controls Bar -->
    <div class="vch-controls-bar">
        <div class="vch-search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="vchSearchInput" class="vch-input" placeholder="Search by voucher name, code, partner..." oninput="debouncedVchSearch()">
        </div>

        <select id="vchMuniFilter" class="vch-select" onchange="applyVchFilters()">
            <option value="">All Municipalities</option>
        </select>

        <select id="vchStatusFilter" class="vch-select" onchange="applyVchFilters()">
            <option value="">All Statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="low_stock">Low Stock (<=5)</option>
            <option value="expired">Expired</option>
            <option value="archived">Archived</option>
        </select>

        <select id="vchTypeFilter" class="vch-select" onchange="applyVchFilters()">
            <option value="">All Discount Types</option>
            <option value="percentage">Percentage Discount</option>
            <option value="fixed">Fixed Amount</option>
            <option value="free_entrance">Free Entrance</option>
            <option value="bogo">Buy One Get One</option>
            <option value="free_souvenir">Free Souvenir</option>
            <option value="custom">Custom Reward</option>
        </select>

        <select id="vchSortSelect" class="vch-select" onchange="applyVchFilters()">
            <option value="created_at">Newest First</option>
            <option value="required_points">Points: Low to High</option>
            <option value="remaining_quantity">Remaining Qty</option>
            <option value="expires_at">Expiration Date</option>
            <option value="voucher_name">Name A–Z</option>
        </select>

        <button class="vch-btn vch-btn-light" onclick="clearVchFilters()" title="Reset Filters">
            <i class="fas fa-times"></i> Clear
        </button>

        <button class="vch-btn vch-btn-light" onclick="refreshVouchers()" title="Refresh">
            <i class="fas fa-sync-alt" id="vchRefreshIcon"></i> Refresh
        </button>

        <?php if ($userRole === 'lupto'): ?>
            <button class="vch-btn vch-btn-primary" onclick="openCreateVoucherModal()">
                <i class="fas fa-plus"></i> Create Voucher
            </button>
        <?php endif; ?>
    </div>

    <!-- Voucher Catalog Table -->
    <div class="vch-card-table">
        <div class="vch-table-wrapper">
            <table class="vch-table" id="vouchersTable">
                <thead>
                    <tr>
                        <th style="width: 70px;">Image</th>
                        <th>Voucher Details</th>
                        <th>Discount</th>
                        <th>Required Points</th>
                        <th>Available Qty</th>
                        <th>Redeemed</th>
                        <th>Remaining</th>
                        <th>Municipality</th>
                        <th>Validity Period</th>
                        <th>Status</th>
                        <th style="width: 130px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="vouchersTableBody">
                    <tr>
                        <td colspan="11" class="vch-empty">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Loading vouchers catalog&hellip;</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="vch-pagination" id="vchPaginationBar">
            <span class="vch-pagination-info" id="vchPaginationInfo"></span>
            <div class="vch-page-btns" id="vchPaginationBtns"></div>
        </div>
    </div>
</div>

<!-- TAB 2: REDEMPTION HISTORY -->
<div class="vch-tab-content" id="tabContentHistory">
    <div class="vch-controls-bar">
        <div class="vch-search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="redSearchInput" class="vch-input" placeholder="Search by redemption code, tourist name..." oninput="debouncedRedSearch()">
        </div>

        <select id="redStatusFilter" class="vch-select" onchange="applyRedFilters()">
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="claimed">Claimed</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
            <option value="expired">Expired</option>
        </select>

        <select id="redMuniFilter" class="vch-select" onchange="applyRedFilters()">
            <option value="">All Municipalities</option>
        </select>

        <button class="vch-btn vch-btn-light" onclick="clearRedFilters()">
            <i class="fas fa-times"></i> Clear
        </button>

        <button class="vch-btn vch-btn-light" onclick="refreshRedemptions()">
            <i class="fas fa-sync-alt" id="redRefreshIcon"></i> Refresh
        </button>
    </div>

    <!-- Redemption History Table -->
    <div class="vch-card-table">
        <div class="vch-table-wrapper">
            <table class="vch-table" id="redemptionsTable">
                <thead>
                    <tr>
                        <th>Redemption Code</th>
                        <th>Tourist</th>
                        <th>Municipality</th>
                        <th>Voucher Name</th>
                        <th>Points Used</th>
                        <th>Redeemed Date</th>
                        <th>Claimed Date</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody id="redemptionsTableBody">
                    <tr>
                        <td colspan="9" class="vch-empty">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Loading redemption history&hellip;</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="vch-pagination" id="redPaginationBar">
            <span class="vch-pagination-info" id="redPaginationInfo"></span>
            <div class="vch-page-btns" id="redPaginationBtns"></div>
        </div>
    </div>
</div>

<!-- TAB 3: ANALYTICS & TRENDS -->
<div class="vch-tab-content" id="tabContentAnalytics">
    <div class="vch-charts-grid">
        <div class="vch-chart-card">
            <div class="vch-chart-header">
                <h3><i class="fas fa-trophy"></i> Most Redeemed Vouchers</h3>
            </div>
            <div class="vch-chart-body">
                <canvas id="chartMostRedeemed"></canvas>
            </div>
        </div>
        <div class="vch-chart-card">
            <div class="vch-chart-header">
                <h3><i class="fas fa-chart-line"></i> Monthly Redemption Trend</h3>
            </div>
            <div class="vch-chart-body">
                <canvas id="chartMonthlyTrend"></canvas>
            </div>
        </div>
        <div class="vch-chart-card">
            <div class="vch-chart-header">
                <h3><i class="fas fa-map-marked-alt"></i> Redeemed by Municipality</h3>
            </div>
            <div class="vch-chart-body">
                <canvas id="chartByMunicipality"></canvas>
            </div>
        </div>
        <div class="vch-chart-card">
            <div class="vch-chart-header">
                <h3><i class="fas fa-chart-pie"></i> Voucher Availability</h3>
            </div>
            <div class="vch-chart-body">
                <canvas id="chartAvailability"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Create / Edit Voucher (LUPTO only) -->
<div class="vch-modal-overlay" id="voucherModal" style="display:none;" onclick="if(event.target===this)closeVoucherModal()">
    <div class="vch-modal-card large">
        <div class="vch-modal-header">
            <div class="vch-modal-header-left">
                <div class="vch-modal-header-icon"><i class="fas fa-ticket-simple"></i></div>
                <div>
                    <h3 id="voucherModalTitle">Create New Voucher</h3>
                    <p id="voucherModalSubtitle" class="vch-modal-subtitle">Fill in the details below to register a new reward voucher</p>
                </div>
            </div>
            <button class="vch-modal-close" onclick="closeVoucherModal()">&times;</button>
        </div>
        <form id="voucherForm" onsubmit="handleVoucherSubmit(event)">
            <input type="hidden" id="formVoucherId" value="">
            <div class="vch-modal-body">

                <!-- Section 1: Basic Information -->
                <div class="vch-section-card">
                    <div class="vch-section-pill"><i class="fas fa-info-circle"></i> BASIC INFORMATION</div>
                    <div class="vch-form-grid">
                        <div class="vch-form-group span-2">
                            <label for="formVoucherName">Voucher Name <span class="req">*</span></label>
                            <input type="text" id="formVoucherName" class="vch-input" placeholder="e.g. 20% Off San Juan Beach Resort Entrance" required>
                        </div>

                        <div class="vch-form-group">
                            <label for="formPartner">Partner Establishment</label>
                            <input type="text" id="formPartner" class="vch-input" value="The La Union Agri-Tourism Center Pasalubong Center" placeholder="e.g. The La Union Agri-Tourism Center Pasalubong Center">
                        </div>

                        <div class="vch-form-group">
                            <label for="formMunicipality">Municipality</label>
                            <select id="formMunicipality" class="vch-select" disabled>
                                <option value="">Provincial / All Municipalities</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Discount & Requirements -->
                <div class="vch-section-card">
                    <div class="vch-section-pill"><i class="fas fa-tags"></i> DISCOUNT & REQUIREMENTS</div>
                    <div class="vch-form-grid">
                        <div class="vch-form-group">
                            <label for="formDiscountType">Discount Type <span class="req">*</span></label>
                            <select id="formDiscountType" class="vch-select" required onchange="handleDiscountTypeChange()">
                                <option value="percentage">Percentage Discount (%)</option>
                                <option value="fixed">Fixed Amount (₱)</option>
                                <option value="free_entrance">Free Entrance</option>
                                <option value="bogo">Buy One Get One</option>
                                <option value="free_souvenir">Free Souvenir</option>
                                <option value="custom">Custom Reward</option>
                            </select>
                        </div>

                        <div class="vch-form-group" id="groupDiscountValue">
                            <label for="formDiscountValue">Discount Value <span class="req">*</span></label>
                            <input type="number" step="0.01" min="0" id="formDiscountValue" class="vch-input" placeholder="e.g. 20 for 20% or 150 for ₱150">
                        </div>

                        <div class="vch-form-group">
                            <label for="formRequiredPoints">Required Points <span class="req">*</span></label>
                            <input type="number" min="0" id="formRequiredPoints" class="vch-input" placeholder="e.g. 500" required>
                        </div>

                        <div class="vch-form-group">
                            <label for="formAvailableQuantity">Total Stock Quantity <span class="req">*</span></label>
                            <input type="number" min="1" id="formAvailableQuantity" class="vch-input" placeholder="e.g. 50" required>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Validity & Details -->
                <div class="vch-section-card">
                    <div class="vch-section-pill"><i class="fas fa-calendar-alt"></i> VALIDITY & STATUS</div>
                    <div class="vch-form-grid">
                        <div class="vch-form-group">
                            <label for="formExpiresAt">Expiration Date (Valid Until) <span class="req">*</span></label>
                            <input type="datetime-local" id="formExpiresAt" class="vch-input" required>
                        </div>

                        <div class="vch-form-group">
                            <label for="formStatus">Status <span class="req">*</span></label>
                            <select id="formStatus" class="vch-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="vch-form-group span-2">
                            <label for="formImage">Voucher Image URL (Optional)</label>
                            <input type="text" id="formImage" class="vch-input" placeholder="https://example.com/images/voucher.jpg">
                        </div>

                        <div class="vch-form-group span-2">
                            <label for="formDescription">Description & Terms and Conditions (Optional)</label>
                            <textarea id="formDescription" class="vch-textarea" rows="2" placeholder="Provide details, rules, or restrictions for tourists..."></textarea>
                        </div>
                    </div>
                </div>

            </div>
            <div class="vch-modal-footer">
                <button type="button" class="vch-btn vch-btn-light" onclick="closeVoucherModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="vch-btn vch-btn-primary" id="btnSubmitVoucher">
                    <i class="fas fa-check-circle"></i> Save Voucher
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Confirmation Modal for Create / Edit Voucher -->
<div class="vch-modal-overlay" id="voucherConfirmModal" style="display:none; z-index: 10000;" onclick="if(event.target===this)closeConfirmModal()">
    <div class="vch-modal-card small" style="max-width: 420px; height: auto !important; max-height: 90vh !important;">
        <div class="vch-modal-header" style="background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%); padding: 16px 20px;">
            <div class="vch-modal-header-left">
                <div class="vch-modal-header-icon" style="width: 38px; height: 38px; font-size: 18px;"><i class="fas fa-question-circle"></i></div>
                <div>
                    <h3 style="font-size: 18px !important; margin: 0;">Confirm Action</h3>
                </div>
            </div>
            <button class="vch-modal-close" onclick="closeConfirmModal()">&times;</button>
        </div>
        <div class="vch-modal-body" style="padding: 24px 20px; text-align: center;">
            <p id="confirmModalText" style="font-size: 15px; color: #0f172a; font-weight: 600; margin: 0; line-height: 1.5;">
                Are you sure you want to add this?
            </p>
        </div>
        <div class="vch-modal-footer" style="padding: 14px 20px; justify-content: center; gap: 12px; background: #ffffff;">
            <button type="button" class="vch-btn vch-btn-light" onclick="closeConfirmModal()" style="min-width: 90px; justify-content: center;">
                <i class="fas fa-times"></i> No
            </button>
            <button type="button" class="vch-btn vch-btn-primary" id="btnConfirmYes" style="min-width: 90px; justify-content: center;">
                <i class="fas fa-check"></i> Yes
            </button>
        </div>
    </div>
</div>

<!-- MODAL: View Voucher Details -->
<div class="vch-modal-overlay" id="voucherDetailsModal" style="display:none;" onclick="if(event.target===this)closeDetailsModal()">
    <div class="vch-modal-card">
        <div class="vch-modal-header">
            <h3><i class="fas fa-info-circle"></i> Voucher Details</h3>
            <button class="vch-modal-close" onclick="closeDetailsModal()">&times;</button>
        </div>
        <div class="vch-modal-body" id="voucherDetailsBody">
            <div class="vch-empty"><i class="fas fa-spinner fa-spin"></i><p>Loading details...</p></div>
        </div>
        <div class="vch-modal-footer">
            <button class="vch-btn vch-btn-light" onclick="closeDetailsModal()">Close</button>
        </div>
    </div>
</div>

<!-- MODAL: Update Redemption Status -->
<div class="vch-modal-overlay" id="updateStatusModal" style="display:none;" onclick="if(event.target===this)closeStatusModal()">
    <div class="vch-modal-card small">
        <div class="vch-modal-header">
            <h3><i class="fas fa-tasks"></i> Update Redemption Status</h3>
            <button class="vch-modal-close" onclick="closeStatusModal()">&times;</button>
        </div>
        <form id="redStatusForm" onsubmit="handleStatusSubmit(event)">
            <input type="hidden" id="statusRedemptionId" value="">
            <div class="vch-modal-body">
                <p style="margin-bottom: 12px; font-size: 13px; color: #475569;">
                    Update status for redemption code: <strong id="statusRedCode" style="color: #1e3a8a;"></strong>
                </p>
                <div class="vch-form-group">
                    <label for="newRedStatus">New Status</label>
                    <select id="newRedStatus" class="vch-select" required>
                        <option value="pending">Pending</option>
                        <option value="claimed">Claimed</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="expired">Expired</option>
                    </select>
                </div>
            </div>
            <div class="vch-modal-footer">
                <button type="button" class="vch-btn vch-btn-light" onclick="closeStatusModal()">Cancel</button>
                <button type="submit" class="vch-btn vch-btn-primary">Update Status</button>
            </div>
        </form>
    </div>
</div>

<script>
    window.__VCH_CURRENT_USER__ = {
        id: <?= (int)($_SESSION['user_id'] ?? 0) ?>,
        role: '<?= addslashes($userRole) ?>'
    };
</script>

<script src="../scripts/functions/voucher-api.js"></script>

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
