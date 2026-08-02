/**
 * proof-validation-api.js
 * Proof Images Validation Module Client Script
 * Shared across PICTO, LUPTO, and MUNICIPAL (MTO) roles.
 */

(function () {
    'use strict';

    if (window.__pvModuleLoaded) {
        if (typeof window.initProofValidationModule === 'function') {
            window.initProofValidationModule();
        }
        return;
    }
    window.__pvModuleLoaded = true;

    // ── Get Base API URL based on active user role ──────────────────────────────
    function getBaseUrl() {
        const role = (window.userRole || document.body?.dataset?.role || document.querySelector('meta[name="user-role"]')?.content || '').toLowerCase();
        const path = (window.location.pathname || '').toUpperCase();
        const host = window.location.hostname || 'localhost';
        const proto = window.location.protocol || 'http:';
        const defaultBase = window.API_CONFIG?.BASE_URL || `${proto}//${host}:8000`;

        if (role === 'picto' || role === 'pitco' || path.includes('/PICTO/')) {
            return window.API_CONFIG?.PITCO || `${defaultBase}/api/pitco`;
        }
        if (role === 'municipal' || role.includes('municipal') || role.endsWith('_mto') || path.includes('/MUNICIPAL/')) {
            return window.API_CONFIG?.MUNICIPAL || `${defaultBase}/api/municipal`;
        }
        return window.API_CONFIG?.LUPTO || `${defaultBase}/api/lupto`;
    }

    // ── State Variables ────────────────────────────────────────────────────────
    let currentPage = 1;
    let targetActionId = null;
    let searchTimeout = null;
    let isInitialized = false;

    // ── Helper: Escape HTML string ─────────────────────────────────────────────
    function escHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── Helper: Toast Notifications ────────────────────────────────────────────
    function showToast(message, type = 'success') {
        const existing = document.querySelector('.pv-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = `pv-toast ${type}`;
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        toast.innerHTML = `<i class="fas ${icon}"></i> <span>${escHtml(message)}</span>`;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }

    // ── Helper: Generic API Fetch ──────────────────────────────────────────────
    async function apiFetch(url, options = {}) {
        if (window.API_CONFIG && typeof window.API_CONFIG.fetch === 'function') {
            return await window.API_CONFIG.fetch(url, options);
        }
        const headers = { Accept: 'application/json', ...(options.headers || {}) };
        if (options.body && !(options.body instanceof FormData) && !headers['Content-Type']) {
            headers['Content-Type'] = 'application/json';
        }
        const res = await fetch(url, { credentials: 'include', ...options, headers });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            throw new Error(data.message || data.error || `HTTP ${res.status}`);
        }
        return data;
    }

    // ── Helper: Status Badge HTML ──────────────────────────────────────────────
    function getStatusBadge(status) {
        const s = (status || 'pending').toLowerCase();
        switch (s) {
            case 'approved':
                return `<span class="pv-badge pv-badge-approved"><i class="fas fa-check-circle"></i> Approved</span>`;
            case 'rejected':
                return `<span class="pv-badge pv-badge-rejected"><i class="fas fa-times-circle"></i> Rejected</span>`;
            case 'under_review':
                return `<span class="pv-badge pv-badge-under_review"><i class="fas fa-spinner fa-spin"></i> Under Review</span>`;
            case 'pending':
            default:
                return `<span class="pv-badge pv-badge-pending"><i class="fas fa-hourglass-half"></i> Pending</span>`;
        }
    }

    // ── 1. Load KPI Cards ──────────────────────────────────────────────────────
    async function loadKpiStats() {
        const totalEl    = document.getElementById('pv-kpi-total');
        const pendingEl  = document.getElementById('pv-kpi-pending');
        const approvedEl = document.getElementById('pv-kpi-approved');
        const rejectedEl = document.getElementById('pv-kpi-rejected');

        try {
            const data = await apiFetch(`${getBaseUrl()}/proof-validation/stats`);
            if (totalEl)    totalEl.textContent    = (data.total || 0).toLocaleString();
            if (pendingEl)  pendingEl.textContent  = (data.pending || 0).toLocaleString();
            if (approvedEl) approvedEl.textContent = (data.approved || 0).toLocaleString();
            if (rejectedEl) rejectedEl.textContent = (data.rejected || 0).toLocaleString();
        } catch (err) {
            console.error('[ProofValidation] Failed to load KPI stats:', err);
            if (totalEl)    totalEl.textContent    = '0';
            if (pendingEl)  pendingEl.textContent  = '0';
            if (approvedEl) approvedEl.textContent = '0';
            if (rejectedEl) rejectedEl.textContent = '0';
        }
    }

    // ── Get Toolbar Filters ────────────────────────────────────────────────────
    function getFilters() {
        return {
            search:          document.getElementById('pv-search-input')?.value.trim() || '',
            municipality_id: document.getElementById('pv-filter-municipality')?.value || '',
            status:          document.getElementById('pv-filter-status')?.value || '',
            date_from:       document.getElementById('pv-date-from')?.value || '',
            date_to:         document.getElementById('pv-date-to')?.value || '',
        };
    }

    // ── 2. Load Submissions Table ──────────────────────────────────────────────
    async function loadSubmissions(page = 1) {
        currentPage = page;
        const tbody = document.getElementById('pv-tbody');
        if (!tbody) return;

        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="pv-empty">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading submissions…</p>
                </td>
            </tr>
        `;

        const filters = getFilters();
        const params = new URLSearchParams({ page, per_page: 15, ...filters });

        try {
            const res = await apiFetch(`${getBaseUrl()}/proof-validation?${params}`);
            populateMunicipalityFilter(res.municipalities);
            renderSubmissionsTable(res);
        } catch (err) {
            console.error('[ProofValidation] Failed to load submissions:', err);
            tbody.innerHTML = `
                <tr>
                    <td colspan="10" class="pv-empty" style="color:#DC2626;">
                        <i class="fas fa-exclamation-circle"></i>
                        <p>Failed to load submission records. Please try again.</p>
                    </td>
                </tr>
            `;
            renderPaginationInfo(0, 0, 0);
            renderPaginationControls(0, 0);
        }
    }

    // Populate Municipalities Filter Dropdown if available
    function populateMunicipalityFilter(municipalities) {
        const select = document.getElementById('pv-filter-municipality');
        if (!select || !municipalities || select.options.length > 1) return;

        municipalities.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.id;
            opt.textContent = m.name;
            select.appendChild(opt);
        });
    }

    // Render Table Rows
    function renderSubmissionsTable(res) {
        const tbody = document.getElementById('pv-tbody');
        if (!tbody) return;

        const items = res.data || [];
        const isMTO = (window.userRole === 'municipal');
        const perPage = res.per_page || 15;
        const startIdx = (res.current_page - 1) * perPage;

        if (items.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="10" class="pv-empty">
                        <i class="fas fa-images"></i>
                        <p>No proof image submissions found matching your filters.</p>
                    </td>
                </tr>
            `;
            renderPaginationInfo(0, 0, 0);
            renderPaginationControls(0, 0);
            return;
        }

        tbody.innerHTML = items.map((item, idx) => {
            const rowNum = startIdx + idx + 1;
            const avatarInitial = (item.tourist_name || 'A')[0].toUpperCase();

            const avatarUrl = window.API_CONFIG?.formatImageUrl
                ? window.API_CONFIG.formatImageUrl(item.tourist_avatar, 'avatars')
                : (item.tourist_avatar ? (item.tourist_avatar.startsWith('http') ? item.tourist_avatar : (item.tourist_avatar.includes('railway.app') ? 'https://' + item.tourist_avatar.replace(/^\/+/, '') : 'https://intanelyumobile-production.up.railway.app/storage/avatars/' + item.tourist_avatar.replace(/^\/?(storage\/|avatars\/)?/, ''))) : null);

            const proofUrl = window.API_CONFIG?.formatImageUrl
                ? window.API_CONFIG.formatImageUrl(item.proof_image, 'proofs')
                : (item.proof_image ? (item.proof_image.startsWith('http') ? item.proof_image : (item.proof_image.includes('railway.app') ? 'https://' + item.proof_image.replace(/^\/+/, '') : 'https://intanelyumobile-production.up.railway.app/storage/proofs/' + item.proof_image.replace(/^\/?(storage\/|proofs\/)?/, ''))) : null);

            const avatarHtml = avatarUrl
                ? `<img src="${escHtml(avatarUrl)}" class="pv-tourist-avatar" alt="${escHtml(item.tourist_name)}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"><div class="pv-tourist-avatar" style="display:none;">${avatarInitial}</div>`
                : `<div class="pv-tourist-avatar">${avatarInitial}</div>`;

            const imageThumb = proofUrl
                ? `<img src="${escHtml(proofUrl)}" class="pv-proof-thumb" alt="Proof" onclick="window.pvOpenLightbox('${escHtml(proofUrl)}')" onerror="this.onerror=null;this.parentElement.innerHTML='<span style=\\'color:#94A3B8;font-size:11px;\\'><i class=\\'fas fa-image\\'></i> Image unavailable</span>';">`
                : `<span style="color:#94A3B8;font-size:12px;">No image</span>`;

            let actionBtns = `
                <div class="pv-dropdown">
                    <button class="pv-dropdown-toggle" onclick="window.pvToggleDropdown(event, ${item.id})" title="Actions">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <div class="pv-dropdown-menu" id="pv-dropdown-${item.id}">
                        <button class="pv-dropdown-item" onclick="window.pvCloseAllDropdowns(); window.pvOpenDetail(${item.id});">
                            <i class="fas fa-eye" style="color:#3B82F6;"></i> View Details
                        </button>
            `;

            if (isMTO && (item.status === 'pending' || item.status === 'under_review')) {
                actionBtns += `
                    <button class="pv-dropdown-item pv-item-approve" onclick="window.pvCloseAllDropdowns(); window.pvApproveSubmission(${item.id});">
                        <i class="fas fa-check-circle" style="color:#16A34A;"></i> Approve Submission
                    </button>
                    <button class="pv-dropdown-item pv-item-reject" onclick="window.pvCloseAllDropdowns(); window.pvRejectSubmission(${item.id});">
                        <i class="fas fa-times-circle" style="color:#DC2626;"></i> Reject Submission
                    </button>
                `;
            }

            actionBtns += `
                    </div>
                </div>
            `;

            return `
                <tr>
                    <td style="font-weight:600;color:#64748B;">${rowNum}</td>
                    <td>
                        <div class="pv-tourist-cell">
                            ${avatarHtml}
                            <div>
                                <div class="pv-tourist-name">${escHtml(item.tourist_name)}</div>
                                <div class="pv-tourist-email">${escHtml(item.tourist_email || '')}</div>
                            </div>
                        </div>
                    </td>
                    <td><strong style="color:#1E293B;">${escHtml(item.tourist_spot)}</strong></td>
                    <td>${escHtml(item.municipality)}</td>
                    <td style="text-align:center;">${imageThumb}</td>
                    <td style="white-space:nowrap;font-size:12px;color:#64748B;">${escHtml(item.date_submitted)}</td>
                    <td>${getStatusBadge(item.status)}</td>
                    <td>${escHtml(item.reviewed_by || '—')}</td>
                    <td style="white-space:nowrap;font-size:12px;color:#64748B;">${escHtml(item.reviewed_at || '—')}</td>
                    <td style="text-align:center;">
                        <div class="pv-actions">
                            ${actionBtns}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        renderPaginationInfo(res.current_page, res.total, res.per_page);
        renderPaginationControls(res.current_page, res.last_page);
    }

    // Render Pagination Info text
    function renderPaginationInfo(current, total, perPage) {
        const infoEl = document.getElementById('pv-results-info');
        if (!infoEl) return;
        if (!total || total === 0) {
            infoEl.textContent = 'Showing 0 submissions';
            return;
        }
        const from = (current - 1) * perPage + 1;
        const to = Math.min(current * perPage, total);
        infoEl.textContent = `Showing ${from} to ${to} of ${total} submissions`;
    }

    // Render Pagination Controls
    function renderPaginationControls(current, last) {
        const container = document.getElementById('pv-pagination');
        if (!container) return;
        if (!last || last <= 1) {
            container.innerHTML = '';
            return;
        }

        const pages = [];
        for (let p = Math.max(1, current - 2); p <= Math.min(last, current + 2); p++) {
            pages.push(p);
        }

        container.innerHTML = `
            <button class="pv-page-btn" ${current <= 1 ? 'disabled' : ''} onclick="window.pvGoPage(${current - 1})">
                <i class="fas fa-chevron-left"></i>
            </button>
            ${pages.map(p => `
                <button class="pv-page-btn ${p === current ? 'active' : ''}" onclick="window.pvGoPage(${p})">${p}</button>
            `).join('')}
            <button class="pv-page-btn" ${current >= last ? 'disabled' : ''} onclick="window.pvGoPage(${current + 1})">
                <i class="fas fa-chevron-right"></i>
            </button>
        `;
    }

    window.pvGoPage = function (p) {
        if (p >= 1) loadSubmissions(p);
    };

    // ── 3. Detail View Modal ──────────────────────────────────────────────────
    async function openDetailModal(id) {
        const overlay = document.getElementById('pv-detail-overlay');
        const leftEl   = document.getElementById('pv-detail-left');
        const imgEl    = document.getElementById('pv-detail-img');
        const footerEl = document.getElementById('pv-detail-footer');

        if (!overlay) return;

        overlay.classList.add('active');
        if (leftEl) leftEl.innerHTML = `<div class="pv-empty"><i class="fas fa-spinner fa-spin"></i><p>Loading submission detail…</p></div>`;
        if (imgEl)  imgEl.style.display = 'none';
        if (footerEl) footerEl.innerHTML = '';

        try {
            const data = await apiFetch(`${getBaseUrl()}/proof-validation/${id}`);
            renderDetailModal(data);
        } catch (err) {
            console.error('[ProofValidation] Failed to load detail:', err);
            if (leftEl) {
                leftEl.innerHTML = `
                    <div class="pv-empty" style="color:#DC2626;">
                        <i class="fas fa-exclamation-circle"></i>
                        <p>Could not load submission details.</p>
                    </div>
                `;
            }
        }
    }

    function renderDetailModal(item) {
        const leftEl   = document.getElementById('pv-detail-left');
        const imgEl    = document.getElementById('pv-detail-img');
        const footerEl = document.getElementById('pv-detail-footer');
        const isMTO    = (window.userRole === 'municipal');

        if (leftEl) {
            leftEl.innerHTML = `
                <div class="pv-info-section">
                    <div class="pv-info-section-title">Tourist Information</div>
                    <div class="pv-info-row">
                        <i class="fas fa-user"></i>
                        <div>
                            <span class="pv-info-row-label">Tourist Name</span>
                            <span class="pv-info-row-value">${escHtml(item.tourist_name)}</span>
                        </div>
                    </div>
                    <div class="pv-info-row">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <span class="pv-info-row-label">Email Address</span>
                            <span class="pv-info-row-value">${escHtml(item.tourist_email || 'N/A')}</span>
                        </div>
                    </div>
                </div>

                <div class="pv-info-section">
                    <div class="pv-info-section-title">Location & Submission</div>
                    <div class="pv-info-row">
                        <i class="fas fa-location-dot"></i>
                        <div>
                            <span class="pv-info-row-label">Tourist Spot</span>
                            <span class="pv-info-row-value">${escHtml(item.tourist_spot)}</span>
                        </div>
                    </div>
                    <div class="pv-info-row">
                        <i class="fas fa-city"></i>
                        <div>
                            <span class="pv-info-row-label">Municipality</span>
                            <span class="pv-info-row-value">${escHtml(item.municipality)}</span>
                        </div>
                    </div>
                    <div class="pv-info-row">
                        <i class="fas fa-calendar"></i>
                        <div>
                            <span class="pv-info-row-label">Date Submitted</span>
                            <span class="pv-info-row-value">${escHtml(item.date_submitted)}</span>
                        </div>
                    </div>
                </div>

                <div class="pv-info-section">
                    <div class="pv-info-section-title">Validation Status</div>
                    <div class="pv-history-box">
                        <div>${getStatusBadge(item.status)}</div>
                        <div style="margin-top:8px;font-size:12px;color:#64748B;">
                            <strong>Reviewed By:</strong> ${escHtml(item.reviewed_by || 'Not yet reviewed')}
                        </div>
                        <div style="font-size:12px;color:#64748B;margin-top:2px;">
                            <strong>Reviewed Date:</strong> ${escHtml(item.reviewed_at || '—')}
                        </div>
                        ${item.rejection_reason ? `
                            <div class="pv-history-reason">
                                <strong>Rejection Reason:</strong><br>
                                ${escHtml(item.rejection_reason)}
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        if (imgEl && item.proof_image) {
            const proofUrl = window.API_CONFIG?.formatImageUrl
                ? window.API_CONFIG.formatImageUrl(item.proof_image, 'proofs')
                : (item.proof_image.startsWith('http') ? item.proof_image : (item.proof_image.includes('railway.app') ? 'https://' + item.proof_image.replace(/^\/+/, '') : 'https://intanelyumobile-production.up.railway.app/storage/proofs/' + item.proof_image.replace(/^\/?(storage\/|proofs\/)?/, '')));
            imgEl.src = proofUrl;
            imgEl.style.display = 'block';
        }

        if (footerEl) {
            let footerBtns = `
                <button class="pv-btn pv-btn-view" onclick="window.pvCloseDetail()">
                    <i class="fas fa-times"></i> Close
                </button>
            `;

            if (isMTO && (item.status === 'pending' || item.status === 'under_review')) {
                footerBtns += `
                    <button class="pv-btn pv-btn-reject" onclick="window.pvCloseDetail(); window.pvRejectSubmission(${item.id});">
                        <i class="fas fa-times"></i> Reject Submission
                    </button>
                    <button class="pv-btn pv-btn-approve" onclick="window.pvCloseDetail(); window.pvApproveSubmission(${item.id});">
                        <i class="fas fa-check"></i> Approve Submission
                    </button>
                `;
            }

            footerEl.innerHTML = footerBtns;
        }
    }

    window.pvOpenDetail = openDetailModal;
    window.pvCloseDetail = function () {
        const overlay = document.getElementById('pv-detail-overlay');
        if (overlay) overlay.classList.remove('active');
    };

    // ── 4. Full-Screen Lightbox ───────────────────────────────────────────────
    window.pvOpenLightbox = function (url) {
        if (!url) return;
        const lightbox = document.getElementById('pv-lightbox');
        const imgEl = document.getElementById('pv-lightbox-img');
        if (lightbox && imgEl) {
            imgEl.src = url;
            lightbox.classList.add('active');
        }
    };

    window.pvCloseLightbox = function () {
        const lightbox = document.getElementById('pv-lightbox');
        if (lightbox) lightbox.classList.remove('active');
    };

    // ── 5. Approve Workflow (MTO) ─────────────────────────────────────────────
    window.pvApproveSubmission = function (id) {
        targetActionId = id;
        const overlay = document.getElementById('pv-approve-overlay');
        if (overlay) overlay.classList.add('active');
    };

    window.pvCancelApprove = function () {
        targetActionId = null;
        const overlay = document.getElementById('pv-approve-overlay');
        if (overlay) overlay.classList.remove('active');
    };

    window.pvDoApprove = async function () {
        if (!targetActionId) return;
        const confirmBtn = document.getElementById('pv-approve-confirm-btn');
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Approving…`;
        }

        try {
            await apiFetch(`${getBaseUrl()}/proof-validation/${targetActionId}/approve`, {
                method: 'POST',
            });
            window.pvCancelApprove();
            showToast('Proof submission approved successfully!', 'success');
            loadKpiStats();
            loadSubmissions(currentPage);
        } catch (err) {
            console.error('[ProofValidation] Approve failed:', err);
            showToast(err.message || 'Failed to approve submission.', 'error');
        } finally {
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = `<i class="fas fa-check"></i> Yes, Approve`;
            }
        }
    };

    // ── 6. Reject Workflow (MTO) ──────────────────────────────────────────────
    window.pvRejectSubmission = function (id) {
        targetActionId = id;
        const overlay = document.getElementById('pv-reject-overlay');
        const reasonInput = document.getElementById('pv-reject-reason');
        const errorEl = document.getElementById('pv-reject-error');

        if (reasonInput) reasonInput.value = '';
        if (errorEl) errorEl.classList.remove('visible');
        if (overlay) overlay.classList.add('active');
    };

    window.pvCancelReject = function () {
        targetActionId = null;
        const overlay = document.getElementById('pv-reject-overlay');
        if (overlay) overlay.classList.remove('active');
    };

    window.pvDoReject = async function () {
        if (!targetActionId) return;
        const reasonInput = document.getElementById('pv-reject-reason');
        const errorEl = document.getElementById('pv-reject-error');
        const confirmBtn = document.getElementById('pv-reject-confirm-btn');

        const reason = reasonInput ? reasonInput.value.trim() : '';

        if (!reason || reason.length < 5) {
            if (errorEl) errorEl.classList.add('visible');
            if (reasonInput) reasonInput.focus();
            return;
        }
        if (errorEl) errorEl.classList.remove('visible');

        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Rejecting…`;
        }

        try {
            await apiFetch(`${getBaseUrl()}/proof-validation/${targetActionId}/reject`, {
                method: 'POST',
                body: JSON.stringify({ rejection_reason: reason }),
            });
            window.pvCancelReject();
            showToast('Proof submission rejected.', 'success');
            loadKpiStats();
            loadSubmissions(currentPage);
        } catch (err) {
            console.error('[ProofValidation] Reject failed:', err);
            showToast(err.message || 'Failed to reject submission.', 'error');
        } finally {
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = `<i class="fas fa-times"></i> Yes, Reject`;
            }
        }
    };

    // ── Toolbar Events Initialization ─────────────────────────────────────────
    function bindToolbarEvents() {
        const searchInput = document.getElementById('pv-search-input');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => loadSubmissions(1), 350);
            });
        }

        ['pv-filter-municipality', 'pv-filter-status', 'pv-date-from', 'pv-date-to'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', () => loadSubmissions(1));
            }
        });

        const resetBtn = document.getElementById('pv-reset-filters');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                if (searchInput) searchInput.value = '';
                const muniSelect = document.getElementById('pv-filter-municipality');
                if (muniSelect) muniSelect.value = '';
                const statusSelect = document.getElementById('pv-filter-status');
                if (statusSelect) statusSelect.value = '';
                const dateFrom = document.getElementById('pv-date-from');
                if (dateFrom) dateFrom.value = '';
                const dateTo = document.getElementById('pv-date-to');
                if (dateTo) dateTo.value = '';

                loadSubmissions(1);
            });
        }

        // Close lightbox on backdrop click
        const lightbox = document.getElementById('pv-lightbox');
        if (lightbox) {
            lightbox.addEventListener('click', (e) => {
                if (e.target === lightbox) window.pvCloseLightbox();
            });
        }

        // Close detail overlay on backdrop click
        const detailOverlay = document.getElementById('pv-detail-overlay');
        if (detailOverlay) {
            detailOverlay.addEventListener('click', (e) => {
                if (e.target === detailOverlay) window.pvCloseDetail();
            });
        }
    }

    // ── Dropdown Controls ───────────────────────────────────────────────────
    window.pvToggleDropdown = function (e, id) {
        if (e) e.stopPropagation();
        const targetMenu = document.getElementById(`pv-dropdown-${id}`);
        const isAlreadyOpen = targetMenu && targetMenu.classList.contains('show');

        window.pvCloseAllDropdowns();

        if (targetMenu && !isAlreadyOpen) {
            targetMenu.classList.add('show');
        }
    };

    window.pvCloseAllDropdowns = function () {
        document.querySelectorAll('.pv-dropdown-menu.show').forEach(el => {
            el.classList.remove('show');
        });
    };

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.pv-dropdown')) {
            window.pvCloseAllDropdowns();
        }
    });

    // ── Module Initialization ─────────────────────────────────────────────────
    function initModule() {
        const table = document.getElementById('pv-table');
        if (!table) return;

        if (!isInitialized) {
            bindToolbarEvents();
            isInitialized = true;
        }

        loadKpiStats();
        loadSubmissions(1);
    }

    window.initProofValidationModule = initModule;

    // SPA navigation hook & DOMReady listener
    document.addEventListener('spa:page:shown', e => {
        if (e.detail?.page === 'proof-validation.php') {
            initModule();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initModule);
    } else {
        setTimeout(initModule, 50);
    }
})();
