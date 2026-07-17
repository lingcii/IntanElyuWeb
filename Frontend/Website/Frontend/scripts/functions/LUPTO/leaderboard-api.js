/**
 * LUPTO Leaderboard API
 * Role: lupto (read-only)
 * Client-side caching — no reload on tab switch
 */

'use strict';

(function () {
    if (window.__LB_LUPTO_LOADED__) return;
    window.__LB_LUPTO_LOADED__ = true;

    // Expose immediately for state restoration and event handlers to avoid race conditions
    window.refreshLeaderboard = refreshLeaderboard;
    window.refreshAll = refreshLeaderboard;
    window.debouncedSearch = debouncedSearch;
    window.applyFilters = applyFilters;
    window.clearSearch = clearSearch;
    window.goPage = goPage;
    window.selectRow = selectRow;
    window.showTouristModal = showTouristModal;
    window.closeTouristModal = closeTouristModal;

    const LB_API = window.API_CONFIG?.LUPTO + '/leaderboard' || 'http://localhost:8000/api/lupto/leaderboard';
    const MEDALS = { 1: '\u{1F947}', 2: '\u{1F948}', 3: '\u{1F949}' };
    const CURRENT_USER = window.__LB_CURRENT_USER__ || { id: 0, role: '' };
    const CACHE_TTL = 60000;

    function lbActionToUrl(action, params = {}) {
        const map = {
            'get_kpis': `${LB_API}/kpis`,
            'get_leaderboard': `${LB_API}`,
        };
        const base = map[action] || `${LB_API}/${action.replace(/_/g, '-')}`;
        const qs = Object.keys(params).length ? '?' + new URLSearchParams(params).toString() : '';
        return base + qs;
    }

    let _maxPoints = 0;
    let _currentPage = 1;
    let _totalRows = 0;
    let PAGE_SIZE = 20;
    let _searchTimer = null;
    let _intervalId = null;
    let _dirty = true;
    let _lastHash = '';

    window.__LB_LUPTO_CACHE__ = window.__LB_LUPTO_CACHE__ || { data: null, timestamp: 0, kpis: null };

    function _filterHash() {
        const search = (document.getElementById('searchInput')?.value || '').trim();
        const sortBy = document.getElementById('sortSelect')?.value || 'points_desc';
        const show = document.getElementById('showFilter')?.value || '20';
        return `${show}:${sortBy}:${currentPage()}:${search}`;
    }

    function currentPage() {
        return showIsAll() ? 1 : _currentPage;
    }

    function showIsAll() {
        return (document.getElementById('showFilter')?.value || '20') === 'all';
    }

    function isCacheFresh() {
        const cache = window.__LB_LUPTO_CACHE__;
        return !_dirty && cache.data && cache.timestamp && (Date.now() - cache.timestamp < CACHE_TTL);
    }

    function readShowFilter() {
        const val = (document.getElementById('showFilter')?.value || '20');
        PAGE_SIZE = val === 'all' ? 999999 : parseInt(val, 10);
    }

    function _startInterval() {
        _stopInterval();
        _intervalId = setInterval(() => { _dirty = true; refreshLeaderboard(); }, 60000);
    }

    function _stopInterval() {
        if (_intervalId) { clearInterval(_intervalId); _intervalId = null; }
    }

    async function refreshLeaderboard() {
        window.refreshLeaderboard = refreshLeaderboard;
        const icon = document.getElementById('refreshIcon');
        if (icon) icon.classList.add('fa-spin');
        _currentPage = 1;

        const cache = window.__LB_LUPTO_CACHE__;
        const canUseCache = !_dirty && cache.data && cache.timestamp && (Date.now() - cache.timestamp < CACHE_TTL);

        if (canUseCache) {
            if (cache.kpis) {
                renderKpis(cache.kpis);
            }
            renderTable(cache.data);
            updatePagination();
            if (icon) icon.classList.remove('fa-spin');
            return;
        }

        await Promise.all([loadKpis(), loadTable(true)]);
        if (icon) icon.classList.remove('fa-spin');
    }

    async function apiFetch(action, params = {}) {
        const url = lbActionToUrl(action, params);
        try {
            const data = await window.API_CONFIG.fetch(url);
            return data;
        } catch (netErr) {
            throw new Error('Network error: ' + netErr.message);
        }
    }

    async function loadKpis() {
        const cache = window.__LB_LUPTO_CACHE__;
        if (!_dirty && cache.kpis && cache.timestamp && (Date.now() - cache.timestamp < CACHE_TTL)) {
            renderKpis(cache.kpis);
            _maxPoints = cache.kpis.highest_points || 1;
            return;
        }
        try {
            const { kpis } = await apiFetch('get_kpis');
            cache.kpis = kpis;
            renderKpis(kpis);
            _maxPoints = kpis.highest_points || 1;
        } catch (err) {
            console.error('[LB] loadKpis:', err);
            ['kpiUsers', 'kpiHighest', 'kpiActivities'].forEach(id => setText(id, '\u2014'));
        }
    }

    function renderKpis(kpis) {
        setText('kpiUsers', formatNum(kpis.total_users));
        setText('kpiHighest', formatNum(kpis.highest_points) + ' pts');
        setText('kpiActivities', formatNum(kpis.total_activities));
    }

    async function loadTable(bypassCache = false) {
        const tbody = document.getElementById('leaderboardBody');
        if (!tbody) return;

        readShowFilter();

        if (!bypassCache && !_dirty && isCacheFresh()) {
            const cache = window.__LB_LUPTO_CACHE__;
            if (cache.data) {
                renderTable(cache.data);
                updatePagination();
                return;
            }
        }

        showTableSkeleton(tbody);

        const search = (document.getElementById('searchInput')?.value || '').trim();
        const sortBy = document.getElementById('sortSelect')?.value || 'points_desc';
        const show = document.getElementById('showFilter')?.value || '20';
        const offset = show === 'all' ? 0 : (_currentPage - 1) * PAGE_SIZE;

        const params = { show, search, sort: sortBy };
        if (show !== 'all') {
            params.limit = PAGE_SIZE;
            params.offset = offset;
        }

        try {
            const data = await apiFetch('get_leaderboard', params);
            _totalRows = data.total || 0;
            if (data.users && data.users.length > 0 && _maxPoints === 0) {
                _maxPoints = data.users[0].total_points || 1;
            }
            renderTable(data.users || []);
            updatePagination();

            const cache = window.__LB_LUPTO_CACHE__;
            cache.data = data.users || [];
            cache.total = _totalRows;
            cache.timestamp = Date.now();
            _lastHash = _filterHash();
            _dirty = false;
        } catch (err) {
            console.error('[LB] loadTable:', err);
            tbody.innerHTML = `<tr><td colspan="6" class="lb-empty"><i class="fas fa-exclamation-circle" style="color:#ef4444;"></i><p>${escHtml(err.message)}</p></td></tr>`;
        }
    }

    function updatePagination() {
        if (!showIsAll()) {
            const offset = (_currentPage - 1) * PAGE_SIZE;
            renderPagination(_totalRows, offset);
        } else {
            hidePagination();
        }
    }

    function showTableSkeleton(tbody) {
        tbody.innerHTML = `<tr><td colspan="6" class="lb-empty"><i class="fas fa-spinner fa-spin"></i><p>Loading leaderboard&hellip;</p></td></tr>`;
    }

    function renderTable(users) {
        const tbody = document.getElementById('leaderboardBody');
        if (!tbody) return;
        if (!users.length) {
            tbody.innerHTML = `<tr><td colspan="6" class="lb-empty"><i class="fas fa-user-slash"></i><p>No users found matching your filters.</p></td></tr>`;
            return;
        }
        const max = _maxPoints || 1;
        tbody.innerHTML = users.map((u, idx) => {
            const isTop3 = u.rank <= 3;
            const pct = Math.round((u.total_points / max) * 100);
            const initials = getInitials(u.full_name);
            const color = getAvatarColor(u.user_id);
            const lastAct = u.last_activity_date ? formatDateShort(u.last_activity_date) : '\u2014';
            const muni = u.municipality_name || '\u2014';
            const isCurrent = (CURRENT_USER.id && u.user_id === CURRENT_USER.id);
            const rowClass = [
                isTop3 ? `lb-row-top3 lb-row-rank-${u.rank}` : '',
                isCurrent ? 'lb-row-current' : '',
            ].filter(Boolean).join(' ');
            const rankHtml = isTop3
                ? `<span class="lb-rank-medal" data-rank="${u.rank}">${MEDALS[u.rank]}</span>`
                : `<span class="lb-rank-plain">#${u.rank}</span>`;
            const animDelay = Math.min(idx * 40, 600);
            const tagStyle = `animation: lbRowSlideIn 0.35s ease ${animDelay}ms both`;

            return `<tr class="${rowClass}"
            onclick="selectRow(this)"
            style="cursor:pointer;${tagStyle}"
            title="Click to highlight"
            data-user-id="${u.user_id}"
            data-user-name="${escHtmlAttr(u.full_name)}"
            data-user-points="${u.total_points}"
            data-user-activities="${u.completed_activities}"
            data-user-spots="${u.spots_managed}"
            data-user-role="${escHtmlAttr(u.role)}"
            data-user-muni="${escHtmlAttr(muni)}"
            data-user-last="${escHtmlAttr(lastAct)}"
            data-user-avatar="${escHtmlAttr(u.avatar || '')}">
            <td class="lb-td-rank">${rankHtml}</td>
            <td><div class="lb-user-cell">
                <div class="lb-user-avatar" style="background:${color};" aria-hidden="true">
                    ${u.avatar ? `<img src="${escHtmlAttr(u.avatar)}" alt="" onerror="this.style.display='none';this.parentElement.textContent='${escHtml(initials)}'">` : initials}
                </div>
                <div><div class="lb-user-cell-name">${escHtml(u.full_name)}${isCurrent ? '<span class="lb-you-badge">You</span>' : ''}</div>
                <div class="lb-user-cell-meta">ID: ${u.user_id}</div></div>
            </div></td>
            <td class="lb-td-muni">${escHtml(muni)}</td>
            <td><div class="lb-points-wrap">
                <div class="lb-points-track" title="${formatNum(u.total_points)} pts"><div class="lb-points-fill" style="width:${pct}%;"></div></div>
                <span class="lb-points-val">${formatNum(u.total_points)}</span>
            </div></td>
            <td class="lb-td-activities"><span class="lb-activities-val">${formatNum(u.completed_activities)}</span></td>
            <td class="lb-td-link">
                <button class="lb-link-btn" onclick="event.stopPropagation(); showTouristModal(${u.user_id})" title="View user details" aria-label="View details for ${escHtmlAttr(u.full_name)}">
                    <i class="fas fa-external-link-alt"></i>
                </button>
            </td>
        </tr>`;
        }).join('');
    }

    function selectRow(row) {
        const prev = document.querySelector('.lb-row-selected');
        if (prev && prev !== row) prev.classList.remove('lb-row-selected');
        row.classList.toggle('lb-row-selected');
    }

    function renderPagination(total, offset) {
        const bar = document.getElementById('paginationBar');
        const info = document.getElementById('paginationInfo');
        const btns = document.getElementById('paginationBtns');
        if (!bar || !info || !btns) return;
        bar.style.display = '';
        const from = total === 0 ? 0 : offset + 1;
        const to = Math.min(offset + PAGE_SIZE, total);
        info.textContent = total === 0 ? 'No results' : `Showing ${from}\u2013${to} of ${formatNum(total)}`;
        const totalPages = Math.ceil(total / PAGE_SIZE);
        let html = '';
        html += `<button class="lb-page-btn" onclick="goPage(${_currentPage - 1})" ${_currentPage === 1 ? 'disabled' : ''}>\u2039 Prev</button>`;
        const start = Math.max(1, _currentPage - 3);
        const end = Math.min(totalPages, start + 6);
        for (let p = start; p <= end; p++) {
            html += `<button class="lb-page-btn ${p === _currentPage ? 'active' : ''}" onclick="goPage(${p})">${p}</button>`;
        }
        html += `<button class="lb-page-btn" onclick="goPage(${_currentPage + 1})" ${_currentPage >= totalPages ? 'disabled' : ''}>Next \u203a</button>`;
        btns.innerHTML = html;
    }

    function hidePagination() {
        const bar = document.getElementById('paginationBar');
        if (bar) bar.style.display = 'none';
    }

    function goPage(p) {
        const totalPages = Math.ceil(_totalRows / PAGE_SIZE);
        if (p < 1 || p > totalPages) return;
        _currentPage = p;
        _lastHash = '';
        _dirty = true;
        loadTable(true);
        document.querySelector('.lb-table-card')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function debouncedSearch() {
        clearTimeout(_searchTimer);
        _searchTimer = setTimeout(() => {
            const h = _filterHash();
            if (h === _lastHash) return;
            _lastHash = h;
            _currentPage = 1;
            _dirty = true;
            loadTable(true);
        }, 320);
    }

    function applyFilters() {
        const h = _filterHash();
        if (h === _lastHash) return;
        _lastHash = h;
        _currentPage = 1;
        _dirty = true;
        loadTable(true);
    }

    function clearSearch() {
        const input = document.getElementById('searchInput');
        const sort = document.getElementById('sortSelect');
        const show = document.getElementById('showFilter');
        if (input) input.value = '';
        if (sort) sort.value = 'points_desc';
        if (show) show.value = '20';
        _currentPage = 1;
        _lastHash = '';
        _dirty = true;
        loadTable(true);
    }

    function showTouristModal(userId) {
        const row = document.querySelector(`tr[data-user-id="${userId}"]`);
        if (!row) return;
        const name = row.getAttribute('data-user-name') || 'Unknown';
        const points = row.getAttribute('data-user-points') || '0';
        const activities = row.getAttribute('data-user-activities') || '0';
        const spots = row.getAttribute('data-user-spots') || '0';
        const muni = row.getAttribute('data-user-muni') || '\u2014';
        const lastAct = row.getAttribute('data-user-last') || '\u2014';
        const avatar = row.getAttribute('data-user-avatar') || '';
        const initials = getInitials(name);
        const color = getAvatarColor(userId);
        const modal = document.getElementById('lbTouristModal');
        const body = document.getElementById('lbModalBody');
        if (!modal || !body) return;
        const rankEl = row.querySelector('.lb-rank-medal') || row.querySelector('.lb-rank-plain');
        const rankText = rankEl ? rankEl.textContent.trim() : '\u2014';
        body.innerHTML = `<div class="lb-modal-user-header">
        <div class="lb-modal-avatar" style="background:${color};">${avatar ? `<img src="${escHtml(avatar)}" alt="" onerror="this.style.display='none';this.parentElement.textContent='${escHtml(initials)}'" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">` : initials}</div>
        <div><h4 class="lb-modal-user-name">${escHtml(name)}</h4><span class="lb-modal-user-id">User ID: ${userId}</span></div>
    </div>
    <div class="lb-modal-stats">
        <div class="lb-modal-stat"><span class="lb-modal-stat-label">Total Points</span><span class="lb-modal-stat-value">${formatNum(points)}</span></div>
        <div class="lb-modal-stat"><span class="lb-modal-stat-label">Activities</span><span class="lb-modal-stat-value">${formatNum(activities)}</span></div>
        <div class="lb-modal-stat"><span class="lb-modal-stat-label">Rank</span><span class="lb-modal-stat-value">${escHtml(rankText)}</span></div>
        <div class="lb-modal-stat"><span class="lb-modal-stat-label">Municipality</span><span class="lb-modal-stat-value" style="font-size:13px;">${escHtml(muni)}</span></div>
        <div class="lb-modal-stat"><span class="lb-modal-stat-label">Spots Managed</span><span class="lb-modal-stat-value">${formatNum(spots)}</span></div>
        <div class="lb-modal-stat"><span class="lb-modal-stat-label">Last Active</span><span class="lb-modal-stat-value" style="font-size:13px;">${escHtml(lastAct)}</span></div>
    </div>`;
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeTouristModal() {
        const modal = document.getElementById('lbTouristModal');
        if (modal) modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeTouristModal();
    });

    function getInitials(name) {
        if (!name) return '?';
        const parts = name.trim().split(/\s+/);
        if (parts.length === 1) return parts[0][0].toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    function getAvatarColor(id) {
        const palette = ['#1a5276', '#1e8449', '#b7950b', '#7d3c98', '#1a6688', '#a04000', '#1f618d', '#196f3d', '#6e2f8c', '#2e86c1'];
        return palette[(id || 0) % palette.length];
    }

    function formatNum(n) {
        if (n === null || n === undefined) return '0';
        return Number(n).toLocaleString('en-PH');
    }

    function formatDateShort(dt) {
        if (!dt) return '\u2014';
        try { return new Date(dt).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }); } catch (_) { return dt; }
    }

    function escHtml(str) {
        if (str === null || str === undefined) return '';
        const d = document.createElement('div');
        d.textContent = String(str);
        return d.innerHTML;
    }

    function escHtmlAttr(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function setText(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    document.addEventListener('DOMContentLoaded', function () {
        readShowFilter();
        const cache = window.__LB_LUPTO_CACHE__;
        if (cache.data && cache.timestamp && (Date.now() - cache.timestamp < CACHE_TTL)) {
            if (cache.kpis) { renderKpis(cache.kpis); _maxPoints = cache.kpis.highest_points || 1; }
            renderTable(cache.data);
            _totalRows = cache.total || cache.data.length;
            updatePagination();
        } else {
            refreshLeaderboard();
        }
        _startInterval();
        window.refreshLeaderboard = refreshLeaderboard;
        window.refreshAll = refreshLeaderboard;
    });

    window.debouncedSearch = debouncedSearch;
    window.applyFilters = applyFilters;
    window.clearSearch = clearSearch;
    window.goPage = goPage;
    window.selectRow = selectRow;
    window.showTouristModal = showTouristModal;
    window.closeTouristModal = closeTouristModal;
    window.refreshLeaderboard = refreshLeaderboard;

})();
