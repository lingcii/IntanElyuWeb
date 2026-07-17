(function() {
/**
 * MUNICIPAL Fare Data API
 * Role: *_mto (Municipal Tourism Offices)
 * Permissions: View, Upload, Activate own Tricycle guides only
 * No delete permission. Other vehicle types (PUB, PUJ, Van) managed by PICTO.
 */

const API_BASE = window.API_CONFIG?.MUNICIPAL ? (window.API_CONFIG.MUNICIPAL + '/fare-data') : 'http://' + (window.location.hostname || '127.0.0.1') + ':8000/api/municipal/fare-data';

// Map legacy action strings to Laravel REST paths
function muniApiUrl(action, params = {}) {
    const map = {
        'get_fare_guides':        `${API_BASE}/guides`,
        'get_fare_matrices':      `${API_BASE}/matrices`,
        'get_uploads':            `${API_BASE}/uploads`,
        'get_import_logs':        `${API_BASE}/import-logs`,
        'get_validation_errors':  `${API_BASE}/validation-errors`,
        'upload_pdf':             `${API_BASE}/upload`,
        'sync_fare_guide':        `${API_BASE}/sync`,
    };
    const base = map[action] || `${API_BASE}/${action.replace(/_/g, '-')}`;
    const allParams = { ...params, _t: Date.now() };
    const qs   = '?' + new URLSearchParams(allParams).toString();
    return base + qs;
}

document.addEventListener('DOMContentLoaded', function () {
    initUploadArea();
    loadFareGuides();
    loadUploadHistory();
});

// ── Upload Area ───────────────────────────────────────────────────────────────

function initUploadArea() {
    const uploadArea = document.getElementById('uploadArea');
    const fileInput  = document.getElementById('fareFileInput');

    uploadArea.addEventListener('dragover', function (e) {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });
    uploadArea.addEventListener('dragleave', function (e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
    });
    uploadArea.addEventListener('drop', function (e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        if (e.dataTransfer.files.length > 0) handleFileUpload(e.dataTransfer.files[0]);
    });
    uploadArea.addEventListener('click', function (e) {
        if (e.target.closest('.fd-btn-browse')) return;
        fileInput.click();
    });
    fileInput.addEventListener('change', function (e) {
        if (e.target.files.length > 0) handleFileUpload(e.target.files[0]);
    });
}

async function handleFileUpload(file) {
    if (!file.name.toLowerCase().endsWith('.csv') && file.type !== 'text/csv') {
        showResult('error', 'Please select a CSV file');
        return;
    }

    const formData = new FormData();
    formData.append('csv_file', file);

    const title = document.getElementById('upTitle')?.value || '';
    const effective_date = document.getElementById('upEffectiveDate')?.value || '';

    if (title) formData.append('title', title);
    if (effective_date) formData.append('effective_date', effective_date);

    showProgress(true, 'Uploading...');

    try {
        const response = await fetch(muniApiUrl('upload_pdf'), {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });
        if (!response.ok) {
            const errText = await response.text();
            throw new Error(`Server error (HTTP ${response.status}): ${errText.slice(0, 150)}`);
        }
        const result = await response.json();
        showProgress(false);

        if (result.success) {
            let html = `
                <div class="fd-result-success">
                    <h4><i class="fas fa-check-circle"></i> Upload Successful!</h4>
                    <p>Total Records: ${result.total_records}</p>
                    <p>Valid Records: ${result.valid_records}</p>
            `;
            if (result.errors && result.errors.length > 0) {
                html += `<p style="margin:8px 0 0;">
                    <i class="fas fa-exclamation-triangle"></i> ${result.errors.length} validation warning(s).
                    <button class="fd-btn-refresh" style="margin-left:10px;padding:4px 10px;font-size:11px;" onclick="showValidationErrors(${result.upload_id})">View Details</button>
                </p>`;
            }
            html += `</div>`;
            showResult('success', html);
            loadFareGuides();
            loadUploadHistory();
            if (typeof window.notifyFareDataChanged === 'function') {
                window.notifyFareDataChanged();
            }
        } else {
            showResult('error', result.error || 'Upload failed');
        }
    } catch (err) {
        showProgress(false);
        showResult('error', 'Upload failed: ' + err.message);
    }
}

function showProgress(show, text = '') {
    const progressDiv  = document.getElementById('uploadProgress');
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');
    if (show) {
        progressDiv.style.display  = 'block';
        progressFill.style.width   = '50%';
        progressText.textContent   = text;
    } else {
        progressFill.style.width = '100%';
        setTimeout(() => { progressDiv.style.display = 'none'; progressFill.style.width = '0%'; }, 500);
    }
}

function showResult(type, message) {
    const resultDiv = document.getElementById('uploadResult');
    resultDiv.style.display = 'block';
    if (type === 'success') {
        resultDiv.innerHTML = `<div class="fd-result-success">${message}</div>`;
    } else {
        resultDiv.innerHTML = `
            <div class="fd-result-error">
                <h4><i class="fas fa-exclamation-circle"></i> Upload Failed</h4>
                <p>${escapeHtml(message)}</p>
            </div>`;
    }
}

// ── Fare Guides ───────────────────────────────────────────────────────────────

async function loadFareGuides() {
    try {
        const response = await fetch(muniApiUrl('get_fare_guides'), { credentials: 'include' });
        const result   = await response.json();
        const tbody    = document.getElementById('fareGuidesBody');

        if (!result.fare_guides || result.fare_guides.length === 0) {
            tbody.innerHTML = `<tr class="fd-empty-row"><td colspan="7">
                <i class="fas fa-inbox"></i>No fare guides yet.
            </td></tr>`;
            return;
        }

        const previousIds = new Set(Array.from(tbody.querySelectorAll('tr')).map(tr => tr.dataset.guideId));

        tbody.innerHTML = result.fare_guides.map(guide => {
            const statusClass = 'fd-status-' + (guide.status || 'draft');
            const canActivate = guide.status !== 'active';

            const isNew = previousIds.size > 0 && !previousIds.has(String(guide.id));
            const animateClass = isNew ? ' class="fd-new-row-animate"' : '';

            return `
                <tr data-guide-id="${guide.id}"${animateClass}>
                    <td><strong>${escapeHtml(guide.title)}</strong></td>
                    <td>${escapeHtml(guide.vehicle_type.replace(/_/g, ' '))}</td>
                    <td>${escapeHtml(guide.region)}</td>
                    <td>${formatDate(guide.effective_date)}</td>
                    <td><span class="fd-status-badge ${statusClass}">${escapeHtml(guide.status)}</span></td>
                    <td>${escapeHtml(guide.created_by_name)}</td>
                    <td>
                        <div class="fd-dropdown">
                            <button class="fd-dropdown-toggle" onclick="toggleDropdown(event,${guide.id})">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="fd-dropdown-menu" id="dropdown-${guide.id}">
                                <button class="fd-dropdown-item"
                                    onclick="viewFareMatrix(${guide.id}, true);closeAllDropdowns();">
                                    <i class="fas fa-eye"></i> View Fare Matrix
                                </button>
                                ${canActivate ? `
                                <button class="fd-dropdown-item"
                                    onclick="syncFareGuide(${guide.id},'active');closeAllDropdowns();">
                                    <i class="fas fa-check-circle" style="color:#10b981;"></i> Activate
                                </button>` : ''}
                            </div>
                        </div>
                    </td>
                </tr>`;
        }).join('');

        // Auto-load matrix of the first/active guide
        const activeGuide = Array.isArray(result.fare_guides) ? (result.fare_guides.find(g => g.status === 'active') || result.fare_guides[0]) : null;
        if (activeGuide) {
            viewFareMatrix(activeGuide.id, false);
        } else {
            const section = document.getElementById('fareMatrixSection');
            if (section) section.style.display = 'none';
        }
    } catch (err) {
        console.error('Failed to load fare guides:', err);
    }
}

// ── Fare Matrix ───────────────────────────────────────────────────────────────

async function viewFareMatrix(guideId, shouldScroll = false) {
    try {
        const response = await fetch(muniApiUrl('get_fare_matrices', { guide_id: guideId }), { credentials: 'include' });
        const result   = await response.json();
        const section  = document.getElementById('fareMatrixSection');
        const tbody    = document.getElementById('fareMatrixBody');

        if (!result.fare_matrices || result.fare_matrices.length === 0) {
            tbody.innerHTML = `<tr class="fd-empty-row"><td colspan="4">No fare data available</td></tr>`;
        } else {
            tbody.innerHTML = result.fare_matrices.map(m => {
                const dist = parseFloat(m.distance_km);
                const regular = parseFloat(m.regular_fare);
                const discounted = parseFloat(m.discounted_fare);
                const savings = regular - discounted;
                return `
                <tr>
                    <td><strong>${dist.toFixed(1)} km</strong></td>
                    <td style="color:#2563eb;font-weight:700;">₱${regular.toFixed(2)}</td>
                    <td style="color:#059669;">₱${discounted.toFixed(2)}</td>
                    <td style="color:#059669;font-weight:600;">${savings > 0 ? 'Save ₱' + savings.toFixed(2) : '—'}</td>
                </tr>`;
            }).join('');
        }

        section.classList.add('active');
        if (shouldScroll) {
            section.scrollIntoView({ behavior: 'smooth' });
        }
    } catch (err) {
        console.error('Failed to load fare matrix:', err);
    }
}

// ── Upload History ────────────────────────────────────────────────────────────

async function loadUploadHistory() {
    try {
        const response = await fetch(muniApiUrl('get_uploads'), { credentials: 'include' });
        const result   = await response.json();
        const tbody    = document.getElementById('uploadHistoryBody');

        if (!result.uploads || result.uploads.length === 0) {
            tbody.innerHTML = `<tr class="fd-empty-row"><td colspan="6">
                <i class="fas fa-inbox"></i>No upload history yet
            </td></tr>`;
            return;
        }

        tbody.innerHTML = result.uploads.map(upload => {
            const statusClass = 'fd-status-' + (upload.status || 'pending');
            return `
                <tr>
                    <td>${escapeHtml(upload.file_name)}</td>
                    <td>${escapeHtml(upload.uploaded_by_name)}</td>
                    <td>${formatDate(upload.created_at)}</td>
                    <td><span class="fd-status-badge ${statusClass}">${escapeHtml(upload.status)}</span></td>
                    <td>${upload.total_records} total / ${upload.valid_records} valid</td>
                    <td>
                        <button class="fd-btn-refresh" style="padding:4px 10px;font-size:11px;" onclick="showImportLogs(${upload.id})">
                            <i class="fas fa-list"></i> Logs
                        </button>
                        <button class="fd-btn-refresh" style="padding:4px 10px;font-size:11px;margin-left:4px;" onclick="showValidationErrors(${upload.id})">
                            <i class="fas fa-exclamation-triangle"></i> Errors
                        </button>
                    </td>
                </tr>`;
        }).join('');
    } catch (err) {
        console.error('Failed to load upload history:', err);
    }
}

// ── Sync / Activate ───────────────────────────────────────────────────────────

let _guideToSync = null;
let _syncStatus  = null;

function syncFareGuide(guideId, status) {
    _guideToSync = guideId;
    _syncStatus  = status;
    closeAllDropdowns();

    document.getElementById('modalTitle').textContent = 'Activate Fare Guide';
    document.getElementById('modalBody').innerHTML = `
        <div style="text-align:center;padding:16px;">
            <i class="fas fa-info-circle" style="font-size:44px;color:#2563eb;margin-bottom:14px;"></i>
            <p style="margin:0 0 20px;font-size:15px;line-height:1.5;">Are you sure you want to <strong>activate</strong> this fare guide?</p>
            <p style="margin:0 0 24px;font-size:12px;color:#64748b;">This will auto-archive any other active Tricycle guide for the same region.</p>
            <div style="display:flex;gap:10px;justify-content:center;">
                <button class="fd-btn-refresh" onclick="cancelSync()"><i class="fas fa-times"></i> Cancel</button>
                <button class="fd-btn-browse" onclick="confirmSync()"><i class="fas fa-check"></i> Confirm</button>
            </div>
        </div>`;
    document.getElementById('detailsModal').classList.add('active');
}

async function confirmSync() {
    if (!_guideToSync) return;
    try {
        const response = await fetch(muniApiUrl('sync_fare_guide'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ guide_id: _guideToSync, status: _syncStatus })
        });
        const result = await response.json();
        if (result.success) {
            document.getElementById('detailsModal').classList.remove('active');
            showSuccessMessage('Fare guide activated successfully!');
            loadFareGuides();
            if (typeof window.notifyFareDataChanged === 'function') {
                window.notifyFareDataChanged();
            }
        } else {
            showErrorMessage('Failed to update: ' + (result.error || 'Unknown error'));
        }
    } catch (err) {
        showErrorMessage('Failed to update: ' + err.message);
    }
    _guideToSync = null; _syncStatus = null;
}

