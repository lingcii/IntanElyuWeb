<?php
/**
 * proof-validation.php
 * Proof Images Validation Module — shared across picto, lupto, municipal roles.
 * PICTO/LUPTO: read-only (view, search, filter, lightbox).
 * Municipal (MTO): full access (approve/reject), municipality-scoped.
 */

require_once __DIR__ . '/../session-bridge.php';
require_once __DIR__ . '/../laravel-api-bridge.php';
$allowedRoles = ['lupto', 'picto', 'municipal'];
require_once __DIR__ . '/_role_guard.php';
$pageTitle = 'Proof Images Validation';

ob_start();
?>
<link rel="stylesheet" href="../css/proof-validation.css">
<?php
$extraHeadContent = ob_get_clean();

ob_start();
$isMTO = ($userRole === 'municipal');
?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     Page Header
     ═══════════════════════════════════════════════════════════════════════════ -->
<div class="pv-page-header">
    <div>
        <h1 class="pv-page-title">
            <i class="fas fa-images"></i> Proof Images Validation
        </h1>
        <p class="pv-page-subtitle">
            <?php if ($isMTO): ?>
                Review and validate proof images submitted by tourists visiting your municipality's spots.
            <?php else: ?>
                Monitor all proof image submissions across the province. Read-only access.
            <?php endif; ?>
        </p>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     KPI Cards
     ═══════════════════════════════════════════════════════════════════════════ -->
<div class="pv-kpi-grid">
    <div class="pv-kpi-card">
        <div class="pv-kpi-icon blue"><i class="fas fa-images"></i></div>
        <div class="pv-kpi-info">
            <h4>Total Submissions</h4>
            <div class="pv-kpi-value" id="pv-kpi-total"><i class="fas fa-spinner fa-spin" style="font-size:18px;color:#9CA3AF;"></i></div>
            <div class="pv-kpi-label">Proof images submitted</div>
        </div>
    </div>
    <div class="pv-kpi-card">
        <div class="pv-kpi-icon yellow"><i class="fas fa-hourglass-half"></i></div>
        <div class="pv-kpi-info">
            <h4>Pending Review</h4>
            <div class="pv-kpi-value" id="pv-kpi-pending"><i class="fas fa-spinner fa-spin" style="font-size:18px;color:#9CA3AF;"></i></div>
            <div class="pv-kpi-label">Awaiting validation</div>
        </div>
    </div>
    <div class="pv-kpi-card">
        <div class="pv-kpi-icon green"><i class="fas fa-circle-check"></i></div>
        <div class="pv-kpi-info">
            <h4>Approved</h4>
            <div class="pv-kpi-value" id="pv-kpi-approved"><i class="fas fa-spinner fa-spin" style="font-size:18px;color:#9CA3AF;"></i></div>
            <div class="pv-kpi-label">Valid submissions</div>
        </div>
    </div>
    <div class="pv-kpi-card">
        <div class="pv-kpi-icon red"><i class="fas fa-circle-xmark"></i></div>
        <div class="pv-kpi-info">
            <h4>Rejected</h4>
            <div class="pv-kpi-value" id="pv-kpi-rejected"><i class="fas fa-spinner fa-spin" style="font-size:18px;color:#9CA3AF;"></i></div>
            <div class="pv-kpi-label">Invalid submissions</div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     Search & Filters Toolbar
     ═══════════════════════════════════════════════════════════════════════════ -->
<div class="pv-toolbar">
    <div class="pv-search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" id="pv-search-input" class="pv-search-input"
               placeholder="Search tourist name, spot, or municipality…">
    </div>

    <?php if ($userRole !== 'municipal'): ?>
    <select id="pv-filter-municipality" class="pv-filter-select">
        <option value="">All Municipalities</option>
    </select>
    <?php endif; ?>

    <select id="pv-filter-status" class="pv-filter-select">
        <option value="">All Statuses</option>
        <option value="pending">🟡 Pending</option>
        <option value="under_review">🔵 Under Review</option>
        <option value="approved">🟢 Approved</option>
        <option value="rejected">🔴 Rejected</option>
    </select>

    <span class="pv-filter-label">From:</span>
    <input type="date" id="pv-date-from" class="pv-date-input" title="Date from">
    <span class="pv-filter-label">To:</span>
    <input type="date" id="pv-date-to"   class="pv-date-input" title="Date to">

    <button id="pv-reset-filters" class="pv-btn pv-btn-view" title="Reset all filters">
        <i class="fas fa-rotate-left"></i> Reset
    </button>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     Submissions Table
     ═══════════════════════════════════════════════════════════════════════════ -->
<div class="pv-table-wrap">
    <table class="pv-table" id="pv-table">
        <thead>
        <tr>
            <th>#</th>
            <th>Tourist Name</th>
            <th>Tourist Spot</th>
            <th>Municipality</th>
            <th style="text-align:center;">Proof Image</th>
            <th>Date Submitted</th>
            <th>Status</th>
            <th>Reviewed By</th>
            <th>Reviewed Date</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody id="pv-tbody">
        <tr>
            <td colspan="10" class="pv-empty">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading submissions…</p>
            </td>
        </tr>
        </tbody>
    </table>

    <div class="pv-pagination">
        <div id="pv-results-info" style="color:#64748B; font-size:13px;"></div>
        <div class="pv-pagination-controls" id="pv-pagination"></div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     Detail Modal (View Submission)
     ═══════════════════════════════════════════════════════════════════════════ -->
