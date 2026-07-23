(function(){
            const BASE = (window.API_CONFIG?.BASE_URL || ('http://' + (window.location.hostname || '127.0.0.1') + ':8000'));
            const API  = BASE + '/api/pitco/settings/backup';
            let _rt = null, _dt = null;

            function showLoading(text) {
                const textEl = document.getElementById('picto-backupLoadingText');
                if (textEl) textEl.textContent = text || 'Processing...';
                const overlay = document.getElementById('picto-backupLoadingOverlay');
                if (overlay) overlay.style.display = 'flex';
                ['picto-btnCreate','picto-btnRestore'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.disabled = true;
                });
            }
            function hideLoading() {
                const overlay = document.getElementById('picto-backupLoadingOverlay');
                if (overlay) overlay.style.display = 'none';
                ['picto-btnCreate','picto-btnRestore'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.disabled = false;
                });
            }
            function showToast(msg, type) {
                let t = document.getElementById('picto-backupToast');
                if (!t) { t=document.createElement('div'); t.id='picto-backupToast'; t.style.cssText='position:fixed;bottom:28px;right:28px;padding:13px 22px;border-radius:10px;font-size:14px;font-weight:600;box-shadow:0 6px 24px rgba(0,0,0,.18);z-index:999999;display:flex;align-items:center;gap:10px;opacity:0;transition:opacity .3s;color:#fff;max-width:360px;'; document.body.appendChild(t); }
                t.style.background = type==='error'?'#dc2626':'#16a34a';
                t.innerHTML = `<i class="fas ${type==='error'?'fa-exclamation-circle':'fa-check-circle'}"></i> ${msg}`;
                t.style.opacity='1'; clearTimeout(t._tid); t._tid=setTimeout(()=>{t.style.opacity='0';},4000);
            }
            async function apiFetch(method, url, body, isFile) {
                const opts = { method, credentials:'include', headers:{'Accept':'application/json'} };
                if (body && !isFile) { opts.headers['Content-Type']='application/json'; opts.body=JSON.stringify(body); }
                else if (isFile) { opts.body=body; }
                const r = await fetch(url, opts);
                if (method==='GET' && url.includes('/download/')) return r;
                return r.json();
            }
            async function loadStats() {
                try {
                    const d = await apiFetch('GET', API+'/stats');
                    if (d.success) {
                        document.getElementById('picto-statTotalVal').textContent = d.total;
                        document.getElementById('picto-statLastVal').textContent  = d.last_backup;
                        document.getElementById('picto-statDbSizeVal').textContent = d.db_size;
                        document.getElementById('picto-statStatusVal').textContent = d.status;
                        document.getElementById('picto-statStatusVal').style.color = d.status==='Healthy'?'#16a34a':'#d97706';
                    }
                } catch(e){}
            }
            async function loadList() {
                const tbody = document.getElementById('picto-backupTableBody');
                tbody.innerHTML='<tr><td colspan="4" style="text-align:center;padding:20px;color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
                try {
                    const d = await apiFetch('GET', API+'/list');
                    if (!d.success||!d.backups.length) { tbody.innerHTML='<tr><td colspan="4" style="text-align:center;padding:24px;color:#94a3b8;"><i class="fas fa-folder-open" style="font-size:28px;display:block;margin-bottom:8px;"></i>No backups found.</td></tr>'; return; }
                    tbody.innerHTML = d.backups.map(b=>`
                        <tr>
                            <td style="font-family:monospace;font-size:13px;color:#1e3a8a;font-weight:600;"><i class="fas fa-file-code" style="color:#3b82f6;margin-right:6px;"></i>${b.filename}</td>
                            <td style="color:#475569;font-size:13px;">${b.date_fmt}</td>
                            <td style="color:#475569;font-size:13px;"><span style="background:#f1f5f9;padding:3px 10px;border-radius:6px;font-weight:600;">${b.size_fmt}</span></td>
                            <td style="text-align:center;"><div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap;">
                                <button onclick="window.picto_backup.download('${b.filename}')" style="background:#eff6ff;color:#3b82f6;border:1px solid #bfdbfe;border-radius:6px;padding:5px 12px;font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:5px;"><i class="fas fa-download"></i> Download</button>
                                <button onclick="window.picto_backup.openRestore('${b.filename}')" style="background:#fff7ed;color:#d97706;border:1px solid #fed7aa;border-radius:6px;padding:5px 12px;font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:5px;"><i class="fas fa-undo-alt"></i> Restore</button>
                                <button onclick="window.picto_backup.openDelete('${b.filename}')" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:6px;padding:5px 12px;font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:5px;"><i class="fas fa-trash-alt"></i> Delete</button>
                            </div></td>
                        </tr>`).join('');
                } catch(e) { tbody.innerHTML='<tr><td colspan="4" style="text-align:center;padding:20px;color:#dc2626;"><i class="fas fa-exclamation-circle"></i> Failed to load backups.</td></tr>'; }
            }
            async function loadLogs() {
                const c = document.getElementById('picto-backupLogsContainer');
                try {
                    const d = await apiFetch('GET', BASE+'/api/pitco/activity-logs?per_page=10&module=Backup');
                    const logs = d.data||d.logs||[];
                    if (!logs.length) { c.innerHTML='<div style="text-align:center;padding:12px;color:#94a3b8;">No backup actions logged yet.</div>'; return; }
                    c.innerHTML = logs.map(l=>`<div style="display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid #f1f5f9;"><div style="width:34px;height:34px;border-radius:8px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-database" style="color:#3b82f6;font-size:14px;"></i></div><div style="flex:1;min-width:0;"><div style="font-weight:700;color:#1e293b;font-size:13px;">${l.user_name||'System'}</div><div style="color:#64748b;font-size:12px;">${l.action} — ${l.description||''}</div></div><div style="font-size:11px;color:#94a3b8;white-space:nowrap;">${l.created_at?new Date(l.created_at).toLocaleString('en-PH',{month:'short',day:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}):''}</div></div>`).join('');
                } catch(e) { c.innerHTML='<div style="text-align:center;padding:12px;color:#94a3b8;">Could not load logs.</div>'; }
            }
            async function create() {
                showLoading('Creating Backup...');
                try {
                    const d = await apiFetch('POST', API+'/create');
                    if (d.success) { showToast(d.message||'Database backup created successfully.','success'); await loadList(); await loadStats(); await loadLogs(); }
                    else { showToast(d.message||'Database backup failed.','error'); }
                } catch(e) { showToast('Database backup failed. Please try again.','error'); }
                finally { hideLoading(); }
            }
            function openRestore(fn) { _rt=fn; document.getElementById('picto-restoreFileName').textContent=fn; document.getElementById('picto-restoreModal').style.display='flex'; }
            function closeRestoreModal() { document.getElementById('picto-restoreModal').style.display='none'; _rt=null; }
            function openDelete(fn)  { _dt=fn; document.getElementById('picto-deleteFileName').textContent=fn; document.getElementById('picto-deleteModal').style.display='flex'; }
            function closeDeleteModal() { document.getElementById('picto-deleteModal').style.display='none'; _dt=null; }
            async function confirmDelete() {
                if (!_dt) return; closeDeleteModal(); const fn=_dt;
                try {
                    const d = await apiFetch('DELETE', API+'/'+encodeURIComponent(fn));
                    if (d.success) { showToast(d.message||'Backup deleted successfully.','success'); await loadList(); await loadStats(); await loadLogs(); }
                    else { showToast(d.message||'Delete failed.','error'); }
                } catch(e) { showToast('Delete failed.','error'); }
            }
            async function download(filename) {
                try {
                    const r = await fetch(API+'/download/'+encodeURIComponent(filename),{credentials:'include'});
                    if (!r.ok) { showToast('Download failed.','error'); return; }
                    const blob=await r.blob(); const url=URL.createObjectURL(blob); const a=document.createElement('a'); a.href=url; a.download=filename; a.click(); URL.revokeObjectURL(url);
                } catch(e) { showToast('Download failed.','error'); }
            }
            async function uploadRestore(input) {
                const file=input.files[0]; if(!file) return;
                if (!file.name.endsWith('.sql')) { showToast('Invalid SQL file. Only .sql files are allowed.','error'); input.value=''; return; }
                _rt=file.name; window._picto_uploadFile=file; input.value='';
                document.getElementById('picto-restoreFileName').textContent=file.name;
                document.getElementById('picto-restoreModal').style.display='flex';
            }
            window.picto_backup = {
                create, loadList, loadStats, loadLogs, openRestore, closeRestoreModal, openDelete, closeDeleteModal, confirmDelete, download, uploadRestore,
                confirmRestore: async function() {
                    closeRestoreModal(); showLoading('Restoring Database...');
                    try {
                        let d;
                        if (window._picto_uploadFile) { const fd=new FormData(); fd.append('backup_file',window._picto_uploadFile); window._picto_uploadFile=null; d=await apiFetch('POST',API+'/restore',fd,true); }
                        else { d=await apiFetch('POST',API+'/restore',{filename:_rt}); _rt=null; }
                        if (d.success) { showToast(d.message||'Database restored successfully.','success'); await loadList(); await loadStats(); await loadLogs(); }
                        else { showToast(d.message||'Restore failed.','error'); }
                    } catch(e) { showToast('Restore failed.','error'); }
                    finally { hideLoading(); }
                },
            };
            loadStats(); loadList(); loadLogs();
        })();


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
            var toast = document.getElementById('pictoSaveToast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'pictoSaveToast';
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
                const resp = await fetch(baseUrl + '/api/picto/settings/password', {
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
    