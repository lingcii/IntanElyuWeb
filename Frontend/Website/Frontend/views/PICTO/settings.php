<?php
require_once __DIR__ . '/../../session-bridge.php';
// Check role
if ($_SESSION['user_role'] !== 'picto' && $_SESSION['user_role'] !== 'pitco') {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'PICTO Settings';

$extraHeadContent = '
    <link rel="stylesheet" href="../../css/PICTO/settings.css">
    <script src="../../scripts/functions/PITCO/settings-api.js" defer></script>
';

ob_start();
?>
    <h2 class="section-title">System Settings</h2>

    <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:16px;">
        <!-- General Settings -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-cog"></i> General Settings</h3>
            </div>
            <div class="card-body">
                <div class="lupto-form-group">
                    <label>System Name</label>
                    <input type="text" class="filter-select" style="width:100%;" value="PICTO - La Union Provincial Information and Communications Technology Office">
                </div>
                <div class="lupto-form-group">
                    <label>Contact Email</label>
                    <input type="email" class="filter-select" style="width:100%;" value="picto@launion.gov.ph">
                </div>
                <div class="lupto-form-group">
                    <label>Contact Number</label>
                    <input type="text" class="filter-select" style="width:100%;" value="+63 912 345 6789">
                </div>
                <div class="lupto-form-group">
                    <label>System Logo</label>
                    <input type="file" style="width:100%;">
                </div>
                <button class="btn-gov">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>

        <!-- Security Settings (Column 2) -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-shield-alt"></i> Security Settings</h3>
            </div>
            <div class="card-body">
                <div class="lupto-form-group">
                    <label>Current Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="currentPassword" class="filter-select" style="width:100%; height:40px; box-sizing:border-box;" placeholder="Enter current password">
                        <button type="button" class="password-toggle-btn" onclick="window.togglePasswordVisibility('currentPassword', this)" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="lupto-form-group">
                    <label>New Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="newPassword" class="filter-select" style="width:100%; height:40px; box-sizing:border-box;" placeholder="Enter new password (min. 6 chars)">
                        <button type="button" class="password-toggle-btn" onclick="window.togglePasswordVisibility('newPassword', this)" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="lupto-form-group">
                    <label>Confirm New Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="confirmPassword" class="filter-select" style="width:100%; height:40px; box-sizing:border-box;" placeholder="Confirm new password">
                        <button type="button" class="password-toggle-btn" onclick="window.togglePasswordVisibility('confirmPassword', this)" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="lupto-form-group">
                    <label>Session Timeout (minutes)</label>
                    <input type="number" class="filter-select" style="width:100%; height:40px; box-sizing:border-box;" value="30" placeholder="Session timeout in minutes">
                </div>
                <button class="btn-gov" onclick="window.showSaveConfirmModal()">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>

        <!-- Backup Settings — Full Width -->
        <div class="card" id="picto-backupSettingsCard" style="grid-column:1/-1;">
            <div class="card-header" style="background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:16px 24px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                <h3 class="card-title" style="font-size:15px; font-weight:700; color:#1e3a8a; margin:0; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-database" style="color:#3b82f6;"></i> Backup Settings
                </h3>
                <span style="font-size:12px; color:#64748b; background:#eff6ff; padding:4px 12px; border-radius:20px; font-weight:600; border:1px solid #bfdbfe;">
                    <i class="fas fa-shield-alt" style="color:#3b82f6; margin-right:4px;"></i>Full System Access
                </span>
            </div>
            <div class="card-body" style="padding:24px;">
                <!-- Stats Row -->
                <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:24px;" id="picto-backupStatsRow">
                    <div class="backup-stat-tile">
                        <div class="bst-icon" style="background:#eff6ff;"><i class="fas fa-database" style="color:#3b82f6;"></i></div>
                        <div class="bst-body"><div class="bst-value" id="picto-statTotalVal">–</div><div class="bst-label">Total Backups</div></div>
                    </div>
                    <div class="backup-stat-tile">
                        <div class="bst-icon" style="background:#f0fdf4;"><i class="fas fa-clock" style="color:#16a34a;"></i></div>
                        <div class="bst-body"><div class="bst-value" id="picto-statLastVal" style="font-size:12px;">–</div><div class="bst-label">Last Backup</div></div>
                    </div>
                    <div class="backup-stat-tile">
                        <div class="bst-icon" style="background:#fdf4ff;"><i class="fas fa-hdd" style="color:#9333ea;"></i></div>
                        <div class="bst-body"><div class="bst-value" id="picto-statDbSizeVal">–</div><div class="bst-label">Database Size</div></div>
                    </div>
                    <div class="backup-stat-tile">
                        <div class="bst-icon" style="background:#f0fdf4;"><i class="fas fa-check-circle" style="color:#16a34a;"></i></div>
                        <div class="bst-body"><div class="bst-value" id="picto-statStatusVal" style="color:#16a34a;">–</div><div class="bst-label">Status</div></div>
                    </div>
                </div>
                <!-- Action Buttons -->
                <div style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap;">
                    <button id="picto-btnCreate" class="btn-gov" onclick="window.picto_backup.create()" style="display:inline-flex; align-items:center; gap:8px; min-width:160px; justify-content:center;">
                        <i class="fas fa-download"></i> Create Backup
                    </button>
                    <button id="picto-btnRestore" class="btn-gov btn-gov-secondary" onclick="document.getElementById('picto-backupFileInput').click()" style="display:inline-flex; align-items:center; gap:8px; min-width:160px; justify-content:center;">
                        <i class="fas fa-upload"></i> Restore Backup
                    </button>
                    <input type="file" id="picto-backupFileInput" accept=".sql" style="display:none;" onchange="window.picto_backup.uploadRestore(this)">
                </div>
                <!-- Table -->
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                    <h4 style="margin:0; font-size:14px; font-weight:700; color:#1e293b;">Recent Backups</h4>
                    <button onclick="window.picto_backup.loadList()" style="background:none; border:none; color:#3b82f6; font-size:12px; cursor:pointer; display:flex; align-items:center; gap:5px; font-weight:600;"><i class="fas fa-sync-alt"></i> Refresh</button>
                </div>
                <div style="overflow-x:auto; border-radius:10px; border:1px solid #e2e8f0;">
                    <table class="data-table" style="margin:0;">
                        <thead><tr><th>File Name</th><th>Backup Date &amp; Time</th><th>File Size</th><th style="text-align:center;">Actions</th></tr></thead>
                        <tbody id="picto-backupTableBody">
                            <tr><td colspan="4" style="text-align:center;padding:24px;color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i> Loading backups...</td></tr>
                        </tbody>
                    </table>
                </div>
                <!-- Logs -->
                <div style="margin-top:24px;">
                    <h4 style="margin:0 0 10px; font-size:14px; font-weight:700; color:#1e293b; display:flex; align-items:center; gap:7px;">
                        <i class="fas fa-history" style="color:#64748b;"></i> Backup Action Logs
                    </h4>
                    <div id="picto-backupLogsContainer" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 16px; font-size:13px; max-height:220px; overflow-y:auto;">
                        <div style="text-align:center;padding:16px;color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i> Loading logs...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Restore Confirmation Modal -->
    <div class="lupto-modal-overlay" id="picto-restoreModal" style="display:none; z-index:10000;">
        <div class="lupto-modal-content" style="max-width:440px; text-align:center;">
            <div class="lupto-modal-header" style="background:#d97706;">
                <h3 class="lupto-modal-title"><i class="fas fa-exclamation-triangle"></i> Confirm Restore</h3>
            </div>
            <div class="lupto-modal-body" style="padding:28px 24px;">
                <i class="fas fa-exclamation-triangle" style="font-size:52px; color:#d97706; display:block; margin:0 auto 18px;"></i>
                <p style="font-size:14px; font-weight:700; color:#1e293b; margin:0 0 10px;">Restoring a backup will replace the current database records.</p>
                <p style="font-size:13px; color:#64748b; margin:0;">File: <strong id="picto-restoreFileName" style="color:#1e3a8a;">–</strong></p>
                <p style="font-size:13px; color:#64748b; margin:8px 0 0;">Do you want to continue?</p>
            </div>
            <div class="lupto-modal-footer" style="justify-content:center; gap:12px; background:#f8fafc; padding:16px; display:flex; border-top:1px solid #e2e8f0;">
                <button class="btn-gov btn-gov-secondary" onclick="window.picto_backup.closeRestoreModal()">Cancel</button>
                <button class="btn-gov" style="background:#d97706; border-color:#d97706;" onclick="window.picto_backup.confirmRestore()">
                    <i class="fas fa-undo-alt"></i> Restore
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="lupto-modal-overlay" id="picto-deleteModal" style="display:none; z-index:10000;">
        <div class="lupto-modal-content" style="max-width:420px; text-align:center;">
            <div class="lupto-modal-header" style="background:#dc2626;">
                <h3 class="lupto-modal-title"><i class="fas fa-trash-alt"></i> Delete Backup</h3>
            </div>
            <div class="lupto-modal-body" style="padding:28px 24px;">
                <i class="fas fa-trash-alt" style="font-size:52px; color:#dc2626; display:block; margin:0 auto 18px;"></i>
                <p style="font-size:14px; font-weight:700; color:#1e293b; margin:0 0 10px;">Are you sure you want to delete this backup file?</p>
                <p style="font-size:13px; color:#64748b; margin:0;">File: <strong id="picto-deleteFileName" style="color:#dc2626;">–</strong></p>
                <p style="font-size:12px; color:#94a3b8; margin:8px 0 0;">This action cannot be undone.</p>
            </div>
            <div class="lupto-modal-footer" style="justify-content:center; gap:12px; background:#f8fafc; padding:16px; display:flex; border-top:1px solid #e2e8f0;">
                <button class="btn-gov btn-gov-secondary" onclick="window.picto_backup.closeDeleteModal()">Cancel</button>
                <button class="btn-gov" style="background:#dc2626; border-color:#dc2626;" onclick="window.picto_backup.confirmDelete()">
                    <i class="fas fa-trash-alt"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="picto-backupLoadingOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:99999; align-items:center; justify-content:center; flex-direction:column; gap:16px;">
        <div style="background:#fff; border-radius:16px; padding:36px 48px; text-align:center; box-shadow:0 20px 60px rgba(0,0,0,.3);">
            <i class="fas fa-spinner fa-spin" style="font-size:40px; color:#3b82f6; display:block; margin-bottom:16px;"></i>
            <p id="picto-backupLoadingText" style="font-size:15px; font-weight:700; color:#1e293b; margin:0;">Processing...</p>
            <p style="font-size:12px; color:#64748b; margin:6px 0 0;">Please wait, do not close this page.</p>
        </div>
    </div>

    <!-- Password Changed Success Modal -->
    <div class="lupto-modal-overlay" id="firstTimeSuccessModal" style="display:none; z-index: 9999;">
        <div class="lupto-modal-content" style="max-width: 420px; text-align: center;">
            <div class="lupto-modal-header" style="background: #16a34a;">
                <h3 class="lupto-modal-title"><i class="fas fa-check-circle"></i> Password Changed</h3>
            </div>
            <div class="lupto-modal-body" style="padding: 24px;">
                <i class="fas fa-check-circle" style="font-size: 56px; color: #16a34a; display: block; margin: 12px auto 20px;"></i>
                <p style="font-size: 14px; font-weight: 700; color: #1e293b; margin: 0 0 8px;">
                    Password Changed Successfully
                </p>
                <p style="font-size: 13px; color: #64748b; line-height: 1.5; margin: 0;">
                    Your password has been updated successfully. Please log in again using your new password.
                </p>
            </div>
            <div class="lupto-modal-footer" style="justify-content: center; gap: 12px; background: #f8fafc; padding: 16px; display: flex; border-top: 1px solid #e2e8f0;">
                <button class="btn-gov btn-gov-secondary" style="min-width: 100px;" onclick="closeFirstTimeSuccessModal()">Close</button>
                <button class="btn-gov" style="background: #16a34a; border-color: #16a34a; min-width: 140px; color: #fff;" onclick="window.location.href='../../logout.php'">Log In Again</button>
            </div>
        </div>
    </div>

    <!-- Save Confirmation Modal -->
    <div class="lupto-modal-overlay" id="saveConfirmModal" style="display:none; z-index: 9999;">
        <div class="lupto-modal-content" style="max-width: 420px; text-align: center;">
            <div class="lupto-modal-header" style="background: #1e3a8a;">
                <h3 class="lupto-modal-title"><i class="fas fa-question-circle"></i> Save Confirmation</h3>
            </div>
            <div class="lupto-modal-body" style="padding: 24px;">
                <i class="fas fa-question-circle" style="font-size: 56px; color: #1e3a8a; display: block; margin: 12px auto 20px;"></i>
                <p style="font-size: 14px; font-weight: 700; color: #1e293b; margin: 0 0 8px;">
                    Confirm Changes
                </p>
                <p style="font-size: 13px; color: #64748b; line-height: 1.5; margin: 0;">
                    Are you sure you want to save your new password settings?
                </p>
            </div>
            <div class="lupto-modal-footer" style="justify-content: center; gap: 12px; background: #f8fafc;">
                <button class="btn-gov btn-gov-secondary" onclick="window.closeSaveConfirmModal()">No</button>
                <button class="btn-gov" style="background: #1e3a8a; border-color: #1e3a8a;" onclick="window.confirmUpdateSecuritySettings()">Yes</button>
            </div>
        </div>
    </div>

    
  
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
