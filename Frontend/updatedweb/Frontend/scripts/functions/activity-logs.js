/**
 * PICTO/LUPTO/MUNICIPAL Activity Logs — Enterprise SSE Real-Time Module
 *
 * Features:
 * - Server-Sent Events with log + stats event types
 * - Animated CountUp statistics cards with pulse effects
 * - Server-side pagination with dynamic filtering
 * - Color-coded action badges & activity type icons
 * - Advanced filters: action, module, role, municipality, search, date presets
 * - Click-to-open detail side drawer with full metadata
 * - Toast notifications for real-time updates
 * - Client-side CSV/Excel/PDF export
 * - Skeleton loaders, empty states, responsive design
 */

const ACTION_ICONS = {
    'User Logged In':         { icon: 'fa-sign-in-alt', color: 'gray' },
    'User Logged Out':        { icon: 'fa-sign-out-alt', color: 'gray' },
    'Login Failed':           { icon: 'fa-exclamation-triangle', color: 'red' },
    'User Created':           { icon: 'fa-user-plus', color: 'green' },
    'User Updated':           { icon: 'fa-user-edit', color: 'blue' },
    'User Deleted':           { icon: 'fa-user-slash', color: 'red' },
    'User Restored':          { icon: 'fa-user-check', color: 'green' },
    'User Archived':          { icon: 'fa-folder', color: 'orange' },
    'User Activated':         { icon: 'fa-toggle-on', color: 'green' },
    'User Deactivated':       { icon: 'fa-toggle-off', color: 'orange' },
    'Password Reset':         { icon: 'fa-key', color: 'blue' },
    'Tourist Spot Added':     { icon: 'fa-map-marker-alt', color: 'green' },
    'Tourist Spot Updated':   { icon: 'fa-edit', color: 'blue' },
    'Tourist Spot Deleted':   { icon: 'fa-trash', color: 'red' },
    'Tourist Spot Approved':  { icon: 'fa-check-circle', color: 'green' },
    'Tourist Spot Rejected':  { icon: 'fa-times-circle', color: 'red' },
    'Tourist Spot Image Uploaded': { icon: 'fa-image', color: 'blue' },
    'Fare Data Uploaded':     { icon: 'fa-upload', color: 'green' },
    'Fare Data Updated':      { icon: 'fa-bus', color: 'blue' },
    'Fare Data Deleted':      { icon: 'fa-trash-alt', color: 'red' },
    'System Settings Updated': { icon: 'fa-cog', color: 'purple' },
    'Profile Updated':        { icon: 'fa-user-circle', color: 'blue' },
    'Password Changed':       { icon: 'fa-lock', color: 'blue' },
    'Data Imported':          { icon: 'fa-file-import', color: 'purple' },
    'Data Exported':          { icon: 'fa-file-export', color: 'purple' },
};

class ActivityLogsModule {
    constructor() {
        this.allLogs = [];
        this.currentPage = 1;
        this.perPage = 10;
        this.totalItems = 0;
        this.totalPages = 0;
        this.maxLogId = 0;

        this.filters = {
            action: [],
            module: [],
            role: null,
            municipality: null,
            search: '',
            datePreset: null,
            dateFrom: '',
            dateTo: '',
            userId: null,
        };

        this.stats = {
            logs_today: 0,
            approvals_today: 0,
            rejections_today: 0,
            active_users_24h: 0,
        };

        this.eventSource = null;
        this.reconnectTimeout = null;
        this.unreadCount = 0;
        this.isLive = false;
        this.syncSeconds = 0;
        this.syncTimer = null;
        this.activeToastIds = new Set();

        const role = (window.userRole || document.body?.dataset?.role || document.querySelector('meta[name="user-role"]')?.content || '').toLowerCase();
        const path = window.location.pathname.toUpperCase();
        this.isPicto = role === 'picto' || role === 'pitco' || path.includes('PICTO');
        this.isLupto = role === 'lupto' || path.includes('LUPTO');
        this.isMunicipal = role === 'municipal' || role.endsWith('_mto') || path.includes('MUNICIPAL');

        if (this.isPicto) {
            this.apiBase = window.API_CONFIG?.PITCO || 'http://localhost:8000/api/pitco';
        } else if (this.isLupto) {
            this.apiBase = window.API_CONFIG?.LUPTO || 'http://localhost:8000/api/lupto';
        } else {
            this.apiBase = window.API_CONFIG?.MUNICIPAL || 'http://localhost:8000/api/municipal';
        }
    }

    // ── INITIALIZATION ──────────────────────────────────────────────
    async init() {
        this.setupUIEventHandlers();
        this.showSkeletonLoader();
        try {
            await this.fetchLogs();
            this.connectSSE();
            this.startSyncTimer();
        } catch (err) {
            console.error('[ActivityLogs] Init failed:', err);
            this.showErrorState(err.message || 'Failed to load activity logs.');
        }
    }

