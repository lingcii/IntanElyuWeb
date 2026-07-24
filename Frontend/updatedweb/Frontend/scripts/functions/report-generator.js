/**
 * Report Generation Module — Frontend Controller
 * Handles filtering, RBAC municipality locking, AJAX generation, dynamic previewing,
 * Chart.js visualization, paginated preview table with live search, and PDF/Excel/CSV exports.
 */

(function () {
    'use strict';

    class ReportGeneratorModule {
        constructor() {
            this.role = this.detectRole();
            this.isMunicipal = this.role === 'municipal' || this.role.endsWith('_mto');
            this.assignedMunicipality = null;
            this.assignedMunicipalityId = null;

            this.reportData = null;
            this.filteredRows = [];
            this.currentPage = 1;
            this.pageSize = 10;
            this.searchQuery = '';

            this.categoryChart = null;
            this.statusChart = null;

            this.recentReportsKey = `rg_recent_reports_${this.role}`;
            this.reportCache = new Map();
            this.debounceTimer = null;
            this.isInitialLoaded = false;
            this.pendingExportFormat = null;
            this.activeLoadingToast = null;

            this.init();
        }

        detectRole() {
            const bodyRole = document.body.dataset.role || '';
            if (bodyRole) return bodyRole;
            const metaRole = document.querySelector('meta[name="user-role"]')?.content;
            if (metaRole) return metaRole;

            const path = window.location.pathname.toLowerCase();
            if (path.includes('/picto/')) return 'picto';
            if (path.includes('/lupto/')) return 'lupto';
            return 'municipal';
        }

        getApiEndpoint(action = 'generate') {
            const baseUrl = (window.API_CONFIG && window.API_CONFIG.BASE_URL)
                ? window.API_CONFIG.BASE_URL
                : `${window.location.protocol}//${window.location.hostname}:8000`;

            const rolePrefix = (this.role === 'picto' || this.role === 'pitco')
                ? 'pitco'
                : (this.role === 'lupto' ? 'lupto' : 'municipal');

            return `${baseUrl}/api/${rolePrefix}/reports/${action}`;
        }

        async init() {
            this.bindElements();
            this.bindEvents();
            await this.loadInitialMetadata();
            this.loadRecentReportsFromStorage();

            // Automatically generate initial default report preview
            await this.handleGenerateReport(false);
            this.isInitialLoaded = true;
        }

        bindElements() {
            this.elForm = document.getElementById('rg-filter-form');
            this.elReportType = document.getElementById('rg-report-type');
            this.elStartDate = document.getElementById('rg-start-date');
            this.elEndDate = document.getElementById('rg-end-date');
            this.elMunicipality = document.getElementById('rg-municipality');
            this.elCategory = document.getElementById('rg-category');
            this.elClassification = document.getElementById('rg-classification');
            this.elStatus = document.getElementById('rg-status');
            this.elExportFormat = document.getElementById('rg-export-format');

            this.btnGenerate = document.getElementById('rg-btn-generate');
            this.btnPreview = document.getElementById('rg-btn-preview');
            this.btnReset = document.getElementById('rg-btn-reset');
            this.btnExportPdf = document.getElementById('rg-btn-pdf');
            this.btnExportExcel = document.getElementById('rg-btn-excel');
            this.btnExportCsv = document.getElementById('rg-btn-csv');
            this.btnPrint = document.getElementById('rg-btn-print');
            this.btnDownloadNow = document.getElementById('rg-btn-download-now');

            this.modalConfirm = document.getElementById('rg-confirm-modal');
            this.modalMsg = document.getElementById('rg-confirm-msg');
            this.btnModalConfirm = document.getElementById('rg-modal-btn-confirm');
            this.btnModalCancel = document.getElementById('rg-modal-btn-cancel');

            this.elKpiContainer = document.getElementById('rg-kpi-container');
            this.elPreviewContainer = document.getElementById('rg-preview-container');
            this.elTableSearch = document.getElementById('rg-table-search');
            this.elTableHead = document.getElementById('rg-table-head');
            this.elTableBody = document.getElementById('rg-table-body');
            this.elPaginationWrap = document.getElementById('rg-pagination-wrap');
            this.elRecentTableBody = document.getElementById('rg-recent-body');
        }

        bindEvents() {
            const filterControls = [
                this.elReportType,
                this.elMunicipality,
                this.elStartDate,
                this.elEndDate
            ];

            filterControls.forEach(ctrl => {
                if (ctrl) {
                    ctrl.addEventListener('change', () => this.debouncedGenerateReport());
                }
            });

            // Confirmation modal only pops up when user explicitly clicks the "Download" button!
            if (this.btnDownloadNow) {
                this.btnDownloadNow.addEventListener('click', () => {
                    const format = this.elExportFormat ? this.elExportFormat.value : 'pdf';
                    if (format === 'print') {
                        this.handlePrint();
                    } else {
                        this.handleExport(format);
                    }
                });
            }

            if (this.btnModalConfirm) {
                this.btnModalConfirm.addEventListener('click', () => this.confirmAndDownload());
            }

            if (this.btnModalCancel) {
                this.btnModalCancel.addEventListener('click', () => this.closeConfirmModal());
            }

            if (this.modalConfirm) {
                this.modalConfirm.classList.remove('show');
                this.modalConfirm.addEventListener('click', (e) => {
                    if (e.target === this.modalConfirm) this.closeConfirmModal();
                });
            }

            if (this.elTableSearch) {
                this.elTableSearch.addEventListener('input', (e) => {
                    this.searchQuery = e.target.value.toLowerCase().trim();
                    this.currentPage = 1;
                    this.applyClientSearchAndPaginate();
                });
            }
        }

        debouncedGenerateReport(forceRefresh = false) {
            if (this.debounceTimer) clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                this.handleGenerateReport(forceRefresh);
            }, 250);
        }

        syncFilterControlsForReportType() {
            const type = this.elReportType ? this.elReportType.value : '';
            if (type.includes('pending') || type.includes('approved') || type.includes('rejected') || type.includes('draft')) {
                if (this.elStatus) this.elStatus.disabled = true;
            } else {
                if (this.elStatus) this.elStatus.disabled = false;
            }
        }

        async loadInitialMetadata() {
            try {
                // Fetch municipal metadata and role index configuration
                const url = this.getApiEndpoint('');
                const res = await fetch(url, { credentials: 'include' });
                const json = await res.json();

                if (json.success) {
                    if (json.is_municipal && json.assigned_municipality) {
                        this.isMunicipal = true;
                        this.assignedMunicipality = json.assigned_municipality;
                        this.assignedMunicipalityId = json.assigned_municipality_id;

                        // Lock Municipality select for Municipal Tourist Office
                        if (this.elMunicipality) {
                            this.elMunicipality.innerHTML = `<option value="${json.assigned_municipality}" selected>${json.assigned_municipality}</option>`;
                            this.elMunicipality.disabled = true;
                            this.elMunicipality.title = "Role Restriction: You can generate reports only for your assigned municipality.";
                        }
                    } else if (this.elMunicipality) {
                        // Populate All Municipalities for PICTO / LUPTO
                        await this.loadMunicipalitiesDropdown();
                    }
                }
            } catch (err) {
                console.warn('Failed to load report metadata overview:', err);
                if (this.elMunicipality && !this.isMunicipal) {
                    this.loadMunicipalitiesDropdown();
                }
            }
        }

        async loadMunicipalitiesDropdown() {
            try {
                const baseUrl = (window.API_CONFIG && window.API_CONFIG.BASE_URL)
                    ? window.API_CONFIG.BASE_URL
                    : `${window.location.protocol}//${window.location.hostname}:8000`;

                const res = await fetch(`${baseUrl}/api/municipalities`, { credentials: 'include' });
                const json = await res.json();

                const munis = json.municipalities || json.data || json || [];
                let optionsHtml = '<option value="all">All Municipalities</option>';
                munis.forEach(m => {
                    optionsHtml += `<option value="${m.id}">${m.name}</option>`;
                });
                this.elMunicipality.innerHTML = optionsHtml;
            } catch (e) {
                console.error('Failed to load municipalities list:', e);
            }
        }

        getFilterParams() {
            const startDate = this.elStartDate ? this.elStartDate.value : '';
            const endDate = this.elEndDate ? this.elEndDate.value : '';

            // Client-side date validation
            if (startDate && endDate && startDate > endDate) {
                alert('Validation Warning: Start Date cannot be after End Date.');
                return null;
            }

            const params = new URLSearchParams();
            params.append('report_type', this.elReportType ? this.elReportType.value : 'tourist_spots_summary');
            params.append('start_date', startDate);
            params.append('end_date', endDate);

            if (this.isMunicipal && this.assignedMunicipality) {
                params.append('municipality', this.assignedMunicipality);
            } else if (this.elMunicipality) {
                params.append('municipality', this.elMunicipality.value);
            }

            if (this.elExportFormat) params.append('format', this.elExportFormat.value);

            return params;
        }

        async handleGenerateReport(forceRefresh = false) {
            const params = this.getFilterParams();
            if (!params) return;

            const cacheKey = params.toString();

            if (!forceRefresh && this.reportCache && this.reportCache.has(cacheKey)) {
                const json = this.reportCache.get(cacheKey);
                this.reportData = json;
                this.renderReportPreview(json);
                return;
            }

            this.showLoadingState();

            try {
                const url = `${this.getApiEndpoint('generate')}?${params.toString()}`;
                const res = await fetch(url, { credentials: 'include' });

                if (!res.ok) throw new Error(`HTTP error ${res.status}`);

                const json = await res.json();
                if (!json.success) throw new Error(json.message || 'Report generation failed.');

                if (this.reportCache) this.reportCache.set(cacheKey, json);
                this.reportData = json;
                this.renderReportPreview(json);

                const format = this.elExportFormat ? this.elExportFormat.value : 'PDF';
                if (format === 'print') {
                    setTimeout(() => window.print(), 300);
                }
                this.addRecentReportLog(json.report_title, json.report_type, format.toUpperCase());
            } catch (err) {
                console.error('Error generating report:', err);
                this.renderErrorState(err.message);
            }
        }

        showLoadingState() {
            if (!this.elPreviewContainer) return;
            this.elPreviewContainer.innerHTML = `
                <div class="rg-loading-overlay">
                    <i class="fas fa-circle-notch fa-spin rg-loading-spinner"></i>
                    <div style="font-weight:600;font-size:1rem;">Generating Dynamic Report…</div>
                    <div style="font-size:0.85rem;color:#64748B;">Fetching records and calculating summary metrics…</div>
                </div>
            `;
        }

        renderErrorState(msg) {
            if (!this.elPreviewContainer) return;
            this.elPreviewContainer.innerHTML = `
                <div style="text-align:center;padding:40px 20px;color:#DC2626;">
                    <i class="fas fa-exclamation-circle" style="font-size:2.5rem;margin-bottom:12px;"></i>
                    <h3 style="margin:0 0 6px 0;">Failed to Generate Report</h3>
                    <p style="margin:0;font-size:0.9rem;color:#64748B;">${msg}</p>
                </div>
            `;
        }

        renderReportPreview(data) {
            if (!this.elPreviewContainer) return;

            const s = data.summary_stats || {};

            // Render KPI cards into top container (above Report Filter Options card)
            if (this.elKpiContainer) {
                this.elKpiContainer.innerHTML = `
                    <div class="rg-kpi-grid">
                        <div class="rg-kpi-card">
                            <div class="rg-kpi-icon blue"><i class="fas fa-folder-open"></i></div>
                            <div class="rg-kpi-info">
                                <h4>Total Records</h4>
                                <div class="rg-kpi-value">${(s.total_spots || 0).toLocaleString()}</div>
                            </div>
                        </div>
                        <div class="rg-kpi-card">
                            <div class="rg-kpi-icon green"><i class="fas fa-check-circle"></i></div>
                            <div class="rg-kpi-info">
                                <h4>Total Approved / Active</h4>
                                <div class="rg-kpi-value">${(s.total_approved || 0).toLocaleString()}</div>
                            </div>
                        </div>
                        <div class="rg-kpi-card">
                            <div class="rg-kpi-icon amber"><i class="fas fa-clock"></i></div>
                            <div class="rg-kpi-info">
                                <h4>Total Pending</h4>
                                <div class="rg-kpi-value">${(s.total_pending || 0).toLocaleString()}</div>
                            </div>
                        </div>
                        <div class="rg-kpi-card">
                            <div class="rg-kpi-icon red"><i class="fas fa-times-circle"></i></div>
                            <div class="rg-kpi-info">
                                <h4>Total Rejected</h4>
                                <div class="rg-kpi-value">${(s.total_rejected || 0).toLocaleString()}</div>
                            </div>
                        </div>
                        <div class="rg-kpi-card">
                            <div class="rg-kpi-icon purple"><i class="fas fa-star"></i></div>
                            <div class="rg-kpi-info">
                                <h4>Average Rating</h4>
                                <div class="rg-kpi-value">${s.avg_rating || '0.0'} ⭐</div>
                            </div>
                        </div>
                    </div>
                `;
            }

            // Render interactive Data Table & Recent Downloads tabbed container below filter options card
            let html = `
                <div id="rg-preview-section">
                    <div class="rg-card" style="margin-bottom:0;">
                        <div class="rg-card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                            <div class="rg-tab-buttons">
                                <button type="button" id="rg-tab-btn-table" class="rg-tab-btn active">
                                    <i class="fas fa-table"></i> Report Data Table (${data.data.length} Records)
                                </button>
                                <button type="button" id="rg-tab-btn-recent" class="rg-tab-btn">
                                    <i class="fas fa-history"></i> Recent Downloads & Generated Reports
                                </button>
                            </div>
                            <div class="rg-table-toolbar" id="rg-table-toolbar">
                                <div class="rg-search-box">
                                    <i class="fas fa-search"></i>
                                    <input type="text" id="rg-table-search" class="rg-input" placeholder="Search preview table records…">
                                </div>
                            </div>
                        </div>
                        <div class="rg-card-body" style="padding:0;">
                            <!-- Tab 1: Report Data Table -->
                            <div id="rg-tab-content-table" class="rg-tab-pane">
                                <div class="rg-table-wrap">
                                    <table class="rg-table">
                                        <thead id="rg-table-head"></thead>
                                        <tbody id="rg-table-body"></tbody>
                                    </table>
                                </div>
                                <div class="rg-pagination-wrap" id="rg-pagination-wrap" style="padding:16px;"></div>
                            </div>

                            <!-- Tab 2: Recent Downloads & Generated Reports -->
                            <div id="rg-tab-content-recent" class="rg-tab-pane" style="display:none;">
                                <div class="rg-table-wrap">
                                    <table class="rg-table">
                                        <thead>
                                            <tr>
                                                <th>Report Name</th>
                                                <th>Type</th>
                                                <th>Date Generated</th>
                                                <th>Generated By</th>
                                                <th>Format</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="rg-recent-body">
                                            <tr>
                                                <td colspan="6" style="text-align:center;padding:24px;color:#94A3B8;">Loading recent reports history…</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            this.elPreviewContainer.innerHTML = html;

            // Re-bind references after DOM insertion
            this.elRecentTableBody = document.getElementById('rg-recent-body');
            this.loadRecentReportsFromStorage();

            // Bind tab switching events
            const btnTableTab = document.getElementById('rg-tab-btn-table');
            const btnRecentTab = document.getElementById('rg-tab-btn-recent');
            const paneTable = document.getElementById('rg-tab-content-table');
            const paneRecent = document.getElementById('rg-tab-content-recent');
            const toolbarSearch = document.getElementById('rg-table-toolbar');

            if (btnTableTab && btnRecentTab) {
                btnTableTab.addEventListener('click', () => {
                    btnTableTab.classList.add('active');
                    btnRecentTab.classList.remove('active');
                    if (paneTable) paneTable.style.display = 'block';
                    if (paneRecent) paneRecent.style.display = 'none';
                    if (toolbarSearch) toolbarSearch.style.display = 'block';
                });

                btnRecentTab.addEventListener('click', () => {
                    btnRecentTab.classList.add('active');
                    btnTableTab.classList.remove('active');
                    if (paneRecent) paneRecent.style.display = 'block';
                    if (paneTable) paneTable.style.display = 'none';
                    if (toolbarSearch) toolbarSearch.style.display = 'none';
                });
            }

            // Re-bind table search input reference after DOM insertion
            this.elTableSearch = document.getElementById('rg-table-search');
            if (this.elTableSearch) {
                this.elTableSearch.addEventListener('input', (e) => {
                    this.searchQuery = e.target.value.toLowerCase().trim();
                    this.currentPage = 1;
                    this.applyClientSearchAndPaginate();
                });
            }

            // Render Charts & Table Data
            this.renderCharts(s);
            this.filteredRows = data.data || [];
            this.currentPage = 1;
            this.renderTableHead(data.columns);
            this.applyClientSearchAndPaginate();
        }

        renderCharts(stats) {
            if (typeof window.Chart === 'undefined') return;

            // Destruct existing charts if present
            if (this.categoryChart) this.categoryChart.destroy();
            if (this.statusChart) this.statusChart.destroy();

            // 1. Category Chart
            const catCtx = document.getElementById('rg-chart-category');
            if (catCtx && stats.category_breakdown) {
                const labels = Object.keys(stats.category_breakdown);
                const values = Object.values(stats.category_breakdown);

                this.categoryChart = new window.Chart(catCtx, {
                    type: 'doughnut',
                    data: {
                        labels: labels.length ? labels : ['No Data'],
                        datasets: [{
                            data: values.length ? values : [1],
                            backgroundColor: ['#2563EB', '#10B981', '#F59E0B', '#EC4899', '#8B5CF6', '#06B6D4', '#64748B'],
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
                        }
                    }
                });
            }

            // 2. Classification Chart
            const statusCtx = document.getElementById('rg-chart-status');
            if (statusCtx && stats.classification_breakdown) {
                const labels = Object.keys(stats.classification_breakdown);
                const values = Object.values(stats.classification_breakdown);

                this.statusChart = new window.Chart(statusCtx, {
                    type: 'bar',
                    data: {
                        labels: labels.length ? labels : ['Existing', 'Emerging', 'Potential'],
                        datasets: [{
                            label: 'Count',
                            data: values.length ? values : [stats.total_spots || 0, 0, 0],
                            backgroundColor: ['#3B82F6', '#8B5CF6', '#F97316'],
                            borderRadius: 6,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true, ticks: { precision: 0 } }
                        }
                    }
                });
            }
        }

        renderTableHead(columns) {
            const headEl = document.getElementById('rg-table-head');
            if (!headEl || !columns) return;

            let tr = '<tr>';
            columns.forEach(col => {
                tr += `<th>${col.label}</th>`;
            });
            tr += '</tr>';
            headEl.innerHTML = tr;
        }

        applyClientSearchAndPaginate() {
            if (!this.reportData || !this.reportData.data) return;

            const allRows = this.reportData.data;

            if (this.searchQuery) {
                this.filteredRows = allRows.filter(row => {
                    return Object.values(row).some(val =>
                        String(val).toLowerCase().includes(this.searchQuery)
                    );
                });
            } else {
                this.filteredRows = allRows;
            }

            const totalItems = this.filteredRows.length;
            const totalPages = Math.ceil(totalItems / this.pageSize) || 1;

            if (this.currentPage > totalPages) this.currentPage = totalPages;

            const startIdx = (this.currentPage - 1) * this.pageSize;
            const endIdx = startIdx + this.pageSize;
            const pageData = this.filteredRows.slice(startIdx, endIdx);

            this.renderTableBody(pageData);
            this.renderPaginationControls(totalItems, totalPages);
        }

        renderTableBody(rows) {
            const bodyEl = document.getElementById('rg-table-body');
            if (!bodyEl || !this.reportData) return;

            if (!rows || rows.length === 0) {
                const colCount = (this.reportData.columns || []).length || 6;
                bodyEl.innerHTML = `<tr><td colspan="${colCount}" style="text-align:center;padding:30px;color:#94A3B8;">No report records match the selected filters or search query.</td></tr>`;
                return;
            }

            let html = '';
            rows.forEach(row => {
                html += '<tr>';
                this.reportData.columns.forEach(col => {
                    const key = col.key;
                    let val = row[key] !== undefined ? row[key] : '';

                    if (key === 'status') {
                        const sLower = String(val).toLowerCase();
                        val = `<span class="rg-badge ${sLower}">${val}</span>`;
                    } else if (key === 'classification') {
                        const cLower = String(val).toLowerCase();
                        val = `<span class="rg-badge ${cLower}">${val}</span>`;
                    }

                    html += `<td>${val}</td>`;
                });
                html += '</tr>';
            });

            bodyEl.innerHTML = html;
        }

        renderPaginationControls(totalItems, totalPages) {
            const pagEl = document.getElementById('rg-pagination-wrap');
            if (!pagEl) return;

            if (totalItems === 0) {
                pagEl.innerHTML = '';
                return;
            }

            const startNum = (this.currentPage - 1) * this.pageSize + 1;
            const endNum = Math.min(this.currentPage * this.pageSize, totalItems);

            let buttonsHtml = '';
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= this.currentPage - 1 && i <= this.currentPage + 1)) {
                    buttonsHtml += `<button class="rg-page-btn ${i === this.currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
                } else if (i === 2 && this.currentPage > 3) {
                    buttonsHtml += `<span style="padding:0 4px;">…</span>`;
                } else if (i === totalPages - 1 && this.currentPage < totalPages - 2) {
                    buttonsHtml += `<span style="padding:0 4px;">…</span>`;
                }
            }

            pagEl.innerHTML = `
                <div>Showing <strong>${startNum}–${endNum}</strong> of <strong>${totalItems}</strong> records</div>
                <div class="rg-pagination-btns">
                    <button class="rg-page-btn" id="rg-prev-page" ${this.currentPage === 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i> Prev</button>
                    ${buttonsHtml}
                    <button class="rg-page-btn" id="rg-next-page" ${this.currentPage === totalPages ? 'disabled' : ''}>Next <i class="fas fa-chevron-right"></i></button>
                </div>
            `;

            // Bind pagination click events
            const btnPrev = pagEl.querySelector('#rg-prev-page');
            const btnNext = pagEl.querySelector('#rg-next-page');

            if (btnPrev) btnPrev.addEventListener('click', () => {
                if (this.currentPage > 1) {
                    this.currentPage--;
                    this.applyClientSearchAndPaginate();
                }
            });

            if (btnNext) btnNext.addEventListener('click', () => {
                if (this.currentPage < totalPages) {
                    this.currentPage++;
                    this.applyClientSearchAndPaginate();
                }
            });

            pagEl.querySelectorAll('.rg-page-btn[data-page]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    this.currentPage = parseInt(e.target.dataset.page, 10);
                    this.applyClientSearchAndPaginate();
                });
            });
        }

        handleExport(format) {
            if (format === 'print') {
                this.handlePrint();
                return;
            }
            this.openConfirmModal(format);
        }

        openConfirmModal(format) {
            let label = 'PDF';
            if (format === 'excel') label = 'Excel (.xlsx)';
            if (format === 'csv') label = 'CSV (.csv)';

            this.pendingExportFormat = format;

            if (this.modalMsg) {
                this.modalMsg.innerHTML = `Are you sure you want to download this report as <strong>${label}</strong>?`;
            }

            if (this.modalConfirm) {
                this.modalConfirm.classList.add('show');
            }
        }

        closeConfirmModal() {
            if (this.modalConfirm) {
                this.modalConfirm.classList.remove('show');
            }
            this.pendingExportFormat = null;
        }

        confirmAndDownload() {
            const format = this.pendingExportFormat;
            this.closeConfirmModal();
            if (format) {
                this.executeDownload(format);
            }
        }

        async executeDownload(format) {
            const params = this.getFilterParams();
            if (!params) return;

            params.set('format', format);
            const url = `${this.getApiEndpoint('export')}?${params.toString()}`;

            if (format === 'pdf' || format === 'print') {
                window.open(url, '_blank');
                this.showToast('Official PDF report document opened for preview & printing.', 'success');
                const title = this.reportData ? this.reportData.report_title : 'Exported Report';
                const type = this.elReportType ? this.elReportType.value : 'Custom';
                this.addRecentReportLog(title, type, 'PDF');
                return;
            }

            const loadingToast = this.showToast('Generating report file for download…', 'info', 0);

            try {
                const res = await fetch(url, { credentials: 'include' });
                if (!res.ok) {
                    throw new Error(`Server returned HTTP status ${res.status}`);
                }

                const blob = await res.blob();
                if (blob.size === 0) {
                    throw new Error('Generated report file is empty.');
                }

                // Format consistent filename
                const dateStr = new Date().toISOString().slice(0, 10);
                const ext = 'csv';
                let fileName = `Tourist_Spots_Report_${dateStr}.${ext}`;

                const disposition = res.headers.get('Content-Disposition');
                if (disposition && disposition.includes('filename=')) {
                    const match = disposition.match(/filename="?([^";]+)"?/);
                    if (match && match[1]) {
                        fileName = match[1].replace(/\.xlsx$/, '.csv').replace(/\.xls$/, '.csv');
                    }
                }

                // Automatic blob download without leaving or refreshing current page
                const blobUrl = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = blobUrl;
                a.download = fileName;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(blobUrl);

                if (loadingToast) loadingToast.remove();
                this.showToast('Report downloaded successfully.', 'success');

                // Record download in recent reports history log
                const title = this.reportData ? this.reportData.report_title : 'Exported Report';
                const type = this.elReportType ? this.elReportType.value : 'Custom';
                this.addRecentReportLog(title, type, format.toUpperCase());
            } catch (err) {
                console.error('Download error:', err);
                if (loadingToast) loadingToast.remove();
                this.showToast(`Failed to download report: ${err.message}`, 'error');
            }
        }

        showToast(message, type = 'info', duration = 3500) {
            let container = document.getElementById('rg-toast-container');
            if (!container) return null;

            const toast = document.createElement('div');
            toast.className = `rg-toast ${type} show`;
            const icon = type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-spinner fa-spin');
            toast.innerHTML = `<i class="fas ${icon}"></i> <span>${message}</span>`;
            container.appendChild(toast);

            if (duration > 0) {
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 300);
                }, duration);
            }

            return toast;
        }

        handlePrint() {
            window.print();
        }

        handleResetFilters() {
            if (this.elStartDate) this.elStartDate.value = '';
            if (this.elEndDate) this.elEndDate.value = '';
            if (this.elExportFormat) this.elExportFormat.value = 'pdf';

            if (!this.isMunicipal && this.elMunicipality) {
                this.elMunicipality.value = 'all';
            }

            if (this.elReportType) {
                this.elReportType.value = 'tourist_spots_summary';
            }

            this.handleGenerateReport();
        }

        addRecentReportLog(name, type, format) {
            try {
                let logs = JSON.parse(localStorage.getItem(this.recentReportsKey) || '[]');
                const newEntry = {
                    id: Date.now(),
                    name: name,
                    type: type,
                    date: new Date().toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }),
                    by: `${this.role.toUpperCase()} User`,
                    format: format,
                };

                logs.unshift(newEntry);
                logs = logs.slice(0, 5); // Keep last 5 recent downloads
                localStorage.setItem(this.recentReportsKey, JSON.stringify(logs));
                this.renderRecentReportsTable(logs);
            } catch (e) { }
        }

        loadRecentReportsFromStorage() {
            try {
                const logs = JSON.parse(localStorage.getItem(this.recentReportsKey) || '[]');
                this.renderRecentReportsTable(logs);
            } catch (e) { }
        }

        renderRecentReportsTable(logs) {
            if (!this.elRecentTableBody) return;

            if (!logs || logs.length === 0) {
                this.elRecentTableBody.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align:center;padding:24px;color:#94A3B8;">No recent report export history recorded yet.</td>
                    </tr>
                `;
                return;
            }

            let html = '';
            logs.forEach(item => {
                let iconClass = 'fa-file-pdf';
                let iconColor = '#DC2626';
                if (item.format === 'EXCEL' || item.format === 'XLSX') {
                    iconClass = 'fa-file-excel';
                    iconColor = '#16A34A';
                } else if (item.format === 'CSV') {
                    iconClass = 'fa-file-csv';
                    iconColor = '#2563EB';
                }

                html += `
                    <tr>
                        <td><i class="fas ${iconClass}" style="color:${iconColor};margin-right:6px;"></i> ${item.name}</td>
                        <td>${item.type}</td>
                        <td>${item.date}</td>
                        <td>${item.by}</td>
                        <td><span class="rg-badge">${item.format}</span></td>
                        <td>
                            <button class="rg-btn rg-btn-outline" style="padding:4px 8px;font-size:11px;" onclick="window.initReportGeneratorModule && window.initReportGeneratorModule().handleGenerateReport()">
                                <i class="fas fa-redo"></i> Re-Run
                            </button>
                        </td>
                    </tr>
                `;
            });

            this.elRecentTableBody.innerHTML = html;
        }
    }

    // Expose global initializer function for SPA tab switching compatibility
    window.initReportGeneratorModule = function (forceRefresh = false) {
        if (!window.__reportGeneratorInstance) {
            window.__reportGeneratorInstance = new ReportGeneratorModule();
        } else {
            // Re-bind elements to guarantee active DOM references
            window.__reportGeneratorInstance.bindElements();
            window.__reportGeneratorInstance.bindEvents();

            const container = document.getElementById('rg-preview-container');
            const hasContent = container && container.children.length > 0;

            if (forceRefresh || !hasContent) {
                window.__reportGeneratorInstance.handleGenerateReport(forceRefresh);
            }
        }
        return window.__reportGeneratorInstance;
    };

    window.softRefreshReportGenerator = async function () {
        if (window.__reportGeneratorInstance) {
            await window.__reportGeneratorInstance.handleGenerateReport(true);
        }
    };

    // Auto initialize if DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.initReportGeneratorModule(false));
    } else {
        window.initReportGeneratorModule(false);
    }
})();
