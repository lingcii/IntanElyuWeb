(function () {
    /**
     * LUPTO/PICTO Dashboard API
     * Fetches real-time data from database via a single Laravel API endpoint for maximum speed and efficiency.
     */

    // ── Guard against duplicate execution ───────────────────────────────────────
    // If your SPA router re-injects this <script> tag every time the user
    // navigates back to the Dashboard, this prevents multiple setInterval loops
    // from stacking up and hammering the API (this was causing the
    // "auto refresh going crazy" behavior).
    if (window.__luptoDashboardLoaded) {
        void 0;
        if (typeof window.startAutoRefresh === 'function') {
            window.startAutoRefresh();
        }
        if (typeof window.softRefreshDashboard === 'function') {
            window.softRefreshDashboard();
        }
        return;
    }
    window.__luptoDashboardLoaded = true;

    // Determine the API base prefix dynamically based on role attribute
    const userRole = (document.body?.dataset?.role || document.querySelector('meta[name="user-role"]')?.content || '').toLowerCase();
    let DASHBOARD_URL = window.API_CONFIG?.LUPTO || 'http://localhost:8000/api/lupto';
    if (userRole === 'picto' || userRole === 'pitco') {
        DASHBOARD_URL = window.API_CONFIG?.PITCO || 'http://localhost:8000/api/pitco';
    } else if (userRole === 'municipal' || userRole.endsWith('_mto')) {
        DASHBOARD_URL = window.API_CONFIG?.MUNICIPAL || 'http://localhost:8000/api/municipal';
    }

    // Real-time refresh interval (10 seconds)
    let refreshTimer = null;
    const FETCH_TIMEOUT_MS = 30000;

    // ── Chart Storage ───────────────────────────────────────────────────────────
    const _dashboardCharts = {};

    // Cache for the single dashboard payload
    let currentDashboardData = null;

    // ── Helper: show an error state instead of leaving spinners stuck forever ──
    function showKpiError() {
        const container = document.getElementById('spa-tab-dashboard.php') || document;
        container.querySelectorAll('.lupto-kpi-card .lupto-kpi-value').forEach(valueEl => {
            valueEl.innerHTML = '<span style="color:#EF4444;font-size:12px;font-weight:600;">Error</span>';
        });
    }

    function showKpiLoading() {
        const container = document.getElementById('spa-tab-dashboard.php') || document;
        container.querySelectorAll('.lupto-kpi-card .lupto-kpi-value').forEach(valueEl => {
            valueEl.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:12px;color:#9CA3AF;"></i>';
        });
    }

    // Helper: Fetch all metrics in a single API request, with a hard timeout
    // so a hung request can never leave the dashboard stuck on spinners forever.
    async function fetchDashboardData() {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), FETCH_TIMEOUT_MS);

        try {
            if (!window.API_CONFIG || typeof window.API_CONFIG.get !== 'function') {
                throw new Error('API_CONFIG is not available. Check that api-config.js loaded before dashboard-api.js.');
            }

            const data = await window.API_CONFIG.get(DASHBOARD_URL + '/dashboard', {
                signal: controller.signal
            });

            currentDashboardData = data;
            return data;
        } catch (err) {
            if (err.name === 'AbortError') {
                console.error('[Dashboard] Request timed out after', FETCH_TIMEOUT_MS, 'ms:', DASHBOARD_URL + '/dashboard');
            } else {
                console.error('[Dashboard] Failed to fetch consolidated dashboard data:', err);
            }
            throw err;
        } finally {
            clearTimeout(timeoutId);
        }
    }

    // ── Initialize Dashboard ───────────────────────────────────────────────────
    async function initializeDashboard() {
        showKpiLoading();

        try {
            // Fetch everything from backend in one query
            await fetchDashboardData();
        } catch (err) {
            console.error('[Dashboard] Initialization pre-fetch failed:', err);
            showKpiError();
            // Still try to render charts/map with fallback data below,
            // but bail out of starting auto-refresh on a broken connection.
            loadKpis();
            initVisitorTrendsChart();
            initTopMunicipalitiesChart();
            initApprovalStatusChart();
            initTopSpotsTable();
            loadMunicipalitiesData();
            renderActivityTimeline();
            return;
        }

        // Initialize all components with the pre-fetched data
        loadKpis();
        initVisitorTrendsChart();
        initTopMunicipalitiesChart();
        initApprovalStatusChart();
        initTopSpotsTable();
        loadMunicipalitiesData();
        renderActivityTimeline();

        // Start real-time auto-refresh
        startAutoRefresh();
    }

    // ── Real-time Auto Refresh ───────────────────────────────────────────────────
    function startAutoRefresh() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
            refreshTimer = null;
        }
        refreshTimer = setInterval(softRefreshDashboard, 30000);
    }

    function stopAutoRefresh() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
            refreshTimer = null;
        }
    }

    async function softRefreshDashboard() {
        try {
            await fetchDashboardData();
        } catch (err) {
            if (err.name !== 'AbortError') {
                console.error('[Dashboard] Soft refresh failed:', err);
                showKpiError();
            }
            return;
        }
        loadKpis();
        initVisitorTrendsChart();
        initTopMunicipalitiesChart();
        initApprovalStatusChart();
        initTopSpotsTable();
        loadMunicipalitiesData();
        renderActivityTimeline();
    }

    // ── Load KPIs from Cached Payload ───────────────────────────────────────────────────
    function loadKpis() {
        const container = document.getElementById('spa-tab-dashboard.php') || document;
        const data = currentDashboardData;
        if (data && data.kpis) {
            const kpis = data.kpis;

            const elSpots = container.querySelector('[data-kpi="total-tourist-spots"] .lupto-kpi-value');
            const elFare = container.querySelector('[data-kpi="total-fare-matrix"] .lupto-kpi-value');
            const elUsers = container.querySelector('[data-kpi="total-tourist-users"] .lupto-kpi-value');
            const elPoints = container.querySelector('[data-kpi="total-points-earned"] .lupto-kpi-value');
            const elVisits = container.querySelector('[data-kpi="total-visits"] .lupto-kpi-value');

            if (elSpots) window.animateKpiValue(elSpots, kpis.total_tourist_spots ?? kpis.totalTouristSpots ?? 0);
            if (elFare) window.animateKpiValue(elFare, kpis.total_fare_matrix ?? 0);
            if (elUsers) window.animateKpiValue(elUsers, kpis.total_tourist_users ?? 0);
            if (elPoints) window.animateKpiValue(elPoints, kpis.total_points_earned ?? 0);
            if (elVisits) window.animateKpiValue(elVisits, kpis.total_visits ?? 0);
        } else {
            const elSpots = container.querySelector('[data-kpi="total-tourist-spots"] .lupto-kpi-value');
            const elFare = container.querySelector('[data-kpi="total-fare-matrix"] .lupto-kpi-value');
            const elUsers = container.querySelector('[data-kpi="total-tourist-users"] .lupto-kpi-value');
            const elPoints = container.querySelector('[data-kpi="total-points-earned"] .lupto-kpi-value');
            const elVisits = container.querySelector('[data-kpi="total-visits"] .lupto-kpi-value');

            if (elSpots) window.animateKpiValue(elSpots, 0);
            if (elFare) window.animateKpiValue(elFare, 0);
            if (elUsers) window.animateKpiValue(elUsers, 0);
            if (elPoints) window.animateKpiValue(elPoints, 0);
            if (elVisits) window.animateKpiValue(elVisits, 0);
        }
    }

    // ── Load Tourist Spots for Map from Cached Payload ────────────────────────────────
    function loadMunicipalitiesData() {
        var mapContainer = document.getElementById('dashboard-map');
        if (!mapContainer) { void 0; return; }

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

        initDashboardMap(spotsWithMuni);
    }

    // ── Initialize Dashboard Map ─────────────────────────────────────────────────
    function initDashboardMap(spots) {
        var mapEl = document.getElementById('dashboard-map');
        if (!mapEl) return;

        if (mapEl._leaflet_map) {
            if (window.MapMarkersConfig && typeof window.MapMarkersConfig.updateMapSpots === 'function') {
                window.MapMarkersConfig.updateMapSpots(mapEl, spots);
                return;
            }
        }

        if (window.MapMarkersConfig && typeof window.MapMarkersConfig.initDashboardMapWithSpots === 'function') {
            window.MapMarkersConfig.initDashboardMapWithSpots(mapEl, spots);
            setupMapFilters(mapEl, spots);
        } else {
            var pollCount = 0;
            var poll = setInterval(function () {
                pollCount++;
                if (window.MapMarkersConfig && typeof window.MapMarkersConfig.initDashboardMapWithSpots === 'function') {
                    clearInterval(poll);
                    window.MapMarkersConfig.initDashboardMapWithSpots(mapEl, spots);
                    setupMapFilters(mapEl, spots);
                } else if (pollCount > 50) {
                    clearInterval(poll);
                    console.error('[Dashboard] MapMarkersConfig failed to load after 5s — map markers will not render. Ensure map-markers-config.js is included in the page.');
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
        });
    }

    // ── Visitor Trends Chart (Line) from Cached Payload ───────────────────────────────────────────────
    function initVisitorTrendsChart(skipError = false) {
        const ctx = document.getElementById('visitorTrendsChart');
        if (!ctx) return;

        try {
            const data = currentDashboardData;
            const trendData = data ? (data.visitorTrends || []) : [];

            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

            // Map visitor trends records to the 12 month labels
            const chartValues = months.map((_, index) => {
                const record = trendData.find(r => r.month == (index + 1));
                return record ? parseInt(record.visits) || 0 : 0;
            });

            const finalData = chartValues.every(v => v === 0)
                ? [32000, 38000, 35000, 42000, 45000, 48000, 41000, 43000, 46000, 48000, 49000, 45200]
                : chartValues;

            if (_dashboardCharts.trends) {
                if (_dashboardCharts.trends.canvas !== ctx) {
                    _dashboardCharts.trends.destroy();
                    _dashboardCharts.trends = null;
                } else {
                    _dashboardCharts.trends.data.datasets[0].data = finalData;
                    _dashboardCharts.trends.update();
                    return;
                }
            }

            // Create premium gradient
            const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 240);
            gradient.addColorStop(0, 'rgba(37, 99, 235, 0.25)');
            gradient.addColorStop(1, 'rgba(37, 99, 235, 0.00)');

            _dashboardCharts.trends = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Monthly Visitors',
                        data: finalData,
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
        } catch (err) {
            if (!skipError) console.error('Failed to load visitor trends:', err);
        }
    }



    // ── Top Municipalities Chart (Bar) from Cached Payload ─────────────────────────────────────────────
    function initTopMunicipalitiesChart(skipError = false) {
        const ctx = document.getElementById('topMunicipalitiesChart');
        if (!ctx) return;

        try {
            const data = currentDashboardData;
            const topMunis = data ? (data.topMunicipalities || []) : [];

            const newLabels = topMunis.length
                ? topMunis.slice(0, 5).map(m => m.name)
                : ['San Juan', 'San Fernando', 'Bauang', 'Agoo', 'Luna'];
            const newValues = topMunis.length
                ? topMunis.slice(0, 5).map(m => parseInt(m.total_visits) || 0)
                : [15200, 12800, 9500, 7800, 6200];

            if (_dashboardCharts.municipalities) {
                if (_dashboardCharts.municipalities.canvas !== ctx) {
                    _dashboardCharts.municipalities.destroy();
                    _dashboardCharts.municipalities = null;
                } else {
                    _dashboardCharts.municipalities.data.labels = newLabels;
                    _dashboardCharts.municipalities.data.datasets[0].data = newValues;
                    _dashboardCharts.municipalities.update();
                    return;
                }
            }

            _dashboardCharts.municipalities = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: topMunis.length
                        ? topMunis.slice(0, 5).map(m => m.name)
                        : ['San Juan', 'San Fernando', 'Bauang', 'Agoo', 'Luna'],
                    datasets: [{
                        label: 'Number of Visitors',
                        data: topMunis.length
                            ? topMunis.slice(0, 5).map(m => parseInt(m.total_visits) || 0)
                            : [15200, 12800, 9500, 7800, 6200],
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
        } catch (err) {
            if (!skipError) console.error('Failed to load top municipalities chart:', err);
        }
    }

    // ── Approval Status Chart (Placeholder logic - returns early if DOM element is missing) ───────────────────────────────────────────
    async function initApprovalStatusChart(skipError = false) {
        const ctx = document.getElementById('approvalStatusChart');
        if (!ctx) return;

        try {
            const fullData = await window.API_CONFIG.get(DASHBOARD_URL + '/analytics/full');

            const pendingCount = fullData.touristSpots
                ? fullData.touristSpots.filter(s => s.status === 'pending').length
                : 15;

            const approvedCount = fullData.touristSpots
                ? fullData.touristSpots.filter(s => s.status === 'approved').length
                : 5;

            const rejectedCount = fullData.touristSpots
                ? fullData.touristSpots.filter(s => s.status === 'rejected').length
                : 0;

            if (_dashboardCharts.approval) {
                if (_dashboardCharts.approval.canvas !== ctx) {
                    _dashboardCharts.approval.destroy();
                    _dashboardCharts.approval = null;
                } else {
                    _dashboardCharts.approval.data.datasets[0].data = [approvedCount, pendingCount, rejectedCount];
                    _dashboardCharts.approval.update();
                    return;
                }
            }

            _dashboardCharts.approval = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Approved', 'Pending', 'Rejected'],
                    datasets: [{
                        data: [approvedCount, pendingCount, rejectedCount],
                        backgroundColor: ['#10B981', '#F59E0B', '#EF4444'],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 14,
                                usePointStyle: true,
                                font: { family: 'Outfit, Inter, sans-serif', size: 11 }
                            }
                        },
                        tooltip: {
                            backgroundColor: '#1E293B',
                            padding: 10,
                            titleFont: { family: 'Outfit, Inter, sans-serif', size: 13, weight: '600' },
                            bodyFont: { family: 'Outfit, Inter, sans-serif', size: 12 },
                            cornerRadius: 8
                        }
                    }
                }
            });
        } catch (err) {
            if (!skipError) console.error('Failed to load approval status chart:', err);
        }
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
    document.addEventListener('click', function(e) {
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

    // Expose control functions globally for the SPA router
    window.startAutoRefresh = startAutoRefresh;
    window.stopAutoRefresh = stopAutoRefresh;
    window.softRefreshDashboard = softRefreshDashboard;

    function preWarmTouristSpotsKpis() {
        const baseUrl = window.API_CONFIG?.BASE_URL || (`http://${window.location.hostname || '127.0.0.1'}:8000`);
        Promise.all([
            window.API_CONFIG.get(`${baseUrl}/api/tourist-spots`),
            window.API_CONFIG.get(`${baseUrl}/api/municipalities`)
        ]).then(([spotsRes, muniRes]) => {
            const spots = spotsRes.data || spotsRes || [];
            const munis = muniRes.municipalities || muniRes.data || muniRes || [];
            const vals = {
                municipalities: munis.length,
                total: spots.length,
                open: spots.filter(s => (s.operation_status || s.status || '') === 'open').length,
                closed: spots.filter(s => (s.operation_status || s.status || '') === 'closed').length,
            };
            try { sessionStorage.setItem('ts_kpis_lupto', JSON.stringify(vals)); } catch (e) { }
        }).catch(() => { });
    }

    // Pre-warm tourist spots KPI cache after dashboard loads
    setTimeout(() => { if (typeof window.softRefreshDashboard === 'function') preWarmTouristSpotsKpis(); }, 1500);

    // ── On DOM Ready ──────────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeDashboard);
    } else {
        initializeDashboard();
    }
})();