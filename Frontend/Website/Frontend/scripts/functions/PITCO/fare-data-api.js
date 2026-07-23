/**
 * PICTO Transportation Fare Management — Redesigned API
 * Role: picto (full access)
 */
(function() {
'use strict';

const FD_API = (window.API_CONFIG?.PITCO ?? 'http://localhost:8000/api/pitco') + '/fare-data';

// Map legacy action strings to Laravel REST paths
function fdActionToUrl(action, params = {}) {
    const map = {
        'get_stats':              `${FD_API}/stats`,
        'get_fare_guides':        `${FD_API}/guides`,
        'get_fare_matrices':      `${FD_API}/matrices`,
        'get_uploads':            `${FD_API}/uploads`,
        'get_import_logs':        `${FD_API}/import-logs`,
        'get_validation_errors':  `${FD_API}/validation-errors`,
        'upload_pdf':             `${FD_API}/upload`,
        'sync_fare_guide':        `${FD_API}/sync`,
    };
    const base = map[action] || `${FD_API}/${action.replace(/_/g, '-')}`;
    const allParams = { ...params, _t: Date.now() };
    const qs   = '?' + new URLSearchParams(allParams).toString();
    return base + qs;
}

// ── State ─────────────────────────────────────────────────────────────────────
let _allGuides      = [];
let _matrixData     = [];
let _matrixTitle    = '';
let _searchTimer    = null;
let _confirmCb      = null;
let _lastRefresh    = 0;          // timestamp of last successful refresh
const REFRESH_TTL   = 60_000;     // 60 s — skip re-fetch if tab revisited quickly

// ── Boot ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    fd_initUploadZone();
    fd_refreshAll();
});

// ── SPA tab-show re-render ────────────────────────────────────────────────────
// When the SPA router shows this tab again (without reloading), re-render cards
// from the cached _allGuides so the grid is never empty. If data is stale, kick
// off a background refresh too.
document.addEventListener('tabshow', (e) => {
    // Only react if the event came from our own tab wrapper or a child of it
    const targetEl = e.target;
    const isOurTab = targetEl.id === 'spa-tab-fare-data.php' ||
                     targetEl.closest?.('#spa-tab-fare-data\\.php') !== null;
    if (!isOurTab) return;

    if (_allGuides.length > 0) {
        // Data is already loaded — just re-render immediately
        fd_filterGuides();
        fd_updateActiveBadges(_allGuides);
    }

    // If data is stale (or empty), do a background force refresh
    if (_allGuides.length === 0 || (Date.now() - _lastRefresh) > REFRESH_TTL) {
        fd_refreshAll(true);
    }
});

// ── Core fetch ────────────────────────────────────────────────────────────────
async function fdFetch(action, params = {}) {
    const url = fdActionToUrl(action, params);
    let resp;
    try { resp = await fetch(url, { credentials: 'include' }); }
    catch (e) { throw new Error('Network error: ' + e.message); }
    const raw = await resp.text();
    if (!raw.trim()) throw new Error(`Empty response (HTTP ${resp.status})`);
    let data;
    try { data = JSON.parse(raw); }
    catch (_) { throw new Error(`Non-JSON (HTTP ${resp.status}): ${raw.slice(0, 200)}`); }
    if (data.error) throw new Error(data.error);
    return data;
}

// ── Tab switching ─────────────────────────────────────────────────────────────
function fd_switchTab(name) {
    document.querySelectorAll('.fd-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.fd-tab-panel').forEach(p => p.classList.remove('active'));
    const btn = document.getElementById(`tab-${name}`);
    const panel = document.getElementById(`panel-${name}`);
    if (btn)   btn.classList.add('active');
    if (panel) panel.classList.add('active');
    if (name === 'history') fd_loadHistory();
}

// ── Full refresh ──────────────────────────────────────────────────────────────
async function fd_refreshAll(force = false) {
    // Skip if data is still fresh (e.g. user just switched tabs)
    if (!force && _allGuides.length > 0 && (Date.now() - _lastRefresh) < REFRESH_TTL) return;

    const icon = document.getElementById('refreshIcon');
    if (icon) icon.classList.add('fa-spin');
    fd_renderStatsSkeletons();
    await Promise.all([fd_loadStats(), fd_loadGuides()]);
    _lastRefresh = Date.now();
    if (icon) icon.classList.remove('fa-spin');
}

// ── Stats ─────────────────────────────────────────────────────────────────────
async function fd_loadStats() {
    try {
        const d = await fdFetch('get_stats');
        const animate = typeof window.animateKpiValue === 'function';

        if (animate) {
            window.animateKpiValue(document.getElementById('statGuides'), d.total_guides || 0);
            window.animateKpiValue(document.getElementById('statActive'), d.active_guides || 0);
            window.animateKpiValue(document.getElementById('statArchived'), d.archived_guides || 0);
            window.animateKpiValue(document.getElementById('statMunicipalities'), d.municipalities_count || 0);
            window.animateKpiValue(document.getElementById('statEntries'), d.total_routes || 0);
            window.animateKpiValue(document.getElementById('statTypes'), d.transportation_types || 0);
            window.animateKpiValue(document.getElementById('statLastUpdated'), d.last_updated_count || 0);
        } else {
            fd_setText('statGuides',  d.total_guides);
            fd_setText('statActive',  d.active_guides);
            fd_setText('statArchived', d.archived_guides);
            fd_setText('statMunicipalities', d.municipalities_count);
            fd_setText('statEntries', d.total_routes);
            fd_setText('statTypes', d.transportation_types);
            fd_setText('statLastUpdated', d.last_updated_count);
        }

        fd_setText('statLowest',  d.lowest_fare  != null ? '₱' + Number(d.lowest_fare).toFixed(2)  : '—');
        fd_setText('statHighest', d.highest_fare != null ? '₱' + Number(d.highest_fare).toFixed(2) : '—');
        fd_setText('statAvg',     d.avg_fare     != null ? '₱' + Number(d.avg_fare).toFixed(2)     : '—');
    } catch (e) { console.error('[FD] stats:', e); }
}

