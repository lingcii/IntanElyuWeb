/**
 * LUPTO Fare Data API
 * Role: lupto
 * Permissions: View-only — search, filter, view matrix, export CSV
 */
(function () {
'use strict';

let _allFareGuides = [];
let _currentMatrixData = [];
let _currentGuideTitle = '';
let refreshInterval = null;
let toastTimer = null;

function getBaseUrl() {
    const role = (window.userRole || '').toLowerCase();
    const path = (window.location.pathname || '').toUpperCase();
    if (role === 'picto' || role === 'pitco' || path.includes('/PICTO/')) {
        return window.API_CONFIG?.PITCO || 'http://127.0.0.1:8000/api/pitco';
    }
    if (role === 'municipal' || role.includes('municipal') || role.endsWith('_mto') || path.includes('/MUNICIPAL/')) {
        return window.API_CONFIG?.MUNICIPAL || 'http://127.0.0.1:8000/api/municipal';
    }
    return window.API_CONFIG?.LUPTO || 'http://127.0.0.1:8000/api/lupto';
}

function fareActionToUrl(action, params = {}) {
    const base = getBaseUrl();
    if (!base) {
        console.error('[Fare Data] API base URL is not available');
        return '';
    }
    const map = {
        'get_fare_guides':   `${base}/fare-data/guides`,
        'get_fare_matrices': `${base}/fare-data/matrices`,
    };
    const url = map[action] || `${base}/fare-data/${action.replace(/_/g, '-')}`;
    const allParams = { ...params, _t: Date.now() };
    return url + '?' + new URLSearchParams(allParams).toString();
}

async function apiFetch(action, extraParams = {}) {
    const url = fareActionToUrl(action, extraParams);
    if (!url) throw new Error('API base URL not configured');
    try {
        return await window.API_CONFIG.fetch(url);
    } catch (e) {
        console.error('[Fare Data] API fetch failed:', e);
        throw e;
    }
}

function showToast(message, type = 'info') {
    const container = document.getElementById('fdToastContainer');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `fd-toast fd-toast-${type}`;
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'}"></i> <span>${escapeHtml(message)}</span>`;
    container.appendChild(toast);
    setTimeout(() => { toast.remove(); }, 4000);
}

function openAddFareGuideModal() {
    const modal = document.getElementById('addFareGuideModal');
    if (!modal) return;
    modal.style.display = 'flex';
    
    document.getElementById('addFareGuideForm')?.reset();
    const nameEl = document.getElementById('fgCsvFileName');
    const previewEl = document.getElementById('fgCsvPreview');
    if (nameEl) nameEl.textContent = 'Click or drag & drop to choose a .CSV file';
    if (previewEl) previewEl.style.display = 'none';

    // Reset title
    const titleEl = document.getElementById('fgTitle');
    if (titleEl) titleEl.value = '';
    
    const role = (window.userRole || '').toLowerCase();
    const path = (window.location.pathname || '').toUpperCase();

    // Municipal: lock to Tricycle only
    if (role === 'municipal' || path.includes('/MUNICIPAL/')) {
        const vt = document.getElementById('fgVehicleType');
        if (vt) { vt.value = 'Tricycle'; vt.disabled = true; }
    }

    // PICTO: auto-fill Region to "All Municipalities, La Union" and lock it
    const regionWrap = document.getElementById('fgRegionWrap');
    const regionInput = document.getElementById('fgRegion');
    if (role === 'picto' || path.includes('/PICTO/')) {
        if (regionInput) {
            regionInput.value = 'All Municipalities, La Union';
            regionInput.readOnly = true;
            regionInput.style.background = '#f1f5f9';
            regionInput.style.color = '#64748b';
        }
        if (regionWrap) {
            const hint = regionWrap.querySelector('.fd-region-hint');
            if (!hint) {
                const p = document.createElement('p');
                p.className = 'fd-region-hint';
                p.style.cssText = 'margin:4px 0 0;font-size:11px;color:#64748b;';
                p.textContent = 'PICTO fare guides apply to all municipalities.';
                regionWrap.appendChild(p);
            }
        }
    } else {
        // Reset for non-PICTO
        if (regionInput) {
            regionInput.readOnly = false;
            regionInput.style.background = '';
            regionInput.style.color = '';
        }
    }

    // Reset date to today
    const dateEl = document.getElementById('fgEffectiveDate');
    if (dateEl && !dateEl.value) {
        dateEl.value = new Date().toISOString().slice(0, 10);
    }
}

function closeAddFareGuideModal() {
    const modal = document.getElementById('addFareGuideModal');
    if (modal) modal.style.display = 'none';
}

function updateCsvFileInfo(input) {
    const file = input?.files?.[0];
    const nameEl    = document.getElementById('fgCsvFileName');
    const previewEl = document.getElementById('fgCsvPreview');
    const previewText = document.getElementById('fgCsvPreviewText');
    const titleEl   = document.getElementById('fgTitle');
    
    if (file) {
        const sizeKb = (file.size / 1024).toFixed(1);
        if (nameEl) nameEl.textContent = `${file.name} (${sizeKb} KB)`;
        if (previewEl && previewText) {
            previewText.textContent = `CSV file "${file.name}" selected — ready for import.`;
            previewEl.style.display = 'block';
        }
        // Auto-fill title from filename (strip extension, replace separators with spaces)
        if (titleEl && !titleEl.value.trim()) {
            const rawName = file.name.replace(/\.csv$/i, '');
            titleEl.value = rawName.replace(/[_\-]+/g, ' ').trim();
        }

        // Peek at the CSV to auto-detect vehicle type from any cell in the first 5 rows
        const reader = new FileReader();
        reader.onload = function(e) {
            const lines = (e.target.result || '').split(/\r?\n/).map(l => l.trim()).filter(Boolean);
            if (lines.length === 0) return;

            const vtMap = {
                'MPUJ': 'PUJ_Aircon',
                'PUJ AIRCON': 'PUJ_Aircon',
                'PUJ_AIRCON': 'PUJ_Aircon',
                'PUJ ORDINARY': 'PUJ_Ordinary',
                'PUJ_ORDINARY': 'PUJ_Ordinary',
                'PUJ': 'PUJ_Ordinary',
                'PUB AIRCON': 'PUB_Aircon',
                'PUB_AIRCON': 'PUB_Aircon',
                'PUB ORDINARY': 'PUB_Ordinary',
                'PUB_ORDINARY': 'PUB_Ordinary',
                'PUB': 'PUB_Ordinary',
                'BUS': 'PUB_Ordinary',
                'VAN': 'Van',
                'UV EXPRESS': 'Van',
                'UV_EXPRESS': 'Van',
                'TRICYCLE': 'Tricycle',
                'TRIKE': 'Tricycle',
            };

            // Scan ALL cells in the first 5 rows for a vehicle keyword
            let detected = null;
            outer:
            for (let r = 0; r < Math.min(5, lines.length); r++) {
                const cells = lines[r].split(',').map(c => c.replace(/['"]/g, '').trim().toUpperCase());
                for (const cell of cells) {
                    if (cell && vtMap[cell]) {
                        detected = vtMap[cell];
                        break outer;
                    }
                }
            }

            if (detected) {
                const vtSel = document.getElementById('fgVehicleType');
                if (vtSel && !vtSel.disabled) vtSel.value = detected;
            }
        };
        reader.readAsText(file.slice(0, 1024)); // Read first 1 KB
    } else {
        if (nameEl) nameEl.textContent = 'Click or drag & drop to choose a .CSV file';
        if (previewEl) previewEl.style.display = 'none';
    }
}

function handleCsvDrop(event) {
    event.preventDefault();
    const dt = event.dataTransfer;
    if (dt && dt.files && dt.files.length > 0) {
        const fileInput = document.getElementById('fgCsvFile');
        if (fileInput) {
            fileInput.files = dt.files;
            updateCsvFileInfo(fileInput);
        }
    }
}

async function submitFareGuideForm(event) {
    event.preventDefault();
    const btn = document.getElementById('btnSubmitFareGuide');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing CSV & Saving...'; }

    try {
        const title = document.getElementById('fgTitle')?.value.trim();
        const vehicleTypeSelect = document.getElementById('fgVehicleType');
        const vehicle_type = vehicleTypeSelect ? vehicleTypeSelect.value : 'Tricycle';
        const region = document.getElementById('fgRegion')?.value.trim();
        const effective_date = document.getElementById('fgEffectiveDate')?.value;

        const fileInput = document.getElementById('fgCsvFile');
        const file = fileInput?.files?.[0];
        if (!file) {
            showToast('Please select a CSV file to import.', 'danger');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Fare Guide'; }
            return;
        }

        const text = await file.text();
        const lines = text.split(/\r?\n/).map(l => l.trim()).filter(l => l.length > 0);

        if (lines.length === 0) {
            showToast('The selected CSV file is empty.', 'danger');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Fare Guide'; }
            return;
        }

        /**
         * Smart CSV parser — column-position agnostic.
         *
         * Government CSVs often look like this (data starts in col B, not col A):
         *   ,MPUJ,,,,           ← vehicle label in col B — ignored (only 1 numeric? no)
         *   ,KM DISTANCE,REGULAR FARE,ELDERLY/DISABLED,...   ← text headers — ignored
         *   ,0,,,               ← only one numeric (0) — skipped
         *   ,1,15,12,,          ← ✅ 1st numeric=distance, 2nd=regular, 3rd=discounted
         *
         * Strategy: for each row, collect ALL numeric values in order they appear.
         *   - Need at least 2 positive numerics → [dist, regular, optional discounted]
         *   - dist must be > 0, regular must be > 0
         */
        const fares = [];
        const seenDistances = new Set();

        for (let i = 0; i < lines.length; i++) {
            const cols = lines[i].split(',').map(c => c.replace(/^["'\s]+|["'\s]+$/g, ''));

            // Collect all positive numeric values from this row in column order
            const nums = [];
            for (let j = 0; j < cols.length; j++) {
                const raw = cols[j];
                if (raw === '') continue;
                const v = parseFloat(raw);
                if (!isNaN(v)) nums.push(v);
            }

            // Need at least distance + regular fare; both must be positive
            if (nums.length < 2) continue;

            const dist = nums[0];
            const reg  = nums[1];
            if (dist <= 0 || reg <= 0) continue;

            // Discounted = third numeric if present, otherwise compute 80%
            const disc = nums.length >= 3
                ? nums[2]
                : Math.round(reg * 0.8 * 100) / 100;

            const distKey = String(dist);
            if (!seenDistances.has(distKey)) {
                seenDistances.add(distKey);
                fares.push({ distance_km: dist, regular_fare: reg, discounted_fare: disc });
            }
        }

        if (fares.length === 0) {
            showToast(
                'No valid rows found in CSV. Each data row needs at least 2 numbers: KM Distance and Regular Fare (any column). All text header rows are automatically skipped.',
                'danger'
            );
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Fare Guide'; }
            return;
        }

        const payload = {
            title,
            vehicle_type,
            region,
            effective_date,
            status: 'active',
            fares
        };

        const base = getBaseUrl();
        const res = await window.API_CONFIG.fetch(`${base}/fare-data`, {
            method: 'POST',
            body: JSON.stringify(payload)
        });

        if (res.success) {
            showToast(`Fare guide created successfully with ${fares.length} matrix rows from CSV!`, 'success');
            closeAddFareGuideModal();
            loadFareGuides();
        } else {
            showToast(res.error || 'Failed to create fare guide.', 'danger');
        }
    } catch (err) {
        showToast(err.message || 'Error submitting fare guide.', 'danger');
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Fare Guide'; }
    }
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleDateString('en-PH', {
            year: 'numeric', month: 'short', day: 'numeric'
        });
    } catch (_) { return dateStr; }
}

function getVehicleClass(vehicleType) {
    if (!vehicleType) return 'default';
    const v = vehicleType.toLowerCase();
    if (v.includes('pub') || v.includes('bus')) return 'bus';
    if (v.includes('puj') || v.includes('jeep')) return 'jeepney';
    if (v.includes('van')) return 'van';
    if (v.includes('tricycle') || v.includes('taxi')) return 'tricycle';
    return 'default';
}

function getStripeClass(vehicleType) {
    const cls = getVehicleClass(vehicleType);
    return 'fd-stripe-' + cls;
}

function getBadgeClass(vehicleType) {
    return getVehicleClass(vehicleType);
}

function vehicleLabel(type) {
    return (type || '').replace(/_/g, ' ');
}

// ── Toast notifications ─────────────────────────────────────────────────
function showToast(message, type) {
    type = type || 'info';
    const container = document.getElementById('fdToastContainer');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = 'fd-toast ' + type;
    const iconMap = { success: 'fa-check-circle', danger: 'fa-exclamation-circle', info: 'fa-info-circle' };
    toast.innerHTML = `<i class="fas ${iconMap[type] || 'fa-info-circle'}"></i> ${escapeHtml(message)}`;
    container.appendChild(toast);
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(40px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => { if (toast.parentNode) toast.remove(); }, 300);
    }, 4000);
}

// ── Fare Guides ─────────────────────────────────────────────────────────
async function loadFareGuides() {
    const grid = document.getElementById('fareGuidesGrid');
    if (grid) {
        grid.innerHTML = `<div class="fd-loading-spinner" style="grid-column: 1 / -1;">
            <i class="fas fa-circle-notch fa-spin"></i>
            <p>Loading fare guides...</p>
        </div>`;
    }

    const countEl = document.getElementById('fareGuidesCount');
    if (countEl) countEl.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i>';

    try {
        const data = await apiFetch('get_fare_guides');
        _allFareGuides = Array.isArray(data.fare_guides) ? data.fare_guides : [];

        // Dynamically populate vehicle type filter options
        const vehicleFilter = document.getElementById('vehicleFilter');
        if (vehicleFilter) {
            const activeVal = vehicleFilter.value;
            const distinctTypes = [...new Set(_allFareGuides.map(g => g.vehicle_type).filter(Boolean))];
            distinctTypes.sort();

            let html = '<option value="">All Vehicle Types</option>';
            distinctTypes.forEach(type => {
                const label = type.replace(/_/g, ' ');
                html += `<option value="${type}">${label}</option>`;
            });
            vehicleFilter.innerHTML = html;
            vehicleFilter.value = activeVal;
        }

        filterFareGuides();

        if (countEl) {
            countEl.textContent = `${_allFareGuides.length} guide(s)`;
        }
    } catch (err) {
        console.error('[LUPTO Fare] loadFareGuides failed:', err);
        if (grid) {
            grid.innerHTML = `<div class="fd-error-state" style="grid-column: 1 / -1;">
                <i class="fas fa-exclamation-circle"></i>
                <p>Failed to load fare data</p>
                <button class="fd-btn-refresh" onclick="loadFareGuides()" style="margin-top:12px;">
                    <i class="fas fa-redo"></i> Retry
                </button>
            </div>`;
        }
        if (countEl) countEl.textContent = 'Error';
    }
}

function filterFareGuides() {
    const search  = (document.getElementById('searchInput')?.value  || '').toLowerCase().trim();
    const vehicle = (document.getElementById('vehicleFilter')?.value || '');

    if (!Array.isArray(_allFareGuides)) return;
    const filtered = _allFareGuides.filter(guide => {
        const matchSearch  = !search  ||
            (guide.title || '').toLowerCase().includes(search) ||
            (guide.region || '').toLowerCase().includes(search) ||
            (guide.created_by_name || '').toLowerCase().includes(search) ||
            vehicleLabel(guide.vehicle_type).toLowerCase().includes(search);
        const matchVehicle = !vehicle || guide.vehicle_type === vehicle;
        return matchSearch && matchVehicle;
    });

    renderFareGuides(filtered);

    const countEl = document.getElementById('fareGuidesCount');
    if (countEl) {
        countEl.textContent = filtered.length === _allFareGuides.length
            ? `${_allFareGuides.length} guide(s)`
            : `${filtered.length} of ${_allFareGuides.length} guide(s)`;
    }
}

function renderFareGuides(guides) {
    const grid = document.getElementById('fareGuidesGrid');
    if (!grid) return;

    if (!guides || guides.length === 0) {
        grid.innerHTML = `<div class="fd-empty-state" style="grid-column: 1 / -1;">
            <i class="fas fa-inbox"></i>
            <p>No active fare guides found</p>
        </div>`;
        return;
    }

    const previousIds = new Set(Array.from(grid.querySelectorAll('.fd-fare-card')).map(card => card.dataset.guideId));

    grid.innerHTML = guides.map(guide => {
        const vLabel = vehicleLabel(guide.vehicle_type);
        const vClass = getBadgeClass(guide.vehicle_type);
        const stripeClass = getStripeClass(guide.vehicle_type);

        const isNew = previousIds.size > 0 && !previousIds.has(String(guide.id));
        const animateClass = isNew ? ' new-card-animate' : '';

        return `
        <div class="fd-fare-card${animateClass}" data-guide-id="${guide.id}" onclick="viewFareMatrix(${guide.id}, \`${escapeHtml(guide.title)}\`)">
            <div class="fd-card-stripe ${stripeClass}"></div>
            <div class="fd-card-body">
                <div class="fd-card-header-row">
                    <span class="fd-vehicle-badge ${vClass}">
                        <i class="fas ${vClass === 'bus' ? 'fa-bus' : vClass === 'jeepney' ? 'fa-shuttle-van' : vClass === 'van' ? 'fa-van-shuttle' : vClass === 'tricycle' ? 'fa-motorcycle' : 'fa-car'}"></i>
                        ${escapeHtml(vLabel)}
                    </span>
                    <span class="fd-region-tag">${escapeHtml(guide.region || '—')}</span>
                </div>
                <h4 class="fd-card-title">${escapeHtml(guide.title)}</h4>
                <div class="fd-card-meta-row">
                    <span><i class="fas fa-calendar-alt"></i> ${formatDate(guide.effective_date)}</span>
                    <span><i class="fas fa-user"></i> ${escapeHtml(guide.created_by_name)}</span>
                </div>
                <button class="fd-view-btn" onclick="event.stopPropagation(); viewFareMatrix(${guide.id}, \`${escapeHtml(guide.title)}\`)">
                    <i class="fas fa-table"></i> View Fare Matrix
                </button>
            </div>
        </div>`;
    }).join('');
}

// ── Fare Matrix ───────────────────────────────────────────────────────────
async function viewFareMatrix(guideId, title) {
    const section = document.getElementById('fareMatrixSection');
    const tbody   = document.getElementById('fareMatrixBody');
    const titleEl = document.getElementById('fareMatrixTitle');

    _currentGuideTitle = title || '';

    if (section) section.classList.add('active');
    if (titleEl) titleEl.innerHTML = `<i class="fas fa-table"></i> Fare Matrix — ${escapeHtml(title || '')}`;
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;padding:28px;color:#9ca3af;">
            <i class="fas fa-circle-notch fa-spin" style="font-size:20px;display:block;margin-bottom:8px;"></i>
            Loading matrix...
        </td></tr>`;
    }

    section.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    try {
        const data = await apiFetch('get_fare_matrices', { guide_id: guideId });
        _currentMatrixData = data.fare_matrices || [];

        if (_currentMatrixData.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;padding:40px;color:#9ca3af;">
                <i class="fas fa-inbox" style="font-size:28px;display:block;margin-bottom:8px;opacity:0.5;"></i>
                No fare matrix rows found for this guide.
            </td></tr>`;
        } else {
            tbody.innerHTML = _currentMatrixData.map(m => {
                const dist = parseFloat(m.distance_km);
                const regular = parseFloat(m.regular_fare);
                const discounted = parseFloat(m.discounted_fare);
                const savings = regular - discounted;
                return `
                <tr>
                    <td class="fd-col-distance">${dist.toFixed(1)} km</td>
                    <td class="fd-col-regular">₱${regular.toFixed(2)}</td>
                    <td class="fd-col-discounted">₱${discounted.toFixed(2)}</td>
                    <td style="color:#059669;font-weight:600;">${savings > 0 ? 'Save ₱' + savings.toFixed(2) : '—'}</td>
                </tr>`;
            }).join('');
        }
    } catch (err) {
        console.error('[LUPTO Fare] viewFareMatrix failed:', err);
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;padding:28px;color:#dc2626;">
                <i class="fas fa-exclamation-circle" style="font-size:20px;display:block;margin-bottom:8px;"></i>
                Failed to load matrix: ${escapeHtml(err.message)}
            </td></tr>`;
        }
    }
}

function closeFareMatrix() {
    const section = document.getElementById('fareMatrixSection');
    if (section) section.classList.remove('active');
    _currentMatrixData = [];
    _currentGuideTitle = '';
}

// ── CSV Export ────────────────────────────────────────────────────────────
function exportFareMatrix() {
    if (!_currentMatrixData || _currentMatrixData.length === 0) {
        showToast('No fare matrix data to export. View a guide first.', 'danger');
        return;
    }

    const title = (_currentGuideTitle || 'fare_matrix').replace(/[^a-z0-9]/gi, '_').toLowerCase();
    const headers = ['Distance (km)', 'Regular Fare (PHP)', 'Discounted Fare (PHP)', 'Savings (PHP)'];
    const rows = _currentMatrixData.map(m => {
        const dist = parseFloat(m.distance_km).toFixed(1);
        const regular = parseFloat(m.regular_fare).toFixed(2);
        const discounted = parseFloat(m.discounted_fare).toFixed(2);
        const savings = (parseFloat(m.regular_fare) - parseFloat(m.discounted_fare)).toFixed(2);
        return [dist, regular, discounted, savings];
    });

    const csv = [headers, ...rows].map(r => r.map(v => `"${v}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `${title}_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);

    showToast(`CSV exported: ${_currentMatrixData.length} rows`, 'success');
}

// ── Auto-refresh ──────────────────────────────────────────────────────────
function startAutoRefresh() {
    stopAutoRefresh();
    refreshInterval = setInterval(loadFareGuides, 30_000);
}

function stopAutoRefresh() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
}

// ── Initialize ────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    loadFareGuides();
    startAutoRefresh();
});

// ── Global visibility ─────────────────────────────────────────────────────
window.loadFareGuides   = loadFareGuides;
window.filterFareGuides = filterFareGuides;
window.viewFareMatrix   = viewFareMatrix;
window.closeFareMatrix  = closeFareMatrix;
window.exportFareMatrix = exportFareMatrix;
window.startAutoRefresh = startAutoRefresh;
window.stopAutoRefresh  = stopAutoRefresh;
window.openAddFareGuideModal = openAddFareGuideModal;
window.closeAddFareGuideModal = closeAddFareGuideModal;
window.updateCsvFileInfo = updateCsvFileInfo;
window.handleCsvDrop = handleCsvDrop;
window.submitFareGuideForm = submitFareGuideForm;
window.softRefreshFareData = async function() {
    await loadFareGuides();
};

})();
