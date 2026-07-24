/**
 * PICTO Archive Management — API & UI
 * Role: picto
 */
'use strict';

const AM_API = 'http://' + (window.location.hostname || '127.0.0.1') + ':8000/api/pitco/archive';

// Helper: map action → Laravel REST route
function amActionToUrl(action, params = {}) {
    const map = {
        'get_stats':                `${AM_API}/stats`,
        'get_archived_fares':       `${AM_API}/fares`,
        'get_archived_fare_detail': `${AM_API}/fares/${params.guide_id || ''}`,
        'restore_fare':             `${AM_API}/fares/${params.guide_id || ''}/restore`,
        'permanent_delete_fare':    `${AM_API}/fares/${params.guide_id || ''}`,
    };
    return map[action] || `${AM_API}/${action.replace(/_/g, '-')}`;
}

// ── State ─────────────────────────────────────────────────────────────────────
let _pendingRestoreId = null;
let _pendingDeleteId  = null;
let _searchTimer      = null;

// Pagination
const PAGE_SIZE = 10;
let _currentPage  = 1;
let _allFares     = [];

// ── Boot ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    am_refresh();
});

// ── Core fetch ────────────────────────────────────────────────────────────────
async function amFetch(action, params = {}) {
    const qs   = new URLSearchParams(params).toString();
    const url  = amActionToUrl(action, params) + (qs && !action.includes('guide_id') ? '?' + qs : '');
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

async function amPost(action, body = {}) {
    let method = 'POST';
    let fetchUrl = amActionToUrl(action, body);

    // permanent_delete uses DELETE in Laravel
    if (action === 'permanent_delete_fare') method = 'DELETE';
    if (action === 'restore_fare') fetchUrl = `${AM_API}/fares/${body.guide_id}/restore`;

    let resp;
    try {
        resp = await fetch(fetchUrl, {
            method,
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: method !== 'DELETE' ? JSON.stringify(body) : undefined,
        });
    } catch (e) { throw new Error('Network error: ' + e.message); }
    const raw = await resp.text();
    if (!raw.trim()) throw new Error(`Empty response (HTTP ${resp.status})`);
    let data;
    try { data = JSON.parse(raw); }
    catch (_) { throw new Error(`Non-JSON: ${raw.slice(0, 200)}`); }
    if (data.error) throw new Error(data.error);
    return data;
}

// ── Tab switching ─────────────────────────────────────────────────────────────
function am_switchTab(name) {
    document.querySelectorAll('.am-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.am-tab-panel').forEach(p => p.classList.remove('active'));
    const btn   = document.getElementById(`amTab-${name}`);
    const panel = document.getElementById(`amPanel-${name}`);
    if (btn)   btn.classList.add('active');
    if (panel) panel.classList.add('active');
}

// ── Full refresh ──────────────────────────────────────────────────────────────
async function am_refresh() {
    const icon = document.getElementById('amRefreshIcon');
    if (icon) icon.classList.add('fa-spin');
    await Promise.all([am_loadStats(), am_loadFares()]);
    if (icon) icon.classList.remove('fa-spin');
}

// ── Stats ─────────────────────────────────────────────────────────────────────
async function am_loadStats() {
    try {
        const d = await amFetch('get_stats');
        am_setText('amStatTotal', d.total_archived ?? '0');
        am_setText('amStatMonth', d.archived_this_month ?? '0');
        const fareModule = (d.modules || []).find(m => m.module === 'Transportation Fare');
        am_setText('amStatFare', fareModule ? fareModule.count : '0');
    } catch (e) {
        console.error('[AM] stats:', e);
    }
}

// ── Load archived fares ───────────────────────────────────────────────────────
async function am_loadFares() {
    _currentPage = 1;
    am_renderTableSkeleton();
    try {
        const search  = document.getElementById('amSearchInput')?.value  || '';
        const vehicle = document.getElementById('amVehicleFilter')?.value || '';
        const sort    = document.getElementById('amSortSelect')?.value    || 'newest';

        const d  = await amFetch('get_archived_fares', { search, vehicle_type: vehicle, sort });
        _allFares = d.fare_guides || [];
        am_renderPage();
    } catch (e) {
        console.error('[AM] loadFares:', e);
        am_renderTableEmpty('Failed to load: ' + e.message);
    }
}

function am_debouncedLoad() {
    clearTimeout(_searchTimer);
    _searchTimer = setTimeout(am_loadFares, 300);
}

function am_clearFilters() {
    ['amSearchInput', 'amVehicleFilter'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    const sort = document.getElementById('amSortSelect');
    if (sort) sort.value = 'newest';
    am_loadFares();
}

// ── Pagination ────────────────────────────────────────────────────────────────
function am_renderPage() {
    const totalPages = Math.max(1, Math.ceil(_allFares.length / PAGE_SIZE));
    _currentPage = Math.min(_currentPage, totalPages);
    const slice = _allFares.slice((_currentPage - 1) * PAGE_SIZE, _currentPage * PAGE_SIZE);

    am_renderTableRows(slice, (_currentPage - 1) * PAGE_SIZE);
    am_renderPagination(totalPages);
}

function am_goPage(page) {
    _currentPage = page;
    am_renderPage();
}

function am_renderPagination(totalPages) {
    const container = document.getElementById('amPagination');
    if (!container) return;

    if (totalPages <= 1) {
        container.style.display = 'none';
        return;
    }
    container.style.display = 'flex';

    let html = '';
    const prev = _currentPage > 1;
    const next = _currentPage < totalPages;

    html += `<button class="am-page-btn" ${prev ? '' : 'disabled'} onclick="am_goPage(${_currentPage - 1})">
                 <i class="fas fa-chevron-left"></i>
             </button>`;

    for (let p = 1; p <= totalPages; p++) {
        if (totalPages > 7 && Math.abs(p - _currentPage) > 2 && p !== 1 && p !== totalPages) {
            if (p === _currentPage - 3 || p === _currentPage + 3) html += `<span class="am-page-ellipsis">…</span>`;
            continue;
        }
        html += `<button class="am-page-btn ${p === _currentPage ? 'active' : ''}" onclick="am_goPage(${p})">${p}</button>`;
    }

    html += `<button class="am-page-btn" ${next ? '' : 'disabled'} onclick="am_goPage(${_currentPage + 1})">
                 <i class="fas fa-chevron-right"></i>
             </button>`;

    html += `<span class="am-page-info">
                 Showing ${(_currentPage - 1) * PAGE_SIZE + 1}–${Math.min(_currentPage * PAGE_SIZE, _allFares.length)} of ${_allFares.length}
             </span>`;

    container.innerHTML = html;
}

// ── Table rendering ───────────────────────────────────────────────────────────
function am_renderTableRows(guides, offset) {
    const tbody = document.getElementById('amFareTbody');
    if (!tbody) return;

    if (!guides.length && offset === 0) {
        am_renderTableEmpty('No archived transportation fare guides found.');
        return;
    }

    tbody.innerHTML = guides.map((g, i) => {
        const vtLabel   = (g.vehicle_type || '').replace(/_/g, ' ');
        const vtClass   = am_vtClass(g.vehicle_type);
        const minFare   = g.min_fare  != null ? '₱' + Number(g.min_fare).toFixed(2)  : '—';
        const maxFare   = g.max_fare  != null ? '₱' + Number(g.max_fare).toFixed(2)  : '—';
        const fareRange = g.min_fare  != null ? `${minFare} – ${maxFare}` : '—';

        return `<tr>
            <td class="am-td-num">${offset + i + 1}</td>
            <td>
                <div class="am-guide-cell">
                    <span class="am-vt-badge ${vtClass}">${am_escHtml(vtLabel)}</span>
                    <span class="am-guide-title">${am_escHtml(g.title)}</span>
                </div>
            </td>
            <td>${am_escHtml(g.region || '—')}</td>
            <td>${am_fmtDate(g.effective_date, false)}</td>
            <td class="am-fare-range">${fareRange}</td>
            <td>${am_fmtDate(g.archived_at)}</td>
            <td>${am_escHtml(g.archived_by_name || '—')}</td>
            <td class="am-actions-cell">
                <button class="am-btn am-btn-view"    onclick="am_viewGuide(${g.id})"    title="View details">
                    <i class="fas fa-eye"></i> View
                </button>
                <button class="am-btn am-btn-restore" onclick="am_confirmRestore(${g.id}, '${am_escAttr(g.title)}')" title="Restore">
                    <i class="fas fa-rotate-left"></i> Restore
                </button>
                <button class="am-btn am-btn-delete"  onclick="am_confirmDelete(${g.id},  '${am_escAttr(g.title)}')" title="Permanently delete">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>`;
    }).join('');
}

function am_renderTableSkeleton() {
    const tbody = document.getElementById('amFareTbody');
    if (!tbody) return;
    tbody.innerHTML = Array(5).fill(`
        <tr class="am-skeleton-row">
            ${Array(8).fill('<td><div class="am-skeleton"></div></td>').join('')}
        </tr>
    `).join('');
    const pg = document.getElementById('amPagination');
    if (pg) pg.style.display = 'none';
}

function am_renderTableEmpty(msg) {
    const tbody = document.getElementById('amFareTbody');
    if (!tbody) return;
    tbody.innerHTML = `
        <tr>
            <td colspan="8">
                <div class="am-empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>No Archived Records</h3>
                    <p>${am_escHtml(msg)}</p>
                </div>
            </td>
        </tr>`;
    const pg = document.getElementById('amPagination');
    if (pg) pg.style.display = 'none';
}

// ── View detail modal ─────────────────────────────────────────────────────────
async function am_viewGuide(guideId) {
    const modal = document.getElementById('amViewModal');
    const body  = document.getElementById('amViewBody');
    if (!modal || !body) return;

    body.innerHTML = '<div style="padding:40px;text-align:center;color:var(--text-muted);"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
    modal.style.display = 'flex';

    try {
        const d = await amFetch('get_archived_fare_detail', { guide_id: guideId });
        const g = d.guide;
        const matrices = d.fare_matrices || [];

        const vtLabel = (g.vehicle_type || '').replace(/_/g, ' ');

        const matrixHtml = matrices.length
            ? `<table class="am-detail-matrix">
                <thead>
                    <tr>
                        <th>Distance (km)</th>
                        <th>Regular Fare</th>
                        <th>Discounted Fare</th>
                        <th>Savings</th>
                    </tr>
                </thead>
                <tbody>
                    ${matrices.map(m => {
                        const reg  = parseFloat(m.regular_fare);
                        const disc = parseFloat(m.discounted_fare);
                        const sav  = reg > 0 ? '₱' + (reg - disc).toFixed(2) + ' (' + Math.round((1 - disc/reg)*100) + '%)' : '—';
                        return `<tr>
                            <td>${parseFloat(m.distance_km).toFixed(1)} km</td>
                            <td class="am-fare-regular">₱${reg.toFixed(2)}</td>
                            <td class="am-fare-disc">₱${disc.toFixed(2)}</td>
                            <td class="am-fare-sav">${sav}</td>
                        </tr>`;
                    }).join('')}
                </tbody>
               </table>`
            : '<p style="text-align:center;padding:24px;color:var(--text-muted);">No fare matrix data available.</p>';

        body.innerHTML = `
            <div class="am-detail-wrap">
                <!-- Header banner -->
                <div class="am-detail-banner am-banner-${am_vtClass(g.vehicle_type)}">
                    <div class="am-detail-banner-icon"><i class="fas fa-bus"></i></div>
                    <div>
                        <div class="am-detail-vt">${am_escHtml(vtLabel)}</div>
                        <div class="am-detail-title">${am_escHtml(g.title)}</div>
                        <div class="am-detail-region">${am_escHtml(g.region || '—')}</div>
                    </div>
                    <span class="am-detail-archived-badge"><i class="fas fa-box-archive"></i> Archived</span>
                </div>

                <!-- Meta grid -->
                <div class="am-detail-meta">
                    <div class="am-detail-meta-item">
                        <span class="am-meta-label">Guide ID</span>
                        <span class="am-meta-value">#${g.id}</span>
                    </div>
                    <div class="am-detail-meta-item">
                        <span class="am-meta-label">Effective Date</span>
                        <span class="am-meta-value">${am_fmtDate(g.effective_date, false)}</span>
                    </div>
                    <div class="am-detail-meta-item">
                        <span class="am-meta-label">Created By</span>
                        <span class="am-meta-value">${am_escHtml(g.created_by_name || '—')}</span>
                    </div>
                    <div class="am-detail-meta-item">
                        <span class="am-meta-label">Created On</span>
                        <span class="am-meta-value">${am_fmtDate(g.created_at)}</span>
                    </div>
                    <div class="am-detail-meta-item">
                        <span class="am-meta-label">Archived By</span>
                        <span class="am-meta-value">${am_escHtml(g.archived_by_name || '—')}</span>
                    </div>
                    <div class="am-detail-meta-item">
                        <span class="am-meta-label">Archived On</span>
                        <span class="am-meta-value">${am_fmtDate(g.archived_at)}</span>
                    </div>
                    ${g.plate_number ? `
                    <div class="am-detail-meta-item">
                        <span class="am-meta-label">Plate Number</span>
                        <span class="am-meta-value">${am_escHtml(g.plate_number)}</span>
                    </div>` : ''}
                    ${g.archived_reason ? `
                    <div class="am-detail-meta-item" style="grid-column:1/-1;">
                        <span class="am-meta-label">Archive Reason</span>
                        <span class="am-meta-value">${am_escHtml(g.archived_reason)}</span>
                    </div>` : ''}
                </div>

                <!-- Fare matrix -->
                <div class="am-detail-section">
                    <h4 class="am-detail-section-title"><i class="fas fa-table"></i> Fare Matrix (${matrices.length} entries)</h4>
                    <div class="am-detail-matrix-wrap">${matrixHtml}</div>
                </div>
            </div>`;
    } catch (e) {
        body.innerHTML = `<div style="padding:32px;text-align:center;color:#dc2626;">${am_escHtml(e.message)}</div>`;
    }
}

function am_closeView() {
    const m = document.getElementById('amViewModal');
    if (m) m.style.display = 'none';
}

// ── Restore ───────────────────────────────────────────────────────────────────
function am_confirmRestore(guideId, title) {
    _pendingRestoreId = guideId;
    const el = document.getElementById('amRestoreText');
    if (el) el.innerHTML = `Restore <strong>${am_escHtml(title)}</strong>?`;
    document.getElementById('amRestoreModal').style.display = 'flex';
}

function am_closeRestore() {
    _pendingRestoreId = null;
    document.getElementById('amRestoreModal').style.display = 'none';
}

async function am_doRestore() {
    if (!_pendingRestoreId) return;
    const id  = _pendingRestoreId;
    const btn = document.getElementById('amRestoreOkBtn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Restoring…'; }

    try {
        const d = await amPost('restore_fare', { guide_id: id });
        am_closeRestore();
        am_toast('success', d.message || 'Fare guide restored successfully.');
        am_refresh();
        if (typeof window.notifyFareDataChanged === 'function') {
            window.notifyFareDataChanged();
        }
    } catch (e) {
        am_toast('error', e.message);
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-rotate-left"></i> Restore'; }
    }
}

// ── Permanent delete ──────────────────────────────────────────────────────────
function am_confirmDelete(guideId, title) {
    _pendingDeleteId = guideId;
    const el = document.getElementById('amDeleteText');
    if (el) el.innerHTML = `Permanently delete <strong>${am_escHtml(title)}</strong>?`;
    document.getElementById('amDeleteModal').style.display = 'flex';
}

function am_closeDelete() {
    _pendingDeleteId = null;
    document.getElementById('amDeleteModal').style.display = 'none';
}

async function am_doDelete() {
    if (!_pendingDeleteId) return;
    const id  = _pendingDeleteId;
    const btn = document.getElementById('amDeleteOkBtn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting…'; }

    try {
        await amPost('permanent_delete_fare', { guide_id: id });
        am_closeDelete();
        am_toast('success', 'Fare guide permanently deleted.');
        am_refresh();
        if (typeof window.notifyFareDataChanged === 'function') {
            window.notifyFareDataChanged();
        }
    } catch (e) {
        am_toast('error', e.message);
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-trash"></i> Delete Permanently'; }
    }
}

// ── Close modals on Escape ────────────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    am_closeView(); am_closeRestore(); am_closeDelete();
});