// ── Load guides ───────────────────────────────────────────────────────────────
async function fd_loadGuides() {
    fd_renderSkeletons();
    try {
        const d = await fdFetch('get_fare_guides');
        _allGuides = Array.isArray(d.fare_guides) ? d.fare_guides : [];

        // Dynamically populate vehicle type filter options
        const vehicleFilter = document.getElementById('fdVehicleFilter');
        if (vehicleFilter) {
            const activeVal = vehicleFilter.value;
            const distinctTypes = [...new Set(_allGuides.map(g => g.vehicle_type).filter(Boolean))];
            
            const labels = {
                'MPUJ': 'MPUJ (Modern PUJ)',
                'TPUJ': 'TPUJ (Traditional PUJ)',
                'PUB_Aircon': 'PUB Aircon',
                'PUB_Regular': 'PUB Regular',
                'PUB_Ordinary': 'PUB Ordinary',
                'TAXI': 'TAXI',
                'UVE': 'UVE (UV Express)',
                'Tricycle': 'Tricycle',
                'Van': 'Van'
            };

            const defaultTypes = ['MPUJ', 'TPUJ', 'PUB_Aircon', 'PUB_Regular', 'TAXI', 'UVE', 'Tricycle', 'Van'];
            const allTypes = [...new Set([...defaultTypes, ...distinctTypes])];

            let html = '<option value="">All Vehicle Types</option>';
            allTypes.forEach(type => {
                const label = labels[type] || type.replace(/_/g, ' ');
                html += `<option value="${type}">${label}</option>`;
            });
            vehicleFilter.innerHTML = html;
            if (activeVal) vehicleFilter.value = activeVal;
        }

        fd_updateActiveBadges(_allGuides);
        fd_filterGuides();

        // Auto-load matrix of the first/active guide
        if (_allGuides.length > 0) {
            const activeGuide = _allGuides.find(g => g.status === 'active') || _allGuides[0];
            fd_viewMatrix(activeGuide.id, activeGuide.title, false);
        } else {
            fd_closeMatrix();
        }
    } catch (e) {
        console.error('[FD] loadGuides:', e);
        fd_renderEmpty('Failed to load fare guides: ' + e.message);
    }
}

function fd_updateActiveBadges(guides) {
    const strip  = document.getElementById('fdBadgeStrip');
    if (!Array.isArray(guides)) { if (strip) strip.style.display = 'none'; return; }
    const active = guides.find(g => g.status === 'active');
    if (active && strip) {
        strip.style.display = 'flex';
        fd_setText('badgeGuideName', active.title);
        fd_setText('badgeEffective', active.effective_date ? fd_fmtDate(active.effective_date, false) : '—');
        fd_setText('badgeUpdated',   active.updated_at    ? fd_fmtDate(active.updated_at)             : '—');
    } else if (strip) {
        strip.style.display = 'none';
    }
}

// ── Filter & render cards ─────────────────────────────────────────────────────
function fd_debouncedFilter() {
    clearTimeout(_searchTimer);
    _searchTimer = setTimeout(fd_filterGuides, 280);
}

function fd_filterGuides() {
    if (!Array.isArray(_allGuides)) return;
    const search  = (document.getElementById('fdSearchInput')?.value   || '').toLowerCase().trim();
    const vehicle = document.getElementById('fdVehicleFilter')?.value  || '';
    const status  = document.getElementById('fdStatusFilter')?.value   || '';
    const sort    = document.getElementById('fdSortSelect')?.value     || 'newest';

    let filtered = _allGuides.filter(g => {
        const matchSearch  = !search  ||
            (g.title        || '').toLowerCase().includes(search) ||
            (g.region       || '').toLowerCase().includes(search) ||
            (g.vehicle_type || '').toLowerCase().includes(search);
        const matchVehicle = !vehicle || g.vehicle_type === vehicle;
        const matchStatus  = !status  || g.status === status;
        return matchSearch && matchVehicle && matchStatus;
    });

    // Sort
    filtered = [...filtered].sort((a, b) => {
        if (sort === 'oldest')    return new Date(a.created_at) - new Date(b.created_at);
        if (sort === 'fare_asc')  return (a._minFare || 0) - (b._minFare || 0);
        if (sort === 'fare_desc') return (b._minFare || 0) - (a._minFare || 0);
        return new Date(b.created_at) - new Date(a.created_at); // newest
    });

    fd_renderCards(filtered);
}