<div class="pv-modal-overlay" id="pv-detail-overlay">
    <div class="pv-modal-content">
        <div class="pv-modal-header">
            <div class="pv-modal-title">
                <i class="fas fa-image"></i> Proof Image Submission Detail
            </div>
            <button class="pv-modal-close" onclick="window.pvCloseDetail()" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="pv-modal-body">
            <!-- Left: Info -->
            <div class="pv-modal-left" id="pv-detail-left">
                <div class="pv-empty"><i class="fas fa-spinner fa-spin"></i></div>
            </div>
            <!-- Right: Image -->
            <div class="pv-modal-right">
                <div class="pv-image-container" id="pv-detail-img-container">
                    <img id="pv-detail-img" class="pv-proof-main-img" src="" alt="Proof Image"
                         style="display:none;">
                </div>
                <div class="pv-image-controls">
                    <button onclick="window.pvOpenLightbox(document.getElementById('pv-detail-img').src)"
                            title="Open full screen">
                        <i class="fas fa-expand"></i> Full Screen
                    </button>
                </div>
            </div>
        </div>
        <div class="pv-modal-footer" id="pv-detail-footer"></div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     Lightbox
     ═══════════════════════════════════════════════════════════════════════════ -->
<div class="pv-lightbox" id="pv-lightbox">
    <button class="pv-lightbox-close" id="pv-lightbox-close" onclick="window.pvCloseLightbox()">
        <i class="fas fa-times"></i>
    </button>
    <img id="pv-lightbox-img" class="pv-lightbox-img" src="" alt="Proof Image (Full Screen)">
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     Approve Confirmation Modal (MTO only)
     ═══════════════════════════════════════════════════════════════════════════ -->
<?php if ($isMTO): ?>
<div class="pv-confirm-overlay" id="pv-approve-overlay">
    <div class="pv-confirm-box">
        <div class="pv-confirm-icon-wrap">
            <div class="pv-confirm-icon green">
                <i class="fas fa-check"></i>
            </div>
            <h3 class="pv-confirm-title">Approve Submission?</h3>
        </div>
        <div class="pv-confirm-body">
            <p class="pv-confirm-text">
                Are you sure you want to <strong>approve</strong> this proof image submission?<br>
                The tourist will be notified of the approval.
            </p>
            <div class="pv-confirm-actions">
                <button class="pv-confirm-btn cancel" onclick="window.pvCancelApprove()">
                    <i class="fas fa-times"></i> No
                </button>
                <button class="pv-confirm-btn confirm-approve" id="pv-approve-confirm-btn" onclick="window.pvDoApprove()">
                    <i class="fas fa-check"></i> Yes, Approve
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     Reject Modal with Reason (MTO only)
     ═══════════════════════════════════════════════════════════════════════════ -->
<div class="pv-confirm-overlay" id="pv-reject-overlay">
    <div class="pv-confirm-box">
        <div class="pv-confirm-icon-wrap">
            <div class="pv-confirm-icon red">
                <i class="fas fa-times"></i>
            </div>
            <h3 class="pv-confirm-title">Reject Submission</h3>
        </div>
        <div class="pv-confirm-body">
            <p class="pv-confirm-text" style="margin-bottom:12px;">
                Please provide a <strong>reason for rejection</strong>.<br>
                The tourist will be notified with this reason.
            </p>
            <textarea id="pv-reject-reason" class="pv-reject-textarea"
                      placeholder="e.g. Blurry image, Invalid tourist spot, Image does not match the location, Duplicate submission…"
                      maxlength="1000"></textarea>
            <div id="pv-reject-error" class="pv-reject-error">
                <i class="fas fa-exclamation-circle"></i> Rejection reason is required (minimum 5 characters).
            </div>
            <div class="pv-confirm-actions">
                <button class="pv-confirm-btn cancel" onclick="window.pvCancelReject()">
                    <i class="fas fa-arrow-left"></i> Cancel
                </button>
                <button class="pv-confirm-btn confirm-reject" id="pv-reject-confirm-btn" onclick="window.pvDoReject()">
                    <i class="fas fa-times"></i> Yes, Reject
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     Script Bootstrap
     ═══════════════════════════════════════════════════════════════════════════ -->
<script>
window.userRole = '<?= htmlspecialchars($userRole ?? 'lupto') ?>';
(function () {
    if (!window.__pvScriptInjected) {
        window.__pvScriptInjected = true;
        const s = document.createElement('script');
        s.src = '../scripts/functions/proof-validation-api.js?v=<?= time() ?>';
        s.onload = function () {
            if (typeof window.initProofValidationModule === 'function') {
                window.initProofValidationModule();
            }
        };
        document.body.appendChild(s);
    } else if (typeof window.initProofValidationModule === 'function') {
        window.initProofValidationModule();
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
