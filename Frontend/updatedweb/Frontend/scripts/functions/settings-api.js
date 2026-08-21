/* ============================================================
   LUPTO Settings Page — External JS
   scripts/functions/LUPTO/settings-api.js
   ============================================================ */

// ── Backup Module ─────────────────────────────────────────────
(function(){
    function getRolePrefix() {
        const role = (window.userRole || document.body?.dataset?.role || document.querySelector('meta[name="user-role"]')?.content || '').toLowerCase();
        const path = (window.location.pathname || '').toUpperCase();
        if (role === 'picto' || role === 'pitco' || path.includes('PICTO')) return 'pitco';
        if (role === 'municipal' || role.endsWith('_mto') || path.includes('MUNICIPAL')) return 'municipal';
        return 'lupto';
    }

    function getBackupApi() {
        const BASE = (window.API_CONFIG?.BASE_URL || ('http://' + (window.location.hostname || '127.0.0.1') + ':8000'));
        return BASE + '/api/' + getRolePrefix() + '/settings/backup';
    }

    let _restoreTarget = null;
    let _deleteTarget  = null;

    function showLoading(text) {
        const textEl = document.getElementById('lupto-backupLoadingText');
        if (textEl) textEl.textContent = text || 'Processing...';
        const overlay = document.getElementById('lupto-backupLoadingOverlay');
        if (overlay) overlay.style.display = 'flex';
        ['lupto-btnCreateBackup','lupto-btnRestoreUpload'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = true;
        });
    }
    function hideLoading() {
        const overlay = document.getElementById('lupto-backupLoadingOverlay');
        if (overlay) overlay.style.display = 'none';
        ['lupto-btnCreateBackup','lupto-btnRestoreUpload'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = false;
        });
    }
    function showToast(msg, type) {
        let t = document.getElementById('lupto-backupToast');
        if (!t) {
            t = document.createElement('div');
            t.id = 'lupto-backupToast';
            t.style.cssText = 'position:fixed;bottom:28px;right:28px;padding:13px 22px;border-radius:10px;font-size:14px;font-weight:600;box-shadow:0 6px 24px rgba(0,0,0,.18);z-index:999999;display:flex;align-items:center;gap:10px;opacity:0;transition:opacity .3s;color:#fff;max-width:360px;';
            document.body.appendChild(t);
        }
        const icon = type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle';
        t.style.background = type === 'error' ? '#dc2626' : '#16a34a';
        t.innerHTML = `<i class="fas ${icon}"></i> ${msg}`;
        t.style.opacity = '1';
        clearTimeout(t._tid);
        t._tid = setTimeout(() => { t.style.opacity = '0'; }, 4000);
    }

    async function apiFetch(method, url, body, isFile) {
        const opts = { method, credentials: 'include', headers: { 'Accept': 'application/json' } };
        if (body && !isFile) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        } else if (isFile) {
            opts.body = body;
        }
        const r = await fetch(url, opts);
        if (method === 'GET' && url.includes('/download/')) return r;
        return r.json();
    }

    async function loadStats() {
        try {
            const d = await apiFetch('GET', getBackupApi() + '/stats');
            if (d.success) {
                document.getElementById('statTotalVal').textContent = d.total;
                document.getElementById('statLastVal').textContent  = d.last_backup;
                document.getElementById('statDbSizeVal').textContent = d.db_size;
                document.getElementById('statStatusVal').textContent = d.status;
                document.getElementById('statStatusVal').style.color = d.status === 'Healthy' ? '#16a34a' : '#d97706';
            }
        } catch(e) {}
    }

    async function loadList() {
        const tbody = document.getElementById('lupto-backupTableBody');
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px;color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
        try {
            const d = await apiFetch('GET', getBackupApi() + '/list');
            if (!d.success || !d.backups.length) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:24px;color:#94a3b8;"><i class="fas fa-folder-open" style="font-size:28px;display:block;margin-bottom:8px;"></i>No backups found.</td></tr>';
                return;
            }
            tbody.innerHTML = d.backups.map(b => `
                <tr>
                    <td style="font-family:monospace; font-size:13px; color:#1e3a8a; font-weight:600;">
                        <i class="fas fa-file-code" style="color:#3b82f6; margin-right:6px;"></i>${b.filename}
                    </td>
                    <td style="color:#475569; font-size:13px;">${b.date_fmt}</td>
                    <td style="color:#475569; font-size:13px;"><span style="background:#f1f5f9;padding:3px 10px;border-radius:6px;font-weight:600;">${b.size_fmt}</span></td>
                    <td style="text-align:center;">
                        <div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap;">
                            <button onclick="window.lupto_backup.download('${b.filename}')" style="background:#eff6ff;color:#3b82f6;border:1px solid #bfdbfe;border-radius:6px;padding:5px 12px;font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:5px;transition:all .2s;" title="Download">
                                <i class="fas fa-download"></i> Download
                            </button>
                            <button onclick="window.lupto_backup.openRestore('${b.filename}')" style="background:#fff7ed;color:#d97706;border:1px solid #fed7aa;border-radius:6px;padding:5px 12px;font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:5px;transition:all .2s;" title="Restore">
                                <i class="fas fa-undo-alt"></i> Restore
                            </button>
                            <button onclick="window.lupto_backup.openDelete('${b.filename}')" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:6px;padding:5px 12px;font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:5px;transition:all .2s;" title="Delete">
                                <i class="fas fa-trash-alt"></i> Delete
                            </button>
                        </div>
                    </td>
                </tr>`).join('');
        } catch(e) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px;color:#dc2626;"><i class="fas fa-exclamation-circle"></i> Failed to load backups.</td></tr>';
        }
    }

    async function loadLogs() {
        const container = document.getElementById('lupto-backupLogsContainer');
        const BASE = (window.API_CONFIG?.BASE_URL || ('http://' + (window.location.hostname || '127.0.0.1') + ':8000'));
        try {
            const d = await apiFetch('GET', BASE + '/api/' + getRolePrefix() + '/activity-logs?per_page=10&module=Backup');
            const logs = d.data || d.logs || [];
            if (!logs.length) {
                container.innerHTML = '<div style="text-align:center;padding:12px;color:#94a3b8;">No backup actions logged yet.</div>';
                return;
            }
            container.innerHTML = logs.map(l => `
                <div style="display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid #f1f5f9;">
                    <div style="width:34px;height:34px;border-radius:8px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-database" style="color:#3b82f6;font-size:14px;"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:700;color:#1e293b;font-size:13px;">${l.user_name || 'System'}</div>
                        <div style="color:#64748b;font-size:12px;margin-top:1px;">${l.action} — ${l.description || ''}</div>
                    </div>
                    <div style="font-size:11px;color:#94a3b8;white-space:nowrap;">${l.created_at ? new Date(l.created_at).toLocaleString('en-PH',{month:'short',day:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}) : ''}</div>
                </div>`).join('');
        } catch(e) {
            container.innerHTML = '<div style="text-align:center;padding:12px;color:#94a3b8;">Could not load logs.</div>';
        }
    }

    async function create() {
        showLoading('Creating Backup...');
        try {
            const d = await apiFetch('POST', getBackupApi() + '/create');
            if (d.success) {
                showToast(d.message || 'Database backup created successfully.', 'success');
                await loadList(); await loadStats(); await loadLogs();
            } else {
                showToast(d.message || 'Database backup failed.', 'error');
            }
        } catch(e) {
            showToast('Database backup failed. Please try again.', 'error');
        } finally {
            hideLoading();
        }
    }

    function openRestore(fn) {
        _restoreTarget = fn;
        document.getElementById('lupto-restoreFileName').textContent = fn;
        document.getElementById('lupto-restoreModal').style.display = 'flex';
    }
    function closeRestoreModal() {
        document.getElementById('lupto-restoreModal').style.display = 'none';
        _restoreTarget = null;
    }
    function openDelete(fn) {
        _deleteTarget = fn;
        document.getElementById('lupto-deleteFileName').textContent = fn;
        document.getElementById('lupto-deleteModal').style.display = 'flex';
    }
    function closeDeleteModal() {
        document.getElementById('lupto-deleteModal').style.display = 'none';
        _deleteTarget = null;
    }
    async function confirmDelete() {
        if (!_deleteTarget) return;
        closeDeleteModal();
        const fn = _deleteTarget;
        try {
            const d = await apiFetch('DELETE', getBackupApi() + '/' + encodeURIComponent(fn));
            if (d.success) {
                showToast(d.message || 'Backup deleted successfully.', 'success');
                await loadList(); await loadStats(); await loadLogs();
            } else {
                showToast(d.message || 'Delete failed.', 'error');
            }
        } catch(e) {
            showToast('Delete failed. Please try again.', 'error');
        }
    }
    async function download(filename) {
        try {
            const r = await fetch(getBackupApi() + '/download/' + encodeURIComponent(filename), { credentials: 'include' });
            if (!r.ok) { showToast('Download failed.', 'error'); return; }
            const blob = await r.blob();
            const url  = URL.createObjectURL(blob);
            const a    = document.createElement('a');
            a.href = url; a.download = filename; a.click();
            URL.revokeObjectURL(url);
        } catch(e) {
            showToast('Download failed. Please try again.', 'error');
        }
    }
    async function uploadRestore(input) {
        const file = input.files[0]; if (!file) return;
        if (!file.name.endsWith('.sql')) {
            showToast('Invalid SQL file. Only .sql files are allowed.', 'error');
            input.value = ''; return;
        }
        _restoreTarget = file.name;
        window._lupto_uploadFile = file;
        input.value = '';
        document.getElementById('lupto-restoreFileName').textContent = file.name;
        document.getElementById('lupto-restoreModal').style.display = 'flex';
    }

    window.lupto_backup = {
        create, loadList, loadStats, loadLogs,
        openRestore, closeRestoreModal,
        openDelete, closeDeleteModal, confirmDelete,
        download, uploadRestore,
        confirmRestore: async function() {
            closeRestoreModal();
            showLoading('Restoring Database...');
            try {
                let d;
                if (window._lupto_uploadFile) {
                    const fd = new FormData();
                    fd.append('backup_file', window._lupto_uploadFile);
                    window._lupto_uploadFile = null;
                    d = await apiFetch('POST', getBackupApi() + '/restore', fd, true);
                } else {
                    d = await apiFetch('POST', getBackupApi() + '/restore', { filename: _restoreTarget });
                    _restoreTarget = null;
                }
                if (d.success) {
                    showToast(d.message || 'Database restored successfully.', 'success');
                    await loadList(); await loadStats(); await loadLogs();
                } else {
                    showToast(d.message || 'Restore failed.', 'error');
                }
            } catch(e) {
                showToast('Restore failed. Please try again.', 'error');
            } finally {
                hideLoading();
            }
        },
    };

    // Auto-init
    loadStats();
    loadList();
    loadLogs();
})();