function cancelSync() {
    document.getElementById('detailsModal').classList.remove('active');
    _guideToSync = null; _syncStatus = null;
}

// ── Logs & Errors ─────────────────────────────────────────────────────────────

async function showImportLogs(uploadId) {
    try {
        const response = await fetch(muniApiUrl('get_import_logs', { upload_id: uploadId }), { credentials: 'include' });
        const result   = await response.json();
        let content = '';
        if (!result.import_logs || result.import_logs.length === 0) {
            content = '<p style="text-align:center;padding:20px;color:#9ca3af;">No logs available</p>';
        } else {
            content = result.import_logs.map(log => {
                const msg = log.message || log.action || '';
                const isError = msg.toLowerCase().includes('error') || msg.toLowerCase().includes('failed');
                const isWarn  = msg.toLowerCase().includes('warning');
                const icon  = isError ? 'exclamation-circle' : isWarn ? 'exclamation-triangle' : 'info-circle';
                const color = isError ? '#991b1b' : isWarn ? '#92400e' : '#1e40af';
                return `<div style="padding:10px 14px;border-bottom:1px solid #f1f5f9;color:${color};">
                    <i class="fas fa-${icon}"></i>
                    <strong>${escapeHtml(log.action)}</strong>
                    <span style="margin-left:8px;font-size:11px;color:#9ca3af;">${formatDate(log.created_at)}</span>
                    <div style="margin-top:4px;font-size:13px;">${escapeHtml(log.details || '')}</div>
                </div>`;
            }).join('');
        }
        showModal('Import Logs', `<div style="padding:16px;">${content}</div>`);
    } catch (err) {
        console.error('Failed to load import logs:', err);
    }
}

