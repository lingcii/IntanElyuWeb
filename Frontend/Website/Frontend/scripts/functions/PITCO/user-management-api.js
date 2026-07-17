/**
 * PICTO User Management API
 * Role: picto (full CRUD)
 * Client-side caching — no reload on tab switch
 */

'use strict';

(function () {
    const UM_API = window.API_CONFIG?.PITCO + '/users' || 'http://localhost:8000/api/pitco/users';
    const CACHE_TTL = 60000;
    window.__UM_CACHE__ = window.__UM_CACHE__ || { users: null, total: 0, stats: null, municipalities: null, timestamp: 0 };

    // Map legacy action strings to Laravel REST endpoints
    function umActionToUrl(action, params = {}) {
        const map = {
            'get_users': `${UM_API}`,
            'get_user': `${UM_API}/${params.id || ''}`,
            'get_municipalities': window.API_CONFIG?.BASE_URL + '/api/municipalities' || 'http://localhost:8000/api/municipalities',
            'get_audit_logs': `${UM_API}/audit-logs`,
            'add_user': `${UM_API}`,
            'edit_user': `${UM_API}/${params.id || ''}`,
            'toggle_status': `${UM_API}/${params.id || ''}/status`,
            'reset_password': `${UM_API}/${params.id || ''}/password`,
            'archive_user': `${UM_API}/${params.id || ''}/archive`,
            'restore_user': `${UM_API}/${params.id || ''}/restore`,
            'delete_user': `${UM_API}/${params.id || ''}`,
        };
        const base = map[action] || `${UM_API}/${action.replace(/_/g, '-')}`;
        const searchParams = { ...params };
        delete searchParams.id;
        const qs = Object.keys(searchParams).length ? '?' + new URLSearchParams(searchParams).toString() : '';
        return base + qs;
    }

    // ── State 
    let _currentPage = 1;
    let _totalRows = 0;
    let _sortCol = 'created_at';
    let _sortDir = 'DESC';
    let _searchTimer = null;
    let _municipalities = [];
    let _roleStats = [];
    let _editingUserRole = null;
    let _editingUserStatus = null;
    let _lastHash = '';
    let _pendingSave = null;

    // Pending confirmation callback
    let _confirmCallback = null;

    const PAGE_SIZE = 25;

    function _filterHash() {
        const search = (document.getElementById('searchInput')?.value || '').trim();
        const role = document.getElementById('roleFilter')?.value || '';
        const status = document.getElementById('statusFilter')?.value || '';
        return `${_sortCol}:${_sortDir}:${_currentPage}:${search}:${role}:${status}`;
    }

    // ── Boot
    let _initialBoot = true;
    document.addEventListener('DOMContentLoaded', () => {
        const cache = window.__UM_CACHE__;
        const fresh = cache.users && cache.timestamp && (Date.now() - cache.timestamp < CACHE_TTL);

        if (_initialBoot && cache.users) {
            _municipalities = cache.municipalities || [];
            populateMuniSelect(_municipalities);
            _totalRows = cache.total;
            const stats = cache.stats || null;
            updateKpis(stats);
            renderTable(cache.users);
            updateTableCount(cache.users.length, cache.total);
            renderPagination(_totalRows, 0);
            _initialBoot = false;
            
            // Background Auto-Refresh (matching LUPTO 60s)
            setInterval(() => loadUsers(true), 60000);

            // If cache is stale, trigger a silent fetch in the background
            if (!fresh) {
                loadUsers(true);
            }
            return;
        }
        _initialBoot = false;
        Promise.all([loadMunicipalities(), loadUsers()]);
        
        // Background Auto-Refresh (matching LUPTO 60s)
        setInterval(() => loadUsers(true), 60000);
    });

    // ── API fetch helper using API_CONFIG
    async function apiFetch(action, params = {}, method = 'GET', body = null) {
        const url = umActionToUrl(action, params);

        // Map method and body based on action
        const httpMethodMap = {
            'add_user': 'POST',
            'edit_user': 'PUT',
            'toggle_status': 'PATCH',
            'reset_password': 'PATCH',
            'archive_user': 'PATCH',
            'restore_user': 'PATCH',
            'delete_user': 'DELETE',
        };
        const resolvedMethod = method !== 'GET' ? method : (httpMethodMap[action] || 'GET');

        try {
            if (resolvedMethod === 'GET') {
                return await window.API_CONFIG.get(url);
            } else if (resolvedMethod === 'POST') {
                return await window.API_CONFIG.post(url, body || {});
            } else if (resolvedMethod === 'PUT') {
                return await window.API_CONFIG.put(url, body || {});
            } else if (resolvedMethod === 'PATCH') {
                return await window.API_CONFIG.fetch(url, { method: 'PATCH', body: JSON.stringify(body || {}) });
            } else if (resolvedMethod === 'DELETE') {
                return await window.API_CONFIG.fetch(url, { method: 'DELETE' });
            }
        } catch (e) {
            throw new Error('API error: ' + e.message);
        }
    }

    // Dropdown helpers
    function toggleUmDropdown(event, userId) {
        event.stopPropagation();
        const dropdown = document.getElementById(`um-dropdown-${userId}`);
        const isShowing = dropdown && dropdown.style.display === 'block';

        closeAllUmDropdowns();

        if (dropdown && !isShowing) {
            dropdown.style.display = 'block';
        }
    }

    function closeAllUmDropdowns() {
        document.querySelectorAll('.um-dropdown-menu').forEach(el => {
            el.style.display = 'none';
        });
    }


    // ── Public: full refresh 
    async function refreshTable() {
        const icon = document.getElementById('refreshIcon');
        if (icon) icon.classList.add('fa-spin');
        _currentPage = 1;
        window.__UM_CACHE__.timestamp = 0;
        await loadUsers();
        if (icon) icon.classList.remove('fa-spin');
    }

    // ── Load municipalities for dropdowns
    async function loadMunicipalities() {
        const cache = window.__UM_CACHE__;
        if (cache.municipalities && cache.municipalities.length) {
            _municipalities = cache.municipalities;
            populateMuniSelect(_municipalities);
            return;
        }
        try {
            const data = await apiFetch('get_municipalities');
            _municipalities = data.municipalities || [];
            cache.municipalities = _municipalities;
            populateMuniSelect(_municipalities);
        } catch (_) { /* non-critical */ }
    }

    function populateMuniSelect(list) {
        const sel = document.getElementById('formMunicipality');
        if (!sel) return;
        sel.innerHTML = '<option value="">— Select Municipality —</option>';
        list.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.id;
            opt.textContent = m.name;
            sel.appendChild(opt);
        });
    }

    // ── Load users 
    async function loadUsers(silent = false) {
        const tbody = document.getElementById('usersBody');
        if (!tbody) return;

        if (!silent) {
            tbody.innerHTML = `<tr><td colspan="9" class="um-empty"><i class="fas fa-spinner fa-spin"></i><p>Loading…</p></td></tr>`;
        }

        const search = (document.getElementById('searchInput')?.value || '').trim();
        const role = document.getElementById('roleFilter')?.value || '';
        const status = document.getElementById('statusFilter')?.value || '';
        const offset = (_currentPage - 1) * PAGE_SIZE;

        try {
            const data = await apiFetch('get_users', {
                search, role, status,
                sort: _sortCol,
                dir: _sortDir,
                limit: PAGE_SIZE,
                offset,
            });

            _totalRows = data.total || 0;

            updateKpis(data.stats);
            renderTable(data.users || []);
            renderPagination(_totalRows, offset);
            updateTableCount(data.users?.length || 0, _totalRows);
            updateSortIcons();

            const cache = window.__UM_CACHE__;
            cache.users = data.users || [];
            cache.total = _totalRows;
            cache.stats = data.stats;
            cache.timestamp = Date.now();
            _lastHash = _filterHash();

        } catch (err) {
            console.error('[UM] loadUsers:', err);
            tbody.innerHTML = `<tr><td colspan="9" class="um-empty"><i class="fas fa-exclamation-circle" style="color:#ef4444;"></i><p>${escHtml(err.message)}</p></td></tr>`;
        }
    }

    // ── KPIs ─
    function updateKpis(stats) {
        if (!stats) return;

        const animate = typeof window.animateKpiValue === 'function';

        if (animate) {
            window.animateKpiValue(document.getElementById('kpiTotal'), stats.total_users || 0);
            window.animateKpiValue(document.getElementById('kpiActive'), stats.active_users || 0);
            window.animateKpiValue(document.getElementById('kpiInactive'), stats.inactive_users || 0);
            window.animateKpiValue(document.getElementById('kpiTourists'), stats.tourist_users || 0);
            window.animateKpiValue(document.getElementById('kpiRecentlyAdded'), stats.recently_added || 0);
        } else {
            setText('kpiTotal', stats.total_users ?? '0');
            setText('kpiActive', stats.active_users ?? '0');
            setText('kpiInactive', stats.inactive_users ?? '0');
            setText('kpiTourists', stats.tourist_users ?? '0');
            setText('kpiRecentlyAdded', stats.recently_added ?? '0');
        }
    }

    // ── Render table 
    function renderUserRow(u) {
        const initials = getInitials(u.name);
        const color = getAvatarColor(u.id);
        const roleBadge = getRoleBadge(u.role);
        const statusBadge = u.status === 'active'
            ? `<span class="um-status-badge um-status-active"><span class="um-status-dot"></span>Active</span>`
            : (u.status === 'pending'
                ? `<span class="um-status-badge um-status-pending"><span class="um-status-dot"></span>Pending</span>`
                : `<span class="um-status-badge um-status-inactive"><span class="um-status-dot"></span>Inactive</span>`);

        const muniName = u.municipality?.name || '—';
        const created = formatDate(u.created_at);
        const lastLogin = formatDate(u.last_activity);

        return `
    <tr data-user-id="${u.id}">
        <td style="font-weight:600; color:var(--text-secondary);">${u.id}</td>
        <td>
            <span class="um-avatar" style="background:${color};">${initials}</span>
            ${escHtml(u.name)}
        </td>
        <td>${roleBadge}</td>
        <td style="font-size:13px;">${escHtml(u.email)}</td>
        <td style="font-size:13px; color:var(--text-secondary);">${escHtml(muniName)}</td>
        <td>${statusBadge}</td>
        <td style="font-size:12px; color:var(--text-muted);">${created}</td>
        <td style="font-size:12px; color:var(--text-muted);">${lastLogin}</td>
        <td style="position:relative; overflow:visible;">
            <div class="um-dropdown" style="position:relative; display:inline-block;">
                <button class="um-action-btn um-dropdown-toggle" style="padding:4px 8px;" onclick="toggleUmDropdown(event, ${u.id})">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <div class="um-dropdown-menu" id="um-dropdown-${u.id}" style="display:none; position:absolute; right:0; top:100%; margin-top:4px; background:white; border:1px solid #e0e0e0; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,0.15); min-width:160px; z-index:999; padding:4px 0;">
                    <button class="um-dropdown-item" style="width:100%; text-align:left; padding:8px 16px; border:none; background:none; cursor:pointer; display:flex; align-items:center; gap:8px; color:var(--text-secondary);"
                        onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background='transparent'"
                        onclick="openEditModal(${u.id}); closeAllUmDropdowns();">
                        <i class="fas fa-pen" style="width:16px;"></i> Edit User
                    </button>
                    <button class="um-dropdown-item" style="width:100%; text-align:left; padding:8px 16px; border:none; background:none; cursor:pointer; display:flex; align-items:center; gap:8px; color:var(--text-secondary);"
                        onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background='transparent'"
                        onclick="openResetPwModal(${u.id},'${escHtml(u.name)}'); closeAllUmDropdowns();">
                        <i class="fas fa-key" style="width:16px;"></i> Reset Password
                    </button>
                    ${u.status === 'active' ? `
                    <button class="um-dropdown-item danger" style="width:100%; text-align:left; padding:8px 16px; border:none; background:none; cursor:pointer; display:flex; align-items:center; gap:8px; color:#dc2626;"
                        onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='transparent'"
                        onclick="confirmToggleStatus(${u.id},'inactive','${escHtml(u.name)}'); closeAllUmDropdowns();">
                        <i class="fas fa-ban" style="width:16px;"></i> Deactivate
                    </button>
                    ` : `
                    <button class="um-dropdown-item success" style="width:100%; text-align:left; padding:8px 16px; border:none; background:none; cursor:pointer; display:flex; align-items:center; gap:8px; color:#16a34a;"
                        onmouseover="this.style.background='#f0fdf4'" onmouseout="this.style.background='transparent'"
                        onclick="confirmToggleStatus(${u.id},'active','${escHtml(u.name)}'); closeAllUmDropdowns();">
                        <i class="fas fa-check-circle" style="width:16px;"></i> Activate
                    </button>
                    `}
                    ${u.status !== 'archived' ? `
                    <button class="um-dropdown-item warning" style="width:100%; text-align:left; padding:8px 16px; border:none; background:none; cursor:pointer; display:flex; align-items:center; gap:8px; color:#d97706;"
                        onmouseover="this.style.background='#fffbeb'" onmouseout="this.style.background='transparent'"
                        onclick="confirmArchiveUser(${u.id},'${escHtml(u.name)}'); closeAllUmDropdowns();">
                        <i class="fas fa-archive" style="width:16px;"></i> Archive
                    </button>
                    ` : `
                    <button class="um-dropdown-item success" style="width:100%; text-align:left; padding:8px 16px; border:none; background:none; cursor:pointer; display:flex; align-items:center; gap:8px; color:#16a34a;"
                        onmouseover="this.style.background='#f0fdf4'" onmouseout="this.style.background='transparent'"
                        onclick="confirmRestoreUser(${u.id},'${escHtml(u.name)}'); closeAllUmDropdowns();">
                        <i class="fas fa-rotate-left" style="width:16px;"></i> Restore
                    </button>
                    `}
                </div>
            </div>
        </td>
    </tr>`;
    }

    function renderTable(users) {
        const tbody = document.getElementById('usersBody');
        if (!tbody) return;

        if (!users.length) {
            tbody.innerHTML = `
            <tr><td colspan="9" class="um-empty">
                <i class="fas fa-user-slash"></i>
                <p>No users match the current filters.</p>
            </td></tr>`;
            return;
        }

        tbody.innerHTML = users.map(u => renderUserRow(u)).join('');
    }

    // ── Sort 
    function sortBy(col) {
        if (_sortCol === col) {
            _sortDir = _sortDir === 'DESC' ? 'ASC' : 'DESC';
        } else {
            _sortCol = col;
            _sortDir = 'DESC';
        }
        _currentPage = 1;
        _lastHash = '';
        loadUsers();
    }

    function updateSortIcons() {
        ['id', 'name', 'email', 'created_at', 'last_activity'].forEach(col => {
            const el = document.getElementById(`sort-${col}`);
            if (!el) return;
            if (col === _sortCol) {
                el.className = _sortDir === 'DESC' ? 'fas fa-sort-down' : 'fas fa-sort-up';
                el.style.color = 'var(--lupto-primary)';
            } else {
                el.className = 'fas fa-sort';
                el.style.color = 'var(--text-muted)';
            }
        });
    }

    // ── Search / filter 
    function debouncedSearch() {
        clearTimeout(_searchTimer);
        _searchTimer = setTimeout(() => {
            _currentPage = 1;
            const h = _filterHash();
            if (h === _lastHash) return;
            _lastHash = h;
            loadUsers();
        }, 200);
    }

    function applyFilters() {
        _currentPage = 1;
        const h = _filterHash();
        if (h === _lastHash) return;
        _lastHash = h;
        loadUsers();
    }

    function clearFilters() {
        const s = document.getElementById('searchInput');
        const r = document.getElementById('roleFilter');
        const t = document.getElementById('statusFilter');
        if (s) s.value = '';
        if (r) r.value = '';
        if (t) t.value = '';
        _currentPage = 1;
        _lastHash = '';
        loadUsers();
    }

    // ── Pagination 
    function renderPagination(total, offset) {
        const info = document.getElementById('paginationInfo');
        const btns = document.getElementById('paginationBtns');
        if (!info || !btns) return;

        const from = total === 0 ? 0 : offset + 1;
        const to = Math.min(offset + PAGE_SIZE, total);
        info.textContent = total === 0 ? 'No results' : `Showing ${from}–${to} of ${total} user(s)`;

        const totalPages = Math.ceil(total / PAGE_SIZE);
        let html = `<button class="um-page-btn" onclick="goPage(${_currentPage - 1})" ${_currentPage === 1 ? 'disabled' : ''}>‹ Prev</button>`;

        const start = Math.max(1, _currentPage - 3);
        const end = Math.min(totalPages, start + 6);
        for (let p = start; p <= end; p++) {
            html += `<button class="um-page-btn ${p === _currentPage ? 'active' : ''}" onclick="goPage(${p})">${p}</button>`;
        }
        html += `<button class="um-page-btn" onclick="goPage(${_currentPage + 1})" ${_currentPage >= totalPages ? 'disabled' : ''}>Next ›</button>`;
        btns.innerHTML = html;
    }

    function goPage(p) {
        const total = Math.ceil(_totalRows / PAGE_SIZE);
        if (p < 1 || p > total) return;
        _currentPage = p;
        _lastHash = '';
        loadUsers();
    }

    function updateTableCount(shown, total) {
        const el = document.getElementById('tableCountLabel');
        if (el) el.textContent = `${total} user(s) found`;
    }

    // ── Optimistic UI helpers (avoid full re-fetch after save)
    function upsertUserInTable(user, isEdit) {
        const tbody = document.getElementById('usersBody');
        if (!tbody) return;

        if (isEdit) {
            const existing = tbody.querySelector(`tr[data-user-id="${user.id}"]`);
            if (existing) {
                existing.insertAdjacentHTML('afterend', renderUserRow(user));
                existing.remove();
            }
        } else {
            const emptyRow = tbody.querySelector('.um-empty');
            if (emptyRow) {
                tbody.innerHTML = renderUserRow(user);
            } else {
                const rowHtml = renderUserRow(user);
                const temp = document.createElement('tbody');
                temp.innerHTML = rowHtml;
                const tr = temp.querySelector('tr');
                if (tr) {
                    tr.classList.add('fd-new-row-animate');
                    tr.style.animation = 'slideFadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards';
                    tbody.insertBefore(tr, tbody.firstChild);
                }
                const rows = tbody.querySelectorAll('tr[data-user-id]');
                if (rows.length > PAGE_SIZE && _currentPage === 1) {
                    rows[rows.length - 1].remove();
                }
            }
            _totalRows++;
            updateTableCount(tbody.querySelectorAll('tr[data-user-id]').length, _totalRows);
            renderPagination(_totalRows, (_currentPage - 1) * PAGE_SIZE);
        }
    }

    function patchRoleStatsAfterSave(user, oldRole, oldStatus, isEdit) {
        if (isEdit && oldRole) {
            const old = _roleStats.find(r => r.role === oldRole);
            if (old) {
                old.cnt = Math.max(0, parseInt(old.cnt) - 1);
                if (oldStatus === 'active') old.active_cnt = Math.max(0, parseInt(old.active_cnt) - 1);
            }
        }
        let stat = _roleStats.find(r => r.role === user.role);
        if (stat) {
            stat.cnt = parseInt(stat.cnt) + 1;
            if (user.status === 'active') stat.active_cnt = parseInt(stat.active_cnt) + 1;
        } else {
            _roleStats.push({ role: user.role, cnt: 1, active_cnt: user.status === 'active' ? 1 : 0 });
        }
        updateKpis(_roleStats);
    }

    function patchRoleStatsForToggle(role, becameActive) {
        const stat = _roleStats.find(r => r.role === role);
        if (stat) {
            if (becameActive) {
                stat.active_cnt = Math.min(parseInt(stat.cnt), parseInt(stat.active_cnt) + 1);
            } else {
                stat.active_cnt = Math.max(0, parseInt(stat.active_cnt) - 1);
            }
        }
        updateKpis(_roleStats);
    }

    function updateStatusRow(userId, newStatus) {
        const row = document.querySelector(`tr[data-user-id="${userId}"]`);
        if (!row) return;
        const td = row.querySelectorAll('td')[5];
        if (!td) return;
        if (newStatus === 'active') {
            td.innerHTML = '<span class="um-status-badge um-status-active"><span class="um-status-dot"></span>Active</span>';
        } else {
            td.innerHTML = '<span class="um-status-badge um-status-inactive"><span class="um-status-dot"></span>Inactive</span>';
        }
        const dropdown = row.querySelector('.um-dropdown-menu');
        if (!dropdown) return;
        const name = row.querySelectorAll('td')[1]?.textContent?.trim() || '';
        if (newStatus === 'active') {
            dropdown.querySelector('.success')?.remove();
            const deactivateBtn = document.createElement('button');
            deactivateBtn.className = 'um-dropdown-item danger';
            deactivateBtn.style.cssText = 'width:100%; text-align:left; padding:8px 16px; border:none; background:none; cursor:pointer; display:flex; align-items:center; gap:8px; color:#dc2626;';
            deactivateBtn.innerHTML = '<i class="fas fa-ban" style="width:16px;"></i> Deactivate';
            deactivateBtn.setAttribute('onclick', `confirmToggleStatus(${userId},'inactive','${escHtml(name)}'); closeAllUmDropdowns();`);
            deactivateBtn.setAttribute('onmouseover', "this.style.background='#fef2f2'");
            deactivateBtn.setAttribute('onmouseout', "this.style.background='transparent'");
            dropdown.appendChild(deactivateBtn);
        } else {
            dropdown.querySelector('.danger')?.remove();
            const activateBtn = document.createElement('button');
            activateBtn.className = 'um-dropdown-item success';
            activateBtn.style.cssText = 'width:100%; text-align:left; padding:8px 16px; border:none; background:none; cursor:pointer; display:flex; align-items:center; gap:8px; color:#16a34a;';
            activateBtn.innerHTML = '<i class="fas fa-check-circle" style="width:16px;"></i> Activate';
            activateBtn.setAttribute('onclick', `confirmToggleStatus(${userId},'active','${escHtml(name)}'); closeAllUmDropdowns();`);
            activateBtn.setAttribute('onmouseover', "this.style.background='#f0fdf4'");
            activateBtn.setAttribute('onmouseout', "this.style.background='transparent'");
            dropdown.appendChild(activateBtn);
        }
    }

    // ── ADD USER MODAL
    function openAddModal() {
        resetForm();
        setText('formModalTitleText', 'Add New User');
        setText('formSubmitText', 'Create User');
        document.getElementById('formPassword').value = 'PASSWORD123';
        document.getElementById('formStatus').value = 'active';
        showModal('userFormModal');
    }

    // ── EDIT USER MODAL 
    async function openEditModal(id) {
        resetForm();
        setText('formModalTitleText', 'Edit User');
        setText('formSubmitText', 'Save Changes');
        showModal('userFormModal');

        try {
            const data = await apiFetch('get_user', { id });
            const u = data.user;

            _editingUserRole = u.role;
            _editingUserStatus = u.status;

            document.getElementById('formUserId').value = u.id;
            document.getElementById('formName').value = u.name;
            document.getElementById('formEmail').value = u.email;
            document.getElementById('formRole').value = u.role;
            document.getElementById('formStatus').value = u.status;
            if (u.municipality_id) {
                document.getElementById('formMunicipality').value = u.municipality_id;
            }
            document.getElementById('formPassword').value = '';
            onRoleChange();

        } catch (err) {
            closeFormModal();
            showToast('error', 'Failed to load user: ' + err.message);
        }
    }

    function onRoleChange() {
        const role = document.getElementById('formRole')?.value || '';
        const muniGroup = document.getElementById('muniGroup');
        if (!muniGroup) return;
        const isMunicipal = role === 'municipal';
        muniGroup.style.display = isMunicipal ? '' : 'none';
        if (isMunicipal && _municipalities.length === 0) {
            loadMunicipalities();
        }
    }

    async function submitUserForm() {
        const id = document.getElementById('formUserId').value;
        const isEdit = !!id;
        const name = document.getElementById('formName').value.trim();
        const email = document.getElementById('formEmail').value.trim();
        const role = document.getElementById('formRole').value;
        const status = document.getElementById('formStatus').value;
        const muniId = document.getElementById('formMunicipality').value || null;
        const password = document.getElementById('formPassword').value;

        if (!name) { focusError('formName', 'Full name is required.'); return; }
        if (!email) { focusError('formEmail', 'Email is required.'); return; }
        if (!role) { focusError('formRole', 'Role is required.'); return; }
        if (!isEdit && password.length < 6) { focusError('formPassword', 'Password must be at least 6 characters.'); return; }

        const body = { name, email, role, status, municipality_id: muniId };
        if (!isEdit || password) body.password = password;
        if (isEdit) body.id = parseInt(id, 10);

        _pendingSave = { isEdit, id, body, oldRole: _editingUserRole, oldStatus: _editingUserStatus };
        document.getElementById('umSaveConfirmText').textContent = isEdit
            ? `Are you sure you want to update this user?`
            : `Are you sure you want to add "${name}"?`;
        showModal('umSaveConfirmModal');
    }

    async function confirmSaveUser() {
        if (!_pendingSave) return;
        const { isEdit, id, body, oldRole, oldStatus } = _pendingSave;

        const confirmBtn = document.getElementById('umSaveConfirmBtn');
        const confirmIcon = document.getElementById('umSaveConfirmIcon');
        const confirmSpinner = document.getElementById('umSaveConfirmSpinner');
        const confirmLabel = document.getElementById('umSaveConfirmLabel');
        if (confirmBtn) { confirmBtn.disabled = true; if (confirmIcon) confirmIcon.style.display = 'none'; if (confirmSpinner) confirmSpinner.style.display = 'inline-block'; if (confirmLabel) confirmLabel.textContent = 'Saving...'; }

        try {
            const action = isEdit ? 'edit_user' : 'add_user';
            const data = await apiFetch(action, isEdit ? { id } : {}, isEdit ? 'PUT' : 'POST', body);
            const savedUser = data.user;

            closeFormModal();
            hideModal('umSaveConfirmModal');
            showToast('success', isEdit ? 'User updated successfully.' : 'User created successfully.');

            if (savedUser) {
                upsertUserInTable(savedUser, isEdit);
            }
            await loadUsers();
            if (typeof window.notifyUserChanged === 'function') {
                window.notifyUserChanged();
            }
            _editingUserRole = null;
            _editingUserStatus = null;
            _pendingSave = null;
        } catch (err) {
            showToast('error', err.message);
        } finally {
            if (confirmBtn) { confirmBtn.disabled = false; if (confirmIcon) confirmIcon.style.display = 'inline-block'; if (confirmSpinner) confirmSpinner.style.display = 'none'; if (confirmLabel) confirmLabel.textContent = 'Yes, Save'; }
        }
    }

    function closeSaveConfirm() {
        hideModal('umSaveConfirmModal');
        _pendingSave = null;
    }

    function closeFormModal() { hideModal('userFormModal'); resetForm(); }

    function resetForm() {
        ['formUserId', 'formName', 'formEmail'].forEach(id => {
            const el = document.getElementById(id);
            if (el) { el.value = ''; el.classList.remove('error'); }
        });
        const pw = document.getElementById('formPassword');
        if (pw) { pw.value = 'PASSWORD123'; }
        const role = document.getElementById('formRole');
        const status = document.getElementById('formStatus');
        const muni = document.getElementById('formMunicipality');
        if (role) { role.value = ''; role.classList.remove('error'); }
        if (status) { status.value = 'active'; }
        if (muni) { muni.value = ''; }
        const muniGroup = document.getElementById('muniGroup');
        if (muniGroup) muniGroup.style.display = 'none';
    }

    // ── ARCHIVE USER
    function confirmArchiveUser(id, name) {
        const iconEl = document.getElementById('confirmIcon');
        if (iconEl) {
            iconEl.className = 'um-confirm-icon warn';
            iconEl.innerHTML = '<i class="fas fa-archive"></i>';
        }
        const textEl = document.getElementById('confirmText');
        if (textEl) {
            textEl.innerHTML = `Are you sure you want to <strong>archive</strong> the account of <strong>${escHtml(name)}</strong>?`;
        }

        _confirmCallback = async () => {
            try {
                await apiFetch('archive_user', { id }, 'PATCH');
                await loadUsers();
                if (typeof window.notifyUserChanged === 'function') {
                    window.notifyUserChanged();
                }
                showToast('success', 'User archived successfully.');
            } catch (err) {
                showToast('error', err.message);
            }
        };

        showModal('confirmModal');
    }

    // ── RESTORE USER
    function confirmRestoreUser(id, name) {
        const iconEl = document.getElementById('confirmIcon');
        if (iconEl) {
            iconEl.className = 'um-confirm-icon info';
            iconEl.innerHTML = '<i class="fas fa-rotate-left"></i>';
        }
        const textEl = document.getElementById('confirmText');
        if (textEl) {
            textEl.innerHTML = `Are you sure you want to <strong>restore</strong> the account of <strong>${escHtml(name)}</strong>?`;
        }

        _confirmCallback = async () => {
            try {
                await apiFetch('restore_user', { id }, 'PATCH');
                await loadUsers();
                if (typeof window.notifyUserChanged === 'function') {
                    window.notifyUserChanged();
                }
                showToast('success', 'User restored successfully.');
            } catch (err) {
                showToast('error', err.message);
            }
        };

        showModal('confirmModal');
    }

    // ── DELETE USER
    function confirmDeleteUser(id, name) {
        const iconEl = document.getElementById('confirmIcon');
        if (iconEl) {
            iconEl.className = 'um-confirm-icon danger';
            iconEl.innerHTML = '<i class="fas fa-trash"></i>';
        }
        const textEl = document.getElementById('confirmText');
        if (textEl) {
            textEl.innerHTML = `Are you sure you want to <strong>permanently delete</strong> the account of <strong>${escHtml(name)}</strong>? This action cannot be undone.`;
        }

        _confirmCallback = async () => {
            try {
                await apiFetch('delete_user', { id }, 'DELETE');
                const row = document.querySelector(`tr[data-user-id="${id}"]`);
                if (row) {
                    row.style.transition = 'opacity 0.3s, transform 0.3s';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-20px)';
                    setTimeout(() => {
                        row.remove();
                        _totalRows--;
                        updateTableCount(document.querySelectorAll('tr[data-user-id]').length, _totalRows);
                    }, 300);
                }
                await loadUsers();
                if (typeof window.notifyUserChanged === 'function') {
                    window.notifyUserChanged();
                }
                showToast('success', 'User deleted permanently.');
            } catch (err) {
                showToast('error', err.message);
            }
        };

        showModal('confirmModal');
    }

    // ── RESET PASSWORD MODAL 
    function openResetPwModal(id, name) {
        document.getElementById('resetPwUserId').value = id;
        document.getElementById('resetPwUserName').textContent = name;
        document.getElementById('resetPwInput').value = '';
        showModal('resetPwModal');
    }

    async function submitResetPassword() {
        const id = parseInt(document.getElementById('resetPwUserId').value, 10);
        const pwd = document.getElementById('resetPwInput').value;

        if (!id) { showToast('error', 'No user selected.'); return; }
        if (pwd.length < 6) { focusError('resetPwInput', 'Password must be at least 6 characters.'); return; }

        try {
            await apiFetch('reset_password', { id }, 'PATCH', { password: pwd });
            closeResetPwModal();
            showToast('success', 'Password reset successfully.');
        } catch (err) {
            showToast('error', err.message);
        }
    }

    function closeResetPwModal() { hideModal('resetPwModal'); }

    // ── TOGGLE STATUS 
    function confirmToggleStatus(id, newStatus, name) {
        const verb = newStatus === 'active' ? 'activate' : 'deactivate';
        const icon = newStatus === 'active' ? 'info' : 'warn';
        const iconEl = document.getElementById('confirmIcon');

        iconEl.className = `um-confirm-icon ${icon}`;
        iconEl.innerHTML = newStatus === 'active'
            ? '<i class="fas fa-check-circle"></i>'
            : '<i class="fas fa-ban"></i>';

        document.getElementById('confirmText').innerHTML =
            `Are you sure you want to <strong>${verb}</strong> the account of <strong>${escHtml(name)}</strong>?`;

        _confirmCallback = async () => {
            try {
                const data = await apiFetch('toggle_status', { id }, 'PATCH', { status: newStatus });
                updateStatusRow(id, newStatus);
                await loadUsers();
                if (typeof window.notifyUserChanged === 'function') {
                    window.notifyUserChanged();
                }
                showToast('success', `Account ${verb}d successfully.`);
            } catch (err) {
                showToast('error', err.message);
            }
        };

        showModal('confirmModal');
    }

    function confirmOk() {
        closeConfirmModal();
        if (_confirmCallback) { _confirmCallback(); _confirmCallback = null; }
    }
    function closeConfirmModal() { hideModal('confirmModal'); }

    // ── AUDIT LOG MODAL 
    async function openAuditLog() {
        const body = document.getElementById('auditLogBody');
        if (body) body.innerHTML = '<div class="um-empty"><i class="fas fa-spinner fa-spin"></i><p>Loading…</p></div>';
        showModal('auditModal');

        try {
            const data = await apiFetch('get_audit_logs');
            const logs = data.logs || [];

            if (!logs.length) {
                body.innerHTML = '<div class="um-empty"><i class="fas fa-clipboard-list"></i><p>No audit entries yet.</p></div>';
                return;
            }

            body.innerHTML = logs.map(l => `
            <div class="um-audit-item">
                <div class="um-audit-dot"></div>
                <div>
                    <div class="um-audit-msg">${escHtml(l.message)}</div>
                    <div class="um-audit-time"><i class="fas fa-clock"></i> ${formatDate(l.created_at)}</div>
                </div>
            </div>`).join('');
        } catch (err) {
            if (body) body.innerHTML = `<div class="um-empty"><i class="fas fa-exclamation-circle" style="color:#ef4444;"></i><p>${escHtml(err.message)}</p></div>`;
        }
    }
    function closeAuditModal() { hideModal('auditModal'); }

    // ── Toast notifications 
    function showToast(type, message) {
        const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', info: 'fa-info-circle' };
        const container = document.getElementById('umToastContainer');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `um-toast ${type}`;
        toast.innerHTML = `
        <i class="fas ${icons[type] || icons.info} um-toast-icon"></i>
        <span class="um-toast-msg">${escHtml(message)}</span>`;
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(16px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }

    // ── Modal helpers ─
    function showModal(id) {
        const el = document.getElementById(id);
        if (el) { el.style.display = 'flex'; el.setAttribute('aria-hidden', 'false'); }
    }
    function hideModal(id) {
        const el = document.getElementById(id);
        if (el) { el.style.display = 'none'; el.setAttribute('aria-hidden', 'true'); }
    }

    // Close modal and dropdown when clicking outside
    document.addEventListener('click', e => {
        // Close dropdowns if click is outside
        if (!e.target.closest('.um-dropdown')) {
            closeAllUmDropdowns();
        }

        ['userFormModal', 'resetPwModal', 'confirmModal', 'auditModal'].forEach(id => {
            const overlay = document.getElementById(id);
            if (overlay && e.target === overlay) hideModal(id);
        });
    });

    // Close modal on Escape key
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        ['userFormModal', 'resetPwModal', 'confirmModal', 'auditModal'].forEach(id => hideModal(id));
    });

    // ── Form helpers 
    function focusError(inputId, message) {
        const el = document.getElementById(inputId);
        if (el) { el.classList.add('error'); el.focus(); }
        showToast('error', message);
    }

    function setSubmitLoading(loading) {
        const btn = document.getElementById('formSubmitBtn');
        const text = document.getElementById('formSubmitText');
        if (!btn) return;
        btn.disabled = loading;
        if (text) text.textContent = loading ? 'Saving…' : 'Save User';
    }

    function togglePwVis(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        const icon = btn.querySelector('i');
        if (icon) icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
    }

    // ── Helpers 
    function getRoleBadge(role) {
        if (role === 'picto') return `<span class="um-role-badge um-role-picto">PICTO</span>`;
        if (role === 'lupto') return `<span class="um-role-badge um-role-lupto">LUPTO</span>`;
        if (role === 'tourist') return `<span class="um-role-badge um-role-tourist">Tourist</span>`;
        if (role.endsWith('_mto') || role === 'municipal')
            return `<span class="um-role-badge um-role-mto">${escHtml(role.replace('_mto', '').replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase()))} MTO</span>`;
        return `<span class="um-role-badge um-role-other">${escHtml(role)}</span>`;
    }

    function getInitials(name) {
        if (!name) return '?';
        const parts = name.trim().split(/\s+/);
        return parts.length === 1
            ? parts[0][0].toUpperCase()
            : (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    function getAvatarColor(id) {
        const palette = ['#1a5276', '#1e8449', '#b7950b', '#7d3c98', '#1a6688', '#a04000', '#1f618d', '#196f3d', '#6e2f8c', '#2e86c1'];
        return palette[(id || 0) % palette.length];
    }

    function formatDate(dt) {
        if (!dt) return '—';
        try {
            return new Date(dt).toLocaleDateString('en-PH', {
                year: 'numeric', month: 'short', day: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });
        } catch (_) { return dt; }
    }

    function escHtml(str) {
        if (str == null) return '';
        const d = document.createElement('div');
        d.textContent = String(str);
        return d.innerHTML;
    }

    function setText(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    window.openAddModal = openAddModal;
    window.openEditModal = openEditModal;
    window.closeFormModal = closeFormModal;
    window.submitUserForm = submitUserForm;
    window.onRoleChange = onRoleChange;
    window.clearFilters = clearFilters;
    window.sortBy = sortBy;
    window.refreshTable = refreshTable;
    window.debouncedSearch = debouncedSearch;
    window.applyFilters = applyFilters;
    window.openAuditLog = openAuditLog;
    window.closeAuditModal = closeAuditModal;
    window.openResetPwModal = openResetPwModal;
    window.closeResetPwModal = closeResetPwModal;
    window.submitResetPassword = submitResetPassword;
    window.confirmToggleStatus = confirmToggleStatus;
    window.confirmOk = confirmOk;
    window.closeConfirmModal = closeConfirmModal;
    window.togglePwVis = togglePwVis;
    window.toggleUmDropdown = toggleUmDropdown;
    window.closeAllUmDropdowns = closeAllUmDropdowns;
    window.upsertUserInTable = upsertUserInTable;
    window.patchRoleStatsAfterSave = patchRoleStatsAfterSave;
    window.patchRoleStatsForToggle = patchRoleStatsForToggle;
    window.updateStatusRow = updateStatusRow;
    window.getRoleBadge = getRoleBadge;
    window.goPage = goPage;
    window.showToast = showToast;
    window.confirmSaveUser = confirmSaveUser;
    window.closeSaveConfirm = closeSaveConfirm;
    window.confirmArchiveUser = confirmArchiveUser;
    window.confirmRestoreUser = confirmRestoreUser;
    window.confirmDeleteUser = confirmDeleteUser;

})();
