<?php
require_once __DIR__ . '/../../session-bridge.php';
// Check role
if ($_SESSION['user_role'] !== 'picto' && $_SESSION['user_role'] !== 'pitco') {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'PICTO – User Management';

ob_start();
?>
<link rel="stylesheet" href="../../css/PICTO/user-management.css">
<?php
$extraHeadContent = ob_get_clean();

ob_start();
?>

 <!-- Page Header  -->
<div class="um-page-header">
    <h2><i class="fas fa-users-cog"></i> User Management</h2>
    <div class="um-header-actions">
        <button class="btn-gov btn-gov-secondary" onclick="openAuditLog()" title="View audit trail">
            <i class="fas fa-history"></i> Audit Log
        </button>
        <button class="btn-gov btn-gov-secondary" onclick="refreshTable()" title="Refresh">
            <i class="fas fa-sync-alt" id="refreshIcon"></i> Refresh
        </button>
        <button class="btn-gov" onclick="openAddModal()">
            <i class="fas fa-user-plus"></i> Add User
        </button>
    </div>
</div>

 <!-- KPI Strip  -->
<div class="um-kpi-strip" id="kpiStrip">
    <div class="um-kpi-card">
        <div class="um-kpi-icon blue"><i class="fas fa-users"></i></div>
        <div class="um-kpi-info"><h4>Total Users</h4><p id="kpiTotal">—</p></div>
    </div>
    <div class="um-kpi-card">
        <div class="um-kpi-icon green"><i class="fas fa-user-check"></i></div>
        <div class="um-kpi-info"><h4>Active Users</h4><p id="kpiActive">—</p></div>
    </div>
    <div class="um-kpi-card">
        <div class="um-kpi-icon red"><i class="fas fa-user-slash"></i></div>
        <div class="um-kpi-info"><h4>Inactive Users</h4><p id="kpiInactive">—</p></div>
    </div>
    <div class="um-kpi-card">
        <div class="um-kpi-icon orange"><i class="fas fa-hiking"></i></div>
        <div class="um-kpi-info"><h4>Total Tourists</h4><p id="kpiTourists">—</p></div>
    </div>
    <div class="um-kpi-card">
        <div class="um-kpi-icon red"><i class="fas fa-user-plus"></i></div>
        <div class="um-kpi-info"><h4>Recently Added</h4><p id="kpiRecentlyAdded">—</p></div>
    </div>
</div>

 <!-- Search / Filter Bar  -->
<div class="card" style="margin-bottom:12px;">
    <div class="um-toolbar">
        <div class="um-search-wrap">
            <i class="fas fa-search"></i>
            <input
                type="text"
                id="searchInput"
                class="um-search-input"
                placeholder="Search by name, email, or User ID…"
                oninput="debouncedSearch()"
                aria-label="Search users">
        </div>
        <select class="um-filter-select" id="roleFilter" onchange="applyFilters()" aria-label="Filter by role">
            <option value="">All Roles</option>
            <option value="pitco">PICTO</option>
            <option value="lupto">LUPTO</option>
            <option value="tourist">Tourist</option>
            <optgroup label="Municipal Tourism Offices">
                <option value="san_juan_mto">San Juan MTO</option>
                <option value="san_fernando_mto">San Fernando MTO</option>
                <option value="bauang_mto">Bauang MTO</option>
                <option value="agoo_mto">Agoo MTO</option>
                <option value="luna_mto">Luna MTO</option>
                <option value="san_gabriel_mto">San Gabriel MTO</option>
                <option value="balaoan_mto">Balaoan MTO</option>
                <option value="aringay_mto">Aringay MTO</option>
                <option value="rosario_mto">Rosario MTO</option>
                <option value="bacnotan_mto">Bacnotan MTO</option>
                <option value="naguilian_mto">Naguilian MTO</option>
                <option value="tubao_mto">Tubao MTO</option>
                <option value="pugo_mto">Pugo MTO</option>
                <option value="caba_mto">Caba MTO</option>
                <option value="santo_tomas_mto">Santo Tomas MTO</option>
                <option value="bangar_mto">Bangar MTO</option>
                <option value="burgos_mto">Burgos MTO</option>
                <option value="bagulin_mto">Bagulin MTO</option>
                <option value="santol_mto">Santol MTO</option>
                <option value="sudipen_mto">Sudipen MTO</option>
            </optgroup>
        </select>
        <select class="um-filter-select" id="statusFilter" onchange="applyFilters()" aria-label="Filter by status">
            <option value="">All Statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="archived">Archived</option>
        </select>
        <button class="btn-gov btn-gov-secondary" onclick="clearFilters()">
            <i class="fas fa-times"></i> Clear
        </button>
    </div>
</div>

 <!-- Users Table  -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header flex-between">
        <h3 class="card-title"><i class="fas fa-table"></i> Registered Users</h3>
        <span id="tableCountLabel" style="font-size:12px; color:var(--text-muted);"></span>
    </div>
    <div class="um-table-wrap">
        <table class="data-table" id="usersTable" style="min-width:860px;">
            <thead>
                <tr>
                    <th style="width:54px; cursor:pointer;" onclick="sortBy('id')">
                        ID <i class="fas fa-sort" id="sort-id" style="font-size:10px; color:var(--text-muted);"></i>
                    </th>
                    <th style="cursor:pointer;" onclick="sortBy('name')">
                        Full Name <i class="fas fa-sort" id="sort-name" style="font-size:10px; color:var(--text-muted);"></i>
                    </th>
                    <th>Role</th>
                    <th style="cursor:pointer;" onclick="sortBy('email')">
                        Email <i class="fas fa-sort" id="sort-email" style="font-size:10px; color:var(--text-muted);"></i>
                    </th>
                    <th>Municipality</th>
                    <th>Status</th>
                    <th style="cursor:pointer;" onclick="sortBy('created_at')">
                        Date Created <i class="fas fa-sort" id="sort-created_at" style="font-size:10px; color:var(--text-muted);"></i>
                    </th>
                    <th style="cursor:pointer;" onclick="sortBy('last_activity')">
                        Last Login <i class="fas fa-sort" id="sort-last_activity" style="font-size:10px; color:var(--text-muted);"></i>
                    </th>
                    <th style="width:130px;">Actions</th>
                </tr>
            </thead>
            <tbody id="usersBody">
                <tr>
                    <td colspan="9" class="um-empty">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading users…</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <!-- Pagination -->
    <div class="um-pagination" id="paginationBar">
        <span id="paginationInfo"></span>
        <div class="um-page-btns" id="paginationBtns"></div>
    </div>
</div>


     <!-- ADD / EDIT USER MODAL -->
<div class="um-modal-overlay" id="userFormModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="formModalTitle">
    <div class="um-modal">
        <div class="um-modal-header">
            <h3 id="formModalTitle"><i class="fas fa-user-edit"></i> <span id="formModalTitleText">Add User</span></h3>
            <button class="um-modal-close" onclick="closeFormModal()" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="um-modal-body">
            <form id="userForm" novalidate>
                <input type="hidden" id="formUserId">
                <div class="um-form-grid">
                    <!-- Full Name -->
                    <div class="um-form-group full">
                        <label class="um-form-label" for="formName">Full Name <span class="required">*</span></label>
                        <input type="text" id="formName" class="um-form-control" placeholder="e.g. Juan dela Cruz" maxlength="100" required>
                    </div>
                    <!-- Email -->
                    <div class="um-form-group">
                        <label class="um-form-label" for="formEmail">Email Address <span class="required">*</span></label>
                        <input type="email" id="formEmail" class="um-form-control" placeholder="user@example.com" maxlength="100" required>
                    </div>
                    <!-- Role -->
                    <div class="um-form-group">
                        <label class="um-form-label" for="formRole">Role <span class="required">*</span></label>
                        <select id="formRole" class="um-form-control" required onchange="onRoleChange()">
                            <option value="">— Select Role —</option>
                            <option value="pitco">PICTO</option>
                            <option value="lupto">LUPTO</option>
                            <option value="municipal">MUNICIPAL</option>
                        </select>
                    </div>
                    <!-- Municipality (shown for MTO roles) -->
                    <div class="um-form-group" id="muniGroup" style="display:none;">
                        <label class="um-form-label" for="formMunicipality">Municipality</label>
                        <select id="formMunicipality" class="um-form-control">
                            <option value="">— Select Municipality —</option>
                        </select>
                    </div>
                    <!-- Status (auto-set) -->
                    <input type="hidden" id="formStatus" value="active">
                    <!-- Password (auto-set) -->
                    <input type="hidden" id="formPassword" value="PASSWORD123">
                    <div class="um-form-group" id="formPasswordGroup" style="display:none;">
                    </div>
                </div><!-- /form-grid -->
            </form>
        </div>
        <div class="um-modal-footer">
            <button class="btn-gov btn-gov-secondary" onclick="closeFormModal()">Cancel</button>
            <button class="btn-gov" id="formSubmitBtn" onclick="submitUserForm()">
                <i class="fas fa-save"></i> <span id="formSubmitText">Save User</span>
            </button>
        </div>
    </div>
</div>


     <!-- RESET PASSWORD MODAL -->

<div class="um-modal-overlay" id="resetPwModal" style="display:none;" role="dialog" aria-modal="true">
    <div class="um-modal sm">
        <div class="um-modal-header">
            <h3><i class="fas fa-key"></i> Reset Password</h3>
            <button class="um-modal-close" onclick="closeResetPwModal()" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="um-modal-body">
            <p style="font-size:13px; color:var(--text-secondary); margin:0 0 14px;">
                Setting a new password for <strong id="resetPwUserName"></strong>.
            </p>
            <input type="hidden" id="resetPwUserId">
            <div class="um-form-group">
                <label class="um-form-label" for="resetPwInput">New Password <span class="required">*</span></label>
                <div class="um-pw-wrap">
                    <input type="password" id="resetPwInput" class="um-form-control" placeholder="Min. 6 characters" autocomplete="new-password">
                    <button type="button" class="um-pw-toggle" onclick="togglePwVis('resetPwInput', this)" aria-label="Toggle visibility">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="um-modal-footer">
            <button class="btn-gov btn-gov-secondary" onclick="closeResetPwModal()">Cancel</button>
            <button class="btn-gov" onclick="submitResetPassword()">
                <i class="fas fa-check"></i> Reset Password
            </button>
        </div>
    </div>
</div>


<!-- SAVE CONFIRMATION MODAL -->
<div class="um-modal-overlay" id="umSaveConfirmModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="umSaveConfirmHeading">
    <div class="um-modal sm">
        <div class="um-modal-body" style="padding:32px 28px 24px; text-align:center;">
            <div class="um-confirm-icon info" style="margin-bottom:18px;">
                <i class="fas fa-user-check" style="font-size:22px;"></i>
            </div>
            <h4 id="umSaveConfirmHeading" style="margin:0 0 8px; font-size:16px; font-weight:700; color:var(--text-primary);">Confirm Save</h4>
            <p class="um-confirm-text" id="umSaveConfirmText">Are you sure you want to save this user?</p>
        </div>
        <div class="um-modal-footer" style="justify-content:center; gap:12px;">
            <button class="btn-gov btn-gov-secondary" onclick="closeSaveConfirm()" style="min-width:110px;">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button class="btn-gov" id="umSaveConfirmBtn" onclick="confirmSaveUser()" style="min-width:130px;">
                <i class="fas fa-check" id="umSaveConfirmIcon"></i>
                <i class="fas fa-circle-notch fa-spin" id="umSaveConfirmSpinner" style="display:none;"></i>
                <span id="umSaveConfirmLabel">Yes, Save User</span>
            </button>
        </div>
    </div>
</div>


     <!-- CONFIRM DIALOG MODAL -->

<div class="um-modal-overlay" id="confirmModal" style="display:none;" role="dialog" aria-modal="true">
    <div class="um-modal sm">
        <div class="um-modal-body" style="padding:28px 24px 20px;">
            <div class="um-confirm-icon warn" id="confirmIcon"><i class="fas fa-exclamation-triangle"></i></div>
            <p class="um-confirm-text" id="confirmText">Are you sure?</p>
        </div>
        <div class="um-modal-footer">
            <button class="btn-gov btn-gov-secondary" onclick="closeConfirmModal()">Cancel</button>
            <button class="btn-gov" id="confirmOkBtn" onclick="confirmOk()">Confirm</button>
        </div>
    </div>
</div>


     <!-- AUDIT LOG MODAL -->

<div class="um-modal-overlay" id="auditModal" style="display:none;" role="dialog" aria-modal="true">
    <div class="um-modal" style="max-width:640px;">
        <div class="um-modal-header">
            <h3><i class="fas fa-history"></i> User Management Audit Log</h3>
            <button class="um-modal-close" onclick="closeAuditModal()" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="um-modal-body" id="auditLogBody" style="max-height:420px; overflow-y:auto;">
            <div class="um-empty"><i class="fas fa-spinner fa-spin"></i><p>Loading…</p></div>
        </div>
        <div class="um-modal-footer">
            <button class="btn-gov btn-gov-secondary" onclick="closeAuditModal()">Close</button>
        </div>
    </div>
</div>

<!-- Toast container  -->
<div id="umToastContainer" aria-live="polite"></div>

<script src="../../scripts/functions/PITCO/user-management-api.js?v=<?= time() ?>"></script>

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