function fd_clearFilters() {
    ['fdSearchInput','fdVehicleFilter','fdStatusFilter'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    const sort = document.getElementById('fdSortSelect');
    if (sort) sort.value = 'newest';
    fd_filterGuides();
}

// ── Card rendering ────────────────────────────────────────────────────────────
function fd_renderCards(guides) {
    const grid = document.getElementById('fdCardsGrid');
    if (!grid) return;

    if (!guides.length) {
        fd_renderEmpty('No fare guides match your filters.');
        return;
    }

    const previousIds = new Set(Array.from(grid.querySelectorAll('.fd-fare-card')).map(card => card.dataset.guideId));

    grid.innerHTML = guides.map(g => fd_buildCard(g, previousIds)).join('');
}

// Store guides by id for safe lookup from event delegation
const _guideMap    = {};
const _matrixCache = {}; // guideId → fare_matrices array

function fd_buildCard(g, previousIds = new Set()) {
    // Cache guide data by id so we can look it up safely on click
    _guideMap[g.id] = g;

    const vt       = (g.vehicle_type || '').toLowerCase();
    const vtLabel  = (g.vehicle_type || '').replace(/_/g, ' ');

    const stripeClass = vt.includes('pub') ? 'fd-stripe-bus'
        : vt.includes('puj')      ? 'fd-stripe-jeepney'
        : vt.includes('van')      ? 'fd-stripe-van'
        : vt.includes('tricycle') ? 'fd-stripe-tricycle'
        : vt.includes('taxi')     ? 'fd-stripe-taxi'
        : 'fd-stripe-default';

    const iconDef = vt.includes('pub')      ? 'fd-vi-bus fa-bus'
        : vt.includes('puj')      ? 'fd-vi-jeepney fa-shuttle-van'
        : vt.includes('van')      ? 'fd-vi-van fa-van-shuttle'
        : vt.includes('tricycle') ? 'fd-vi-tricycle fa-motorcycle'
        : vt.includes('taxi')     ? 'fd-vi-taxi fa-taxi'
        : 'fd-vi-default fa-bus-simple';

    const [iconBg, iconFa] = iconDef.split(' ');

    const statusClass = g.status === 'active' ? 'fd-status-active'
        : g.status === 'draft' ? 'fd-status-draft'
        : 'fd-status-archived';
    const statusIcon = g.status === 'active' ? 'fa-circle-dot'
        : g.status === 'draft' ? 'fa-pen-to-square'
        : 'fa-archive';

    // Use data-guide-id — never embed title in onclick to avoid XSS/HTML-break
    // Active/draft guides show Archive button; archived guides show Activate button
    const actionItem = g.status === 'archived'
        ? `<button class="fd-dropdown-item btn-activate" onclick="event.stopPropagation(); fd_confirmSync(${g.id}, 'active'); fd_closeAllDropdowns()">
               <i class="fas fa-check-circle"></i> Activate
           </button>`
        : `<button class="fd-dropdown-item btn-archive" onclick="event.stopPropagation(); fd_confirmSync(${g.id}, 'archived'); fd_closeAllDropdowns()">
               <i class="fas fa-archive"></i> Archive
           </button>`;

    const editItem = `<button class="fd-dropdown-item" onclick="event.stopPropagation(); fd_openEditModal(${g.id}); fd_closeAllDropdowns()">
               <i class="fas fa-edit"></i> Edit
           </button>`;
    const deleteItem = `<button class="fd-dropdown-item btn-delete" onclick="event.stopPropagation(); fd_confirmDelete(${g.id}); fd_closeAllDropdowns()">
               <i class="fas fa-trash"></i> Delete
           </button>`;

    const isNew = previousIds.size > 0 && !previousIds.has(String(g.id));
    const animateClass = isNew ? ' new-card-animate' : '';

    return `
    <div class="fd-fare-card${animateClass}" data-guide-id="${g.id}">
        <div class="fd-fare-card-stripe ${stripeClass}"></div>
        <div class="fd-fare-card-body">
            <div class="fd-card-vehicle-row">
                <div class="fd-vehicle-icon-wrap">
                    <div class="fd-vehicle-icon ${iconBg}"><i class="fas ${iconFa}"></i></div>
                    <span class="fd-vehicle-type-label">${fd_escHtml(vtLabel)}</span>
                </div>
                <span class="fd-card-region-badge">${fd_escHtml(g.region || '—')}</span>
            </div>
            <div class="fd-card-title">${fd_escHtml(g.title)}</div>
            <div class="fd-card-meta">
                <span class="fd-card-meta-item"><i class="fas fa-calendar"></i> ${fd_fmtDate(g.effective_date, false)}</span>
                <span class="fd-card-meta-item"><i class="fas fa-user"></i> ${fd_escHtml(g.created_by_name || '—')}</span>
            </div>
        </div>
        <div class="fd-card-footer">
            <span class="fd-card-status ${statusClass}">
                <i class="fas ${statusIcon}"></i> ${fd_escHtml(g.status)}
            </span>
            <div class="fd-dropdown-wrap">
                <button class="fd-dropdown-trigger" onclick="event.stopPropagation(); fd_toggleDropdown(${g.id})" title="Actions">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <div class="fd-dropdown-menu" id="fd-dropdown-${g.id}">
                    ${actionItem}
                    ${editItem}
                    ${deleteItem}
                </div>
            </div>
        </div>
    </div>`;
}

// ── Card body click → view matrix ────────────────────────────────────────────
document.addEventListener('click', e => {
    const card = e.target.closest('.fd-fare-card');
    if (card && !e.target.closest('.fd-card-footer')) {
        const id    = parseInt(card.dataset.guideId, 10);
        const guide = _guideMap[id];
        if (guide) fd_viewMatrix(id, guide.title, true);
    }
});

// ── Matrix viewer ─────────────────────────────────────────────────────────────
async function fd_viewMatrix(guideId, title, shouldScroll = false) {
    const panel = document.getElementById('fdMatrixPanel');
    const tbody = document.getElementById('fdMatrixBody');
    fd_setText('fdMatrixTitle', title || 'Fare Matrix');
    if (panel) panel.style.display = 'block';
    if (shouldScroll) {
        panel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Use cached result if available
    if (_matrixCache[guideId]) {
        _matrixData  = _matrixCache[guideId];
        _matrixTitle = title || 'fare_matrix';
        fd_renderMatrixRows(tbody, _matrixData);
        return;
    }

    if (tbody) {
        tbody.innerHTML = Array(4).fill(`
            <tr>
                <td><div class="fd-skeleton" style="height:18px;width:70px;margin:2px 0;"></div></td>
                <td><div class="fd-skeleton" style="height:18px;width:80px;margin:2px 0;"></div></td>
                <td><div class="fd-skeleton" style="height:18px;width:80px;margin:2px 0;"></div></td>
                <td><div class="fd-skeleton" style="height:18px;width:100px;margin:2px 0;"></div></td>
            </tr>
        `).join('');
    }

    try {
        const data = await fdFetch('get_fare_matrices', { guide_id: guideId });
        _matrixData  = data.fare_matrices || [];
        _matrixTitle = title || 'fare_matrix';
        _matrixCache[guideId] = _matrixData; // cache for re-use

        fd_renderMatrixRows(tbody, _matrixData);
    } catch (e) {
        if (tbody) tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;padding:24px;color:#dc2626;">${fd_escHtml(e.message)}</td></tr>`;
    }
}

function fd_renderMatrixRows(tbody, rows) {
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;padding:32px;color:var(--text-muted);">No fare matrix data for this guide.</td></tr>`;
        return;
    }
    tbody.innerHTML = rows.map(m => {
        const reg  = parseFloat(m.regular_fare);
        const disc = parseFloat(m.discounted_fare);
        const savings = reg > 0 ? '₱' + (reg - disc).toFixed(2) + ' (' + Math.round((1 - disc/reg)*100) + '%)' : '—';
        return `<tr>
            <td>${parseFloat(m.distance_km).toFixed(1)} km</td>
            <td style="font-weight:700;color:var(--lupto-primary);">₱${reg.toFixed(2)}</td>
            <td style="color:#15803d;">₱${disc.toFixed(2)}</td>
            <td style="font-size:12px;color:var(--text-muted);">${savings}</td>
        </tr>`;
    }).join('');
}

function fd_closeMatrix() {
    const p = document.getElementById('fdMatrixPanel');
    if (p) p.style.display = 'none';
}

function fd_exportMatrix() {
    if (!_matrixData.length) { fd_toast('info', 'Load a fare matrix first.'); return; }
    const hdrs = ['Distance (km)', 'Regular Fare (PHP)', 'Discounted Fare (PHP)'];
    const rows = _matrixData.map(m => [
        parseFloat(m.distance_km).toFixed(1),
        parseFloat(m.regular_fare).toFixed(2),
        parseFloat(m.discounted_fare).toFixed(2)
    ]);
    const csv  = [hdrs, ...rows].map(r => r.map(v => `"${v}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const a    = Object.assign(document.createElement('a'), {
        href: URL.createObjectURL(blob),
        download: `${_matrixTitle.replace(/[^a-z0-9]/gi,'_').toLowerCase()}_${new Date().toISOString().slice(0,10)}.csv`
    });
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    URL.revokeObjectURL(a.href);
}

// ── Upload zone ───────────────────────────────────────────────────────────────
function fd_initUploadZone() {
    const zone  = document.getElementById('fdUploadZone');
    const input = document.getElementById('fdFileInput');
    if (!zone || !input) return;

    // Click anywhere in the zone → open file picker
    zone.addEventListener('click', () => input.click());

    zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', e => { e.preventDefault(); zone.classList.remove('drag-over'); });
    zone.addEventListener('drop', e => {
        e.preventDefault(); zone.classList.remove('drag-over');
        if (e.dataTransfer.files[0]) fd_handleUpload(e.dataTransfer.files[0]);
    });

    // Change fires once per file selection; reset value so same file can be re-selected
    input.addEventListener('change', e => {
        if (e.target.files[0]) {
            fd_handleUpload(e.target.files[0]);
            e.target.value = '';
        }
    });
}

async function fd_handleUpload(file) {
    if (!file.name.toLowerCase().endsWith('.csv') && file.type !== 'text/csv') {
        fd_toast('error', 'Please select a CSV file.'); return;
    }
    if (file.size > 20 * 1024 * 1024) {
        fd_toast('error', 'File exceeds 20 MB limit.'); return;
    }

    // Hide any previous result
    const resultEl = document.getElementById('fdUploadResult');
    if (resultEl) resultEl.style.display = 'none';

    fd_setProgress(true, 'Uploading…', 30);
    const formData = new FormData();
    formData.append('csv_file', file);



    try {
        const resp = await fetch(fdActionToUrl('upload_pdf'), { method: 'POST', body: formData, credentials: 'include' });
        if (!resp.ok) {
            const errText = await resp.text();
            throw new Error(`Server error (HTTP ${resp.status}): ${errText.slice(0, 150)}`);
        }
        fd_setProgress(true, 'Processing CSV…', 70);
        const result = await resp.json();
        fd_setProgress(true, 'Saving data…', 90);
        await new Promise(r => setTimeout(r, 400));
        fd_setProgress(false);

        if (result.success) {
            const errCount = result.errors?.length || 0;
            const html = `<div style="background:#dcfce7;border:1px solid #86efac;border-radius:10px;padding:16px;display:flex;gap:12px;align-items:flex-start;">
                <i class="fas fa-check-circle" style="color:#15803d;font-size:22px;margin-top:1px;"></i>
                <div>
                    <strong style="color:#15803d;">Upload Successful!</strong>
                    <p style="margin:4px 0 0;font-size:13px;color:#166534;">
                        Extracted <strong>${result.total_records}</strong> records — <strong>${result.valid_records}</strong> valid.
                        ${errCount ? `<span style="color:#b45309;"> · ${errCount} warning(s).</span>` : ''}
                    </p>
                    <p style="margin:6px 0 0;font-size:12px;color:#64748b;">The guide is now <strong>Active</strong> and visible to tourists.</p>
                </div>
            </div>`;
            fd_showResult(html);
            fd_toast('success', 'CSV uploaded and activated successfully.');
            fd_refreshAll(true);
            if (typeof window.notifyFareDataChanged === 'function') {
                window.notifyFareDataChanged();
            }
            fd_switchTab('browse');
        } else {
            fd_showResult(`<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:10px;padding:16px;">
                <strong style="color:#dc2626;"><i class="fas fa-exclamation-circle"></i> Upload Failed</strong>
                <p style="margin:6px 0 0;font-size:13px;color:#991b1b;">${fd_escHtml(result.error || 'Unknown error')}</p>
            </div>`);
            fd_toast('error', result.error || 'Upload failed.');
        }
    } catch (e) {
        fd_setProgress(false);
        fd_toast('error', 'Upload error: ' + e.message);
    }
    // Reset file input
    const inp = document.getElementById('fdFileInput');
    if (inp) inp.value = '';
}

function fd_setProgress(show, label = '', pct = 0) {
    const wrap = document.getElementById('fdProgressWrap');
    const fill = document.getElementById('fdProgressFill');
    const lbl  = document.getElementById('fdProgressLabel');
    const pctEl= document.getElementById('fdProgressPct');
    if (!wrap) return;
    if (show) {
        wrap.style.display = 'block';
        if (fill) fill.style.width = pct + '%';
        if (lbl)  lbl.textContent  = label;
        if (pctEl)pctEl.textContent= pct + '%';
    } else {
        if (fill) fill.style.width = '100%';
        if (pctEl)pctEl.textContent= '100%';
        setTimeout(() => { wrap.style.display = 'none'; if (fill) fill.style.width = '0%'; }, 500);
    }
}

function fd_showResult(html) {
    const el = document.getElementById('fdUploadResult');
    if (el) { el.style.display = 'block'; el.innerHTML = html; }
}

// ── Upload history ────────────────────────────────────────────────────────────
async function fd_loadHistory() {
    const tbody = document.getElementById('fdHistoryBody');
    if (tbody) tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading…</td></tr>`;
    try {
        const data = await fdFetch('get_uploads');
        const uploads = data.uploads || [];
        if (!uploads.length) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted);"><i class="fas fa-inbox" style="font-size:28px;display:block;margin-bottom:10px;"></i>No uploads yet.</td></tr>`;
            return;
        }
        const statusMap = { completed:'background:#dcfce7;color:#15803d;', failed:'background:#fee2e2;color:#dc2626;', processing:'background:#dbeafe;color:#1d4ed8;', pending:'background:#fef3c7;color:#b45309;' };
        tbody.innerHTML = uploads.map(u => {
            const st = statusMap[u.status] || 'background:#f1f5f9;color:#475569;';
            return `<tr>
                <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${fd_escHtml(u.file_name)}">${fd_escHtml(u.file_name)}</td>
                <td>${fd_escHtml(u.uploaded_by_name)}</td>
                <td style="font-size:12px;">${fd_fmtDate(u.created_at)}</td>
                <td><span style="padding:3px 9px;border-radius:12px;font-size:11px;font-weight:600;${st}">${fd_escHtml(u.status)}</span></td>
                <td style="text-align:center;">${u.valid_records} / ${u.total_records}</td>
                <td>
                    <button class="btn-gov btn-gov-secondary" style="padding:4px 9px;font-size:11px;" onclick="fd_showLogs(${u.id},'import')"><i class="fas fa-list"></i> Logs</button>
                    <button class="btn-gov btn-gov-secondary" style="padding:4px 9px;font-size:11px;margin-left:4px;" onclick="fd_showLogs(${u.id},'errors')"><i class="fas fa-exclamation-triangle"></i> Errors</button>
                </td>
            </tr>`;
        }).join('');
    } catch (e) {
        if (tbody) tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:32px;color:#dc2626;">${fd_escHtml(e.message)}</td></tr>`;
    }
}

async function fd_showLogs(uploadId, type) {
    const modal = document.getElementById('fdLogsModal');
    const title = document.getElementById('fdLogsTitle');
    const body  = document.getElementById('fdLogsBody');
    if (!modal) return;
    const isErrors = type === 'errors';
    if (title) title.innerHTML = isErrors ? '<i class="fas fa-exclamation-triangle"></i> Validation Errors' : '<i class="fas fa-list-alt"></i> Import Logs';
    if (body)  body.innerHTML  = '<div style="padding:24px;text-align:center;color:var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading…</div>';
    modal.classList.add('fd-open');

    try {
        const action = isErrors ? 'get_validation_errors' : 'get_import_logs';
        const param  = isErrors ? { upload_id: uploadId } : { upload_id: uploadId };
        const data   = await fdFetch(action, param);
        const items  = isErrors ? (data.validation_errors || []) : (data.import_logs || []);
        if (!items.length) {
            body.innerHTML = '<div style="padding:24px;text-align:center;color:var(--text-muted);">No entries found.</div>';
            return;
        }
        if (isErrors) {
            body.innerHTML = `<table style="width:100%;border-collapse:collapse;">
                <thead><tr style="background:#f8fafc;">
                    <th style="padding:8px 12px;font-size:11px;text-align:left;border-bottom:2px solid var(--border);">Row</th>
                    <th style="padding:8px 12px;font-size:11px;text-align:left;border-bottom:2px solid var(--border);">Field</th>
                    <th style="padding:8px 12px;font-size:11px;text-align:left;border-bottom:2px solid var(--border);">Error</th>
                    <th style="padding:8px 12px;font-size:11px;text-align:left;border-bottom:2px solid var(--border);">Value</th>
                </tr></thead>
                <tbody>${items.map(e => `<tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:8px 12px;">${e.row_number||'—'}</td>
                    <td style="padding:8px 12px;">${fd_escHtml(e.field_name||e.field||'—')}</td>
                    <td style="padding:8px 12px;color:#dc2626;">${fd_escHtml(e.error_message||e.error)}</td>
                    <td style="padding:8px 12px;font-family:monospace;font-size:12px;">${fd_escHtml(e.invalid_value||'—')}</td>
                </tr>`).join('')}</tbody></table>`;
        } else {
            const sev = { error:'#dc2626', warning:'#b45309', info:'#1d4ed8' };
            body.innerHTML = items.map(l => {
                const msg = l.message || l.action || '';
                const isErr = msg.toLowerCase().includes('error') || msg.toLowerCase().includes('failed');
                const isWarn = msg.toLowerCase().includes('warning');
                const color = isErr ? '#dc2626' : isWarn ? '#b45309' : sev[l.severity] || '#475569';
                return `<div style="padding:10px 16px;border-bottom:1px solid var(--border);color:${color};">
                <strong style="font-size:13px;">${fd_escHtml(l.action)}</strong>
                <span style="font-size:11px;color:var(--text-muted);margin-left:8px;">${fd_fmtDate(l.created_at)}</span>
                <div style="font-size:12px;color:var(--text-secondary);margin-top:3px;">${fd_escHtml(l.details)}</div>
            </div>`;
            }).join('');
        }
    } catch (e) {
        if (body) body.innerHTML = `<div style="padding:24px;text-align:center;color:#dc2626;">${fd_escHtml(e.message)}</div>`;
    }
}
function fd_closeLogs() {
    const m = document.getElementById('fdLogsModal');
    if (m) { m.classList.remove('fd-open'); m.style.removeProperty('display'); }
}

// ── Sync (activate / archive) with confirmation ───────────────────────────────
function fd_confirmSync(guideId, status) {
    const isActivate = status === 'active';

    const modal = document.getElementById('fdConfirmModal');
    if (!modal) {
        // Fallback: native confirm if modal element is missing
        if (confirm(isActivate ? 'Activate this fare guide?' : 'Archive this fare guide?')) {
            fd_doSync(guideId, status);
        }
        return;
    }

    // Icon
    document.getElementById('fdConfirmIcon').textContent = isActivate ? '✅' : '📦';

    // Title
    document.getElementById('fdConfirmTitle').innerHTML = isActivate
        ? '<i class="fas fa-check-circle" style="color:#15803d;"></i> Activate Fare Guide'
        : '<i class="fas fa-archive" style="color:#f59e0b;"></i> Archive Fare Guide';

    // Body text
    document.getElementById('fdConfirmText').innerHTML = isActivate
        ? 'Are you sure you want to <strong>activate</strong> this fare guide?<br><span style="font-size:12px;color:#64748b;">Any other active guide for the same vehicle type and region will be archived automatically.</span>'
        : 'Are you sure you want to <strong>archive</strong> this fare guide?<br><span style="font-size:12px;color:#64748b;">It will be moved to Archive Management and hidden from active guides.</span>';

    // OK button
    const okBtn = document.getElementById('fdConfirmOkBtn');
    if (okBtn) {
        okBtn.textContent    = isActivate ? 'Yes, Activate' : 'Yes, Archive';
        okBtn.style.background  = isActivate ? '' : '#f59e0b';
        okBtn.style.borderColor = isActivate ? '' : '#f59e0b';
        okBtn.style.color       = '#fff';
        okBtn.disabled          = false;
    }

    // Cancel button label
    const cancelBtn = document.querySelector('#fdConfirmModal .btn-gov-secondary');
    if (cancelBtn) cancelBtn.textContent = 'No, Cancel';

    // Store callback
    _confirmCb = () => fd_doSync(guideId, status);

    // Show modal using class (avoids inline style specificity wars)
    modal.classList.add('fd-open');
}

// Performs the actual API call after confirmation
async function fd_doSync(guideId, status) {
    const isActivate = status === 'active';
    const okBtn = document.getElementById('fdConfirmOkBtn');
    if (okBtn) {
        okBtn.disabled  = true;
        okBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + (isActivate ? 'Activating…' : 'Archiving…');
    }
    try {
        const resp = await fetch(fdActionToUrl('sync_fare_guide'), {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ guide_id: guideId, status }),
        });
        const d = await resp.json();
        if (d.error) throw new Error(d.error);
        fd_toast('success', isActivate
            ? 'Fare guide activated successfully.'
            : 'Fare guide archived. View it in Archive Management.');
        delete _matrixCache[guideId]; // invalidate cached matrix for this guide
        fd_refreshAll(true);
        if (typeof window.notifyFareDataChanged === 'function') {
            window.notifyFareDataChanged();
        }
    } catch (e) {
        fd_toast('error', e.message);
    } finally {
        if (okBtn) {
            okBtn.disabled  = false;
            okBtn.textContent = isActivate ? 'Yes, Activate' : 'Yes, Archive';
        }
    }
}