async function showValidationErrors(uploadId) {
    try {
        const response = await fetch(muniApiUrl('get_validation_errors', { upload_id: uploadId }), { credentials: 'include' });
        const result   = await response.json();
        let content = '';
        if (!result.validation_errors || result.validation_errors.length === 0) {
            content = '<p style="color:var(--text-muted);text-align:center;padding:20px;">No validation errors found</p>';
        } else {
            content = `<table style="width:100%;border-collapse:collapse;">
                <thead><tr style="background:#f8fafc;">
                    <th style="padding:8px 12px;text-align:left;font-size:11px;font-weight:700;color:#64748b;">Row</th>
                    <th style="padding:8px 12px;text-align:left;font-size:11px;font-weight:700;color:#64748b;">Field</th>
                    <th style="padding:8px 12px;text-align:left;font-size:11px;font-weight:700;color:#64748b;">Error</th>
                </tr></thead>
                <tbody>${result.validation_errors.map(err => `
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:8px 12px;">${err.row_number || '-'}</td>
                        <td style="padding:8px 12px;font-weight:600;">${escapeHtml(err.field_name || err.field || '-')}</td>
                        <td style="padding:8px 12px;color:#991b1b;">${escapeHtml(err.error_message || err.error)}</td>
                    </tr>`).join('')}
                </tbody></table>`;
        }
        showModal('Validation Errors', content);
    } catch (err) {
        console.error('Failed to load validation errors:', err);
    }
}

