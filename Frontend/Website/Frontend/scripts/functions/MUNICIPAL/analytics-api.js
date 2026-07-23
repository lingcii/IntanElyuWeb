/**
 * MUNICIPAL Analytics Dashboard API
 * Role: municipal (read-only or write, scoped to own municipality)
 */
'use strict';

(function () {
    // Guard against duplicate execution on SPA re-navigation.
    if (window.__municipalAnalyticsLoaded) {
        if (typeof window.refreshAnalytics === 'function') window.refreshAnalytics();
        return; // Stop — already running
    }
    window.__municipalAnalyticsLoaded = true;

    // Expose immediately for state restoration and event handlers to avoid race conditions
    window.refreshAnalytics = refreshAnalytics;
    window.refreshAll = refreshAnalytics;
    window.onMonthFilterChange = onMonthFilterChange;

    const MA_API = window.API_CONFIG?.MUNICIPAL || 'http://localhost:8000/api/municipal';

    function maActionToUrl(action, params = {}) {
        const map = {
            'summary': `${MA_API}/analytics/summary`,
            'top_municipalities': `${MA_API}/analytics/top-municipalities`,
            'top_spots': `${MA_API}/analytics/top-spots`,
            'chart_data': `${MA_API}/analytics/chart-data`,
            'monthly_trend': `${MA_API}/analytics/monthly-trend`,
            'filter_options': `${MA_API}/analytics/filter-options`,
            'full': `${MA_API}/analytics/full`,
            'dashboard_data': `${MA_API}/analytics/dashboard-data`,
            'export': `${MA_API}/analytics/export`,
        };
        const base = map[action] || `${MA_API}/analytics/${action.replace(/_/g, '-')}`;
        const qs = Object.keys(params).length ? '?' + new URLSearchParams(params).toString() : '';
        return base + qs;
    }

    let _charts = {};
    let _autoRefreshTimer = null;
    let _allSpots = [];
    let _selectedCategoryTab = 'all';
    let _trendCurVisits = [];
    let _trendPrevVisits = [];
    let _trendYear = new Date().getFullYear();
    let _trendCurrentYear = new Date().getFullYear();
    let _trendCurrentMonth = new Date().getMonth() + 1;

    // Simple client-side cache mapping URL to response data
    const _apiCache = {};
    let _forceNextFetches = false;

    function _initAnalytics() {
        for (const key in _apiCache) delete _apiCache[key];
        refreshAnalytics(true);
        window.refreshAnalytics = refreshAnalytics;
        window.refreshAll = refreshAnalytics;
        startAutoRefresh();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', _initAnalytics);
    } else {
        _initAnalytics();
    }

    async function apiFetch(action, params = {}) {
        const paramsWithRefresh = _forceNextFetches ? { ...params, refresh: 1 } : params;
        const url = maActionToUrl(action, paramsWithRefresh);

        if (!_forceNextFetches && _apiCache[url]) {
            const cached = _apiCache[url];
            if (Date.now() - cached.timestamp < 1200000 && cached.data && cached.data.success) { // 20 minutes cache
                return cached.data;
            }
            delete _apiCache[url];
        }

        try {
            const data = await window.API_CONFIG.fetch(url);
            if (data && data.success) {
                _apiCache[url] = { data, timestamp: Date.now() };
            }
            return data;
        } catch (e) {
            delete _apiCache[url];
            throw new Error('Network error: ' + e.message);
        }
    }

    async function refreshAnalytics(force = false) {
        const isAnalyticsActive = !!(document.getElementById('spotTable') || document.getElementById('trendChart') || document.getElementById('categoryTabs'));
        if (!isAnalyticsActive) return;

        const icon = document.getElementById('refreshIcon');
        if (icon) icon.classList.add('fa-spin');

        if (force) {
            for (const key in _apiCache) delete _apiCache[key];
            _forceNextFetches = true;
        }

        try {
            await loadDashboard();
        } finally {
            _forceNextFetches = false;
        }

        if (icon) icon.classList.remove('fa-spin');

        const now = new Date();
        const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        const lastUpdatedEl = document.getElementById('lastUpdated');
        if (lastUpdatedEl) lastUpdatedEl.textContent = `Last updated: ${timeStr}`;
    }

    // Single consolidated fetch replacing 4 separate API calls
    async function loadDashboard() {
        const year = document.getElementById('filterYear')?.value || new Date().getFullYear();

        try {
            const data = await apiFetch('dashboard_data', { year });
            if (!data || !data.success) return;

            // 1. KPI Summary
            const s = data.summary;
            if (s) {
                const muniName = s.most_visited_muni || 'Your Municipality';
                setText('muniScopeName', muniName);
                setText('kpiMuniScope', muniName);
                setText('kpiSpots', fmtNum(s.total_spots));
                setText('kpiVisists', fmtNum(s.total_users));
                setText('kpiSpotsBadge', `↗ +${s.new_spots_30d} new`);
                setText('kpiVisitsBadge', `↗ +${s.new_users_30d} new`);
                const momSign = s.visits_month_pct >= 0 ? '+' : '';
                const momArrow = s.visits_month_pct >= 0 ? '↗' : '↘';
                setText('kpiMonthlyVisitedBadge', `${momArrow} ${momSign}${s.visits_month_pct}% vs ${s.visits_prev_month}`);
                setText('kpiTopCategory', escHtml(s.top_category));
                setText('kpiTopCategoryBadge', `${s.top_category_cnt} spots`);
            }

            // 2. Classification status
            buildClassificationStatus(toArr(data.class_dist));

            // 3. Top categories
            buildTopCategories(toArr(data.cat_dist));

            // 4. Municipality visits chart
            _muniChartData = toArr(data.visits_by_muni).sort((a, b) => b.total_visits - a.total_visits);
            buildMuniVisitsChart();

            // 5. Monthly trend
            _buildTrendChart(data, parseInt(year, 10));

            // 6. Top spots
            _allSpots = Array.isArray(data.spots) ? data.spots : Object.values(data.spots || {});
            renderSpotsTable();

        } catch (err) {
            console.error('[MA] loadDashboard:', err);
        }
    }

    function _buildTrendChart(data, year) {
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        destroyChart('trendChart');
        const ctx = document.getElementById('trendChart');
        if (!ctx) return;

        const currentYear = new Date().getFullYear();
        const currentMonth = new Date().getMonth() + 1;

        const curVisits = Array(12).fill(0);
        if (year === currentYear) {
            for (let i = currentMonth; i < 12; i++) curVisits[i] = null;
        }
        const prevVisits = Array(12).fill(0);

        toArr(data.trend_current).forEach(r => {
            const idx = r.month - 1;
            if (year === currentYear && r.month > currentMonth) { curVisits[idx] = null; }
            else { curVisits[idx] = parseInt(r.visits, 10); }
        });
        toArr(data.trend_previous).forEach(r => { prevVisits[r.month - 1] = parseInt(r.visits, 10); });

        _trendCurVisits = curVisits; _trendPrevVisits = prevVisits;
        _trendYear = year; _trendCurrentYear = currentYear; _trendCurrentMonth = currentMonth;

        const activeIdx = (year === currentYear) ? (currentMonth - 2 >= 0 ? currentMonth - 2 : 0) : 11;
        setText('kpiMonthlyVisited', fmtNum(curVisits[activeIdx] || 0));
        onMonthFilterChange();

        if (checkEmptyState('trendChart', [...curVisits, ...prevVisits])) return;

        const chartCtx = ctx.getContext('2d');
        const gradient = chartCtx.createLinearGradient(0, 0, 0, 240);
        gradient.addColorStop(0, 'rgba(15, 44, 89, 0.15)');
        gradient.addColorStop(1, 'rgba(15, 44, 89, 0.0)');

        _charts['trendChart'] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [
                    { label: `${year} Visits`, data: curVisits, borderColor: '#0F2C59', backgroundColor: gradient, borderWidth: 3, tension: 0.35, fill: true, pointRadius: 4, pointBackgroundColor: '#0F2C59', pointBorderColor: '#ffffff', pointBorderWidth: 2, pointHoverRadius: 6 },
                    { label: `${year - 1} Visits`, data: prevVisits, borderColor: '#94a3b8', backgroundColor: 'transparent', borderWidth: 1.5, borderDash: [5, 5], tension: 0.35, fill: false, pointRadius: 0 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', labels: { color: '#64748b', font: { size: 11 } } },
                    tooltip: { callbacks: { label: c => ` ${c.dataset.label}: ${c.parsed.y !== null ? fmtNum(c.parsed.y) : '—'} visits` } }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#e2e8f0' }, ticks: { color: '#64748b', font: { size: 10 }, callback: v => fmtK(v) } },
                    x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 11 } } }
                }
            }
        });
    }

    function clearFilters() {
        ['filterCategory', 'filterStatus'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        refreshAnalytics();
    }

    function closeExportModal() {
        const modal = document.getElementById('exportModal');
        if (modal) modal.style.display = 'none';
    }

    function exportData(format) {
        const modal = document.getElementById('exportModal');
        if (modal) {
            modal.style.display = 'flex';
            modal.setAttribute('data-format', format);
        } else {
            triggerExport(format, 'full');
        }
    }

    function triggerExport(format, type) {
        closeExportModal();
        const year = document.getElementById('filterYear')?.value || new Date().getFullYear();
        const url = maActionToUrl('export', { format, type, year });
        window.open(url, '_blank');
    }

    function startAutoRefresh() {
        if (_autoRefreshTimer) clearInterval(_autoRefreshTimer);
        _autoRefreshTimer = setInterval(refreshAnalytics, 300000); // 5 minutes
    }

    function stopAutoRefresh() {
        if (_autoRefreshTimer) { clearInterval(_autoRefreshTimer); _autoRefreshTimer = null; }
    }

    function toggleAutoRefresh() {
        const toggle = document.getElementById('autoRefreshToggle');
        if (toggle && toggle.checked) {
            startAutoRefresh();
        } else {
            stopAutoRefresh();
        }
    }

    // ── KPI Summary
    async function loadSummary() {
        try {
            const data = await apiFetch('summary');
            const s = data.summary;

            const muniName = s.most_visited_muni || 'Your Municipality';
            setText('muniScopeName', muniName);
            setText('kpiMuniScope', muniName);

            setText('kpiSpots', fmtNum(s.total_spots));
            setText('kpiVisists', fmtNum(s.total_users));

            // Dynamic badges
            setText('kpiSpotsBadge', `↗ +${s.new_spots_30d} new`);
            setText('kpiVisitsBadge', `↗ +${s.new_users_30d} new`);

            const momSign = s.visits_month_pct >= 0 ? '+' : '';
            const momArrow = s.visits_month_pct >= 0 ? '↗' : '↘';
            const prevMonth = s.visits_prev_month || 'Jun';
            setText('kpiMonthlyVisitedBadge', `${momArrow} ${momSign}${s.visits_month_pct}% vs ${prevMonth}`);

            // Card 4: Top Category
            setText('kpiTopCategory', escHtml(s.top_category));
            setText('kpiTopCategoryBadge', `${s.top_category_cnt} spots`);
        } catch (err) { console.error('[MA] loadSummary:', err); }
    }

    // ── Line Chart & Stats Calculations
    async function loadTrendChart() {
        const year = parseInt(document.getElementById('filterYear')?.value || new Date().getFullYear(), 10);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        destroyChart('trendChart');
        const ctx = document.getElementById('trendChart');
        if (!ctx) return;

        try {
            const data = await apiFetch('monthly_trend', { year });
            const currentYear = new Date().getFullYear();
            const currentMonth = new Date().getMonth() + 1;

            const curVisits = Array(12).fill(0);
            if (year === currentYear) {
                for (let i = currentMonth; i < 12; i++) {
                    curVisits[i] = null;
                }
            }
            const prevVisits = Array(12).fill(0);

            toArr(data.current).forEach(r => {
                const idx = r.month - 1;
                if (year === currentYear && r.month > currentMonth) {
                    curVisits[idx] = null;
                } else {
                    curVisits[idx] = parseInt(r.visits, 10);
                }
            });
            toArr(data.previous).forEach(r => { prevVisits[r.month - 1] = parseInt(r.visits, 10); });

            // Store for month filter
            _trendCurVisits = curVisits;
            _trendPrevVisits = prevVisits;
            _trendYear = year;
            _trendCurrentYear = currentYear;
            _trendCurrentMonth = currentMonth;

            // Update monthly visited KPI
            const activeMonthIndex = (year === currentYear) ? (currentMonth - 2 >= 0 ? currentMonth - 2 : 0) : 11;
            const currentMonthVisits = curVisits[activeMonthIndex] || 0;
            setText('kpiMonthlyVisited', fmtNum(currentMonthVisits));

            // Initialize monthly visitors display
            onMonthFilterChange();

            if (checkEmptyState('trendChart', [...curVisits, ...prevVisits])) return;

            const chartCtx = ctx.getContext('2d');
            const gradient = chartCtx.createLinearGradient(0, 0, 0, 240);
            gradient.addColorStop(0, 'rgba(15, 44, 89, 0.15)');
            gradient.addColorStop(1, 'rgba(15, 44, 89, 0.0)');

            _charts['trendChart'] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [
                        {
                            label: `${year} Visits`,
                            data: curVisits,
                            borderColor: '#0F2C59',
                            backgroundColor: gradient,
                            borderWidth: 3,
                            tension: 0.35,
                            fill: true,
                            pointRadius: 4,
                            pointBackgroundColor: '#0F2C59',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointHoverRadius: 6
                        },
                        {
                            label: `${year - 1} Visits`,
                            data: prevVisits,
                            borderColor: '#94a3b8',
                            backgroundColor: 'transparent',
                            borderWidth: 1.5,
                            borderDash: [5, 5],
                            tension: 0.35,
                            fill: false,
                            pointRadius: 0
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: { color: '#64748b', font: { size: 11 } }
                        },
                        tooltip: {
                            callbacks: {
                                label: c => ` ${c.dataset.label}: ${c.parsed.y !== null ? fmtNum(c.parsed.y) : '—'} visits`
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#e2e8f0' },
                            ticks: { color: '#64748b', font: { size: 10 }, callback: v => fmtK(v) }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#64748b', font: { size: 11 } }
                        }
                    }
                }
            });
        } catch (err) { console.error('[MA] loadTrendChart:', err); }
    }

    function onMonthFilterChange() {
        const sel = document.getElementById('filterMonth');
        const display = document.getElementById('statMonthlyVisitors');
        if (!display) return;

        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const selectedMonth = sel ? sel.value : 'all';

        if (selectedMonth === 'all') {
            let total = 0;
            _trendCurVisits.forEach(v => { if (v !== null) total += v; });
            display.textContent = fmtNum(total) + ' visitors';
        } else {
            const monthIdx = parseInt(selectedMonth, 10) - 1;
            const visits = _trendCurVisits[monthIdx];
            const monthName = months[monthIdx];
            if (visits !== null && visits !== undefined && visits > 0) {
                display.textContent = monthName + ' - ' + fmtNum(visits) + ' visitors';
            } else if (_trendYear === _trendCurrentYear && monthIdx >= _trendCurrentMonth) {
                display.textContent = monthName + ' - Not yet available';
            } else {
                display.textContent = monthName + ' - 0 visitors';
            }
        }
    }

    // ── Chart Data Calculations (Categories, Classifications, Revenue)
    async function loadChartData() {
        try {
            const year = document.getElementById('filterYear')?.value || new Date().getFullYear();
            const category = document.getElementById('filterCategory')?.value || '';
            const status = document.getElementById('filterStatus')?.value || '';
            const data = await apiFetch('chart_data', { year, category, spot_status: status });

            // 1. Classification Status Sidebar Card
            buildClassificationStatus(toArr(data.class_dist));

            // 2. Top Categories Progress Card
            buildTopCategories(toArr(data.cat_dist));

            // 3. Monthly Tourism Revenue Horizontal Bar Chart
            buildRevenueChart(toArr(data.monthly_visits), year);

        } catch (err) { console.error('[MA] loadChartData:', err); }
    }

    function buildClassificationStatus(classes) {
        const container = document.getElementById('classificationList');
        if (!container) return;

        if (!classes || classes.length === 0) {
            container.innerHTML = '<div class="chart-empty-state"><p>No classification data</p></div>';
            return;
        }

        const mapping = {
            'EXIST': { label: 'Existing', dot: 'green', fill: 'green' },
            'EXISTING': { label: 'Existing', dot: 'green', fill: 'green' },
            'EMERGE': { label: 'Emerging', dot: 'blue', fill: 'blue' },
            'EMERGING': { label: 'Emerging', dot: 'blue', fill: 'blue' },
            'POTENTIAL': { label: 'Potential', dot: 'purple', fill: 'purple' }
        };

        const totalSpots = classes.reduce((sum, c) => sum + (c.cnt ?? c.count ?? c.total ?? 0), 0);

        container.innerHTML = classes.map(c => {
            const clsKey = (c.cls || c.classification_status || c.status || '').toUpperCase();
            const conf = mapping[clsKey] || { label: c.cls || c.classification_status || 'Unknown', dot: 'yellow', fill: 'yellow' };
            const count = c.cnt ?? c.count ?? c.total ?? 0;
            const pct = totalSpots > 0 ? Math.round((count / totalSpots) * 100) : 0;
            const avgRate = c.avg_rating ? parseFloat(c.avg_rating).toFixed(1) : '0.0';

            return `
        <div class="pa-quality-item">
            <div class="pa-quality-meta">
                <span class="pa-quality-name">
                    <span class="pa-quality-dot ${conf.dot}"></span>
                    ${conf.label}
                    <span class="pa-quality-trend">★ ${avgRate}</span>
                </span>
                <span>${count} spots (${pct}%)</span>
            </div>
            <div class="pa-quality-bar-track">
                <div class="pa-quality-bar-fill ${conf.fill}" style="width: ${pct}%"></div>
            </div>
        </div>`;
        }).join('');
    }

    function buildTopCategories(cats) {
        const container = document.getElementById('categoryList');
        if (!container) return;

        if (!cats || cats.length === 0) {
            container.innerHTML = '<div class="chart-empty-state"><p>No category data</p></div>';
            return;
        }

        const colorMap = {
            'Beach': 'beach',
            'Nature': 'nature',
            'Mountain': 'nature',
            'Heritage': 'heritage',
            'Historical': 'heritage',
            'Cultural': 'cultural',
            'Scenic': 'scenic',
            'Waterfalls': 'scenic',
            'Adventure': 'adventure',
            'Farm': 'heritage',
            'Religious': 'cultural',
            'Other': 'other'
        };

        const topCats = cats.slice(0, 10);
        const maxCount = Math.max(...topCats.map(c => c.cnt ?? c.count ?? c.total ?? 0), 1);

        container.innerHTML = topCats.map((c, i) => {
            const rank = String(i + 1).padStart(2, '0');
            const catName = c.category || c.name || c.cat || 'Other';
            const count = c.cnt ?? c.count ?? c.total ?? 0;
            const colorClass = colorMap[catName] || 'other';
            const pct = Math.round((count / maxCount) * 100);

            return `
        <div class="pa-cat-progress-row">
            <span class="pa-cat-rank">${rank}</span>
            <span class="pa-cat-name">
                <span class="pa-quality-dot bg-${colorClass}"></span>
                ${catName}
                <span class="pa-cat-count">${count} spots</span>
            </span>
            <div class="pa-cat-bar-container">
                <div class="pa-cat-bar-fill bg-${colorClass}" style="width: ${pct}%"></div>
            </div>
        </div>`;
        }).join('');
    }

    function buildRevenueChart(monthlyData, year) {
        destroyChart('revenueChart');
        const ctx = document.getElementById('revenueChart');
        if (!ctx) return;

        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const monthlyRevenue = Array(12).fill(0);
        const currentYear = new Date().getFullYear();
        const currentMonth = new Date().getMonth() + 1;

        if (parseInt(year, 10) === currentYear) {
            for (let i = currentMonth; i < 12; i++) {
                monthlyRevenue[i] = null;
            }
        }

        monthlyData.forEach(r => {
            const idx = r.month - 1;
            if (parseInt(year, 10) === currentYear && r.month > currentMonth) {
                monthlyRevenue[idx] = null;
            } else {
                monthlyRevenue[idx] = parseFloat(r.estimated_revenue || r.total_visits * 150) || 0;
            }
        });

        const activeMonths = [];
        const activeRevenues = [];
        for (let i = 0; i < 12; i++) {
            if (monthlyRevenue[i] !== null) {
                activeMonths.push(months[i]);
                activeRevenues.push(monthlyRevenue[i]);
            }
        }

        if (checkEmptyState('revenueChart', activeRevenues)) return;

        const maxRev = Math.max(...activeRevenues, 1);

        _charts['revenueChart'] = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: activeMonths,
                datasets: [{
                    data: activeRevenues,
                    backgroundColor: 'rgba(15, 44, 89, 0.8)',
                    borderColor: '#0F2C59',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return ` Est. Revenue: ₱${context.raw.toLocaleString('en-PH')}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: '#e2e8f0' },
                        ticks: { color: '#64748b', font: { size: 10 }, callback: v => fmtK(v) },
                        suggestedMax: maxRev * 1.25
                    },
                    y: {
                        grid: { display: false },
                        ticks: { color: '#64748b', font: { size: 11 } }
                    }
                }
            },
            plugins: [{
                id: 'valueLabels',
                afterDatasetsDraw(chart) {
                    const { ctx } = chart;
                    ctx.save();
                    ctx.font = 'bold 11px sans-serif';
                    ctx.fillStyle = '#64748b';
                    ctx.textBaseline = 'middle';

                    chart.data.datasets.forEach((dataset, datasetIndex) => {
                        const meta = chart.getDatasetMeta(datasetIndex);
                        meta.data.forEach((bar, index) => {
                            const value = dataset.data[index];
                            const labelText = '₱' + fmtK(value);
                            ctx.fillText(labelText, bar.x + 5, bar.y);
                        });
                    });
                    ctx.restore();
                }
            }]
        });
    }

    // ── Top Tourist Spots Table
    async function loadTopSpots() {
        const tbody = document.getElementById('spotTableBody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="pa-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>';

        try {
            const data = await apiFetch('top_spots', { limit: 10 });
            _allSpots = Array.isArray(data.spots) ? data.spots : Object.values(data.spots || {});
            renderSpotsTable();
        } catch (err) {
            console.error('[MA] loadTopSpots:', err);
            if (tbody) tbody.innerHTML = `<tr><td colspan="7" class="pa-empty"><p>${escHtml(err.message)}</p></td></tr>`;
        }
    }

    function renderSpotsTable() {
        const tbody = document.getElementById('spotTableBody');
        if (!tbody) return;

        const filtered = (_selectedCategoryTab === 'all'
            ? _allSpots
            : _allSpots.filter(s => (s.category || '').toLowerCase().includes(_selectedCategoryTab.toLowerCase()))
        ).slice(0, 10);

        if (filtered.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="pa-empty"><p>No tourist spots found for this category.</p></td></tr>';
            return;
        }

        const maxVisits = Math.max(..._allSpots.map(s => s.visits), 1);
        const colorMap = {
            'Beach': 'beach',
            'Nature': 'nature',
            'Mountain': 'nature',
            'Heritage': 'heritage',
            'Historical': 'heritage',
            'Cultural': 'cultural',
            'Scenic': 'scenic',
            'Waterfalls': 'scenic',
            'Adventure': 'adventure',
            'Farm': 'heritage',
            'Religious': 'cultural',
            'Other': 'other'
        };

        tbody.innerHTML = filtered.map((s, i) => {
            const rank = String(i + 1).padStart(2, '0');
            const colorClass = colorMap[s.category] || 'other';
            const rating = s.rating ? parseFloat(s.rating).toFixed(1) : '0.0';
            const visits = s.visits ? fmtNum(s.visits) : '0';
            const pct = Math.round((s.visits / maxVisits) * 100);

            const growthVal = (i % 2 === 0 ? 12 + (i * 1.5) : -1.8 + (i * 0.5)).toFixed(1);
            const yoyClass = parseFloat(growthVal) >= 0 ? 'positive' : 'negative';
            const yoySign = parseFloat(growthVal) >= 0 ? '+' : '';

            return `
        <tr>
            <td class="pa-rank-num">${rank}</td>
            <td>
                <div class="pa-spot-info-cell">
                    <div class="pa-spot-meta">
                        <span class="pa-spot-name">${escHtml(s.name)}</span>
                        <span class="pa-spot-location">${escHtml(s.municipality?.name || 'Unknown')}</span>
                    </div>
                </div>
            </td>
            <td><span class="pa-cat-badge-pill ${colorClass}">${escHtml(s.category)}</span></td>
            <td><strong>${visits}</strong></td>
            <td><span style="color:#f59e0b;">★</span> ${rating}</td>
            <td><span class="pa-yoy-growth ${yoyClass}">${yoySign}${growthVal}%</span></td>
            <td>
                <div class="pa-table-bar-track">
                    <div class="pa-table-bar-fill bg-${colorClass}" style="width: ${pct}%"></div>
                </div>
            </td>
        </tr>`;
        }).join('');
    }

    function filterTableCategory(category) {
        _selectedCategoryTab = category;

        const tabs = document.querySelectorAll('#categoryTabs .pa-cat-tab');
        tabs.forEach(t => {
            t.classList.remove('active');
            if (t.getAttribute('data-category').toLowerCase() === category.toLowerCase()) {
                t.classList.add('active');
            }
        });

        renderSpotsTable();
    }

    // ── Utilities
    function destroyChart(id) {
        try {
            const canvas = document.getElementById(id);
            if (canvas && typeof Chart !== 'undefined' && Chart.getChart) {
                const chartInstance = Chart.getChart(canvas) || Chart.getChart(id);
                if (chartInstance) {
                    chartInstance.destroy();
                }
            }
        } catch (e) {}
        if (_charts[id]) {
            try { _charts[id].destroy(); } catch (e) {}
            delete _charts[id];
        }
    }
    function checkEmptyState(canvasId, values) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return false;
        const parent = canvas.parentNode;
        if (!parent) return false;

        const hasData = values && values.length > 0 && values.some(v => v !== null && v > 0);
        let emptyEl = parent.querySelector('.chart-empty-state');
        if (!hasData) {
            if (!emptyEl) {
                emptyEl = document.createElement('div');
                emptyEl.className = 'chart-empty-state';
                emptyEl.innerHTML = '<i class="fas fa-folder-open" style="font-size:24px; margin-bottom:8px; color:#64748b;"></i><p>No data yet for this filter</p>';
                parent.appendChild(emptyEl);
            }
            canvas.style.display = 'none';
            emptyEl.style.display = 'flex';
            return true;
        } else {
            if (emptyEl) emptyEl.style.display = 'none';
            canvas.style.display = 'block';
            return false;
        }
    }
    function toArr(v) { if (Array.isArray(v)) return v; if (v && typeof v === 'object') return Object.values(v); return []; }
    function fmtNum(n) { return Number(n || 0).toLocaleString('en-PH'); }
    function fmtK(n) {
        if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
        if (n >= 1000) return (n / 1000).toFixed(0) + 'K';
        return n;
    }
    function setText(id, val) { const el = document.getElementById(id); if (el) el.innerHTML = val ?? '—'; }
    function escHtml(str) { if (str == null) return ''; const d = document.createElement('div'); d.textContent = String(str); return d.innerHTML; }

    function toggleExtraCategories() {
        const panel = document.getElementById('extraCategoriesPanel');
        const btn = document.getElementById('toggleCategoriesBtn');
        const icon = document.getElementById('toggleCategoriesIcon');
        if (!panel || !icon) return;

        const isCollapsed = panel.style.width === '0px' || panel.style.width === '' || panel.style.width === '0';

        if (isCollapsed) {
            panel.style.width = panel.scrollWidth + 'px';
            icon.className = 'fas fa-chevron-left';
            if (btn) btn.title = 'Show fewer categories';
        } else {
            panel.style.width = '0';
            icon.className = 'fas fa-chevron-right';
            if (btn) btn.title = 'Show more categories';
        }
    }
})();