function fd_confirmOk() {
    const cb = _confirmCb;
    fd_closeConfirm();
    if (cb) cb();
}

function fd_closeConfirm() {
    const modal = document.getElementById('fdConfirmModal');
    if (modal) {
        modal.classList.remove('fd-open');
        modal.style.removeProperty('display');
    }
    const okBtn = document.getElementById('fdConfirmOkBtn');
    if (okBtn) {
        okBtn.style.background  = '';
        okBtn.style.borderColor = '';
        okBtn.style.color       = '';
        okBtn.disabled          = false;
    }
    const cancelBtn = document.querySelector('#fdConfirmModal .btn-gov-secondary');
    if (cancelBtn) cancelBtn.textContent = 'Cancel';
    _confirmCb = null;
}

// Close modals on Escape
document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    fd_closeConfirm();
    fd_closeLogs();
});

// ── Toast ─────────────────────────────────────────────────────────────────────
function fd_toast(type, message) {
    const icons = { success:'fa-check-circle', error:'fa-exclamation-circle', info:'fa-info-circle' };
    const container = document.getElementById('fdToastContainer');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `fd-toast ${type}`;
    toast.innerHTML = `<i class="fas ${icons[type]||icons.info} fd-toast-icon"></i><span class="fd-toast-msg">${fd_escHtml(message)}</span>`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.cssText += 'opacity:0;transform:translateX(16px);transition:all 0.3s ease;';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

// ── Skeleton & empty helpers ──────────────────────────────────────────────────
function fd_renderStatsSkeletons() {
    const ids = ['statGuides', 'statActive', 'statArchived', 'statTypes', 'statLastUpdated', 'statLowest', 'statHighest', 'statAvg'];
    ids.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.innerHTML = `<div class="fd-skeleton" style="height:26px;width:52px;display:inline-block;vertical-align:middle;"></div>`;
        }
    });
}