// ── Dropdown ──────────────────────────────────────────────────────────────────

function toggleDropdown(event, guideId) {
    event.stopPropagation();
    closeAllDropdowns();
    const dropdown = document.getElementById(`dropdown-${guideId}`);
    if (dropdown) {
        const isOpen = dropdown.style.display === 'block';
        dropdown.style.display = isOpen ? 'none' : 'block';
    }
}

function closeAllDropdowns() {
    document.querySelectorAll('.fd-dropdown-menu, .dropdown-menu').forEach(d => { d.style.display = 'none'; });
}

document.addEventListener('click', closeAllDropdowns);

// ── Modal Helpers ─────────────────────────────────────────────────────────────

function showModal(title, content) {
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalBody').innerHTML    = content;
    document.getElementById('detailsModal').classList.add('active');
}

function showSuccessMessage(message) {
    showModal('Success', `
        <div style="text-align:center;padding:24px;">
            <i class="fas fa-check-circle" style="font-size:48px;color:#10b981;margin-bottom:14px;"></i>
            <p style="margin:0;font-size:15px;color:#1e293b;">${escapeHtml(message)}</p>
            <button class="fd-btn-browse" style="margin-top:20px;" onclick="document.getElementById('detailsModal').classList.remove('active')">OK</button>
        </div>`);
}

function showErrorMessage(message) {
    showModal('Error', `
        <div style="text-align:center;padding:24px;">
            <i class="fas fa-times-circle" style="font-size:48px;color:#dc2626;margin-bottom:14px;"></i>
            <p style="margin:0;font-size:15px;color:#1e293b;">${escapeHtml(message)}</p>
            <button class="fd-btn-browse" style="margin-top:20px;" onclick="document.getElementById('detailsModal').classList.remove('active')">OK</button>
        </div>`);
}