// ── Password / Security Settings ──────────────────────────────
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
    const syncUrl = new URL('../../sync-session.php', window.location.href).href;
    fetch(syncUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ clear_must_change_password: true })
    }).catch(err => console.error(err));
};

window.closePasswordSuccessModal = function() {
    const modal = document.getElementById('firstTimeSuccessModal');
    if (modal) modal.style.display = 'none';
    window.MUST_CHANGE_PASSWORD = false;
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
    const newPassword     = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    if (!currentPassword || !newPassword || !confirmPassword) {
        alert('Please fill in all password fields.'); return;
    }
    if (newPassword !== confirmPassword) {
        alert('New password and confirmation do not match.'); return;
    }
    if (newPassword.length < 6) {
        alert('New password must be at least 6 characters.'); return;
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
    const newPassword     = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const updateBtn = document.querySelector('button[onclick="window.showSaveConfirmModal()"]') ||
                      document.querySelector('.btn-gov[onclick*="showSaveConfirmModal"]');
    const originalText = updateBtn ? updateBtn.innerHTML : '';

    if (updateBtn) {
        updateBtn.disabled = true;
        updateBtn.style.background = 'linear-gradient(135deg,#15803d 0%,#22c55e 100%)';
        updateBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Saving...';
    }

    // Ensure error toast exists
    var toast = document.getElementById('luptoSaveToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'luptoSaveToast';
        toast.style.cssText = 'position:fixed;bottom:28px;right:28px;padding:12px 22px;border-radius:10px;font-size:14px;font-weight:600;box-shadow:0 6px 20px rgba(0,0,0,.2);z-index:99999;display:flex;align-items:center;gap:10px;opacity:0;transition:opacity .3s;color:#fff;';
        document.body.appendChild(toast);
    }

    try {
        // Local helper — getRolePrefix() from the backup IIFE is not in scope here
        function getLocalRolePrefix() {
            const role = (window.userRole || document.body?.dataset?.role || document.querySelector('meta[name="user-role"]')?.content || '').toLowerCase();
            const path = (window.location.pathname || '').toUpperCase();
            if (role === 'picto' || role === 'pitco' || path.includes('PICTO')) return 'pitco';
            if (role === 'municipal' || role.endsWith('_mto') || path.includes('MUNICIPAL')) return 'municipal';
            return 'lupto';
        }

        const controller = new AbortController();
        const timeoutId  = setTimeout(() => controller.abort(), 8000);
        const baseUrl = window.API_CONFIG?.BASE_URL || ('http://' + (window.location.hostname || '127.0.0.1') + ':8000');
        const resp = await fetch(baseUrl + '/api/' + getLocalRolePrefix() + '/settings/password', {
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
            // Show error toast
            toast.style.background = '#dc2626';
            toast.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + (data.error || data.message || 'Failed to update password.');
            toast.style.opacity = '1';
            setTimeout(() => { toast.style.opacity = '0'; }, 4000);
            if (updateBtn) { updateBtn.disabled = false; updateBtn.style.background = ''; updateBtn.innerHTML = originalText; }
        } else {
            // SUCCESS
            document.getElementById('currentPassword').value = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
            if (updateBtn) { updateBtn.disabled = false; updateBtn.style.background = ''; updateBtn.innerHTML = originalText; }

            const isFirst = data.first_time_reset || window.isFirstLogin;

            if (isFirst) {
                // Clear session flags in PHP session
                try {
                    await fetch('../sync-session.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ clear_must_change_password: true })
                    });
                } catch (e) {}

                toast.style.background = '#16a34a';
                toast.innerHTML = '<i class="fas fa-check-circle"></i> Password updated! Your account is now <strong>ACTIVE</strong>. Redirecting...';
                toast.style.opacity = '1';

                const banner = document.getElementById('firstLoginBanner');
                if (banner) {
                    banner.style.background = 'linear-gradient(135deg, #DCFCE7, #BBF7D0)';
                    banner.style.borderColor = '#16A34A';
                    banner.innerHTML = `
                        <div style="width:44px;height:44px;background:#16A34A;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas fa-check" style="color:#fff;font-size:18px;"></i>
                        </div>
                        <div>
                            <h3 style="margin:0 0 6px;color:#14532D;font-size:15px;font-weight:700;">Account Activated Successfully!</h3>
                            <p style="margin:0;color:#166534;font-size:13px;">Your password has been set. Redirecting you to the dashboard...</p>
                        </div>
                    `;
                }

                setTimeout(() => {
                    window.location.href = 'dashboard.php';
                }, 1800);

            } else {
                const successModal = document.getElementById('firstTimeSuccessModal');
                if (successModal) {
                    successModal.style.display = 'flex';
                } else {
                    toast.style.background = '#16a34a';
                    toast.innerHTML = '<i class="fas fa-check-circle"></i> Password updated successfully!';
                    toast.style.opacity = '1';
                    setTimeout(() => { toast.style.opacity = '0'; }, 4000);
                }
            }
        }
    } catch (err) {
        if (err.name !== 'AbortError') {
            toast.style.background = '#dc2626';
            toast.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + err.message;
            toast.style.opacity = '1';
            setTimeout(() => { toast.style.opacity = '0'; }, 4000);
        }
        if (updateBtn) { updateBtn.disabled = false; updateBtn.style.background = ''; updateBtn.innerHTML = originalText; }
    }
};

// Auto-scroll and focus currentPassword if first_login
if (window.isFirstLogin) {
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            const cp = document.getElementById('currentPassword');
            if (cp) {
                cp.focus();
                cp.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, 300);
    });
}


