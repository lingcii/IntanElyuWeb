<?php

//  Shared System Settings page — all roles.
//   The full settings HTML is identical across LUPTO, PICTO, and Municipal.
//   The role-specific settings JS is loaded via $settingsScriptPath set by the calling stub.
 
require_once __DIR__ . '/../session-bridge.php';

// Allow the relevant roles — guard normalises $userRole
$allowedRoles  = ['lupto', 'picto', 'municipal'];
$loginRedirect = '../login.php';
require_once __DIR__ . '/_role_guard.php';

if (!isset($pageTitle)) {
    $pageTitle = strtoupper($userRole) . ' Settings';
}

// Detect first-login forced redirect from session-bridge.php
$isFirstLogin = isset($_GET['first_login']) && $_GET['first_login'] === '1';

$settingsScriptPath = '../scripts/functions/settings-api.js';

$extraHeadContent = '
    <script>window.userRole = "' . htmlspecialchars($userRole ?? 'lupto') . '";</script>
    <script>window.isFirstLogin = ' . ($isFirstLogin ? 'true' : 'false') . ';</script>
    <script>window.isPicto = ' . ($userRole === 'picto' ? 'true' : 'false') . ';</script>
    <script src="../scripts/api-config.js"></script>
    <link rel="stylesheet" href="../css/settings.css">
    <script src="' . $settingsScriptPath . '" defer></script>
';

