/**
 * INTAN ELYU — Voucher & Rewards API & UI Logic (Optimized)
 */

'use strict';

(function () {
    if (window.__VOUCHERS_MODULE_LOADED__) return;
    window.__VOUCHERS_MODULE_LOADED__ = true;

    // Expose functions globally for UI handlers
    window.switchVoucherTab = switchVoucherTab;
    window.refreshVouchers = refreshVouchers;
    window.refreshRedemptions = refreshRedemptions;
    window.debouncedVchSearch = debouncedVchSearch;
    window.applyVchFilters = applyVchFilters;
    window.clearVchFilters = clearVchFilters;
    window.debouncedRedSearch = debouncedRedSearch;
    window.applyRedFilters = applyRedFilters;
    window.clearRedFilters = clearRedFilters;
    window.goVchPage = goVchPage;
    window.goRedPage = goRedPage;
    window.openCreateVoucherModal = openCreateVoucherModal;
    window.openEditVoucherModal = openEditVoucherModal;
    window.closeVoucherModal = closeVoucherModal;
    window.closeConfirmModal = closeConfirmModal;
    window.handleVoucherSubmit = handleVoucherSubmit;
    window.openDetailsModal = openDetailsModal;
    window.closeDetailsModal = closeDetailsModal;
    window.toggleVoucherStatus = toggleVoucherStatus;
    window.archiveVoucher = archiveVoucher;
    window.openStatusModal = openStatusModal;
    window.closeStatusModal = closeStatusModal;
    window.handleStatusSubmit = handleStatusSubmit;
    window.handleDiscountTypeChange = handleDiscountTypeChange;
    window.escapeHtml = escapeHtml;

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    const userRole = (document.body?.dataset?.role || document.querySelector('meta[name="user-role"]')?.content || 'lupto').toLowerCase();
    
    let VCH_API = (window.API_CONFIG?.LUPTO || 'http://localhost:8000/api/lupto') + '/vouchers';
    if (userRole === 'picto' || userRole === 'pitco') {
        VCH_API = (window.API_CONFIG?.PITCO || 'http://localhost:8000/api/pitco') + '/vouchers';
    } else if (userRole === 'municipal' || userRole.endsWith('_mto')) {
        VCH_API = (window.API_CONFIG?.MUNICIPAL || 'http://localhost:8000/api/municipal') + '/vouchers';
    }

    let MUNI_API = (window.API_CONFIG?.BASE_URL ? `${window.API_CONFIG.BASE_URL}/api/municipalities` : 'http://localhost:8000/api/municipalities');

    // Central fetch wrapper with credentials: 'include' for session cookies
    async function apiFetch(url, options = {}) {
        if (window.API_CONFIG && typeof window.API_CONFIG.fetch === 'function') {
            return await window.API_CONFIG.fetch(url, options);
        }

        const method = (options.method || 'GET').toUpperCase();
        const headers = {
            'Accept': 'application/json',
            ...(options.headers || {})
        };
        if (method !== 'GET' && method !== 'HEAD' && !headers['Content-Type']) {
            headers['Content-Type'] = 'application/json';
        }

        const res = await fetch(url, {
            credentials: 'include',
            ...options,
            headers
        });
        return await res.json();
    }

    // Module State & Caching
    let vchCurrentPage = 1;
    let vchSearchTimeout = null;
    let redCurrentPage = 1;
    let redSearchTimeout = null;
    let charts = {};

    window.__VCH_MODULE_CACHE__ = window.__VCH_MODULE_CACHE__ || {
        vouchers: null,
        redemptions: null,
        kpis: null,
        charts: null,
    };

    // Initialize on DOM Ready
    document.addEventListener('DOMContentLoaded', () => {
        initVouchersModule();
    });
    // Run immediately if DOM already loaded (SPA navigation)
    if (document.readyState !== 'loading') {
        initVouchersModule();
    }

    async function initVouchersModule() {
        // If cache exists, render immediately for instant 0ms response
        if (window.__VCH_MODULE_CACHE__.kpis) {
            renderKpis(window.__VCH_MODULE_CACHE__.kpis);
        }

        // Run municipality fetching, vouchers list, and KPI fetching concurrently in parallel
        await Promise.allSettled([
            fetchMunicipalities(),
            loadVouchers(1),
            loadStats(false)
        ]);
    }

    // Switch Tabs
    function switchVoucherTab(tabName) {
        document.querySelectorAll('.vch-tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.vch-tab-content').forEach(ct => ct.classList.remove('active'));

        if (tabName === 'catalog') {
            document.getElementById('tabBtnCatalog')?.classList.add('active');
            document.getElementById('tabContentCatalog')?.classList.add('active');
            loadVouchers();
        } else if (tabName === 'history') {
            document.getElementById('tabBtnHistory')?.classList.add('active');
            document.getElementById('tabContentHistory')?.classList.add('active');
            loadRedemptions();
        } else if (tabName === 'analytics') {
            document.getElementById('tabBtnAnalytics')?.classList.add('active');
            document.getElementById('tabContentAnalytics')?.classList.add('active');
            loadStats(true); // Load heavy charts dataset on demand
        }
    }

    // Fetch Municipalities (Cached in memory)
    async function fetchMunicipalities() {
        if (window.__MUNI_LIST_CACHE__ && Array.isArray(window.__MUNI_LIST_CACHE__)) {
            populateMuniDropdowns(window.__MUNI_LIST_CACHE__);
            return;
        }

        try {
            const result = await apiFetch(MUNI_API);
            if (result && result.success && Array.isArray(result.data)) {
                window.__MUNI_LIST_CACHE__ = result.data;
                populateMuniDropdowns(result.data);
            }
        } catch (err) {
            console.error('Failed to load municipalities:', err);
        }
    }

    function populateMuniDropdowns(municipalitiesList) {
        const vchSelect = document.getElementById('vchMuniFilter');
        const redSelect = document.getElementById('redMuniFilter');
        const formSelect = document.getElementById('formMunicipality');

        let options = '<option value="">All Municipalities</option>';
        municipalitiesList.forEach(m => {
            options += `<option value="${m.id}">${escapeHtml(m.name)}</option>`;
        });

        if (vchSelect) vchSelect.innerHTML = options;
        if (redSelect) redSelect.innerHTML = options;
        if (formSelect) {
            formSelect.innerHTML = '<option value="">Provincial / All Municipalities</option>';
            formSelect.value = '';
            formSelect.disabled = true;
        }
    }

    // LOAD VOUCHERS
    async function loadVouchers(page = 1) {
        vchCurrentPage = page;
        const tbody = document.getElementById('vouchersTableBody');
        if (!tbody) return;

        // Render from cache first if available for instant load
        if (page === 1 && window.__VCH_MODULE_CACHE__.vouchers) {
            renderVouchersTable(window.__VCH_MODULE_CACHE__.vouchers.data);
            renderPagination('vchPaginationInfo', 'vchPaginationBtns', window.__VCH_MODULE_CACHE__.vouchers.meta, goVchPage);
        } else if (!tbody.children.length || tbody.querySelector('.vch-empty')) {
            tbody.innerHTML = `<tr><td colspan="11" class="vch-empty"><i class="fas fa-spinner fa-spin"></i><p>Loading vouchers catalog...</p></td></tr>`;
        }

        const params = new URLSearchParams({
            page: page,
            per_page: 12,
            search: document.getElementById('vchSearchInput')?.value || '',
            municipality_id: document.getElementById('vchMuniFilter')?.value || '',
            status: document.getElementById('vchStatusFilter')?.value || '',
            discount_type: document.getElementById('vchTypeFilter')?.value || '',
            sort_by: document.getElementById('vchSortSelect')?.value || 'created_at',
        });

        try {
            const result = await apiFetch(`${VCH_API}?${params.toString()}`);

            if (result && result.success && Array.isArray(result.data)) {
                if (page === 1 && !params.get('search') && !params.get('municipality_id') && !params.get('status') && !params.get('discount_type')) {
                    window.__VCH_MODULE_CACHE__.vouchers = result;
                }
                renderVouchersTable(result.data);
                renderPagination('vchPaginationInfo', 'vchPaginationBtns', result.meta, goVchPage);
            } else {
                tbody.innerHTML = `<tr><td colspan="11" class="vch-empty"><i class="fas fa-info-circle"></i><p>No vouchers found.</p></td></tr>`;
            }
        } catch (err) {
            console.error('Error loading vouchers:', err);
            tbody.innerHTML = `<tr><td colspan="11" class="vch-empty"><i class="fas fa-exclamation-triangle"></i><p>Failed to connect to backend service.</p></td></tr>`;
        }
    }

    function renderVouchersTable(vouchers) {
        const tbody = document.getElementById('vouchersTableBody');
        if (!tbody) return;

        if (!vouchers || vouchers.length === 0) {
            tbody.innerHTML = `<tr><td colspan="11" class="vch-empty"><i class="fas fa-inbox"></i><p>No matching vouchers available.</p></td></tr>`;
            return;
        }

        const isLupto = userRole === 'lupto';

        tbody.innerHTML = vouchers.map(v => {
            const rawImg = v.image || '../images/LOGO.png';
            const imgUrl = (window.API_CONFIG && typeof window.API_CONFIG.resolveImageUrl === 'function') 
                ? window.API_CONFIG.resolveImageUrl(rawImg) 
                : rawImg;

            const muniName = v.municipality ? v.municipality.name : 'Provincial';
            const discountLabel = formatDiscount(v.discount_type, v.discount_value);
            const validRange = `${formatDate(v.valid_from)} – ${formatDate(v.expires_at)}`;
            
            let statusBadge = `<span class="vch-badge ${v.status_badge}">${v.is_expired ? 'Expired' : v.status}</span>`;

            let actionsHtml = `
                <button class="vch-btn vch-btn-light vch-btn-sm" onclick="openDetailsModal(${v.id})" title="View Details">
                    <i class="fas fa-eye"></i>
                </button>
            `;

            if (isLupto) {
                actionsHtml += `
                    <button class="vch-btn vch-btn-light vch-btn-sm" onclick="openEditVoucherModal(${v.id})" title="Edit Voucher">
                        <i class="fas fa-edit"></i>
                    </button>
                `;
                if (v.status === 'active') {
                    actionsHtml += `
                        <button class="vch-btn vch-btn-light vch-btn-sm" onclick="toggleVoucherStatus(${v.id}, 'inactive')" title="Deactivate">
                            <i class="fas fa-pause" style="color: #ea580c;"></i>
                        </button>
                    `;
                } else if (v.status === 'inactive') {
                    actionsHtml += `
                        <button class="vch-btn vch-btn-light vch-btn-sm" onclick="toggleVoucherStatus(${v.id}, 'active')" title="Activate">
                            <i class="fas fa-play" style="color: #16a34a;"></i>
                        </button>
                    `;
                }

                if (v.status !== 'archived') {
                    actionsHtml += `
                        <button class="vch-btn vch-btn-light vch-btn-sm" onclick="archiveVoucher(${v.id})" title="Archive">
                            <i class="fas fa-archive" style="color: #7c3aed;"></i>
                        </button>
                    `;
                }
            }

            return `
                <tr>
                    <td>
                        <img src="${escapeHtml(imgUrl)}" class="vch-table-img" alt="${escapeHtml(v.voucher_name)}" onerror="this.onerror=null;this.src='../images/LOGO.png';">
                    </td>
                    <td>
                        <div style="font-weight: 700; color: #1e3a8a;">${escapeHtml(v.voucher_name)}</div>
                        <div style="font-size: 11px; color: #64748b; font-family: monospace;">CODE: ${escapeHtml(v.voucher_code)}</div>
                        ${v.partner_establishment ? `<div style="font-size: 11px; color: #0284c7;"><i class="fas fa-store"></i> ${escapeHtml(v.partner_establishment)}</div>` : ''}
                    </td>
                    <td><strong>${discountLabel}</strong></td>
                    <td><span style="font-weight: 700; color: #eab308;"><i class="fas fa-star"></i> ${v.required_points} pts</span></td>
                    <td>${v.available_quantity}</td>
                    <td><span style="color: #9333ea; font-weight: 600;">${v.redeemed_quantity}</span></td>
                    <td><strong style="color: ${v.remaining_quantity <= 5 ? '#c2410c' : '#16a34a'};">${v.remaining_quantity}</strong></td>
                    <td><span class="vch-badge blue">${escapeHtml(muniName)}</span></td>
                    <td><div style="font-size: 11px; line-height: 1.3;">${validRange}</div></td>
                    <td>${statusBadge}</td>
                    <td style="text-align: right; white-space: nowrap;">${actionsHtml}</td>
                </tr>
            `;
        }).join('');
    }

    // LOAD REDEMPTIONS HISTORY
    async function loadRedemptions(page = 1) {
        redCurrentPage = page;
        const tbody = document.getElementById('redemptionsTableBody');
        if (!tbody) return;

        if (page === 1 && window.__VCH_MODULE_CACHE__.redemptions) {
            renderRedemptionsTable(window.__VCH_MODULE_CACHE__.redemptions.data);
            renderPagination('redPaginationInfo', 'redPaginationBtns', window.__VCH_MODULE_CACHE__.redemptions.meta, goRedPage);
        } else if (!tbody.children.length || tbody.querySelector('.vch-empty')) {
            tbody.innerHTML = `<tr><td colspan="9" class="vch-empty"><i class="fas fa-spinner fa-spin"></i><p>Loading redemption records...</p></td></tr>`;
        }

        const params = new URLSearchParams({
            page: page,
            per_page: 15,
            search: document.getElementById('redSearchInput')?.value || '',
            status: document.getElementById('redStatusFilter')?.value || '',
            municipality_id: document.getElementById('redMuniFilter')?.value || '',
        });

        try {
            const result = await apiFetch(`${VCH_API}/redemptions?${params.toString()}`);

            if (result && result.success && Array.isArray(result.data)) {
                if (page === 1 && !params.get('search') && !params.get('status') && !params.get('municipality_id')) {
                    window.__VCH_MODULE_CACHE__.redemptions = result;
                }
                renderRedemptionsTable(result.data);
                renderPagination('redPaginationInfo', 'redPaginationBtns', result.meta, goRedPage);
            } else {
                tbody.innerHTML = `<tr><td colspan="9" class="vch-empty"><i class="fas fa-info-circle"></i><p>No redemption history found.</p></td></tr>`;
            }
        } catch (err) {
            console.error('Error loading redemptions:', err);
            tbody.innerHTML = `<tr><td colspan="9" class="vch-empty"><i class="fas fa-exclamation-triangle"></i><p>Failed to connect to backend service.</p></td></tr>`;
        }
    }

    function renderRedemptionsTable(redemptions) {
        const tbody = document.getElementById('redemptionsTableBody');
        if (!tbody) return;

        if (!redemptions || redemptions.length === 0) {
            tbody.innerHTML = `<tr><td colspan="9" class="vch-empty"><i class="fas fa-inbox"></i><p>No redemption records match the criteria.</p></td></tr>`;
            return;
        }

        tbody.innerHTML = redemptions.map(r => {
            const touristName = r.user ? r.user.name : 'Unknown Tourist';
            const muniName = (r.user && r.user.municipality) ? r.user.municipality.name : 'Provincial';
            const voucherName = r.voucher ? r.voucher.voucher_name : 'Deleted Voucher';
            const redeemedDate = formatDate(r.redeemed_at, true);
            const claimedDate = r.claimed_at ? formatDate(r.claimed_at, true) : '—';

            let statusClass = 'gray';
            if (r.status === 'claimed') statusClass = 'blue';
            if (r.status === 'completed') statusClass = 'green';
            if (r.status === 'pending') statusClass = 'orange';
            if (r.status === 'cancelled' || r.status === 'expired') statusClass = 'red';

            return `
                <tr>
                    <td><strong style="color: #1e3a8a; font-family: monospace;">${escapeHtml(r.redemption_code)}</strong></td>
                    <td>${escapeHtml(touristName)}</td>
                    <td><span class="vch-badge blue">${escapeHtml(muniName)}</span></td>
                    <td><strong>${escapeHtml(voucherName)}</strong></td>
                    <td><span style="font-weight: 700; color: #eab308;"><i class="fas fa-star"></i> ${r.points_used}</span></td>
                    <td><div style="font-size: 11px;">${redeemedDate}</div></td>
                    <td><div style="font-size: 11px;">${claimedDate}</div></td>
                    <td><span class="vch-badge ${statusClass}">${r.status}</span></td>
                    <td style="text-align: right;">
                        <button class="vch-btn vch-btn-light vch-btn-sm" onclick="openStatusModal(${r.id}, '${r.status}', '${r.redemption_code}')" title="Update Status">
                            <i class="fas fa-tasks"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    // LOAD STATS & CHARTS
    async function loadStats(includeCharts = false) {
        const url = `${VCH_API}/stats` + (includeCharts ? '?charts=1' : '');
        try {
            const result = await apiFetch(url);

            if (result && result.success && result.data) {
                const d = result.data;
                if (d.kpis) {
                    window.__VCH_MODULE_CACHE__.kpis = d.kpis;
                    renderKpis(d.kpis);
                }
                if (includeCharts && d.most_redeemed) {
                    window.__VCH_MODULE_CACHE__.charts = d;
                    renderAnalyticsCharts(d);
                }
            }
        } catch (err) {
            console.error('Error loading stats:', err);
        }
    }

    function renderKpis(kpis) {
        if (!kpis) return;
        setKpiValue('kpiTotalVouchers', kpis.total_vouchers);
        setKpiValue('kpiActiveVouchers', kpis.active_vouchers);
        setKpiValue('kpiTotalRedeemed', kpis.total_redeemed);
        setKpiValue('kpiTotalPointsRedeemed', kpis.total_points_redeemed);
        setKpiValue('kpiExpiredVouchers', kpis.expired_vouchers);
    }

    function setKpiValue(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = Number(val || 0).toLocaleString();
    }

    function renderAnalyticsCharts(data) {
        if (typeof Chart === 'undefined') return;

        // 1. Most Redeemed Vouchers (Bar)
        const ctxMost = document.getElementById('chartMostRedeemed')?.getContext('2d');
        if (ctxMost && data.most_redeemed) {
            if (charts.most) charts.most.destroy();
            charts.most = new Chart(ctxMost, {
                type: 'bar',
                data: {
                    labels: data.most_redeemed.map(v => v.voucher_name),
                    datasets: [{
                        label: 'Redemptions',
                        data: data.most_redeemed.map(v => v.redeemed_quantity),
                        backgroundColor: '#2563eb',
                        borderRadius: 6
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }

        // 2. Monthly Trend (Line)
        const ctxTrend = document.getElementById('chartMonthlyTrend')?.getContext('2d');
        if (ctxTrend && data.monthly_trend) {
            if (charts.trend) charts.trend.destroy();
            charts.trend = new Chart(ctxTrend, {
                type: 'line',
                data: {
                    labels: data.monthly_trend.map(t => t.month_label),
                    datasets: [{
                        label: 'Total Redemptions',
                        data: data.monthly_trend.map(t => t.total_redemptions),
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22, 163, 74, 0.1)',
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }

        // 3. By Municipality (Doughnut)
        const ctxMuni = document.getElementById('chartByMunicipality')?.getContext('2d');
        if (ctxMuni && data.by_municipality) {
            if (charts.muni) charts.muni.destroy();
            charts.muni = new Chart(ctxMuni, {
                type: 'doughnut',
                data: {
                    labels: data.by_municipality.map(m => m.municipality_name),
                    datasets: [{
                        data: data.by_municipality.map(m => m.count),
                        backgroundColor: ['#1e3a8a', '#2563eb', '#9333ea', '#ea580c', '#eab308', '#16a34a', '#0284c7']
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }

        // 4. Availability (Pie)
        const ctxAvail = document.getElementById('chartAvailability')?.getContext('2d');
        if (ctxAvail && data.availability_breakdown) {
            if (charts.avail) charts.avail.destroy();
            const ab = data.availability_breakdown;
            charts.avail = new Chart(ctxAvail, {
                type: 'pie',
                data: {
                    labels: ['Active', 'Inactive', 'Expired', 'Out of Stock'],
                    datasets: [{
                        data: [ab.active, ab.inactive, ab.expired, ab.out_of_stock],
                        backgroundColor: ['#16a34a', '#64748b', '#dc2626', '#ea580c']
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }
    }

    // MODALS HANDLERS
    function openCreateVoucherModal() {
        document.getElementById('voucherForm')?.reset();
        document.getElementById('formVoucherId').value = '';
        const formPartner = document.getElementById('formPartner');
        if (formPartner) { formPartner.value = 'The La Union Agri-Tourism Center Pasalubong Center'; }
        const formMuni = document.getElementById('formMunicipality');
        if (formMuni) { formMuni.value = ''; formMuni.disabled = true; }
        document.getElementById('voucherModalTitle').innerHTML = '<i class="fas fa-ticket-simple"></i> Create New Voucher';
        document.getElementById('voucherModal').style.display = 'flex';
        handleDiscountTypeChange();
    }

    async function openEditVoucherModal(id) {
        try {
            const result = await apiFetch(`${VCH_API}/${id}`);

            if (result && result.success && result.data) {
                const v = result.data;
                const setVal = (elemId, val) => {
                    const el = document.getElementById(elemId);
                    if (el) el.value = val ?? '';
                };

                setVal('formVoucherId', v.id);
                setVal('formVoucherName', v.voucher_name);
                setVal('formVoucherCode', v.voucher_code);
                setVal('formDiscountType', v.discount_type || 'percentage');
                setVal('formDiscountValue', v.discount_value);
                setVal('formRequiredPoints', v.required_points || 0);
                setVal('formAvailableQuantity', v.available_quantity || 1);
                setVal('formMaxRedemption', v.maximum_redemption_per_user || 1);
                const formMuni = document.getElementById('formMunicipality');
                if (formMuni) { formMuni.value = v.municipality_id ?? ''; formMuni.disabled = true; }
                setVal('formPartner', v.partner_establishment || 'The La Union Agri-Tourism Center Pasalubong Center');
                setVal('formValidFrom', formatISOForInput(v.valid_from));
                setVal('formExpiresAt', formatISOForInput(v.expires_at));
                setVal('formStatus', v.status || 'active');
                setVal('formImage', v.image);
                setVal('formDescription', v.description);
                setVal('formTerms', v.terms_and_conditions);

                document.getElementById('voucherModalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Voucher';
                document.getElementById('voucherModal').style.display = 'flex';
                handleDiscountTypeChange();
            }
        } catch (err) {
            alert('Failed to load voucher details for editing.');
        }
    }

    function closeVoucherModal() {
        document.getElementById('voucherModal').style.display = 'none';
    }

    function closeConfirmModal() {
        const modal = document.getElementById('voucherConfirmModal');
        if (modal) modal.style.display = 'none';
    }

    function handleDiscountTypeChange() {
        const type = document.getElementById('formDiscountType')?.value;
        const valGroup = document.getElementById('groupDiscountValue');
        if (!valGroup) return;

        if (['free_entrance', 'bogo', 'free_souvenir', 'custom'].includes(type)) {
            valGroup.style.display = 'none';
            const valInput = document.getElementById('formDiscountValue');
            if (valInput) valInput.required = false;
        } else {
            valGroup.style.display = 'block';
            const valInput = document.getElementById('formDiscountValue');
            if (valInput) valInput.required = true;
        }
    }

    function handleVoucherSubmit(e) {
        e.preventDefault();

        const form = document.getElementById('voucherForm');
        if (form && !form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const id = document.getElementById('formVoucherId')?.value;
        const isEdit = !!id;

        const getVal = (elemId) => {
            const raw = document.getElementById(elemId)?.value;
            return (raw !== undefined && raw !== null && String(raw).trim() !== '') ? String(raw).trim() : null;
        };

        const payload = {
            voucher_name: getVal('formVoucherName'),
            voucher_code: getVal('formVoucherCode'),
            discount_type: getVal('formDiscountType') || 'percentage',
            discount_value: getVal('formDiscountValue') ? parseFloat(getVal('formDiscountValue')) : null,
            required_points: parseInt(getVal('formRequiredPoints') || '0', 10),
            available_quantity: parseInt(getVal('formAvailableQuantity') || '1', 10),
            maximum_redemption_per_user: parseInt(getVal('formMaxRedemption') || '1', 10),
            municipality_id: getVal('formMunicipality') ? parseInt(getVal('formMunicipality'), 10) : null,
            partner_establishment: getVal('formPartner'),
            valid_from: getVal('formValidFrom'),
            expires_at: getVal('formExpiresAt'),
            status: getVal('formStatus') || 'active',
            image: getVal('formImage'),
            description: getVal('formDescription'),
            terms_and_conditions: getVal('formTerms'),
        };

        const confirmText = isEdit 
            ? 'Are you sure you want to save changes to this voucher?' 
            : 'Are you sure you want to add this?';

        const confirmTextEl = document.getElementById('confirmModalText');
        if (confirmTextEl) confirmTextEl.textContent = confirmText;

        const confirmYesBtn = document.getElementById('btnConfirmYes');
        if (confirmYesBtn) {
            confirmYesBtn.onclick = async () => {
                closeConfirmModal();
                await executeSaveVoucher(payload, isEdit, id);
            };
        }

        const confirmModal = document.getElementById('voucherConfirmModal');
        if (confirmModal) {
            confirmModal.style.display = 'flex';
        } else {
            if (confirm(confirmText)) {
                executeSaveVoucher(payload, isEdit, id);
            }
        }
    }

    async function executeSaveVoucher(payload, isEdit, id) {
        const url = isEdit ? `${VCH_API}/${id}` : VCH_API;
        const method = isEdit ? 'PUT' : 'POST';

        const btn = document.getElementById('btnSubmitVoucher');
        if (btn) btn.disabled = true;

        try {
            const result = await apiFetch(url, {
                method: method,
                body: JSON.stringify(payload)
            });

            if (result && (result.success || result.data)) {
                closeVoucherModal();
                window.__VCH_MODULE_CACHE__.vouchers = null;
                loadVouchers(1);
                loadStats(false);
            } else {
                alert((result && (result.message || result.error)) || 'Operation failed.');
            }
        } catch (err) {
            alert((err && (err.message || err.error)) || 'Failed to save voucher. Please try again.');
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    async function openDetailsModal(id) {
        const modal = document.getElementById('voucherDetailsModal');
        const body = document.getElementById('voucherDetailsBody');
        modal.style.display = 'flex';
        body.innerHTML = `<div class="vch-empty"><i class="fas fa-spinner fa-spin"></i><p>Loading details...</p></div>`;

        try {
            const result = await apiFetch(`${VCH_API}/${id}`);

            if (result && result.success && result.data) {
                const v = result.data;
                const muniName = v.municipality ? v.municipality.name : 'Provincial';
                const creator = v.user ? v.user.name : 'System Admin';
                const rawImg = v.image || '../images/LOGO.png';
                const imgUrl = (window.API_CONFIG && typeof window.API_CONFIG.resolveImageUrl === 'function')
                    ? window.API_CONFIG.resolveImageUrl(rawImg)
                    : rawImg;

                body.innerHTML = `
                    <div style="text-align: center; margin-bottom: 16px;">
                        <img src="${escapeHtml(imgUrl)}" style="max-height: 160px; border-radius: 10px; object-fit: cover;" onerror="this.onerror=null;this.src='../images/LOGO.png';">
                        <h2 style="font-family: Outfit, sans-serif; margin: 10px 0 4px; color: #1e3a8a;">${escapeHtml(v.voucher_name)}</h2>
                        <span class="vch-badge ${v.status_badge}">${v.status}</span>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; background: #f8fafc; padding: 14px; border-radius: 8px; font-size: 13px; margin-bottom: 14px;">
                        <div><strong>Voucher Code:</strong> <span style="font-family: monospace;">${escapeHtml(v.voucher_code)}</span></div>
                        <div><strong>Discount:</strong> ${formatDiscount(v.discount_type, v.discount_value)}</div>
                        <div><strong>Required Points:</strong> ${v.required_points} pts</div>
                        <div><strong>Remaining Stock:</strong> ${v.remaining_quantity} / ${v.available_quantity}</div>
                        <div><strong>Redeemed:</strong> ${v.redeemed_quantity} times</div>
                        <div><strong>Municipality:</strong> ${escapeHtml(muniName)}</div>
                        <div><strong>Partner:</strong> ${escapeHtml(v.partner_establishment || 'N/A')}</div>
                        <div><strong>Created By:</strong> ${escapeHtml(creator)}</div>
                        <div style="grid-column: span 2;"><strong>Validity:</strong> ${formatDate(v.valid_from)} to ${formatDate(v.expires_at)}</div>
                    </div>

                    ${v.description ? `<div style="margin-bottom: 12px;"><strong>Description:</strong><p style="font-size: 13px; color: #475569; margin: 4px 0;">${escapeHtml(v.description)}</p></div>` : ''}
                    ${v.terms_and_conditions ? `<div><strong>Terms & Conditions:</strong><p style="font-size: 13px; color: #475569; margin: 4px 0;">${escapeHtml(v.terms_and_conditions)}</p></div>` : ''}
                `;
            }
        } catch (err) {
            body.innerHTML = `<div class="vch-empty"><i class="fas fa-exclamation-circle"></i><p>Failed to load details.</p></div>`;
        }
    }

    function closeDetailsModal() {
        document.getElementById('voucherDetailsModal').style.display = 'none';
    }

    async function toggleVoucherStatus(id, newStatus) {
        if (!confirm(`Are you sure you want to set status to ${newStatus}?`)) return;

        try {
            const result = await apiFetch(`${VCH_API}/${id}/status`, {
                method: 'PATCH',
                body: JSON.stringify({ status: newStatus })
            });

            if (result && result.success) {
                window.__VCH_MODULE_CACHE__.vouchers = null;
                loadVouchers(vchCurrentPage);
                loadStats(false);
            } else {
                alert(result.message || 'Action failed.');
            }
        } catch (err) {
            alert('Failed to update status.');
        }
    }

    async function archiveVoucher(id) {
        if (!confirm('Are you sure you want to archive this voucher? It will remain stored for record keeping.')) return;
        return toggleVoucherStatus(id, 'archived');
    }

    function openStatusModal(id, currentStatus, code) {
        document.getElementById('statusRedemptionId').value = id;
        document.getElementById('statusRedCode').textContent = code;
        document.getElementById('newRedStatus').value = currentStatus;
        document.getElementById('updateStatusModal').style.display = 'flex';
    }

    function closeStatusModal() {
        document.getElementById('updateStatusModal').style.display = 'none';
    }

    async function handleStatusSubmit(e) {
        e.preventDefault();
        const id = document.getElementById('statusRedemptionId').value;
        const newStatus = document.getElementById('newRedStatus').value;

        try {
            const result = await apiFetch(`${VCH_API}/redemptions/${id}/status`, {
                method: 'PATCH',
                body: JSON.stringify({ status: newStatus })
            });

            if (result && result.success) {
                closeStatusModal();
                window.__VCH_MODULE_CACHE__.redemptions = null;
                loadRedemptions(redCurrentPage);
            } else {
                alert(result.message || 'Failed to update status.');
            }
        } catch (err) {
            alert('Failed to update status.');
        }
    }

    // Filter & Search Handlers
    function debouncedVchSearch() {
        clearTimeout(vchSearchTimeout);
        vchSearchTimeout = setTimeout(() => loadVouchers(1), 350);
    }

    function applyVchFilters() { loadVouchers(1); }

    function clearVchFilters() {
        document.getElementById('vchSearchInput').value = '';
        document.getElementById('vchMuniFilter').value = '';
        document.getElementById('vchStatusFilter').value = '';
        document.getElementById('vchTypeFilter').value = '';
        document.getElementById('vchSortSelect').value = 'created_at';
        loadVouchers(1);
    }

    function refreshVouchers() {
        const icon = document.getElementById('vchRefreshIcon');
        if (icon) icon.classList.add('fa-spin');
        window.__VCH_MODULE_CACHE__.vouchers = null;
        loadVouchers(vchCurrentPage).finally(() => {
            if (icon) icon.classList.remove('fa-spin');
        });
    }

    function debouncedRedSearch() {
        clearTimeout(redSearchTimeout);
        redSearchTimeout = setTimeout(() => loadRedemptions(1), 350);
    }

    function applyRedFilters() { loadRedemptions(1); }

    function clearRedFilters() {
        document.getElementById('redSearchInput').value = '';
        document.getElementById('redStatusFilter').value = '';
        document.getElementById('redMuniFilter').value = '';
        loadRedemptions(1);
    }

    function refreshRedemptions() {
        const icon = document.getElementById('redRefreshIcon');
        if (icon) icon.classList.add('fa-spin');
        window.__VCH_MODULE_CACHE__.redemptions = null;
        loadRedemptions(redCurrentPage).finally(() => {
            if (icon) icon.classList.remove('fa-spin');
        });
    }

    function goVchPage(page) { loadVouchers(page); }
    function goRedPage(page) { loadRedemptions(page); }

    // Pagination Renderer
    function renderPagination(infoId, btnsId, meta, pageCallback) {
        const infoEl = document.getElementById(infoId);
        const btnsEl = document.getElementById(btnsId);

        if (!meta || meta.total === 0) {
            if (infoEl) infoEl.textContent = '0 items';
            if (btnsEl) btnsEl.innerHTML = '';
            return;
        }

        const start = (meta.current_page - 1) * meta.per_page + 1;
        const end = Math.min(meta.current_page * meta.per_page, meta.total);
        if (infoEl) infoEl.textContent = `Showing ${start}–${end} of ${meta.total}`;

        let html = '';
        if (meta.current_page > 1) {
            html += `<button class="vch-page-btn" onclick="(${pageCallback.name})(${meta.current_page - 1})"><i class="fas fa-chevron-left"></i></button>`;
        }

        for (let i = 1; i <= meta.last_page; i++) {
            if (i === 1 || i === meta.last_page || (i >= meta.current_page - 1 && i <= meta.current_page + 1)) {
                html += `<button class="vch-page-btn ${i === meta.current_page ? 'active' : ''}" onclick="(${pageCallback.name})(${i})">${i}</button>`;
            } else if (i === meta.current_page - 2 || i === meta.current_page + 2) {
                html += `<span style="padding: 0 4px; font-size: 12px; color: #94a3b8;">...</span>`;
            }
        }

        if (meta.current_page < meta.last_page) {
            html += `<button class="vch-page-btn" onclick="(${pageCallback.name})(${meta.current_page + 1})"><i class="fas fa-chevron-right"></i></button>`;
        }

        if (btnsEl) btnsEl.innerHTML = html;
    }

    // Utility Helpers
    function formatDiscount(type, val) {
        switch (type) {
            case 'percentage': return `${val || 0}% Off`;
            case 'fixed': return `₱${Number(val || 0).toLocaleString()} Off`;
            case 'free_entrance': return 'Free Entrance';
            case 'bogo': return 'Buy One Get One';
            case 'free_souvenir': return 'Free Souvenir';
            case 'custom': return 'Custom Reward';
            default: return 'Discount';
        }
    }

    function formatDate(dtStr, withTime = false) {
        if (!dtStr) return '—';
        const d = new Date(dtStr);
        if (isNaN(d.getTime())) return dtStr;
        const opts = { month: 'short', day: 'numeric', year: 'numeric' };
        if (withTime) {
            opts.hour = '2-digit';
            opts.minute = '2-digit';
        }
        return d.toLocaleDateString('en-US', opts);
    }

    function formatISOForInput(dtStr) {
        if (!dtStr) return '';
        const d = new Date(dtStr);
        if (isNaN(d.getTime())) return '';
        return d.toISOString().slice(0, 16);
    }

})();