// Close on overlay click
['amViewModal', 'amRestoreModal', 'amDeleteModal'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('click', function(e) {
            if (e.target === this) {
                am_closeView(); am_closeRestore(); am_closeDelete();
            }
        });
    }
});

// ── Toast ─────────────────────────────────────────────────────────────────────
function am_toast(type, message) {
    const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', info: 'fa-info-circle' };
    const container = document.getElementById('amToastContainer');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `am-toast ${type}`;
    toast.innerHTML = `<i class="fas ${icons[type] || icons.info} am-toast-icon"></i>
                       <span class="am-toast-msg">${am_escHtml(message)}</span>`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.cssText += 'opacity:0;transform:translateX(16px);transition:all 0.3s ease;';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

// ── Utilities ─────────────────────────────────────────────────────────────────
function am_vtClass(vt) {
    const v = (vt || '').toLowerCase();
    if (v.includes('pub'))      return 'am-vt-bus';
    if (v.includes('puj'))      return 'am-vt-jeepney';
    if (v.includes('van'))      return 'am-vt-van';
    if (v.includes('tricycle')) return 'am-vt-tricycle';
    if (v.includes('taxi'))     return 'am-vt-taxi';
    return 'am-vt-default';
}

function am_escHtml(str) {
    if (str == null) return '';
    const d = document.createElement('div');
    d.textContent = String(str);
    return d.innerHTML;
}

function am_escAttr(str) {
    return String(str || '').replace(/'/g, '\\\'');
}

function am_fmtDate(dt, withTime = true) {
    if (!dt) return '—';
    try {
        const normalized = /^\d{4}-\d{2}-\d{2}$/.test(dt) ? dt + 'T00:00:00' : dt;
        const opts = withTime
            ? { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }
            : { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(normalized).toLocaleDateString('en-PH', opts);
    } catch (_) { return dt; }
}

function am_setText(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val ?? '—';
}