// ── Maintenance Mode Module (PICTO-only) ───────────────────────────────────
(function () {
    if (!window.isPicto) return; // Only runs for PICTO

    function getApiBase() {
        return (window.API_CONFIG?.BASE_URL || ('http://' + (window.location.hostname || '127.0.0.1') + ':8000'));
    }

    function showToast(msg, type) {
        let t = document.getElementById('maintenanceToast');
        if (!t) {
            t = document.createElement('div');
            t.id = 'maintenanceToast';
            t.style.cssText = 'position:fixed;bottom:28px;left:50%;transform:translateX(-50%);padding:13px 26px;border-radius:10px;font-size:14px;font-weight:600;box-shadow:0 6px 24px rgba(0,0,0,.2);z-index:999999;display:flex;align-items:center;gap:10px;opacity:0;transition:opacity .3s;color:#fff;min-width:300px;justify-content:center;';
            document.body.appendChild(t);
        }
        const icon = type === 'error' ? 'fa-exclamation-circle' : (type === 'warning' ? 'fa-exclamation-triangle' : 'fa-check-circle');
        const bg   = type === 'error' ? '#dc2626' : (type === 'warning' ? '#d97706' : '#16a34a');
        t.style.background = bg;
        t.innerHTML = `<i class="fas ${icon}"></i> ${msg}`;
        t.style.opacity = '1';
        clearTimeout(t._tid);
        t._tid = setTimeout(() => { t.style.opacity = '0'; }, 4500);
    }

    async function apiFetch(method, endpoint, body) {
        const url = getApiBase() + endpoint;
        const opts = {
            method,
            credentials: 'include',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' }
        };
        if (body) opts.body = JSON.stringify(body);
        const r = await fetch(url, opts);
        return r.json();
    }

    function formatDate(iso) {
        if (!iso) return '—';
        try {
            return new Date(iso).toLocaleString('en-PH', {
                month: 'short', day: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });
        } catch (e) { return iso; }
    }

    function renderStatus(data) {
        const active = !!data.maintenance;

        // Status badge
        const badge    = document.getElementById('maintenanceStatusBadge');
        const badgeTxt = document.getElementById('maintenanceStatusText');
        if (badge && badgeTxt) {
            badge.className = active
                ? 'maintenance-status-badge maintenance-status-danger'
                : 'maintenance-status-badge maintenance-status-active';
            badgeTxt.textContent = active ? 'Maintenance Active' : 'System Active';
        }

        // Action icon
        const iconWrap = document.getElementById('maintenanceActionIcon');
        const iconEl   = document.getElementById('maintenanceActionIconI');
        const label    = document.getElementById('maintenanceActionLabel');
        const sub      = document.getElementById('maintenanceActionSub');
        const btn      = document.getElementById('maintenancePrimaryBtn');
        const btnText  = document.getElementById('maintenanceBtnText');
        const lastUpd  = document.getElementById('maintenanceLastUpdated');

        if (iconWrap && iconEl) {
            if (active) {
                iconWrap.style.background = '#fef2f2';
                iconEl.className = 'fas fa-tools';
                iconEl.style.color = '#dc2626';
            } else {
                iconWrap.style.background = '#eff6ff';
                iconEl.className = 'fas fa-check-circle';
                iconEl.style.color = '#16a34a';
            }
        }
        if (label) label.textContent = active ? 'Maintenance Active' : 'System Active';
        if (sub)   sub.textContent   = active ? 'LUPTO & Municipal users are restricted' : 'All users have normal access';
        if (btn) {
            btn.className = active
                ? 'btn-gov btn-maintenance-deactivate'
                : 'btn-gov btn-maintenance-activate';
            btn.onclick = active
                ? () => window.maintenanceMode.showDeactivateModal()
                : () => window.maintenanceMode.showActivateModal();
        }
        if (btnText) btnText.textContent = active ? 'Close Maintenance Mode' : 'Activate Maintenance Mode';
        if (lastUpd && data.activated_at) {
            lastUpd.textContent = active
                ? `Active since: ${formatDate(data.activated_at)}`
                : '';
        } else if (lastUpd) {
            lastUpd.textContent = '';
        }

        // Top banner
        const banner = document.getElementById('maintenanceActiveBanner');
        if (banner) {
            if (active) {
                banner.style.display = 'flex';
                const byEl = document.getElementById('maintenanceActivatedBy');
                const atEl = document.getElementById('maintenanceActivatedAt');
                if (byEl) byEl.textContent = data.activated_by || '—';
                if (atEl) atEl.textContent = formatDate(data.activated_at);
            } else {
                banner.style.display = 'none';
            }
        }
    }

    async function loadStatus() {
        try {
            const data = await apiFetch('GET', '/api/pitco/settings/maintenance');
            renderStatus(data);
        } catch (e) {
            const badgeTxt = document.getElementById('maintenanceStatusText');
            if (badgeTxt) badgeTxt.textContent = 'Unavailable';
        }
    }

    // ── Modal helpers ─────────────────────────────────────────────────────────
    function removeModal(id) {
        const el = document.getElementById(id);
        if (el) el.remove();
    }

    function showActivateModal() {
        removeModal('maintenanceActivateModal');
        const m = document.createElement('div');
        m.id = 'maintenanceActivateModal';
        m.className = 'lupto-modal-overlay';
        m.style.cssText = "display:flex;z-index:10001;font-family:'Inter','Outfit',system-ui,-apple-system,sans-serif;";
        m.innerHTML = `
            <div class="lupto-modal-content" style="max-width:460px;text-align:center;font-family:'Inter','Outfit',system-ui,-apple-system,sans-serif;">
                <div class="lupto-modal-header" style="background:linear-gradient(135deg,#1e3a8a,#2563eb);font-family:'Outfit','Inter',system-ui,sans-serif;">
                    <h3 class="lupto-modal-title" style="font-family:'Outfit','Inter',system-ui,sans-serif;font-size:16px;font-weight:700;"><i class="fas fa-tools"></i> Activate Maintenance Mode</h3>
                </div>
                <div class="lupto-modal-body" style="padding:32px 28px 20px;font-family:'Inter',system-ui,-apple-system,sans-serif;">
                    <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#dbeafe,#eff6ff);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;box-shadow:0 4px 16px rgba(30,58,138,0.15);">
                        <i class="fas fa-tools" style="font-size:30px;color:#1e3a8a;"></i>
                    </div>
                    <p style="font-size:16px;font-weight:700;color:#1e293b;margin:0 0 10px;font-family:'Outfit','Inter',system-ui,sans-serif;">Activate System Maintenance?</p>
                    <p style="font-size:13px;color:#64748b;line-height:1.7;margin:0 0 18px;font-family:'Inter',system-ui,-apple-system,sans-serif;">
                        Activating maintenance mode will <strong style="color:#1e3a8a;">immediately restrict access</strong>
                        for all <strong>LUPTO and Municipal</strong> users.<br>
                        They will see a maintenance screen and cannot access any system features until you close maintenance mode.
                    </p>
                    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px 16px;text-align:left;">
                        <p style="font-size:12px;color:#1e3a8a;margin:0;display:flex;align-items:flex-start;gap:8px;font-family:'Inter',system-ui,-apple-system,sans-serif;">
                            <i class="fas fa-info-circle" style="margin-top:2px;flex-shrink:0;color:#2563eb;"></i>
                            <span><strong>Note:</strong> User sessions are preserved — no one will be logged out.
                            PICTO will retain full access throughout maintenance.</span>
                        </p>
                    </div>
                </div>
                <div class="lupto-modal-footer" style="justify-content:center;gap:12px;display:flex;padding:16px 24px;font-family:'Inter',system-ui,-apple-system,sans-serif;">
                    <button class="btn-gov btn-gov-secondary" onclick="document.getElementById('maintenanceActivateModal').remove()" style="min-width:110px;font-family:'Inter',system-ui,-apple-system,sans-serif;font-weight:600;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button class="btn-gov" id="maintenanceConfirmActivateBtn" onclick="window.maintenanceMode._confirmActivate()" style="background:linear-gradient(135deg,#1e3a8a,#2563eb);border-color:#1e3a8a;min-width:180px;justify-content:center;font-family:'Inter',system-ui,-apple-system,sans-serif;font-weight:600;">
                        <i class="fas fa-tools"></i> Activate Maintenance
                    </button>
                </div>
            </div>`;
        document.body.appendChild(m);
    }

    function showDeactivateModal() {
        removeModal('maintenanceDeactivateModal');
        const m = document.createElement('div');
        m.id = 'maintenanceDeactivateModal';
        m.className = 'lupto-modal-overlay';
        m.style.cssText = "display:flex;z-index:10001;font-family:'Inter','Outfit',system-ui,-apple-system,sans-serif;";
        m.innerHTML = `
            <div class="lupto-modal-content" style="max-width:440px;text-align:center;font-family:'Inter','Outfit',system-ui,-apple-system,sans-serif;">
                <div class="lupto-modal-header" style="background:linear-gradient(135deg,#991b1b,#dc2626);font-family:'Outfit','Inter',system-ui,sans-serif;">
                    <h3 class="lupto-modal-title" style="font-family:'Outfit','Inter',system-ui,sans-serif;font-size:16px;font-weight:700;"><i class="fas fa-power-off"></i> Close Maintenance Mode</h3>
                </div>
                <div class="lupto-modal-body" style="padding:32px 28px 20px;font-family:'Inter',system-ui,-apple-system,sans-serif;">
                    <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#dcfce7,#bbf7d0);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;box-shadow:0 4px 16px rgba(22,163,74,0.2);">
                        <i class="fas fa-unlock" style="font-size:30px;color:#16a34a;"></i>
                    </div>
                    <p style="font-size:16px;font-weight:700;color:#1e293b;margin:0 0 10px;font-family:'Outfit','Inter',system-ui,sans-serif;">Restore Normal System Access?</p>
                    <p style="font-size:13px;color:#64748b;line-height:1.7;margin:0;font-family:'Inter',system-ui,-apple-system,sans-serif;">
                        Closing maintenance mode will <strong style="color:#16a34a;">immediately restore</strong>
                        full access for all LUPTO and Municipal users.<br>
                        They will be able to use the system normally without any manual intervention.
                    </p>
                </div>
                <div class="lupto-modal-footer" style="justify-content:center;gap:12px;display:flex;padding:16px 24px;font-family:'Inter',system-ui,-apple-system,sans-serif;">
                    <button class="btn-gov btn-gov-secondary" onclick="document.getElementById('maintenanceDeactivateModal').remove()" style="min-width:110px;font-family:'Inter',system-ui,-apple-system,sans-serif;font-weight:600;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button class="btn-gov" id="maintenanceConfirmDeactivateBtn" onclick="window.maintenanceMode._confirmDeactivate()" style="background:linear-gradient(135deg,#16a34a,#15803d);border-color:#15803d;min-width:180px;justify-content:center;font-family:'Inter',system-ui,-apple-system,sans-serif;font-weight:600;">
                        <i class="fas fa-unlock"></i> Restore Access
                    </button>
                </div>
            </div>`;
        document.body.appendChild(m);
    }

    async function _confirmActivate() {
        const btn = document.getElementById('maintenanceConfirmActivateBtn');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Activating...'; }
        try {
            const data = await apiFetch('POST', '/api/pitco/settings/maintenance/activate');
            removeModal('maintenanceActivateModal');
            if (data.success) {
                showToast('Maintenance mode activated. Users are now restricted.', 'warning');
                await loadStatus();
            } else {
                showToast(data.error || data.message || 'Failed to activate.', 'error');
            }
        } catch (e) {
            showToast('Failed to activate. Please try again.', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-tools"></i> Activate Maintenance'; }
        }
    }

    async function _confirmDeactivate() {
        const btn = document.getElementById('maintenanceConfirmDeactivateBtn');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Restoring...'; }
        try {
            const data = await apiFetch('POST', '/api/pitco/settings/maintenance/deactivate');
            removeModal('maintenanceDeactivateModal');
            if (data.success) {
                showToast('Maintenance mode closed. System access restored.', 'success');
                await loadStatus();
            } else {
                showToast(data.error || data.message || 'Failed to deactivate.', 'error');
            }
        } catch (e) {
            showToast('Failed to deactivate. Please try again.', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-unlock"></i> Restore Access'; }
        }
    }

    window.maintenanceMode = {
        loadStatus,
        showActivateModal,
        showDeactivateModal,
        _confirmActivate,
        _confirmDeactivate,
    };

    // Auto-init
    document.addEventListener('DOMContentLoaded', loadStatus);
    // Also init if DOM already ready
    if (document.readyState !== 'loading') loadStatus();
})();