ob_start();
?>
    <h2 class="section-title">System Settings</h2>

    <?php if ($userRole === 'picto'): ?>
    <!-- ── Maintenance Mode Active Banner (PICTO-only, shown only when active) ── -->
    <div id="maintenanceActiveBanner" style="display:none;
        background:linear-gradient(135deg,#eff6ff,#dbeafe);
        border:2px solid #3b82f6;
        border-radius:12px;
        padding:16px 22px;
        margin-bottom:18px;
        align-items:center;
        gap:14px;
        box-shadow:0 4px 16px rgba(30,58,138,0.15);
    ">
        <div style="width:40px;height:40px;background:#1e3a8a;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fas fa-tools" style="color:#fff;font-size:16px;"></i>
        </div>
        <div style="flex:1;">
            <strong style="color:#1e3a8a;font-size:14px;"><i class="fas fa-shield-alt" style="color:#2563eb;margin-right:4px;"></i> Maintenance Mode is ACTIVE</strong>
            <p style="margin:2px 0 0;color:#1e40af;font-size:12px;">
                LUPTO and Municipal users are currently restricted. Activated by <span id="maintenanceActivatedBy" style="font-weight:700;">—</span>
                at <span id="maintenanceActivatedAt" style="font-weight:700;">—</span>.
            </p>
        </div>
        <button onclick="window.maintenanceMode.showDeactivateModal()" style="
            background:#dc2626;color:#fff;border:none;border-radius:8px;
            padding:8px 18px;font-size:13px;font-weight:700;cursor:pointer;
            display:flex;align-items:center;gap:7px;flex-shrink:0;
            transition:background .2s;
        " onmouseover="this.style.background='#b91c1c'" onmouseout="this.style.background='#dc2626'">
            <i class="fas fa-power-off"></i> Close Maintenance
        </button>
    </div>
    <?php endif; ?>

    <?php if ($isFirstLogin): ?>
    <!-- ── First-Login Mandatory Banner ─────────────────────────────────── -->
    <div id="firstLoginBanner" style="
        background: linear-gradient(135deg,#FEF3C7,#FDE68A);
        border: 2px solid #F59E0B;
        border-radius: 12px;
        padding: 18px 24px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 16px;
        box-shadow: 0 4px 16px rgba(245,158,11,0.18);
    ">
        <div style="width:44px;height:44px;background:#F59E0B;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fas fa-key" style="color:#fff;font-size:18px;"></i>
        </div>
        <div>
            <h3 style="margin:0 0 6px;color:#92400E;font-size:15px;font-weight:700;">
                Action Required: Change Your Password
            </h3>
            <p style="margin:0;color:#78350F;font-size:13px;line-height:1.6;">
                Welcome! Your account was set up with a temporary default password.
                <strong>You must change your password before accessing the system.</strong>
                Your account will be automatically activated once you complete this step.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:16px;">
        <!-- General Settings -->
        <div class="card" <?php if ($isFirstLogin): ?>style="opacity:0.5; pointer-events:none;" title="Complete your password change first"<?php endif; ?>>
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-cog"></i> General Settings</h3>
            </div>
            <div class="card-body">
                <div class="lupto-form-group">
                    <label>System Name</label>
                    <input type="text" class="filter-select" style="width:100%;" value="<?= htmlspecialchars(strtoupper($userRole)) ?> - La Union Tourism System">
                </div>
                <div class="lupto-form-group">
                    <label>Contact Email</label>
                    <input type="email" class="filter-select" style="width:100%;" value="<?= strtolower($userRole) ?>@launion.gov.ph">
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

        <!-- Security Settings (Column 2) — highlighted when first login -->
        <div class="card" id="securitySettingsCard" <?php if ($isFirstLogin): ?>style="border:2px solid #F59E0B;box-shadow:0 0 0 4px rgba(245,158,11,0.12);"<?php endif; ?>>
            <div class="card-header" <?php if ($isFirstLogin): ?>style="background:linear-gradient(135deg,#FFFBEB,#FEF3C7);"<?php endif; ?>>
                <h3 class="card-title">
                    <i class="fas fa-shield-alt"></i> Security Settings
                    <?php if ($isFirstLogin): ?>
                    <span style="margin-left:10px;background:#F59E0B;color:#fff;font-size:11px;padding:2px 10px;border-radius:20px;font-weight:700;vertical-align:middle;">REQUIRED</span>
                    <?php endif; ?>
                </h3>
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
                <button class="btn-gov" onclick="window.showSaveConfirmModal()">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>

        <!-- Backup Settings — Full Width -->
        <?php if ($userRole === 'picto'): ?>
        <!-- ── Maintenance Mode Card (PICTO-only) ─────────────────────────── -->
        <div class="card maintenance-mode-card" id="maintenanceModeCard" style="grid-column:1/-1;">
            <div class="card-header" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;padding:16px 24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                <h3 class="card-title" style="font-size:15px;font-weight:700;color:#1e3a8a;margin:0;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-tools" style="color:#2563eb;"></i> Maintenance Mode
                </h3>
                <div style="display:flex;align-items:center;gap:10px;">
                    <span id="maintenanceStatusBadge" class="maintenance-status-badge maintenance-status-active">
                        <span class="maintenance-badge-dot"></span>
                        <span id="maintenanceStatusText">Loading...</span>
                    </span>
                    <span style="font-size:12px;color:#1e3a8a;background:#eff6ff;padding:4px 12px;border-radius:20px;font-weight:600;border:1px solid #bfdbfe;">
                        <i class="fas fa-lock" style="color:#2563eb;margin-right:4px;"></i>PICTO Only
                    </span>
                </div>
            </div>
            <div class="card-body" style="padding:24px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
                    <!-- Left: description -->
                    <div>
                        <h4 style="font-size:14px;font-weight:700;color:#1e293b;margin:0 0 10px;">System-Wide Maintenance Control</h4>
                        <p style="font-size:13px;color:#64748b;line-height:1.7;margin:0 0 14px;">
                            Activating maintenance mode will <strong>temporarily restrict access</strong> for
                            all <strong>LUPTO and Municipal</strong> users across the entire system —
                            including dashboards, modules, CRUD functions, reports, and maps.
                        </p>
                        <ul style="font-size:12px;color:#64748b;padding-left:18px;line-height:2;margin:0;">
                            <li>User sessions are preserved (no forced logout)</li>
                            <li>PICTO retains full system access</li>
                            <li>Blocked users see a professional maintenance screen</li>
                            <li>State persists even after browser refresh</li>
                        </ul>
                    </div>
                    <!-- Right: action -->
                    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;background:#f8fafc;border-radius:14px;border:1px solid #e2e8f0;padding:24px;">
                        <div id="maintenanceActionIcon" style="width:64px;height:64px;border-radius:50%;background:#eff6ff;display:flex;align-items:center;justify-content:center;">
                            <i id="maintenanceActionIconI" class="fas fa-check-circle" style="font-size:28px;color:#16a34a;"></i>
                        </div>
                        <div style="text-align:center;">
                            <p id="maintenanceActionLabel" style="font-size:13px;font-weight:700;color:#1e293b;margin:0 0 4px;">System Active</p>
                            <p id="maintenanceActionSub" style="font-size:11px;color:#64748b;margin:0;">All users have normal access</p>
                        </div>
                        <button id="maintenancePrimaryBtn" class="btn-gov btn-maintenance-activate"
                            onclick="window.maintenanceMode.showActivateModal()"
                            style="width:100%;justify-content:center;font-size:14px;padding:11px 20px;">
                            <i class="fas fa-tools"></i>
                            <span id="maintenanceBtnText">Activate Maintenance Mode</span>
                        </button>
                        <p id="maintenanceLastUpdated" style="font-size:11px;color:#94a3b8;margin:0;text-align:center;"></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Backup Settings — Full Width -->
        <div class="card" id="backupSettingsCard" style="grid-column:1/-1;">
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
                <div id="backupStatsRow" style="display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:24px;">
                    <div class="backup-stat-tile" id="statTotal">
                        <div class="bst-icon" style="background:#eff6ff;"><i class="fas fa-database" style="color:#3b82f6;"></i></div>
                        <div class="bst-body">
                            <div class="bst-value" id="statTotalVal">–</div>
                            <div class="bst-label">Total Backups</div>
                        </div>
                    </div>
                    <div class="backup-stat-tile" id="statLast">
                        <div class="bst-icon" style="background:#f0fdf4;"><i class="fas fa-clock" style="color:#16a34a;"></i></div>
                        <div class="bst-body">
                            <div class="bst-value" id="statLastVal" style="font-size:12px;">–</div>
                            <div class="bst-label">Last Backup</div>
                        </div>
                    </div>
                    <div class="backup-stat-tile" id="statDbSize">
                        <div class="bst-icon" style="background:#fdf4ff;"><i class="fas fa-hdd" style="color:#9333ea;"></i></div>
                        <div class="bst-body">
                            <div class="bst-value" id="statDbSizeVal">–</div>
                            <div class="bst-label">Database Size</div>
                        </div>
                    </div>
                    <div class="backup-stat-tile" id="statStatus">
                        <div class="bst-icon" style="background:#f0fdf4;"><i class="fas fa-check-circle" style="color:#16a34a;"></i></div>
                        <div class="bst-body">
                            <div class="bst-value" id="statStatusVal" style="color:#16a34a;">–</div>
                            <div class="bst-label">Status</div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; align-items:center;">
                    <button id="lupto-btnCreateBackup" class="btn-gov" onclick="window.lupto_backup.create()" style="display:inline-flex; align-items:center; gap:8px; min-width:160px; justify-content:center;">
                        <i class="fas fa-download"></i> Create Backup
                    </button>
                    <button id="lupto-btnRestoreUpload" class="btn-gov btn-gov-secondary" onclick="document.getElementById('lupto-backupFileInput').click()" style="display:inline-flex; align-items:center; gap:8px; min-width:160px; justify-content:center;">
                        <i class="fas fa-upload"></i> Restore Backup
                    </button>
                    <input type="file" id="lupto-backupFileInput" accept=".sql" style="display:none;" onchange="window.lupto_backup.uploadRestore(this)">
                </div>

                <!-- Recent Backups Table -->
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                    <h4 style="margin:0; font-size:14px; font-weight:700; color:#1e293b;">Recent Backups</h4>
                    <button onclick="window.lupto_backup.loadList()" style="background:none; border:none; color:#3b82f6; font-size:12px; cursor:pointer; display:flex; align-items:center; gap:5px; font-weight:600;">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
                <div style="overflow-x:auto; border-radius:10px; border:1px solid #e2e8f0;">
                    <table class="data-table" style="margin:0;">
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <th>Backup Date &amp; Time</th>
                                <th>File Size</th>
                                <th style="text-align:center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="lupto-backupTableBody">
                            <tr><td colspan="4" style="text-align:center; padding:24px; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i> Loading backups...</td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- Backup Action Logs -->
                <div style="margin-top:24px;">
                    <h4 style="margin:0 0 10px; font-size:14px; font-weight:700; color:#1e293b; display:flex; align-items:center; gap:7px;">
                        <i class="fas fa-history" style="color:#64748b;"></i> Backup Action Logs
                    </h4>
                    <div id="lupto-backupLogsContainer" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 16px; font-size:13px; color:#475569; max-height:220px; overflow-y:auto;">
                        <div style="text-align:center; padding:16px; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i> Loading logs...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Restore Confirmation Modal -->
        <div class="lupto-modal-overlay" id="lupto-restoreModal" style="display:none; z-index:10000;">
            <div class="lupto-modal-content" style="max-width:440px; text-align:center;">
                <div class="lupto-modal-header" style="background:#d97706;">
                    <h3 class="lupto-modal-title"><i class="fas fa-exclamation-triangle"></i> Confirm Restore</h3>
                </div>
                <div class="lupto-modal-body" style="padding:28px 24px;">
                    <i class="fas fa-exclamation-triangle" style="font-size:52px; color:#d97706; display:block; margin:0 auto 18px;"></i>
                    <p style="font-size:14px; font-weight:700; color:#1e293b; margin:0 0 10px;">Restoring a backup will replace the current database records.</p>
                    <p style="font-size:13px; color:#64748b; margin:0;">File: <strong id="lupto-restoreFileName" style="color:#1e3a8a;">–</strong></p>
                    <p style="font-size:13px; color:#64748b; margin:8px 0 0;">Do you want to continue?</p>
                </div>
                <div class="lupto-modal-footer" style="justify-content:center; gap:12px; background:#f8fafc; padding:16px; display:flex; border-top:1px solid #e2e8f0;">
                    <button class="btn-gov btn-gov-secondary" onclick="window.lupto_backup.closeRestoreModal()">Cancel</button>
                    <button class="btn-gov" style="background:#d97706; border-color:#d97706;" onclick="window.lupto_backup.confirmRestore()">
                        <i class="fas fa-undo-alt"></i> Restore
                    </button>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="lupto-modal-overlay" id="lupto-deleteModal" style="display:none; z-index:10000;">
            <div class="lupto-modal-content" style="max-width:420px; text-align:center;">
                <div class="lupto-modal-header" style="background:#dc2626;">
                    <h3 class="lupto-modal-title"><i class="fas fa-trash-alt"></i> Delete Backup</h3>
                </div>
                <div class="lupto-modal-body" style="padding:28px 24px;">
                    <i class="fas fa-trash-alt" style="font-size:52px; color:#dc2626; display:block; margin:0 auto 18px;"></i>
                    <p style="font-size:14px; font-weight:700; color:#1e293b; margin:0 0 10px;">Are you sure you want to delete this backup file?</p>
                    <p style="font-size:13px; color:#64748b; margin:0;">File: <strong id="lupto-deleteFileName" style="color:#dc2626;">–</strong></p>
                    <p style="font-size:12px; color:#94a3b8; margin:8px 0 0;">This action cannot be undone.</p>
                </div>
                <div class="lupto-modal-footer" style="justify-content:center; gap:12px; background:#f8fafc; padding:16px; display:flex; border-top:1px solid #e2e8f0;">
                    <button class="btn-gov btn-gov-secondary" onclick="window.lupto_backup.closeDeleteModal()">Cancel</button>
                    <button class="btn-gov" style="background:#dc2626; border-color:#dc2626;" onclick="window.lupto_backup.confirmDelete()">
                        <i class="fas fa-trash-alt"></i> Delete
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div id="lupto-backupLoadingOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:99999; align-items:center; justify-content:center; flex-direction:column; gap:16px;">
            <div style="background:#fff; border-radius:16px; padding:36px 48px; text-align:center; box-shadow:0 20px 60px rgba(0,0,0,.3);">
                <i class="fas fa-spinner fa-spin" style="font-size:40px; color:#3b82f6; display:block; margin-bottom:16px;"></i>
                <p id="lupto-backupLoadingText" style="font-size:15px; font-weight:700; color:#1e293b; margin:0;">Processing...</p>
                <p style="font-size:12px; color:#64748b; margin:6px 0 0;">Please wait, do not close this page.</p>
            </div>
        </div>
    </div>

    <!-- Password Changed Success Modal -->
    <div class="lupto-modal-overlay" id="firstTimeSuccessModal" style="display:none; z-index: 9999;">
        <div class="lupto-modal-content" style="max-width: 440px; text-align: center;">
            <div class="lupto-modal-header" style="background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); padding: 20px 24px;">
                <h3 class="lupto-modal-title" style="font-size: 16px; letter-spacing: 0.3px;">
                    <i class="fas fa-check-circle"></i> Password Changed Successfully
                </h3>
            </div>
            <div class="lupto-modal-body" style="padding: 32px 28px 24px;">
                <div style="width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg, #dcfce7, #bbf7d0); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; box-shadow: 0 4px 16px rgba(22,163,74,0.2);">
                    <i class="fas fa-lock" style="font-size: 32px; color: #16a34a;"></i>
                </div>
                <p style="font-size: 15px; font-weight: 700; color: #1e293b; margin: 0 0 10px;">Your password has been updated!</p>
                <p style="font-size: 13px; color: #64748b; line-height: 1.6; margin: 0;">
                    For security, please log in again using your new password.
                    <br>Would you like to go to the login page now?
                </p>
            </div>
            <div class="lupto-modal-footer" style="justify-content: center; gap: 12px; background: #f8fafc; padding: 18px 24px; display: flex; border-top: 1px solid #e2e8f0;">
                <button class="btn-gov btn-gov-secondary" style="min-width: 130px; display:flex; align-items:center; gap:6px; justify-content:center;" onclick="window.closePasswordSuccessModal()">
                    <i class="fas fa-times"></i> No, Stay Here
                </button>
                <button class="btn-gov" style="background: linear-gradient(135deg, #16a34a, #15803d); border-color: #15803d; min-width: 140px; color: #fff; display:flex; align-items:center; gap:6px; justify-content:center;" onclick="window.location.href='../logout.php'">
                    <i class="fas fa-sign-in-alt"></i> Yes, Login Now
                </button>
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
                <p style="font-size: 14px; font-weight: 700; color: #1e293b; margin: 0 0 8px;">Confirm Changes</p>
                <p style="font-size: 13px; color: #64748b; line-height: 1.5; margin: 0;">Are you sure you want to save your new password settings?</p>
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
include '../components/sections.php';
