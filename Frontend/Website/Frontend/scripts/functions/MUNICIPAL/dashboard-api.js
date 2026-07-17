(function () {
    /**
     * MUNICIPAL Dashboard API — Real-Time Synchronization
     *
     * Features:
     *  1. Smart polling every 30 s via lightweight /dashboard/poll endpoint
     *     (only fetches full data when the fingerprint hash changes)
     *  2. Instant refresh on notifyTouristSpotChanged / notifyFareDataChanged events
     *  3. Smooth counter animation on every KPI value change
     *  4. Chart refresh without page reload
     *  5. Recent Activities auto-update
     */

    if (window.__muniDashboardLoaded) {
        void 0;
        if (typeof window.softRefreshDashboard === 'function') {
            window.softRefreshDashboard();
        }
        return;
    }
    window.__muniDashboardLoaded = true;

    const DASHBOARD_URL = window.API_CONFIG?.MUNICIPAL || 'http://localhost:8000/api/municipal';
    const POLL_URL = DASHBOARD_URL + '/dashboard/poll';
    const DATA_URL = DASHBOARD_URL + '/dashboard';
    const FETCH_TIMEOUT = 30000;
    const POLL_INTERVAL = 30000;   // 30-second polling interval
    const MIN_REFRESH_GAP = 5000;    // Debounce: never refresh more than once per 5 s

    const _dashboardCharts = {};
    let currentDashboardData = null;
    let _lastKnownHash = null;    // Fingerprint returned by /dashboard/poll
    let _pollTimer = null;
    let _refreshPending = false;
    let _lastRefreshAt = 0;

    // ── Helpers ─────────────────────────────────────────────────────────────────
    function showKpiError() {
        document.querySelectorAll('.lupto-kpi-card .lupto-kpi-value').forEach(el => {
            el.innerHTML = '<span style="color:#EF4444;font-size:12px;font-weight:600;">Error</span>';
        });
    }

    function showKpiLoading() {
        document.querySelectorAll('.lupto-kpi-card .lupto-kpi-value').forEach(el => {
            el.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:12px;color:#9CA3AF;"></i>';
        });
    }

    function hideLoadingOverlay() {
        const overlay = document.getElementById('dashboard-loading-overlay');
        if (overlay) {
            overlay.classList.add('fade-out');
            setTimeout(() => { overlay.remove(); }, 350);
        }
    }

    async function apiFetch(url) {
        const controller = new AbortController();
        const tid = setTimeout(() => controller.abort(), FETCH_TIMEOUT);
        try {
            return await window.API_CONFIG.get(url, { signal: controller.signal });
        } finally {
            clearTimeout(tid);
        }
    }

    // ── Fetch Full Dashboard Data ──────────────────────────────────────────────
    async function fetchDashboardData() {
        const data = await apiFetch(DATA_URL);
        currentDashboardData = data;
        return data;
    }

    // ── Poll for Changes (cheap) ───────────────────────────────────────────────
    async function pollForChanges() {
        // Skip poll if the dashboard tab is not visible
        if (!isDashboardVisible()) return;

        try {
            const res = await apiFetch(POLL_URL);
            if (!res || !res.hash) return;

            if (_lastKnownHash !== null && res.hash !== _lastKnownHash) {
                void 0;
                _lastKnownHash = res.hash;
                scheduleSoftRefresh();
            } else if (_lastKnownHash === null) {
                _lastKnownHash = res.hash;
            }
        } catch (err) {
            if (err.name !== 'AbortError') {
                void 0;
            }
        }
    }

    function isDashboardVisible() {
        const el = document.getElementById('dashboard-activity-feed')
            || document.querySelector('[data-kpi="total-tourist-spots"]');
        return !!el;
    }

    // ── Debounced Refresh ──────────────────────────────────────────────────────
    function scheduleSoftRefresh() {
        const now = Date.now();
        if (now - _lastRefreshAt < MIN_REFRESH_GAP) {
            // Already refreshed recently — defer
            if (!_refreshPending) {
                _refreshPending = true;
                setTimeout(() => {
                    _refreshPending = false;
                    doSoftRefresh();
                }, MIN_REFRESH_GAP);
            }
            return;
        }
        doSoftRefresh();
    }

    // ── Soft Refresh (no loading overlay) ─────────────────────────────────────
    async function doSoftRefresh() {
        _lastRefreshAt = Date.now();
        void 0;

        try {
            await fetchDashboardData();
        } catch (err) {
            if (err.name !== 'AbortError') {
                console.error('[MUNI Dashboard] Soft refresh failed:', err);
                showKpiError();
            }
            return;
        }

        loadKpis();
        refreshChartsInPlace();
        renderActivityTimeline();

        // Also trigger analytics refresh if on that tab
        if (typeof window.refreshAnalytics === 'function') {
            try { window.refreshAnalytics(true); } catch (e) { }
        }
    }

    // Expose for external callers (spa-router calls window.softRefreshDashboard)
    window.softRefreshDashboard = async function () {
        // Force fetch fresh data (bypass debounce since caller already knows data changed)
        _lastRefreshAt = 0;
        scheduleSoftRefresh();

        // Update fingerprint after refresh so polling doesn't double-trigger
        try {
            const res = await apiFetch(POLL_URL);
            if (res && res.hash) _lastKnownHash = res.hash;
        } catch (e) { /* non-critical */ }
    };

    // ── Start / Stop Polling ──────────────────────────────────────────────────
    function startPolling() {
        stopPolling();
        _pollTimer = setInterval(pollForChanges, POLL_INTERVAL);
        void 0;
    }

    function stopPolling() {
        if (_pollTimer) { clearInterval(_pollTimer); _pollTimer = null; }
    }

    // Pause polling when tab is hidden (saves bandwidth)
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopPolling();
        } else {
            startPolling();
            // Immediate poll when user returns to the tab
            pollForChanges();
        }
    });

    // ── Hook into SPA Tab Events ───────────────────────────────────────────────
    // The SPA router dispatches 'tabshow' on the tab wrapper element when the
    // dashboard tab is activated. We refresh if data is stale.
    document.addEventListener('tabshow', (e) => {
        // Check if the tabshow was dispatched on the dashboard tab
        const target = e.target;
        if (target && (target.id === 'spa-tab-dashboard.php' || target.querySelector('[data-kpi="total-tourist-spots"]'))) {
            const staleThreshold = 60000; // 60 s
            if (Date.now() - _lastRefreshAt > staleThreshold) {
                scheduleSoftRefresh();
            }
        }
    });

    // Hook into Tourist Spot and Fare Change Events
    // These are fired by tourist-spots-api.js and fare-data-api.js immediately
    // after any CRUD operation, giving us instant (not polled) dashboard updates.
    // We defer the patch so spa-router has time to install these functions first.
    function patchNotifiers() {
        const origSpot = window.notifyTouristSpotChanged;
        window.notifyTouristSpotChanged = function () {
            _lastKnownHash = null;
            if (typeof origSpot === 'function') origSpot.apply(this, arguments);
        };

        const origFare = window.notifyFareDataChanged;
        window.notifyFareDataChanged = function () {
            _lastKnownHash = null;
            if (typeof origFare === 'function') origFare.apply(this, arguments);
        };
    }
    // Run after current call stack so spa-router.js can finish defining these
    setTimeout(patchNotifiers, 0);

    // ── Initialize Dashboard ───────────────────────────────────────────────────
    async function initializeDashboard() {
        showKpiLoading();

        try {
            await fetchDashboardData();
        } catch (err) {
            console.error('[MUNI Dashboard] Initialization pre-fetch failed:', err);
            hideLoadingOverlay();
            showKpiError();
            loadKpis();
            initMap();
            initVisitorTrendsChart();
            initTopSpotsChart();
            initTopSpotsTable();
            renderActivityTimeline();
            startPolling();
            return;
        }

        // Seed the hash so the first poll doesn't trigger a redundant refresh
        try {
            const res = await apiFetch(POLL_URL);
            if (res && res.hash) _lastKnownHash = res.hash;
        } catch (e) { /* non-critical */ }

        hideLoadingOverlay();
        loadKpis();
        initMap();
        initVisitorTrendsChart();
        initTopSpotsChart();
        initTopSpotsTable();
        renderActivityTimeline();
        startPolling();
    }

    // ── KPI Cards ──────────────────────────────────────────────────────────────
    function loadKpis() {
        const container = document.getElementById('spa-tab-dashboard.php') || document;
        const data = currentDashboardData;
        if (data && data.kpis) {
            const kpis = data.kpis;

            const elSpots = container.querySelector('[data-kpi="total-tourist-spots"] .lupto-kpi-value');
            const elApproved = container.querySelector('[data-kpi="total-approved-spots"] .lupto-kpi-value');
            const elUsers = container.querySelector('[data-kpi="total-tourist-users"] .lupto-kpi-value');
            const elPoints = container.querySelector('[data-kpi="total-points-earned"] .lupto-kpi-value');
            const elVisits = container.querySelector('[data-kpi="total-visits"] .lupto-kpi-value');

            if (elSpots) window.animateKpiValue(elSpots, kpis.total_tourist_spots ?? kpis.totalSpots ?? 0);
            if (elApproved) window.animateKpiValue(elApproved, kpis.total_approved_spots ?? kpis.approvedSpots ?? 0);
            if (elUsers) window.animateKpiValue(elUsers, kpis.total_tourist_users ?? 0);
            if (elPoints) window.animateKpiValue(elPoints, kpis.total_points_earned ?? 0);
            if (elVisits) window.animateKpiValue(elVisits, kpis.total_visits ?? 0);
        } else {
            const container2 = document.getElementById('spa-tab-dashboard.php') || document;
            const z = (sel) => container2.querySelector(sel);
            const elSpots = z('[data-kpi="total-tourist-spots"] .lupto-kpi-value');
            const elApproved = z('[data-kpi="total-approved-spots"] .lupto-kpi-value');
            const elUsers = z('[data-kpi="total-tourist-users"] .lupto-kpi-value');
            const elPoints = z('[data-kpi="total-points-earned"] .lupto-kpi-value');
            const elVisits = z('[data-kpi="total-visits"] .lupto-kpi-value');

            if (elSpots) window.animateKpiValue(elSpots, 0);
            if (elApproved) window.animateKpiValue(elApproved, 0);
            if (elUsers) window.animateKpiValue(elUsers, 0);
            if (elPoints) window.animateKpiValue(elPoints, 0);
            if (elVisits) window.animateKpiValue(elVisits, 0);
        }
    }

    // ── Municipality Map ───────────────────────────────────────────────────────
    function initMap() {
        var mapEl = document.getElementById('dashboard-map');
        if (!mapEl) { void 0; return; }

        var data = currentDashboardData;
        var spots = data ? (data.touristSpots || []) : [];
        var municipalities = data ? (data.municipalities || []) : [];

        void 0;

        if (spots.length > 0) {
            var first = spots[0];
            void 0;
            void 0;
        }

        var muniCoordMap = {};
        municipalities.forEach(function (m) {
            if (m.latitude && m.longitude) {
                muniCoordMap[m.id] = { lat: parseFloat(m.latitude), lng: parseFloat(m.longitude), name: m.name };
            }
        });

        var spotsWithMuni = spots.map(function (s) {
            var muniName = s.municipality ? s.municipality.name : (s.municipality_name || '');
            var lat = s.latitude != null ? s.latitude : null;
            var lng = s.longitude != null ? s.longitude : null;

            if ((lat == null || lng == null || lat === '' || lng === '' || lat === 0) && s.municipality_id && muniCoordMap[s.municipality_id]) {
                var mc = muniCoordMap[s.municipality_id];
                if (!lat || lat === '' || lat === 0) lat = mc.lat;
                if (!lng || lng === '' || lng === 0) lng = mc.lng;
                void 0;
            }

            return {
                id: s.id,
                name: s.name,
                latitude: lat,
                longitude: lng,
                category: s.category,
                classification_status: s.classification_status,
                rating: s.rating,
                description: s.description,
                photo_url: s.photo_url,
                entrance_fee: s.entrance_fee,
                opening_time: s.opening_time,
                closing_time: s.closing_time,
                is_maintenance: s.is_maintenance,
                barangay: s.barangay,
                images: s.photo_url ? [{ photo_url: s.photo_url }] : [],
                municipality: { name: muniName },
                municipality_name: muniName
            };
        }).filter(function (s) {
            var hasLat = s.latitude != null && s.latitude !== '' && s.latitude !== 0;
            var hasLng = s.longitude != null && s.longitude !== '' && s.longitude !== 0;
            return hasLat && hasLng;
        });

        void 0;
        if (spotsWithMuni.length > 0) {
            void 0;
        } else if (spots.length > 0) {
            void 0;
            spots.slice(0, 3).forEach(function (s, i) {
                void 0;
            });
        }

        if (mapEl._leaflet_map) {
            if (window.MapMarkersConfig && typeof window.MapMarkersConfig.updateMapSpots === 'function') {
                window.MapMarkersConfig.updateMapSpots(mapEl, spotsWithMuni);
                return;
            }
        }

        if (window.MapMarkersConfig && typeof window.MapMarkersConfig.initDashboardMapWithSpots === 'function') {
            window.MapMarkersConfig.initDashboardMapWithSpots(mapEl, spotsWithMuni);
            setupMapFilters(mapEl, spotsWithMuni);
        } else {
            var pollCount = 0;
            var poll = setInterval(function () {
                pollCount++;
                if (window.MapMarkersConfig && typeof window.MapMarkersConfig.initDashboardMapWithSpots === 'function') {
                    clearInterval(poll);
                    window.MapMarkersConfig.initDashboardMapWithSpots(mapEl, spotsWithMuni);
                    setupMapFilters(mapEl, spotsWithMuni);
                } else if (pollCount > 50) {
                    clearInterval(poll);
                    console.error('[MUNI Dashboard] MapMarkersConfig failed to load after 5s — map markers will not render. Ensure map-markers-config.js is included in the page.');
                }
            }, 100);
        }
    }

    function setupMapFilters(mapEl, spots) {
        var filterContainer = document.getElementById('dashboard-map-filters');
        if (!filterContainer || !window.MapMarkersConfig) return;

        if (filterContainer._filtersBuilt) {
            window.MapMarkersConfig.updateMapSpots(mapEl, spots);
            return;
        }
        filterContainer._filtersBuilt = true;

        window.MapMarkersConfig.buildFilterControls(filterContainer, function (selectedCats, selectedClasses) {
            var map = mapEl._leaflet_map;
            if (!map) return;

            map._selectedCategories = selectedCats;
            map._selectedClassifications = selectedClasses;

            window.MapMarkersConfig.updateMapSpots(mapEl, spots);
        }, { hideMunicipality: true });

    }

    // ── Chart Refresh Helpers ─────────────────────────────────────────────────
    /**
     * Refresh all charts in-place using new data (no full reinit needed).
     * Destroys and re-creates charts so they animate smoothly.
     */
    function refreshChartsInPlace() {
        initVisitorTrendsChart();
        initTopSpotsChart();
        initTopSpotsTable();
    }

    // ── Visitor Trends Chart ───────────────────────────────────────────────────
    function initVisitorTrendsChart() {
        const ctx = document.getElementById('visitorTrendsChart');
        if (!ctx) return;

        const data = currentDashboardData;
        const trendData = data?.visitorTrends || [];

        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const fallback = [1200, 1400, 1300, 1600, 1700, 1850, 1500, 1600, 1700, 1750, 1800, 1700];

        const chartValues = months.map((_, i) => {
            const record = trendData.find(r => r.month == (i + 1));
            return record ? parseInt(record.visits) || 0 : 0;
        });

        const finalValues = chartValues.every(v => v === 0) ? fallback : chartValues;

        if (_dashboardCharts.trends) {
            if (_dashboardCharts.trends.canvas !== ctx) {
                _dashboardCharts.trends.destroy();
                _dashboardCharts.trends = null;
            } else {
                _dashboardCharts.trends.data.datasets[0].data = finalValues;
                _dashboardCharts.trends.update();
                return;
            }
        }

        const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 240);
        gradient.addColorStop(0, 'rgba(37, 99, 235, 0.25)');
        gradient.addColorStop(1, 'rgba(37, 99, 235, 0.00)');

        _dashboardCharts.trends = new Chart(ctx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Monthly Visitors',
                    data: finalValues,
                    borderColor: '#0B5394',
                    borderWidth: 3,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.45,
                    pointRadius: 4,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#0B5394',
                    pointBorderColor: '#FFFFFF',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 600, easing: 'easeInOutQuart' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1E293B',
                        padding: 10,
                        titleFont: { family: 'Outfit, Inter, sans-serif', size: 13, weight: '600' },
                        bodyFont: { family: 'Outfit, Inter, sans-serif', size: 12 },
                        cornerRadius: 8,
                        boxPadding: 4
                    }
                },
                scales: {
                    x: {
                        grid: { color: '#F1F5F9', drawTicks: false },
                        ticks: { font: { family: 'Outfit, Inter, sans-serif', size: 11 } }
                    },
                    y: {
                        grid: { color: '#F1F5F9', drawTicks: false },
                        ticks: {
                            callback: value => Number(value).toLocaleString(),
                            font: { family: 'Outfit, Inter, sans-serif', size: 11 }
                        }
                    }
                }
            }
        });
    }


    // ── Top Spots by Visits Chart ──────────────────────────────────────────────
    function initTopSpotsChart() {
        const ctx = document.getElementById('topSpotsChart');
        if (!ctx) return;

        const data = currentDashboardData;
        const topSpots = data?.topSpots || [];

        const newLabels = topSpots.length ? topSpots.slice(0, 5).map(s => s.name) : ['Spot A', 'Spot B', 'Spot C', 'Spot D', 'Spot E'];
        const newValues = topSpots.length ? topSpots.slice(0, 5).map(s => parseInt(s.visits) || 0) : [1200, 950, 780, 650, 500];

        if (_dashboardCharts.topSpots) {
            if (_dashboardCharts.topSpots.canvas !== ctx) {
                _dashboardCharts.topSpots.destroy();
                _dashboardCharts.topSpots = null;
            } else {
                _dashboardCharts.topSpots.data.labels = newLabels;
                _dashboardCharts.topSpots.data.datasets[0].data = newValues;
                _dashboardCharts.topSpots.update();
                return;
            }
        }

        _dashboardCharts.topSpots = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: topSpots.length ? topSpots.slice(0, 5).map(s => s.name) : ['Spot A', 'Spot B', 'Spot C', 'Spot D', 'Spot E'],
                datasets: [{
                    label: 'Total Visits',
                    data: topSpots.length ? topSpots.slice(0, 5).map(s => parseInt(s.visits) || 0) : [1200, 950, 780, 650, 500],
                    backgroundColor: function (ctx) {
                        return ctx.raw === Math.max.apply(null, ctx.chart.data.datasets[0].data)
                            ? '#0B5394' : '#6BAED6';
                    },
                    borderRadius: 6,
                    barThickness: 16
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 600, easing: 'easeInOutQuart' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1E293B',
                        padding: 10,
                        titleFont: { family: 'Outfit, Inter, sans-serif', size: 13, weight: '600' },
                        bodyFont: { family: 'Outfit, Inter, sans-serif', size: 12 },
                        cornerRadius: 8
                    }
                },
                scales: {
                    x: {
                        grid: { color: '#F1F5F9', drawTicks: false },
                        ticks: {
                            callback: value => Number(value).toLocaleString(),
                            font: { family: 'Outfit, Inter, sans-serif', size: 11 }
                        }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { font: { family: 'Outfit, Inter, sans-serif', size: 11 } }
                    }
                }
            }
        });
    }

    // ── Top Tourist Spots Table ──────────────────────────────────────────────────
    let _selectedCategoryFilter = 'all';
    let _topSpotsCurrentPage = 1;
    const _topSpotsPageSize = 10;

    function initTopSpotsTable() {
        const tableBody = document.getElementById('top-spots-table-body');
        if (!tableBody) return;

        const pageInfo = document.getElementById('top-spots-page-info');
        const pageButtons = document.getElementById('top-spots-page-buttons');

        const data = currentDashboardData;
        let spots = data ? (data.touristSpots || []) : [];

        // Sort by visits desc, then rating desc
        spots = [...spots].sort((a, b) => {
            const visitDiff = (b.visits || 0) - (a.visits || 0);
            if (visitDiff !== 0) return visitDiff;
            return (b.rating || 0) - (a.rating || 0);
        });

        // Filter based on selected category pill
        if (_selectedCategoryFilter !== 'all') {
            spots = spots.filter(spot => {
                const catStr = (spot.category || '').toLowerCase();
                if (_selectedCategoryFilter === 'Beach') {
                    return catStr.includes('beach');
                } else if (_selectedCategoryFilter === 'Nature') {
                    return catStr.includes('nature') || catStr.includes('mountain') || catStr.includes('waterfalls') ||
                        catStr.includes('river') || catStr.includes('lake') || catStr.includes('forest') ||
                        catStr.includes('park') || catStr.includes('garden') || catStr.includes('cave');
                } else if (_selectedCategoryFilter === 'Heritage') {
                    return catStr.includes('heritage') || catStr.includes('historical') || catStr.includes('museum') ||
                        catStr.includes('monument') || catStr.includes('landmark');
                } else if (_selectedCategoryFilter === 'Cultural') {
                    return catStr.includes('cultural') || catStr.includes('religious') || catStr.includes('temple') ||
                        catStr.includes('church');
                }
                return false;
            });
        }

        const totalSpots = spots.length;
        const totalPages = Math.ceil(totalSpots / _topSpotsPageSize) || 1;

        if (_topSpotsCurrentPage > totalPages) _topSpotsCurrentPage = totalPages;
        if (_topSpotsCurrentPage < 1) _topSpotsCurrentPage = 1;

        const startIdx = (_topSpotsCurrentPage - 1) * _topSpotsPageSize;
        const endIdx = Math.min(startIdx + _topSpotsPageSize, totalSpots);

        if (totalSpots === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align: center; padding: 30px; color: #94A3B8;">
                        No tourist spots found matching this category.
                    </td>
                </tr>
            `;
            if (pageInfo) pageInfo.textContent = 'Showing 0-0 of 0 spots';
            if (pageButtons) pageButtons.innerHTML = '';
            return;
        }

        const displaySpots = spots.slice(startIdx, endIdx);

        let html = '';
        displaySpots.forEach((spot, idx) => {
            const rankStr = String(startIdx + idx + 1).padStart(2, '0');
            const ratingVal = parseFloat(spot.rating || 0).toFixed(1);
            const visitsCount = spot.visits || 0;
            const barangayText = spot.barangay || 'N/A';
            const municipalText = spot.municipality ? spot.municipality.name : 'N/A';

            // Split categories and render them nicely
            const categories = (spot.category || '').split(',').map(c => c.trim()).filter(Boolean);
            const categoriesHtml = categories.map(cat => `
                <span style="background: #F1F5F9; color: #475569; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-right: 4px; display: inline-block;">${cat}</span>
            `).join('');

            html += `
                <tr style="border-bottom: 1px solid #F1F5F9;">
                    <td style="padding: 12px 8px; font-weight: 700; color: #94A3B8;">${rankStr}</td>
                    <td style="padding: 12px 8px; font-weight: 600; color: #1E293B;">${spot.name}</td>
                    <td style="padding: 12px 8px; color: #475569;">${barangayText}</td>
                    <td style="padding: 12px 8px; color: #475569;">${municipalText}</td>
                    <td style="padding: 12px 8px;">${categoriesHtml}</td>
                    <td style="padding: 12px 8px; text-align: center; font-weight: 700; color: #1E293B;">${visitsCount}</td>
                    <td style="padding: 12px 8px; text-align: center; font-weight: 600; color: #F59E0B;">
                        <i class="fas fa-star" style="font-size: 11px;"></i> ${ratingVal}
                    </td>
                </tr>
            `;
        });

        tableBody.innerHTML = html;

        if (pageInfo) {
            pageInfo.textContent = `Showing ${startIdx + 1}-${endIdx} of ${totalSpots} spots`;
        }

        if (pageButtons) {
            let btnsHtml = '';

            // Previous Button
            const prevDisabled = _topSpotsCurrentPage === 1;
            btnsHtml += `
                <button class="top-spots-prev-btn" style="cursor: ${prevDisabled ? 'not-allowed' : 'pointer'}; opacity: ${prevDisabled ? 0.5 : 1}; padding: 6px 12px; border: 1px solid #E2E8F0; background: #fff; color: #475569; border-radius: 6px; font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 4px; transition: all 0.2s ease;" ${prevDisabled ? 'disabled' : ''}>
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
            `;

            // Page Number Buttons
            for (let i = 1; i <= totalPages; i++) {
                const isActive = i === _topSpotsCurrentPage;
                btnsHtml += `
                    <button class="top-spots-page-num-btn" data-page="${i}" style="cursor: pointer; min-width: 32px; height: 32px; padding: 0 6px; border: 1px solid ${isActive ? '#0B5394' : '#E2E8F0'}; background: ${isActive ? '#0B5394' : '#fff'}; color: ${isActive ? '#fff' : '#475569'}; border-radius: 6px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s ease;">
                        ${i}
                    </button>
                `;
            }

            // Next Button
            const nextDisabled = _topSpotsCurrentPage === totalPages;
            btnsHtml += `
                <button class="top-spots-next-btn" style="cursor: ${nextDisabled ? 'not-allowed' : 'pointer'}; opacity: ${nextDisabled ? 0.5 : 1}; padding: 6px 12px; border: 1px solid #E2E8F0; background: #fff; color: #475569; border-radius: 6px; font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 4px; transition: all 0.2s ease;" ${nextDisabled ? 'disabled' : ''}>
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            `;

            pageButtons.innerHTML = btnsHtml;
        }
    }

    // Set up filter pill and pagination click listeners once in document load
    document.addEventListener('click', function (e) {
        const pill = e.target.closest('.spot-filter-pill');
        if (pill) {
            const container = pill.parentElement;
            container.querySelectorAll('.spot-filter-pill').forEach(p => {
                p.classList.remove('active');
                p.style.background = '#F1F5F9';
                p.style.color = '#475569';
            });

            pill.classList.add('active');
            pill.style.background = '#10B981';
            pill.style.color = '#fff';

            _selectedCategoryFilter = pill.getAttribute('data-category');
            _topSpotsCurrentPage = 1; // reset page on filter change
            initTopSpotsTable();
            return;
        }

        const prevBtn = e.target.closest('.top-spots-prev-btn');
        if (prevBtn && _topSpotsCurrentPage > 1) {
            _topSpotsCurrentPage--;
            initTopSpotsTable();
            return;
        }

        const nextBtn = e.target.closest('.top-spots-next-btn');
        if (nextBtn) {
            const data = currentDashboardData;
            const spots = data ? (data.touristSpots || []) : [];
            let filteredSpotsCount = spots.length;
            if (_selectedCategoryFilter !== 'all') {
                filteredSpotsCount = spots.filter(spot => {
                    const catStr = (spot.category || '').toLowerCase();
                    if (_selectedCategoryFilter === 'Beach') {
                        return catStr.includes('beach');
                    } else if (_selectedCategoryFilter === 'Nature') {
                        return catStr.includes('nature') || catStr.includes('mountain') || catStr.includes('waterfalls') ||
                            catStr.includes('river') || catStr.includes('lake') || catStr.includes('forest') ||
                            catStr.includes('park') || catStr.includes('garden') || catStr.includes('cave');
                    } else if (_selectedCategoryFilter === 'Heritage') {
                        return catStr.includes('heritage') || catStr.includes('historical') || catStr.includes('museum') ||
                            catStr.includes('monument') || catStr.includes('landmark');
                    } else if (_selectedCategoryFilter === 'Cultural') {
                        return catStr.includes('cultural') || catStr.includes('religious') || catStr.includes('temple') ||
                            catStr.includes('church');
                    }
                    return false;
                }).length;
            }
            const totalPages = Math.ceil(filteredSpotsCount / _topSpotsPageSize) || 1;
            if (_topSpotsCurrentPage < totalPages) {
                _topSpotsCurrentPage++;
                initTopSpotsTable();
            }
            return;
        }

        const pageNumBtn = e.target.closest('.top-spots-page-num-btn');
        if (pageNumBtn) {
            const pageNum = parseInt(pageNumBtn.getAttribute('data-page'));
            if (pageNum && pageNum !== _topSpotsCurrentPage) {
                _topSpotsCurrentPage = pageNum;
                initTopSpotsTable();
            }
        }
    });

    // ── Render Activity Timeline from Activity Logs ────────────────────────────────
    var _activityFilter = 'all';
    var _allActivities = [];

    function resolveActivityCategory(activity) {
        var module = (activity.module || '').toLowerCase();
        var action = (activity.action || '').toLowerCase();
        if (module.indexOf('user') !== -1 || action.indexOf('user') !== -1 || action.indexOf('login') !== -1 || action.indexOf('logout') !== -1 || action.indexOf('password') !== -1 || action.indexOf('profile') !== -1) {
            return 'user';
        }
        if (module.indexOf('tourist') !== -1 || module.indexOf('spot') !== -1 || module.indexOf('approval') !== -1 || action.indexOf('spot') !== -1) {
            return 'spot';
        }
        if (module.indexOf('municipal') !== -1 || action.indexOf('municipal') !== -1) {
            return 'municipal';
        }
        return 'system';
    }

    function getActivityIcon(action) {
        var map = {
            'User Logged In': 'fa-sign-in-alt', 'User Logged Out': 'fa-sign-out-alt',
            'User Created': 'fa-user-plus', 'User Updated': 'fa-user-edit',
            'User Deleted': 'fa-user-slash', 'User Restored': 'fa-user-check',
            'User Archived': 'fa-folder', 'User Activated': 'fa-toggle-on',
            'User Deactivated': 'fa-toggle-off', 'Password Reset': 'fa-key',
            'Tourist Spot Added': 'fa-map-marker-alt', 'Tourist Spot Updated': 'fa-edit',
            'Tourist Spot Deleted': 'fa-trash', 'Tourist Spot Approved': 'fa-check-circle',
            'Tourist Spot Rejected': 'fa-times-circle', 'Fare Data Uploaded': 'fa-upload',
            'Fare Data Updated': 'fa-bus', 'Fare Data Deleted': 'fa-trash-alt',
            'System Settings Updated': 'fa-cog', 'Profile Updated': 'fa-user-circle',
            'Password Changed': 'fa-lock', 'Data Imported': 'fa-file-import',
            'Data Exported': 'fa-file-export'
        };
        return map[action] || 'fa-bell';
    }

    function renderActivityTimeline() {
        var feed = document.getElementById('dashboard-activity-feed');
        if (!feed) return;

        var data = currentDashboardData;
        var activities = data ? (data.recent_activities || data.alerts || []) : [];
        _allActivities = activities;

        var totalCount = activities.length;
        var countEl = document.getElementById('act-total-count');
        if (countEl) {
            countEl.textContent = totalCount + ' Activit' + (totalCount !== 1 ? 'ies' : 'y');
        }

        var filtered = _activityFilter === 'all'
            ? activities
            : activities.filter(function (a) { return resolveActivityCategory(a) === _activityFilter; });

        if (!filtered.length) {
            var emptyMsg = _activityFilter === 'all'
                ? '<i class="fas fa-inbox"></i><span>No recent activity</span>'
                : '<i class="fas fa-search"></i><span>No activities match this filter</span>';
            feed.innerHTML = '<div class="dash-timeline-empty">' + emptyMsg + '</div>';
            return;
        }

        var html = '';
        filtered.forEach(function (act, idx) {
            var category = resolveActivityCategory(act);
            var iconClass = act.action_icon || getActivityIcon(act.action) || 'fa-bell';
            var actionLabel = act.action || 'Activity';
            var moduleLabel = act.module || '';
            var description = act.description || act.message || '';
            var userName = act.user_name || (act.user ? act.user.name : '') || 'System';
            var municipality = act.municipality || '';
            var timeAgo = timeAgoStr(act.created_at);
            var exactDate = act.created_at ? new Date(act.created_at).toLocaleString() : '';
            var delay = idx * 40;

            html += '<div class="act-card cat-' + category + '" style="animation-delay:' + delay + 'ms" data-activity=\'' + JSON.stringify({
                id: act.id, action: actionLabel, description: description,
                module: moduleLabel, user_name: userName, municipality: municipality,
                created_at: act.created_at, category: category
            }).replace(/'/g, '&#39;') + '\'>';

            html += '<div class="act-icon cat-' + category + '"><i class="fas ' + iconClass + '"></i></div>';
            html += '<div class="act-body">';
            html += '<div class="act-header"><span class="act-action cat-' + category + '">' + escapeHtmlAlert(actionLabel) + '</span></div>';
            html += '<div class="act-desc">' + escapeHtmlAlert(description) + '</div>';
            html += '<div class="act-meta">';
            html += '<span class="act-meta-item"><i class="fas fa-user-circle"></i> ' + escapeHtmlAlert(userName) + '</span>';
            if (municipality) {
                html += '<span class="act-meta-item"><i class="fas fa-map-pin"></i> ' + escapeHtmlAlert(municipality) + '</span>';
            }
            html += '</div>';
            html += '</div>';
            html += '<div class="act-time" title="' + exactDate + '"><i class="far fa-clock"></i> ' + timeAgo + '</div>';
            html += '</div>';
        });

        html += '<a class="act-view-all" href="activity-logs.php">' +
            '<i class="fas fa-list"></i> View All Activities <i class="fas fa-arrow-right"></i></a>';

        feed.innerHTML = html;
    }

    function setupActivityFilters() {
        var pills = document.querySelectorAll('#act-filters .act-filter-pill');
        pills.forEach(function (pill) {
            pill.addEventListener('click', function () {
                pills.forEach(function (p) { p.classList.remove('active'); });
                pill.classList.add('active');
                _activityFilter = pill.getAttribute('data-filter');
                renderActivityTimeline();
            });
        });
    }

    window.filterDashboardActivities = function (pill, filter) {
        var pills = document.querySelectorAll('#act-filters .act-filter-pill');
        pills.forEach(function (p) { p.classList.remove('active'); });
        pill.classList.add('active');
        _activityFilter = filter;
        if (typeof renderActivityTimeline === 'function') {
            renderActivityTimeline();
        }
    };

    window._activityFilterGet = function () { return _activityFilter; };

    function timeAgoStr(dateStr) {
        if (!dateStr) return '';
        var now = new Date();
        var then = new Date(dateStr);
        var diff = Math.floor((now - then) / 1000);
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
        if (diff < 86400) return Math.floor(diff / 3600) + ' hr ago';
        if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
        return then.toLocaleDateString();
    }

    function escapeHtmlAlert(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // ── Smart Insights Cards ───────────────────────────────────────────────────
    function loadInsights() {
        const spots = currentDashboardData?.touristSpots || [];
        const kpis = currentDashboardData?.kpis || {};

        const topEl = document.getElementById('insight-top-spot');
        if (topEl) {
            if (spots.length > 0) {
                const sorted = [...spots].sort((a, b) => (b.visits || 0) - (a.visits || 0));
                const top = sorted[0];
                topEl.textContent = top.name + ` — ${(top.visits || 0).toLocaleString()} visitors this month`;
            } else {
                topEl.textContent = 'No tourist spots yet';
            }
        }

        const needsAttnEl = document.getElementById('insight-needs-attention');
        if (needsAttnEl) {
            const pendingCount = kpis.total_pending_spots ?? 0;
            const maintenanceCount = spots.filter(s => s.is_maintenance).length;
            needsAttnEl.textContent = pendingCount > 0
                ? pendingCount + ' spot(s) pending approval'
                : maintenanceCount > 0
                    ? maintenanceCount + ' spot(s) under maintenance'
                    : 'All spots are in good standing';
        }

        const trendEl = document.getElementById('insight-trend');
        if (trendEl) {
            const totalVisits = kpis.total_visits ?? 0;
            const totalSpots = kpis.total_tourist_spots ?? 0;
            trendEl.textContent = totalSpots > 0
                ? totalVisits.toLocaleString() + ' total visits across ' + totalSpots + ' spot(s)'
                : 'No activity data available yet';
        }
    }

    // Pre-warm municipal tourist spot KPIs into sessionStorage for faster tab loads
    function preWarmMuniTouristSpotsKpis() {
        const baseUrl = window.API_CONFIG?.BASE_URL || (`http://${window.location.hostname || '127.0.0.1'}:8000`);
        window.API_CONFIG.get(`${baseUrl}/api/municipal/tourist-spots`).then(spotsRes => {
            const spots = spotsRes.data || spotsRes || [];
            const vals = {
                total: spots.length,
                open: spots.filter(s => !s.is_maintenance && (s.status || s.operation_status || '') !== 'closed').length,
                closed: spots.filter(s => s.is_maintenance || (s.status || s.operation_status || '') === 'closed').length,
                visits: Number(spots.reduce((sum, s) => sum + (parseInt(s.visits) || 0), 0)).toLocaleString(),
            };
            try { sessionStorage.setItem('ts_kpis_municipal', JSON.stringify(vals)); } catch (e) { }
        }).catch(() => { });
    }

    setTimeout(() => { preWarmMuniTouristSpotsKpis(); }, 1500);

    // ── On DOM Ready ──────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeDashboard);
    } else {
        initializeDashboard();
    }
})();