// ── Utilities ─────────────────────────────────────────────────────────────────

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
    });
}

function fd_openAddModal() {
    document.getElementById('aeGuideId').value = '';
    document.getElementById('aeTitle').value = '';
    document.getElementById('aeEffectiveDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('aeRowsBody').innerHTML = '';
    document.getElementById('fdAddEditTitle').innerHTML = '<i class="fas fa-plus"></i> Add Fare Matrix';
    ae_addRow();
    document.getElementById('fdAddEditModal').classList.add('active');
}

function fd_closeAddEdit() {
    document.getElementById('fdAddEditModal').classList.remove('active');
}

function ae_addRow(distance = '', regular = '', discounted = '') {
    const tbody = document.getElementById('aeRowsBody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td style="padding:4px;"><input type="number" step="0.1" required class="fd-input ae-dist" style="width:100%;padding:4px;border:1px solid #bfdbfe;border-radius:4px;" value="${distance}" placeholder="e.g. 1.0"></td>
        <td style="padding:4px;"><input type="number" step="0.01" required class="fd-input ae-regular" style="width:100%;padding:4px;border:1px solid #bfdbfe;border-radius:4px;" value="${regular}" placeholder="e.g. 15.00"></td>
        <td style="padding:4px;"><input type="number" step="0.01" class="fd-input ae-discount" style="width:100%;padding:4px;border:1px solid #bfdbfe;border-radius:4px;" value="${discounted}" placeholder="e.g. 12.00"></td>
        <td style="padding:4px;text-align:center;"><button type="button" class="fd-btn-refresh" style="padding:4px 8px;background:#ef4444;border-color:#ef4444;color:white;margin:0;" onclick="this.closest('tr').remove()"><i class="fas fa-trash-alt"></i></button></td>
    `;
    tbody.appendChild(tr);
}

async function fd_saveManual(event) {
    event.preventDefault();
    const saveBtn = document.getElementById('aeSaveBtn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    const title = document.getElementById('aeTitle').value;
    const vehicleType = document.getElementById('aeVehicleType').value;
    const region = document.getElementById('aeRegion').value;
    const effectiveDate = document.getElementById('aeEffectiveDate').value;

    const fares = Array.from(document.querySelectorAll('#aeRowsBody tr')).map(tr => {
        const dist = tr.querySelector('.ae-dist').value;
        const reg = tr.querySelector('.ae-regular').value;
        const disc = tr.querySelector('.ae-discount').value;
        return {
            distance_km: parseFloat(dist),
            regular_fare: parseFloat(reg),
            discounted_fare: disc ? parseFloat(disc) : null
        };
    });

    if (fares.length === 0) {
        showErrorMessage('Please add at least one row to the fare matrix.');
        saveBtn.disabled = false;
        saveBtn.innerHTML = 'Save';
        return;
    }

    const payload = {
        title,
        vehicle_type: vehicleType,
        region,
        effective_date: effectiveDate,
        fares
    };

    const url = API_BASE;

    try {
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload)
        });
        const result = await resp.json();
        if (result.error) throw new Error(result.error);

        showSuccessMessage('Fare matrix manually created successfully.');
        fd_closeAddEdit();
        loadFareGuides();
        if (typeof window.notifyFareDataChanged === 'function') {
            window.notifyFareDataChanged();
        }
    } catch (e) {
        showErrorMessage(e.message);
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = 'Save';
    }
}

// ── Global visibility ─────────────────────────────────────────────────────
window.loadFareGuides     = loadFareGuides;
window.viewFareMatrix     = viewFareMatrix;
window.showValidationErrors = showValidationErrors;
window.showImportLogs     = showImportLogs;
window.toggleDropdown     = toggleDropdown;
window.closeAllDropdowns  = closeAllDropdowns;
window.syncFareGuide      = syncFareGuide;
window.cancelSync         = cancelSync;
window.confirmSync        = confirmSync;
window.softRefreshFareData = async function() {
    await Promise.all([loadFareGuides(), loadUploadHistory()]);
};
window.fd_openAddModal    = fd_openAddModal;
window.fd_closeAddEdit    = fd_closeAddEdit;
window.ae_addRow          = ae_addRow;
window.fd_saveManual      = fd_saveManual;

})();