function fd_renderSkeletons() {
    const grid = document.getElementById('fdCardsGrid');
    if (!grid) return;
    grid.innerHTML = Array(6).fill('<div class="fd-skeleton fd-skeleton-card"></div>').join('');
}

function fd_renderEmpty(msg = 'No fare guides found.') {
    const grid = document.getElementById('fdCardsGrid');
    if (!grid) return;
    grid.innerHTML = `<div class="fd-empty" style="grid-column:1/-1;">
        <i class="fas fa-folder-open"></i>
        <h3>No Guides Found</h3>
        <p>${fd_escHtml(msg)}</p>
    </div>`;
}

// ── Utilities ─────────────────────────────────────────────────────────────────
function fd_escHtml(str) {
    if (str == null) return '';
    const d = document.createElement('div');
    d.textContent = String(str);
    return d.innerHTML;
}

function fd_fmtDate(dt, withTime = true) {
    if (!dt) return '—';
    try {
        // Append time component to date-only strings to avoid UTC-midnight timezone shift
        const normalized = /^\d{4}-\d{2}-\d{2}$/.test(dt) ? dt + 'T00:00:00' : dt;
        const opts = withTime
            ? { year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' }
            : { year:'numeric', month:'short', day:'numeric' };
        return new Date(normalized).toLocaleDateString('en-PH', opts);
    } catch (_) { return dt; }
}

function fd_setText(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val ?? '—';
}

function fd_openAddModal() {
    document.getElementById('aeGuideId').value = '';
    document.getElementById('aeTitle').value = '';
    document.getElementById('aeVehicleType').value = 'PUB_Aircon';
    document.getElementById('aeRegion').value = 'La Union';
    document.getElementById('aeEffectiveDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('aeRowsBody').innerHTML = '';
    document.getElementById('fdAddEditTitle').innerHTML = '<i class="fas fa-plus"></i> Add Fare Matrix';
    ae_addRow();
    document.getElementById('fdAddEditModal').classList.add('fd-open');
}

async function fd_openEditModal(guideId) {
    const g = _guideMap[guideId];
    if (!g) return;

    document.getElementById('aeGuideId').value = g.id;
    document.getElementById('aeTitle').value = g.title;
    document.getElementById('aeVehicleType').value = g.vehicle_type;
    document.getElementById('aeRegion').value = g.region;
    document.getElementById('aeEffectiveDate').value = g.effective_date ? g.effective_date.split('T')[0] : '';
    document.getElementById('fdAddEditTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Fare Matrix';

    const tbody = document.getElementById('aeRowsBody');
    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:12px;">Loading matrix...</td></tr>';
    document.getElementById('fdAddEditModal').classList.add('fd-open');

    try {
        const url = fdActionToUrl('get_fare_matrices', { guide_id: g.id });
        const resp = await fetch(url, { credentials: 'include' });
        const data = await resp.json();
        const rows = data.fare_matrices || [];
        tbody.innerHTML = '';
        if (rows.length === 0) {
            ae_addRow();
        } else {
            rows.forEach(r => {
                ae_addRow(r.distance_km, r.regular_fare, r.discounted_fare);
            });
        }
    } catch (e) {
        fd_toast('error', 'Failed to load matrices: ' + e.message);
        tbody.innerHTML = '';
        ae_addRow();
    }
}

function fd_closeAddEdit() {
    document.getElementById('fdAddEditModal').classList.remove('fd-open');
}

function ae_addRow(distance = '', regular = '', discounted = '') {
    const tbody = document.getElementById('aeRowsBody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td style="padding:4px;"><input type="number" step="0.1" required class="fd-input ae-dist" style="width:100%;padding:4px;border:1px solid var(--border);border-radius:4px;" value="${distance}" placeholder="e.g. 1.0"></td>
        <td style="padding:4px;"><input type="number" step="0.01" required class="fd-input ae-regular" style="width:100%;padding:4px;border:1px solid var(--border);border-radius:4px;" value="${regular}" placeholder="e.g. 15.00"></td>
        <td style="padding:4px;"><input type="number" step="0.01" class="fd-input ae-discount" style="width:100%;padding:4px;border:1px solid var(--border);border-radius:4px;" value="${discounted}" placeholder="e.g. 12.00"></td>
        <td style="padding:4px;text-align:center;"><button type="button" class="btn-gov" style="padding:4px 8px;background:#ef4444;border-color:#ef4444;color:white;" onclick="this.closest('tr').remove()"><i class="fas fa-trash-alt"></i></button></td>
    `;
    tbody.appendChild(tr);
}

async function fd_saveManual(event) {
    event.preventDefault();
    const saveBtn = document.getElementById('aeSaveBtn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    const guideId = document.getElementById('aeGuideId').value;
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
        fd_toast('error', 'Please add at least one row to the fare matrix.');
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

    const isEdit = !!guideId;
    const url = isEdit ? `${FD_API}/${guideId}` : `${FD_API}`;
    const method = isEdit ? 'PUT' : 'POST';

    try {
        const resp = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload)
        });
        const result = await resp.json();
        if (result.error) throw new Error(result.error);

        fd_toast('success', isEdit ? 'Fare matrix updated successfully.' : 'Fare matrix created successfully.');
        fd_closeAddEdit();
        delete _matrixCache[guideId];
        fd_refreshAll(true);
        if (typeof window.notifyFareDataChanged === 'function') {
            window.notifyFareDataChanged();
        }
    } catch (e) {
        fd_toast('error', e.message);
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = 'Save';
    }
}

function fd_confirmDelete(guideId) {
    const g = _guideMap[guideId];
    if (!g) return;

    const modal = document.getElementById('fdConfirmModal');
    if (!modal) {
        if (confirm(`Are you sure you want to permanently delete "${g.title}"?`)) {
            fd_doDelete(guideId);
        }
        return;
    }

    document.getElementById('fdConfirmIcon').textContent = '🗑️';
    document.getElementById('fdConfirmTitle').innerHTML = '<i class="fas fa-trash-alt" style="color:#ef4444;"></i> Delete Fare Matrix';
    document.getElementById('fdConfirmText').innerHTML = `Are you sure you want to permanently delete <strong>"${fd_escHtml(g.title)}"</strong>?<br><span style="font-size:12px;color:#64748b;">This action cannot be undone and will delete all associated fare matrix rows.</span>`;

    const okBtn = document.getElementById('fdConfirmOkBtn');
    if (okBtn) {
        okBtn.textContent = 'Yes, Delete';
        okBtn.style.background = '#ef4444';
        okBtn.style.borderColor = '#ef4444';
        okBtn.style.color = '#fff';
        okBtn.disabled = false;
    }

    _confirmCb = () => fd_doDelete(guideId);
    modal.classList.add('fd-open');
}

async function fd_doDelete(guideId) {
    const okBtn = document.getElementById('fdConfirmOkBtn');
    if (okBtn) {
        okBtn.disabled = true;
        okBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
    }
    try {
        const resp = await fetch(`${FD_API}/${guideId}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        const result = await resp.json();
        if (result.error) throw new Error(result.error);

        fd_toast('success', 'Fare guide and matrix deleted successfully.');
        delete _matrixCache[guideId];
        fd_refreshAll(true);
        if (typeof window.notifyFareDataChanged === 'function') {
            window.notifyFareDataChanged();
        }
    } catch (e) {
        fd_toast('error', e.message);
    } finally {
        fd_closeConfirm();
    }
}

function fd_toggleDropdown(id) {
    const activeMenu = document.getElementById(`fd-dropdown-${id}`);
    if (!activeMenu) return;
    const wasOpen = activeMenu.classList.contains('show');
    fd_closeAllDropdowns();
    if (!wasOpen) {
        activeMenu.classList.add('show');
    }
}

function fd_closeAllDropdowns() {
    document.querySelectorAll('.fd-dropdown-menu.show').forEach(menu => {
        menu.classList.remove('show');
    });
}

document.addEventListener('click', () => {
    fd_closeAllDropdowns();
});

// ── Global visibility ─────────────────────────────────────────────────────
window.fd_refreshAll      = fd_refreshAll;
window.fd_switchTab       = fd_switchTab;
window.fd_debouncedFilter = fd_debouncedFilter;
window.fd_filterGuides    = fd_filterGuides;
window.fd_clearFilters    = fd_clearFilters;
window.fd_exportMatrix    = fd_exportMatrix;
window.fd_closeMatrix     = fd_closeMatrix;
window.fd_confirmOk       = fd_confirmOk;
window.fd_closeConfirm    = fd_closeConfirm;
window.fd_closeLogs       = fd_closeLogs;
window.fd_confirmSync     = fd_confirmSync;
window.fd_showLogs        = fd_showLogs;
window.fd_viewMatrix      = fd_viewMatrix;
window.fd_loadHistory     = fd_loadHistory;
window.fd_toast           = fd_toast;
window.fd_openAddModal    = fd_openAddModal;
window.fd_openEditModal   = fd_openEditModal;
window.fd_closeAddEdit    = fd_closeAddEdit;
window.ae_addRow          = ae_addRow;
window.fd_saveManual      = fd_saveManual;
window.fd_confirmDelete   = fd_confirmDelete;
window.fd_toggleDropdown   = fd_toggleDropdown;
window.fd_closeAllDropdowns = fd_closeAllDropdowns;
window.softRefreshFareData = async function() {
    await fd_refreshAll(true);
};

})();
