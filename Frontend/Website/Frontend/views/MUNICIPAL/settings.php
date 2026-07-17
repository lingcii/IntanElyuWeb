<?php
require_once __DIR__ . '/../../session-bridge.php';
// Check role
if ($_SESSION['user_role'] !== 'municipal' && !str_ends_with($_SESSION['user_role'], '_mto')) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'Municipal Settings';

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
                    <input type="text" class="filter-select" style="width:100%;" value="MUNICIPAL - La Union Municipal Tourism Office">
                </div>
                <div class="lupto-form-group">
                    <label>Contact Email</label>
                    <input type="email" class="filter-select" style="width:100%;" value="municipal@launion.gov.ph">
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

        <!-- Backup Settings -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-database"></i> Backup Settings</h3>
            </div>
            <div class="card-body">
                <p style="margin-bottom:16px; color:var(--text-secondary);">Create or restore database backups.</p>
                <div style="display:flex; gap:8px; margin-bottom:16px;">
                    <button class="btn-gov">
                        <i class="fas fa-download"></i> Create Backup
                    </button>
                    <button class="btn-gov btn-gov-secondary">
                        <i class="fas fa-upload"></i> Restore Backup
                    </button>
                </div>
                <h4 style="margin-bottom:8px;">Recent Backups</h4>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Date</th>
                            <th>Size</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>backup_20260619_120000.sql</td>
                            <td><?= date('M d, Y h:i A') ?></td>
                            <td>2.4 MB</td>
                        </tr>
                        <tr>
                            <td>backup_20260618_120000.sql</td>
                            <td><?= date('M d, Y', strtotime('-1 day')) ?></td>
                            <td>2.3 MB</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Security Settings -->
        <style>
            .password-input-wrapper {
                position: relative;
                display: flex;
                align-items: center;
                width: 100%;
            }
            .password-input-wrapper input {
                padding-right: 40px !important;
            }
            .password-toggle-btn {
                position: absolute;
                right: 8px;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                color: #94a3b8;
                cursor: pointer;
                font-size: 14px;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 6px;
                border-radius: 50%;
                transition: all 0.2s ease;
            }
            .password-toggle-btn:hover {
                color: #1e3a8a;
                background-color: #f1f5f9;
            }
        </style>
        <div class="card" style="grid-column:1/-1; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <div class="card-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 16px 24px;">
                <h3 class="card-title" style="font-size: 15px; font-weight: 700; color: #1e3a8a; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-shield-alt" style="color: #3b82f6;"></i> Security Settings
                </h3>
            </div>
            <div class="card-body" style="padding: 24px;">
                <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:20px; margin-bottom: 24px;">
                    
                    <div class="lupto-form-group" style="display: flex; flex-direction: column; gap: 6px;">
                        <label style="font-size: 13px; font-weight: 600; color: #475569;">Current Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="currentPassword" class="filter-select" style="width:100%; height: 40px; box-sizing: border-box;" placeholder="Enter current password">
                            <button type="button" class="password-toggle-btn" onclick="window.togglePasswordVisibility('currentPassword', this)" aria-label="Toggle password visibility">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="lupto-form-group" style="display: flex; flex-direction: column; gap: 6px;">
                        <label style="font-size: 13px; font-weight: 600; color: #475569;">New Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="newPassword" class="filter-select" style="width:100%; height: 40px; box-sizing: border-box;" placeholder="Enter new password (min. 6 chars)">
                            <button type="button" class="password-toggle-btn" onclick="window.togglePasswordVisibility('newPassword', this)" aria-label="Toggle password visibility">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="lupto-form-group" style="display: flex; flex-direction: column; gap: 6px;">
                        <label style="font-size: 13px; font-weight: 600; color: #475569;">Confirm New Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="confirmPassword" class="filter-select" style="width:100%; height: 40px; box-sizing: border-box;" placeholder="Confirm new password">
                            <button type="button" class="password-toggle-btn" onclick="window.togglePasswordVisibility('confirmPassword', this)" aria-label="Toggle password visibility">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="lupto-form-group" style="display: flex; flex-direction: column; gap: 6px;">
                        <label style="font-size: 13px; font-weight: 600; color: #475569;">Session Timeout (minutes)</label>
                        <input type="number" class="filter-select" style="width:100%; height: 40px; box-sizing: border-box;" value="30" placeholder="Session timeout in minutes">
                    </div>
                    
                </div>
                <button class="btn-gov" style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); border: none; padding: 10px 20px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; border-radius: 6px; color: #ffffff; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 6px -1px rgba(30, 58, 138, 0.2);" onclick="window.showSaveConfirmModal()">
                    <i class="fas fa-save"></i> Update Security Settings
                </button>
            </div>
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

    <script>
        window.togglePasswordVisibility = function(id, btn) {
            const input = document.getElementById(id);
            if (!input) return;
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        };

        window.closeFirstTimeSuccessModal = function() {
            const modal = document.getElementById('firstTimeSuccessModal');
            if (modal) modal.style.display = 'none';
            window.MUST_CHANGE_PASSWORD = false;
            
            // Sync with backend PHP session to clear MUST_CHANGE_PASSWORD restriction
            const syncUrl = new URL('../../sync-session.php', window.location.href).href;
            fetch(syncUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ clear_must_change_password: true })
            }).catch(err => console.error(err));
        };

        window.focusSecuritySettings = function() {
            const modal = document.getElementById('globalFirstTimeLoginModal');
            if (modal) modal.style.display = 'none';
            const currentPwd = document.getElementById('currentPassword');
            if (currentPwd) {
                currentPwd.focus();
                currentPwd.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        };

        window.showSaveConfirmModal = function() {
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (!currentPassword || !newPassword || !confirmPassword) {
                alert('Please fill in all password fields.');
                return;
            }

            if (newPassword !== confirmPassword) {
                alert('New password and confirmation do not match.');
                return;
            }

            if (newPassword.length < 6) {
                alert('New password must be at least 6 characters.');
                return;
            }

            const modal = document.getElementById('saveConfirmModal');
            if (modal) modal.style.display = 'flex';
        };

        window.closeSaveConfirmModal = function() {
            const modal = document.getElementById('saveConfirmModal');
            if (modal) modal.style.display = 'none';
        };

        window.confirmUpdateSecuritySettings = async function() {
            window.closeSaveConfirmModal();

            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            const updateBtn = document.querySelector('button[onclick="window.showSaveConfirmModal()"]') || document.querySelector('.btn-gov[onclick*="showSaveConfirmModal"]');
            const originalText = updateBtn ? updateBtn.innerHTML : '';

            /* --- Optimistic UI: show success immediately --- */
            if (updateBtn) {
                updateBtn.disabled = true;
                updateBtn.style.background = 'linear-gradient(135deg,#15803d 0%,#22c55e 100%)';
                updateBtn.innerHTML = '<i class="fas fa-check"></i> Saving...';
            }
            document.getElementById('currentPassword').value = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';

            /* --- Show inline toast --- */
            var toast = document.getElementById('municipalSaveToast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'municipalSaveToast';
                toast.style.cssText = 'position:fixed;bottom:28px;right:28px;background:#15803d;color:#fff;padding:12px 22px;border-radius:10px;font-size:14px;font-weight:600;box-shadow:0 6px 20px rgba(0,0,0,.2);z-index:99999;display:flex;align-items:center;gap:10px;opacity:0;transition:opacity .3s';
                toast.innerHTML = '<i class="fas fa-check-circle"></i> Password updated successfully!';
                document.body.appendChild(toast);
            }
            toast.style.opacity = '1';

            /* --- Background API call --- */
            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 8000);

                const baseUrl = window.API_CONFIG?.BASE_URL || ('http://' + (window.location.hostname || '127.0.0.1') + ':8000');
                const resp = await fetch(baseUrl + '/api/municipal/settings/password', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    credentials: 'include',
                    signal: controller.signal,
                    body: JSON.stringify({
                        current_password: currentPassword,
                        new_password: newPassword,
                        new_password_confirmation: confirmPassword
                    })
                });
                clearTimeout(timeoutId);

                const data = await resp.json();

                if (!resp.ok || data.error) {
                    /* Rollback optimistic UI on error */
                    toast.style.background = '#dc2626';
                    toast.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + (data.error || data.message || 'Failed to update password.');
                    if (updateBtn) {
                        updateBtn.disabled = false;
                        updateBtn.style.background = '';
                        updateBtn.innerHTML = originalText;
                    }
                } else if (data.first_time_reset || window.MUST_CHANGE_PASSWORD) {
                    /* First-time password reset — show success modal then logout */
                    const successModal = document.getElementById('firstTimeSuccessModal');
                    if (successModal) {
                        successModal.style.display = 'flex';
                    } else {
                        window.location.href = '../../logout.php';
                    }
                } else {
                    /* Normal save — restore button */
                    if (updateBtn) {
                        updateBtn.disabled = false;
                        updateBtn.style.background = '';
                        updateBtn.innerHTML = originalText;
                    }
                }
            } catch (err) {
                if (err.name !== 'AbortError') {
                    toast.style.background = '#dc2626';
                    toast.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + err.message;
                }
                if (updateBtn) {
                    updateBtn.disabled = false;
                    updateBtn.style.background = '';
                    updateBtn.innerHTML = originalText;
                }
            } finally {
                setTimeout(() => { toast.style.opacity = '0'; }, 3500);
            }
        };
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