    // ── API CALLS ───────────────────────────────────────────────────
    async fetchStats() {
        try {
            const data = await window.API_CONFIG.get(`${this.apiBase}/activity-logs/stats`);
            this.animateStats(data);
        } catch (err) {
            void 0;
        }
    }

    async fetchLogs(append = false) {
        const params = this.buildQueryParams();
        try {
            const data = await window.API_CONFIG.get(`${this.apiBase}/activity-logs`, params);

            if (data.stats) {
                this.animateStats(data.stats);
            }

            if (append) {
                this.allLogs = [...this.allLogs, ...data.logs];
            } else {
                this.allLogs = data.logs || [];
                this.allLogs.forEach(log => {
                    if (log.id > this.maxLogId) this.maxLogId = log.id;
                });
            }

            if (data.pagination) {
                this.currentPage = data.pagination.current_page;
                this.perPage = data.pagination.per_page;
                this.totalItems = data.pagination.total;
                this.totalPages = data.pagination.last_page;
            }

            this.populateDropdownFilters();
            this.renderTable();
            this.updateSyncTime(true);
        } catch (err) {
            throw err;
        }
    }

    buildQueryParams() {
        const p = { page: this.currentPage, per_page: this.perPage };
        if (this.filters.action.length) p.action = this.filters.action.join(',');
        if (this.filters.module.length) p.module = this.filters.module.join(',');
        if (this.filters.role) p.role = this.filters.role;
        if (this.filters.municipality) p.municipality = this.filters.municipality;
        if (this.filters.search) p.search = this.filters.search;
        if (this.filters.datePreset) p.date_preset = this.filters.datePreset;
        if (this.filters.dateFrom) p.date_from = this.filters.dateFrom;
        if (this.filters.dateTo) p.date_to = this.filters.dateTo;
        if (this.filters.userId) p.user_id = this.filters.userId;
        return p;
    }

    // ── SSE REAL-TIME ───────────────────────────────────────────────
    connectSSE() {
        if (this.eventSource) this.eventSource.close();

        const url = `${this.apiBase}/activity-logs/stream?last_id=${this.maxLogId}`;
        this.updateLiveStatus('syncing', 'Connecting...');

        this.eventSource = new EventSource(url, { withCredentials: true });

        this.eventSource.addEventListener('log', (e) => {
            try {
                const log = JSON.parse(e.data);
                if (!log || !log.id) return;
                if (this.allLogs.some(l => l.id === log.id)) return;

                if (log.id > this.maxLogId) this.maxLogId = log.id;

                log._isNew = true;
                this.allLogs.unshift(log);

                // Trim if exceeding 500 items to prevent memory issues
                if (this.allLogs.length > 500) {
                    this.allLogs = this.allLogs.slice(0, 500);
                }

                const scrollTop = window.scrollY || document.documentElement.scrollTop;
                const filtersActive = this.areFiltersActive();

                if (!filtersActive && this.currentPage === 1) {
                    this.renderTable();
                } else {
                    this.unreadCount++;
                    this.showFloatingToast();
                }

                this.showToastNotification(log);
                this.updateSyncTime(true);

                // Adjust pagination to account for new prepended item
                if (!filtersActive && this.currentPage === 1) {
                    this.totalItems++;
                }
            } catch (err) {
                console.error('[ActivityLogs] SSE log parse error:', err);
            }
        });

        this.eventSource.addEventListener('stats', (e) => {
            try {
                const freshStats = JSON.parse(e.data);
                this.animateStats(freshStats);
            } catch (err) {}
        });

        this.eventSource.onopen = () => {
            this.updateLiveStatus('live', 'Live');
            this.isLive = true;
            if (this.reconnectTimeout) {
                clearTimeout(this.reconnectTimeout);
                this.reconnectTimeout = null;
            }
        };

        this.eventSource.onerror = () => {
            if (this.eventSource.readyState === EventSource.CLOSED) {
                this.updateLiveStatus('disconnected', 'Disconnected');
                this.isLive = false;
                if (!this.reconnectTimeout) {
                    this.reconnectTimeout = setTimeout(() => {
                        this.reconnectTimeout = null;
                        this.connectSSE();
                    }, 5000);
                }
            } else if (this.eventSource.readyState === EventSource.CONNECTING) {
                this.updateLiveStatus('syncing', 'Syncing...');
                this.isLive = false;
            }
        };
    }

    // ── STATS ANIMATION (CountUp) ───────────────────────────────────
    animateStats(newStats) {
        const oldStats = { ...this.stats };
        this.stats = { ...this.stats, ...newStats };

        const mappings = [
            { el: 'stat-logs-today', key: 'logs_today', cardClass: 'blue' },
            { el: 'stat-approvals-today', key: 'approvals_today', cardClass: 'green' },
            { el: 'stat-rejections-today', key: 'rejections_today', cardClass: 'red' },
            { el: 'stat-active-users', key: 'active_users_24h', cardClass: 'yellow' },
        ];

        mappings.forEach(({ el, key, cardClass }) => {
            const element = document.getElementById(el);
            if (!element) return;
            const from = oldStats[key] || 0;
            const to = newStats[key] || 0;

            if (from === to) {
                element.textContent = to;
                return;
            }

            this.animateValue(element, from, to, 800);

            const card = element.closest('.stat-card');
            if (card && Math.abs(to - from) > 0) {
                card.classList.add('pulse');
                setTimeout(() => card.classList.remove('pulse'), 600);
            }
        });

        // Optional 5th/6th card if present
        ['stat-total-logs'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = newStats.total_logs || 0;
        });
    }

    animateValue(element, start, end, duration) {
        const startTime = performance.now();
        const update = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
            element.textContent = Math.floor(start + (end - start) * eased);
            if (progress < 1) requestAnimationFrame(update);
        };
        requestAnimationFrame(update);
    }

    // ── TABLE RENDERING ─────────────────────────────────────────────
    renderTable() {
        try {
            const tbody = document.querySelector('.data-table tbody');
            if (!tbody) return;

            if (this.allLogs.length === 0) {
                tbody.innerHTML = this.emptyStateHTML();
                this.renderPagination();
                return;
            }

            let html = '';
            this.allLogs.forEach(log => {
                html += this.renderRow(log);
            });

            tbody.innerHTML = html;

            tbody.querySelectorAll('tr[data-log-id]').forEach(row => {
                row.addEventListener('click', () => {
                    const logId = parseInt(row.getAttribute('data-log-id'));
                    this.openDrawer(logId);
                });
            });

            // Remove flash after animation
            setTimeout(() => {
                tbody.querySelectorAll('.highlight-flash').forEach(r => {
                    r.classList.remove('highlight-flash');
                });
            }, 2100);

            this.renderPagination();
        } catch (err) {
            console.error('[ActivityLogs] renderTable failed:', err);

            // Visual debug banner
            const debugDiv = document.createElement('div');
            debugDiv.style.background = '#f97316';
            debugDiv.style.color = '#ffffff';
            debugDiv.style.padding = '12px 20px';
            debugDiv.style.position = 'fixed';
            debugDiv.style.top = '50px';
            debugDiv.style.left = '0';
            debugDiv.style.right = '0';
            debugDiv.style.zIndex = '999999';
            debugDiv.style.fontWeight = 'bold';
            debugDiv.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
            debugDiv.textContent = 'RENDER ERROR: ' + (err.stack || err.message || err);
            document.body.appendChild(debugDiv);
        }
    }

    renderRow(log) {
        let parsed = {};
        try {
            parsed = typeof log.details === 'string' ? JSON.parse(log.details) : (log.details || {});
        } catch (e) {
            parsed = { description: log.description || log.details || '' };
        }

        const description = log.description || parsed.description || '';
        const module = log.module || parsed.module || 'System';
        const user = log.user_name || (log.user ? log.user.name : 'System');
        const iconInfo = ACTION_ICONS[log.action] || { icon: 'fa-info-circle', color: 'gray' };
        const color = iconInfo.color;
        const badgeColor = this.getBadgeColor(log.action);
        const isNew = log._isNew ? ' new-row' : '';
        const rowClass = `row-${color}${isNew}`;
        const formattedDate = this.formatDateTime(log.created_at);
        const relTime = this.timeAgo(log.created_at);

        // Clean up the flag
        if (log._isNew) delete log._isNew;

        return `
            <tr class="${rowClass}" data-log-id="${log.id}">
                <td class="activity-icon-cell">
                    <i class="fas ${iconInfo.icon}" style="color: var(--accent-${color === 'gray' ? 'gray' : color}); font-size: 15px;"></i>
                </td>
                <td>
                    <span class="action-badge badge-${badgeColor}">${log.action}</span>
                </td>
                <td style="font-weight: 600; color: #475569;">${module}</td>
                <td style="color: #1e293b; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    ${this.escapeHTML(description)}
                </td>
                <td style="font-weight: 600; color: #334155;">
                    <span title="${user}">${user}</span>
                    ${log.municipality ? `<div style="font-size: 10px; color: var(--text-muted);">${log.municipality}</div>` : ''}
                </td>
                <td>
                    <div style="font-weight: 600; color: #334155;">${formattedDate}</div>
                    <div style="font-size: 11px; color: var(--text-muted); font-weight: 500;">${relTime}</div>
                </td>
            </tr>
        `;
    }

    emptyStateHTML() {
        const filtersActive = this.areFiltersActive();
        return `
            <tr>
                <td colspan="6">
                    <div class="empty-state-wrap">
                        <div class="empty-state-illustration">
                            <i class="fas fa-${filtersActive ? 'filter' : 'history'}"></i>
                        </div>
                        <h4>${filtersActive ? 'No matching logs' : 'No activity logs yet'}</h4>
                        <p>${filtersActive ? 'No activity logs match your current filters. Try adjusting or clearing them.' : 'Activity logs will appear here as actions are performed in the system.'}</p>
                    </div>
                </td>
            </tr>
        `;
    }

    getBadgeColor(action) {
        const c = (ACTION_ICONS[action] || {}).color || 'gray';
        return c;
    }

    // ── PAGINATION ──────────────────────────────────────────────────
    renderPagination() {
        const footer = document.getElementById('table-pagination-container');
        if (!footer) return;

        if (this.totalItems === 0) {
            footer.innerHTML = `
                <div style="font-size: 13px; color: var(--text-muted);">No records found</div>
            `;
            return;
        }

        const start = (this.currentPage - 1) * this.perPage + 1;
        const end = Math.min(start + this.perPage - 1, this.totalItems);

        let pageBtns = '';
        let startPage = Math.max(1, this.currentPage - 2);
        let endPage = Math.min(this.totalPages, startPage + 4);
        if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);

        for (let p = startPage; p <= endPage; p++) {
            pageBtns += `<button class="pagination-btn ${p === this.currentPage ? 'active' : ''}" data-page="${p}">${p}</button>`;
        }

        footer.innerHTML = `
            <div>Showing <b>${start}-${end}</b> of <b>${this.totalItems}</b> activity logs</div>
            <div class="pagination-controls-wrap">
                <span style="font-weight:500; font-size:12px;">Rows:</span>
                <select class="pagination-select" id="rows-per-page-select">
                    <option value="10" ${this.perPage === 10 ? 'selected' : ''}>10</option>
                    <option value="25" ${this.perPage === 25 ? 'selected' : ''}>25</option>
                    <option value="50" ${this.perPage === 50 ? 'selected' : ''}>50</option>
                </select>
                <div class="pagination-buttons">
                    <button class="pagination-btn" id="pagination-prev" ${this.currentPage === 1 ? 'disabled' : ''}>
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    ${pageBtns}
                    <button class="pagination-btn" id="pagination-next" ${this.currentPage >= this.totalPages ? 'disabled' : ''}>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        `;

        this.wirePaginationEvents(footer);
    }

    wirePaginationEvents(footer) {
        footer.querySelectorAll('.pagination-btn[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                this.currentPage = parseInt(btn.getAttribute('data-page'));
                this.fetchLogs();
            });
        });

        const prev = footer.querySelector('#pagination-prev');
        if (prev) prev.addEventListener('click', () => {
            if (this.currentPage > 1) { this.currentPage--; this.fetchLogs(); }
        });

        const next = footer.querySelector('#pagination-next');
        if (next) next.addEventListener('click', () => {
            if (this.currentPage < this.totalPages) { this.currentPage++; this.fetchLogs(); }
        });

        const select = footer.querySelector('#rows-per-page-select');
        if (select) select.addEventListener('change', (e) => {
            this.perPage = parseInt(e.target.value);
            this.currentPage = 1;
            this.fetchLogs();
        });
    }

    // ── FILTERS ─────────────────────────────────────────────────────
    areFiltersActive() {
        return this.filters.action.length > 0 ||
               this.filters.module.length > 0 ||
               this.filters.role ||
               this.filters.municipality ||
               this.filters.search ||
               this.filters.datePreset ||
               this.filters.dateFrom ||
               this.filters.dateTo;
    }

    applyFilters() {
        this.currentPage = 1;
        this.fetchLogs();
        this.updateFilterBadges();
        this.updateClearButton();
    }

    clearAllFilters() {
        this.filters = {
            action: [],
            module: [],
            role: null,
            municipality: null,
            search: '',
            datePreset: null,
            dateFrom: '',
            dateTo: '',
            userId: null,
        };

        // Reset UI
        const searchInput = document.querySelector('.search-input-wrap input');
        if (searchInput) searchInput.value = '';

        const fromDate = document.getElementById('date-from');
        if (fromDate) fromDate.value = '';

        const toDate = document.getElementById('date-to');
        if (toDate) toDate.value = '';

        // Clear dropdowns
        document.querySelectorAll('.custom-dropdown-select').forEach(sel => {
            sel.querySelectorAll('.custom-dropdown-option').forEach(opt => opt.classList.remove('selected'));
        });
        this.updateDropdownLabel('dropdown-action', [], 'All Actions');
        this.updateDropdownLabel('dropdown-module', [], 'All Modules');

        // Clear date presets
        document.querySelectorAll('.date-preset-pill').forEach(p => p.classList.remove('active'));

        this.applyFilters();
    }

    updateFilterBadges() {
        const container = document.getElementById('filter-badges-container');
        if (!container) return;

        const badges = [];
        if (this.filters.action.length) {
            badges.push(this.buildBadge(`Action: ${this.filters.action.join(', ')}`, () => {
                this.filters.action = [];
                this.applyFilters();
            }));
        }
        if (this.filters.module.length) {
            badges.push(this.buildBadge(`Module: ${this.filters.module.join(', ')}`, () => {
                this.filters.module = [];
                this.applyFilters();
            }));
        }
        if (this.filters.role) {
            badges.push(this.buildBadge(`Role: ${this.filters.role}`, () => {
                this.filters.role = null;
                this.applyFilters();
            }));
        }
        if (this.filters.municipality) {
            badges.push(this.buildBadge(`Municipality: ${this.filters.municipality}`, () => {
                this.filters.municipality = null;
                this.applyFilters();
            }));
        }
        if (this.filters.search) {
            badges.push(this.buildBadge(`Search: ${this.filters.search}`, () => {
                this.filters.search = '';
                this.applyFilters();
            }));
        }
        if (this.filters.datePreset) {
            badges.push(this.buildBadge(`Date: ${this.filters.datePreset}`, () => {
                this.filters.datePreset = null;
                this.applyFilters();
            }));
        }

        container.innerHTML = badges.join('');
    }

    buildBadge(label, onClick) {
        return `<span class="filter-badge">${this.escapeHTML(label)} <span class="badge-remove" onclick="event.stopPropagation(); (${onClick.toString()})()">✕</span></span>`;
    }

    updateClearButton() {
        const btn = document.getElementById('btn-clear-filters');
        if (btn) {
            btn.style.display = this.areFiltersActive() ? 'inline-flex' : 'none';
        }
    }

    updateDropdownLabel(id, list, defaultLabel) {
        const triggerSpan = document.querySelector(`#${id} .custom-dropdown-trigger span`);
        if (!triggerSpan) return;
        if (list.length === 0) triggerSpan.textContent = defaultLabel;
        else if (list.length === 1) triggerSpan.textContent = list[0];
        else triggerSpan.textContent = `${list.length} selected`;
    }

    populateDropdownFilters() {
        const actions = [...new Set(this.allLogs.map(l => l.action))].filter(Boolean).sort();
        const modules = [...new Set(this.allLogs.map(l => {
            let mod = l.module;
            if (!mod) {
                try {
                    const d = typeof l.details === 'string' ? JSON.parse(l.details) : (l.details || {});
                    mod = d.module;
                } catch (e) {}
            }
            return mod || 'System';
        }))].filter(Boolean).sort();

        this.renderDropdown('dropdown-action-menu', actions, 'action');
        this.renderDropdown('dropdown-module-menu', modules, 'module');
        this.wireDropdownOptions();
    }

    renderDropdown(menuId, items, filterKey) {
        const menu = document.getElementById(menuId);
        if (!menu) return;

        menu.innerHTML = items.map(item => `
            <div class="custom-dropdown-option ${this.filters[filterKey].includes(item) ? 'selected' : ''}" data-value="${item}">
                <span>${item}</span>
                <i class="fas fa-check option-check"></i>
            </div>
        `).join('');
    }

    wireDropdownOptions() {
        ['dropdown-action-menu', 'dropdown-module-menu'].forEach(menuId => {
            const filterKey = menuId === 'dropdown-action-menu' ? 'action' : 'module';
            const dropdownId = menuId.replace('-menu', '');

            document.querySelectorAll(`#${menuId} .custom-dropdown-option`).forEach(opt => {
                opt.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const val = opt.getAttribute('data-value');
                    const idx = this.filters[filterKey].indexOf(val);
                    if (idx >= 0) {
                        this.filters[filterKey].splice(idx, 1);
                        opt.classList.remove('selected');
                    } else {
                        this.filters[filterKey].push(val);
                        opt.classList.add('selected');
                    }
                    this.updateDropdownLabel(dropdownId, this.filters[filterKey],
                        filterKey === 'action' ? 'All Actions' : 'All Modules');
                    this.applyFilters();
                });
            });
        });
    }

    // ── DATE PRESETS ────────────────────────────────────────────────
    setDatePreset(preset, pill) {
        // Deactivate all pills
        document.querySelectorAll('.date-preset-pill').forEach(p => p.classList.remove('active'));

        if (this.filters.datePreset === preset) {
            this.filters.datePreset = null;
        } else {
            this.filters.datePreset = preset;
            pill.classList.add('active');
        }
        this.filters.dateFrom = '';
        this.filters.dateTo = '';
        this.applyFilters();
    }

    // ── DETAIL DRAWER ───────────────────────────────────────────────
    openDrawer(logId) {
        const log = this.allLogs.find(l => l.id === logId);
        if (!log) return;

        let parsed = {};
        try {
            parsed = typeof log.details === 'string' ? JSON.parse(log.details) : (log.details || {});
        } catch (e) {
            parsed = {};
        }

        const user = log.user || {};
        const userName = log.user_name || user.name || 'System';
        const userRole = log.user_role || user.role || '—';
        const municipality = log.municipality || '—';
        const module = log.module || parsed.module || 'System';
        const description = log.description || parsed.description || '—';
        const device = log.device || '—';
        const browser = log.browser || '—';
        const os = log.os || '—';
        const ip = log.ip_address || '—';
        const userAgent = log.user_agent || parsed.device_browser || '—';

        // Avatar initials
        const initials = userName.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
        const avatarColor = ['blue', 'green', 'purple', 'navy'][userName.length % 4] !== 'navy'
            ? ['blue', 'green', 'purple'][userName.length % 3]
            : 'blue';

        // Populate drawer
        document.getElementById('drawer-user-avatar').textContent = initials;
        document.getElementById('drawer-user-avatar').className = `drawer-avatar ${avatarColor}`;
        document.getElementById('drawer-user-name').textContent = userName;
        document.getElementById('drawer-user-meta').textContent = `${userRole}${municipality !== '—' ? ' · ' + municipality : ''}`;
        document.getElementById('drawer-action').textContent = log.action;
        document.getElementById('drawer-module').textContent = module;
        document.getElementById('drawer-description').textContent = description;
        document.getElementById('drawer-timestamp').textContent = this.formatDateTime(log.created_at);
        document.getElementById('drawer-relative-time').textContent = this.timeAgo(log.created_at);
        document.getElementById('drawer-ip').textContent = ip;
        document.getElementById('drawer-device').textContent = device;
        document.getElementById('drawer-browser').textContent = browser;
        document.getElementById('drawer-os').textContent = os;
        document.getElementById('drawer-user-agent').textContent = userAgent;

        // Old / New values
        const oldVal = log.old_value || parsed.old_value || null;
        const newVal = log.new_value || parsed.new_value || null;
        document.getElementById('drawer-old-val').textContent = oldVal
            ? (typeof oldVal === 'object' ? JSON.stringify(oldVal, null, 2) : oldVal)
            : 'None';
        document.getElementById('drawer-new-val').textContent = newVal
            ? (typeof newVal === 'object' ? JSON.stringify(newVal, null, 2) : newVal)
            : 'None';

        // Show drawer
        const drawer = document.getElementById('detail-drawer');
        const overlay = document.getElementById('detail-drawer-overlay');
        if (drawer) drawer.classList.add('active');
        if (overlay) overlay.classList.add('active');

        // Close on Escape
        this._escHandler = (e) => { if (e.key === 'Escape') this.closeDrawer(); };
        document.addEventListener('keydown', this._escHandler);
    }

    closeDrawer() {
        const drawer = document.getElementById('detail-drawer');
        const overlay = document.getElementById('detail-drawer-overlay');
        if (drawer) drawer.classList.remove('active');
        if (overlay) overlay.classList.remove('active');
        if (this._escHandler) {
            document.removeEventListener('keydown', this._escHandler);
            this._escHandler = null;
        }
    }

    // ── TOAST NOTIFICATIONS ─────────────────────────────────────────
    showToastNotification(log) {
        const description = log.description || 'New activity recorded';
        const iconInfo = ACTION_ICONS[log.action] || { icon: 'fa-info-circle', color: 'gray' };
        const toastType = iconInfo.color === 'red' ? 'toast-error' :
                         iconInfo.color === 'green' ? 'toast-success' :
                         iconInfo.color === 'orange' ? 'toast-warning' : 'toast-info';

        const toast = document.createElement('div');
        toast.className = `activity-toast ${toastType}`;
        toast.innerHTML = `<i class="fas ${iconInfo.icon}"></i> ${this.escapeHTML(description)}`;
        document.body.appendChild(toast);

        requestAnimationFrame(() => toast.classList.add('show'));

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 300);
        }, 4500);
    }

    showFloatingToast() {
        const alert = document.getElementById('floating-toast');
        if (!alert) return;

        alert.innerHTML = `<i class="fas fa-arrow-up"></i> ${this.unreadCount} new log${this.unreadCount > 1 ? 's' : ''} available`;
        alert.classList.add('show');
    }

    handleFloatingToastClick() {
        const alert = document.getElementById('floating-toast');
        if (alert) alert.classList.remove('show');
        this.unreadCount = 0;
        this.clearAllFilters();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // ── EXPORT ──────────────────────────────────────────────────────
    exportLogs(format) {
        if (this.allLogs.length === 0) {
            alert('No data to export.');
            return;
        }

        const headers = ['Log ID', 'Action', 'Module', 'Description', 'User', 'Role', 'Municipality', 'IP Address', 'Device', 'Browser', 'OS', 'Created At'];
        const rows = this.allLogs.map(l => {
            let parsed = {};
            try {
                parsed = typeof l.details === 'string' ? JSON.parse(l.details) : (l.details || {});
            } catch (e) {}
            return [
                `#LOG-${String(l.id).padStart(4, '0')}`,
                l.action,
                l.module || parsed.module || 'System',
                l.description || parsed.description || '',
                l.user_name || (l.user ? l.user.name : 'System'),
                l.user_role || (l.user ? l.user.role : ''),
                l.municipality || '',
                l.ip_address || '',
                l.device || '',
                l.browser || '',
                l.os || '',
                l.created_at,
            ];
        });

        if (format === 'csv' || format === 'xlsx') {
            let content = headers.map(h => `"${h}"`).join(',') + '\n';
            rows.forEach(r => {
                content += r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',') + '\n';
            });

            const mime = format === 'csv' ? 'text/csv;charset=utf-8;' : 'text/csv;charset=utf-8;';
            const blob = new Blob(['\uFEFF' + content], { type: mime }); // BOM for Excel
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `activity_logs_${new Date().toISOString().slice(0,10)}.csv`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        } else if (format === 'pdf') {
            const printWin = window.open('', '_blank');
            printWin.document.write(this.buildPrintHTML(headers, rows));
            printWin.document.close();
        }
    }

    buildPrintHTML(headers, rows) {
        const now = new Date().toLocaleString();
        return `<!DOCTYPE html><html><head>
            <title>Activity Logs Export</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; padding: 24px; color: #1e293b; }
                h2 { color: #1e3a8a; margin-bottom: 4px; }
                .meta { font-size:12px; color:#64748b; margin-bottom:20px; }
                table { width:100%; border-collapse:collapse; font-size:11px; }
                th { background:#f1f5f9; padding:8px 10px; text-align:left; font-weight:700; border:1px solid #e2e8f0; }
                td { padding:6px 10px; border:1px solid #e2e8f0; }
                tr:nth-child(even) td { background:#fafbfc; }
            </style></head><body>
            <h2>Activity Logs Report</h2>
            <p class="meta">Generated: ${now} · Total Records: ${rows.length}</p>
            <table><thead><tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr></thead><tbody>
            ${rows.map(r => `<tr>${r.map(c => `<td>${c}</td>`).join('')}</tr>`).join('')}
            </tbody></table>
            <script>window.onload=function(){window.print();}</script>
            </body></html>`;
    }

    // ── UI EVENT HANDLERS ───────────────────────────────────────────
    setupUIEventHandlers() {
        const self = this;

        // Search input (debounced)
        const searchInput = document.querySelector('.search-input-wrap input');
        if (searchInput) {
            searchInput.addEventListener('input', this.debounce((e) => {
                self.filters.search = e.target.value.trim();
                self.applyFilters();
            }, 300));
        }

        // Date pickers
        const dateFrom = document.getElementById('date-from');
        if (dateFrom) dateFrom.addEventListener('change', (e) => {
            self.filters.dateFrom = e.target.value;
            self.filters.datePreset = null;
            document.querySelectorAll('.date-preset-pill').forEach(p => p.classList.remove('active'));
            self.applyFilters();
        });

        const dateTo = document.getElementById('date-to');
        if (dateTo) dateTo.addEventListener('change', (e) => {
            self.filters.dateTo = e.target.value;
            self.filters.datePreset = null;
            self.applyFilters();
        });

        // Date preset pills
        document.querySelectorAll('.date-preset-pill').forEach(pill => {
            pill.addEventListener('click', () => {
                self.setDatePreset(pill.getAttribute('data-preset'), pill);
            });
        });

        // Clear filters
        const clearBtn = document.getElementById('btn-clear-filters');
        if (clearBtn) clearBtn.addEventListener('click', () => self.clearAllFilters());

        // Dropdown trigger open/close
        document.querySelectorAll('.custom-dropdown-trigger').forEach(trigger => {
            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                const parent = trigger.closest('.custom-dropdown-select');
                if (!parent) return;
                const wasActive = parent.classList.contains('active');
                document.querySelectorAll('.custom-dropdown-select').forEach(el => el.classList.remove('active'));
                if (!wasActive) parent.classList.add('active');
            });
        });

        // Close dropdowns on outside click
        document.addEventListener('click', () => {
            document.querySelectorAll('.custom-dropdown-select').forEach(el => el.classList.remove('active'));
            const exportMenu = document.getElementById('export-formats-menu');
            if (exportMenu) exportMenu.classList.remove('active');
        });

        // Drawer close
        const drawerCloseBtn = document.getElementById('btn-drawer-close');
        if (drawerCloseBtn) drawerCloseBtn.addEventListener('click', () => self.closeDrawer());

        const drawerOverlay = document.getElementById('detail-drawer-overlay');
        if (drawerOverlay) drawerOverlay.addEventListener('click', () => self.closeDrawer());

        // Floating toast click
        const floatingToast = document.getElementById('floating-toast');
        if (floatingToast) floatingToast.addEventListener('click', () => self.handleFloatingToastClick());

        // Export button
        const btnExport = document.getElementById('btn-export-logs');
        const exportMenu = document.getElementById('export-formats-menu');
        const exportWrapper = btnExport ? btnExport.closest('.export-dropdown-wrapper') : null;
        if (btnExport) {
            btnExport.addEventListener('click', (e) => {
                e.stopPropagation();
                if (exportWrapper) exportWrapper.classList.toggle('active');
                if (exportMenu) exportMenu.classList.toggle('active');
            });

            document.addEventListener('click', (e) => {
                if (exportWrapper && !exportWrapper.contains(e.target)) {
                    exportWrapper.classList.remove('active');
                }
                if (exportMenu && !exportMenu.contains(e.target)) {
                    exportMenu.classList.remove('active');
                }
            });
        }

        // Export format options
        document.querySelectorAll('.export-dropdown-item').forEach(item => {
            item.addEventListener('click', () => {
                const format = item.getAttribute('data-format');
                self.exportLogs(format);
                if (exportWrapper) exportWrapper.classList.remove('active');
                if (exportMenu) exportMenu.classList.remove('active');
            });
        });

        // Manual refresh
        const refreshBtn = document.getElementById('btn-manual-refresh');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', async () => {
                const icon = refreshBtn.querySelector('i');
                if (icon) icon.classList.add('fa-spin');
                try {
                    await self.fetchStats();
                    await self.fetchLogs();
                    self.connectSSE();
                } finally {
                    if (icon) setTimeout(() => icon.classList.remove('fa-spin'), 600);
                }
            });
        }
    }

    // ── LIVE STATUS ─────────────────────────────────────────────────
    updateLiveStatus(status, text) {
        const dot = document.getElementById('live-dot');
        const label = document.getElementById('live-status-label');
        if (dot) {
            dot.className = 'live-dot ' + status;
        }
        if (label) label.textContent = text;
    }

    startSyncTimer() {
        this.syncSeconds = 0;
        if (this.syncTimer) clearInterval(this.syncTimer);
        this.syncTimer = setInterval(() => {
            this.syncSeconds++;
            const el = document.getElementById('last-synced-time');
            if (el) {
                if (this.syncSeconds < 10) el.textContent = 'just now';
                else if (this.syncSeconds < 60) el.textContent = `${this.syncSeconds}s ago`;
                else el.textContent = `${Math.floor(this.syncSeconds / 60)}m ago`;
            }
        }, 1000);
    }

    updateSyncTime(reset = false) {
        if (reset) this.syncSeconds = 0;
    }

    // ── SKELETON / ERROR ────────────────────────────────────────────
    showSkeletonLoader() {
        const tbody = document.querySelector('.data-table tbody');
        if (!tbody) return;

        let html = '';
        for (let i = 0; i < 5; i++) {
            html += `
                <tr class="skeleton-row">
                    <td class="activity-icon-cell"><div class="shimmer" style="height:20px;width:20px;border-radius:50%;display:inline-block;"></div></td>
                    <td><div class="shimmer" style="height:24px;width:120px;border-radius:12px;"></div></td>
                    <td><div class="shimmer" style="height:16px;width:100px;border-radius:4px;"></div></td>
                    <td><div class="shimmer" style="height:16px;width:90%;border-radius:4px;"></div></td>
                    <td><div class="shimmer" style="height:16px;width:80px;border-radius:4px;"></div></td>
                    <td><div class="shimmer" style="height:16px;width:140px;border-radius:4px;"></div></td>
                </tr>
            `;
        }
        tbody.innerHTML = html;
    }

    showErrorState(message) {
        const tbody = document.querySelector('.data-table tbody');
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align:center;padding:40px;color:var(--accent-red);font-weight:600;">
                        <i class="fas fa-exclamation-circle" style="font-size:24px;display:block;margin-bottom:8px;"></i>
                        <p>${this.escapeHTML(message)}</p>
                        <button class="btn-gov" style="margin-top:12px;" onclick="window.activityLogsModule.fetchLogs()">
                            <i class="fas fa-redo"></i> Retry
                        </button>
                    </td>
                </tr>
            `;
        }
    }

    // ── UTILITIES ───────────────────────────────────────────────────
    formatDateTime(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }) + ' ' +
               d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    }

    timeAgo(dateStr) {
        if (!dateStr) return '';
        const now = new Date();
        const date = new Date(dateStr);
        const seconds = Math.floor((now - date) / 1000);
        if (seconds < 0) return 'just now';
        const intervals = [
            ['year', 31536000], ['month', 2592000], ['week', 604800],
            ['day', 86400], ['hour', 3600], ['minute', 60],
        ];
        for (const [label, s] of intervals) {
            const count = Math.floor(seconds / s);
            if (count >= 1) return `${count} ${label}${count !== 1 ? 's' : ''} ago`;
        }
        return 'just now';
    }

    escapeHTML(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    debounce(fn, delay) {
        let timer;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    // ── DESTROY ─────────────────────────────────────────────────────
    destroy() {
        if (this.eventSource) this.eventSource.close();
        if (this.reconnectTimeout) clearTimeout(this.reconnectTimeout);
        if (this.syncTimer) clearInterval(this.syncTimer);
        if (this._escHandler) document.removeEventListener('keydown', this._escHandler);
    }
}

// ── BOOTSTRAP ──────────────────────────────────────────────────────
function _initActivityLogs() {
    if (window.activityLogsModule) {
        try { window.activityLogsModule.destroy(); } catch (e) {}
    }
    const module = new ActivityLogsModule();
    window.activityLogsModule = module;
    module.init();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', _initActivityLogs);
} else {
    _initActivityLogs();
}
