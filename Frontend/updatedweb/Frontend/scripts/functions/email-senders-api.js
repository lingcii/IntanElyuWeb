(function () {
    'use strict';

    const API = window.ES_API_URL;
    let senders = [];

    // ── Toast ──
    function toast(msg, type) {
        const colors = { success: '#16A34A', danger: '#DC2626', info: '#2563EB' };
        const icons = { success: 'fa-check-circle', danger: 'fa-times-circle', info: 'fa-info-circle' };
        const el = document.createElement('div');
        el.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:99999;padding:14px 20px;border-radius:10px;font-size:14px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.2);display:flex;align-items:center;gap:10px;max-width:360px;animation:slideIn 0.3s ease;';
        el.style.background = colors[type] || '#1E293B';
        el.style.color = 'white';
        el.innerHTML = '<i class="fas ' + (icons[type] || 'fa-bell') + '"></i> ' + msg;
        document.body.appendChild(el);
        setTimeout(function () { el.style.opacity = '0'; el.style.transition = 'opacity 0.4s'; setTimeout(function () { el.remove(); }, 400); }, 3000);
    }

    // ── API Helpers ──
    async function apiGet() {
        const r = await fetch(API, { credentials: 'same-origin' });
        return r.json();
    }

    async function apiPost(body) {
        const r = await fetch(API, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin', body: JSON.stringify(body),
        });
        return r.json();
    }

    async function apiPut(id, body) {
        const r = await fetch(API + '?id=' + id, {
            method: 'PUT', headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin', body: JSON.stringify(body),
        });
        return r.json();
    }

    async function apiDelete(id) {
        const r = await fetch(API + '?id=' + id, { method: 'DELETE', credentials: 'same-origin' });
        return r.json();
    }

    async function apiAction(action, body) {
        const r = await fetch(API + '?action=' + action, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin', body: JSON.stringify(body || {}),
        });
        return r.json();
    }

    // ── Render ──
    function render() {
        const grid = document.getElementById('esGrid');
        const empty = document.getElementById('esEmpty');

        const activeSenders = senders.filter(function (s) { return s.is_active; });
        const totalSent = senders.reduce(function (sum, s) { return sum + s.emails_sent; }, 0);

        document.getElementById('esStatTotal').textContent = senders.length;
        document.getElementById('esStatActive').textContent = activeSenders.length;
        document.getElementById('esStatSent').textContent = totalSent;

        if (senders.length === 0) {
            grid.innerHTML = '';
            grid.appendChild(empty);
            empty.style.display = '';
            return;
        }

        empty.style.display = 'none';
        grid.innerHTML = senders.map(function (s) {
            return '<div class="es-card' + (s.is_active ? '' : ' inactive') + '">' +
                (s.is_default ? '<div class="es-card-default-badge">Default</div>' : '') +
                '<div class="es-card-status ' + (s.is_active ? 'active' : 'disabled') + '">' +
                (s.is_active ? 'Active' : 'Disabled') + '</div>' +
                '<div class="es-card-email">' + esc(s.email) + '</div>' +
                '<div class="es-card-name">' + esc(s.name || 'Unnamed Sender') + '</div>' +
                '<div class="es-card-meta">' +
                    '<span><i class="fas fa-sort-numeric-down"></i> Priority: ' + s.priority + '</span>' +
                    '<span><i class="fas fa-paper-plane"></i> Sent: ' + s.emails_sent + '</span>' +
                '</div>' +
                '<div class="es-card-actions">' +
                    '<button class="es-card-btn primary" onclick="ES_edit(' + s.id + ')"><i class="fas fa-pen-to-square"></i> Edit</button>' +
                    '<button class="es-card-btn" onclick="ES_toggle(' + s.id + ')">' +
                        '<i class="fas ' + (s.is_active ? 'fa-pause' : 'fa-play') + '"></i> ' + (s.is_active ? 'Disable' : 'Enable') +
                    '</button>' +
                    '<button class="es-card-btn danger" onclick="ES_delete(' + s.id + ', \'' + esc(s.email) + '\')"><i class="fas fa-trash"></i> Remove</button>' +
                '</div>' +
            '</div>';
        }).join('');
    }

    function esc(str) { return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }

    // ── Load Logs ──
    async function loadLogs() {
        try {
            const r = await fetch(API.replace('email_senders', 'email_service') + '?action=logs', { credentials: 'same-origin' });
            const d = await r.json();
            const tbody = document.getElementById('esLogsBody');
            if (d.success && d.logs && d.logs.length) {
                tbody.innerHTML = d.logs.map(function (l) {
                    return '<tr><td>' + esc(l.sender_email || '-') + '</td><td>' + esc(l.recipient) + '</td><td>' + esc(l.subject) + '</td><td><span class="es-log-status ' + l.status + '">' + l.status + '</span></td><td>' + esc(l.method) + '</td><td>' + esc(l.created_at) + '</td></tr>';
                }).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#9CA3AF;">No email logs yet.</td></tr>';
            }
        } catch (_) {
            document.getElementById('esLogsBody').innerHTML = '<tr><td colspan="6" style="text-align:center;color:#9CA3AF;">Could not load logs.</td></tr>';
        }
    }

    // ── Form ──
    function openForm(id) {
        document.getElementById('esFormError').style.display = 'none';
        if (id) {
            var s = senders.find(function (x) { return x.id === id; });
            if (!s) return;
            document.getElementById('esFormTitle').textContent = 'Edit Sender Account';
            document.getElementById('esSubmitLabel').textContent = 'Save Changes';
            document.getElementById('esId').value = s.id;
            document.getElementById('esName').value = s.name;
            document.getElementById('esEmail').value = s.email;
            document.getElementById('esPassword').value = '';
            document.getElementById('esPriority').value = s.priority;
            document.getElementById('esIsDefault').checked = s.is_default;
        } else {
            document.getElementById('esFormTitle').textContent = 'Add Sender Account';
            document.getElementById('esSubmitLabel').textContent = 'Save Sender';
            document.getElementById('esId').value = '';
            document.getElementById('esName').value = '';
            document.getElementById('esEmail').value = '';
            document.getElementById('esPassword').value = '';
            document.getElementById('esPriority').value = '10';
            document.getElementById('esIsDefault').checked = false;
        }
        document.getElementById('esFormModal').classList.add('active');
    }

    function closeForm() { document.getElementById('esFormModal').classList.remove('active'); }

    document.getElementById('esForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        var id = document.getElementById('esId').value;
        var error = document.getElementById('esFormError');
        error.style.display = 'none';

        var body = {
            name: document.getElementById('esName').value.trim(),
            email: document.getElementById('esEmail').value.trim(),
            app_password: document.getElementById('esPassword').value,
            priority: parseInt(document.getElementById('esPriority').value) || 10,
            is_default: document.getElementById('esIsDefault').checked,
        };

        if (!body.email) { error.textContent = 'Email is required.'; error.style.display = 'block'; return; }
        if (!id && !body.app_password) { error.textContent = 'App Password is required for new senders.'; error.style.display = 'block'; return; }

        var result;
        if (id) result = await apiPut(parseInt(id), body);
        else result = await apiPost(body);

        if (result.success) {
            toast(id ? 'Sender updated.' : 'Sender added.', 'success');
            closeForm();
            refresh();
        } else {
            error.textContent = result.message || 'Failed to save.';
            error.style.display = 'block';
        }
    });

    document.getElementById('esFormClose').addEventListener('click', closeForm);
    document.getElementById('esFormModal').addEventListener('click', function (e) { if (e.target.id === 'esFormModal') closeForm(); });

    // ── Test Connection ──
    document.getElementById('esTestBtn').addEventListener('click', async function () {
        var email = document.getElementById('esEmail').value.trim();
        var password = document.getElementById('esPassword').value;
        var error = document.getElementById('esFormError');
        error.style.display = 'none';

        if (!email || !password) {
            error.textContent = 'Email and App Password required to test.';
            error.style.display = 'block';
            return;
        }

        var btn = document.getElementById('esTestBtn');
        btn.disabled = true;
        var orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Testing...';

        var result = await apiAction('test', { email: email, app_password: password });
        btn.disabled = false;
        btn.innerHTML = orig;

        if (result.success) {
            toast('Connection successful! Credentials are valid.', 'success');
        } else {
            error.textContent = 'Connection failed: ' + (result.message || 'Invalid credentials or network error.');
            error.style.display = 'block';
        }
    });

    // ── Global Actions ──
    window.ES_toggle = async function (id) {
        await apiAction('toggle', { id: id });
        refresh();
    };

    window.ES_delete = async function (id, email) {
        if (!confirm('Remove sender "' + email + '"?\n\nThis sender will no longer be available for sending emails.')) return;
        await apiDelete(id);
        toast('Sender removed.', 'success');
        refresh();
    };

    window.ES_edit = function (id) { openForm(id); };

    // ── Strategy ──
    document.getElementById('esStrategy').addEventListener('change', function () {
        sessionStorage.setItem('es_strategy', this.value);
        toast('Strategy updated to: ' + this.value.replace('_', ' '), 'info');
    });

    // ── Refresh ──
    async function refresh() {
        try {
            var data = await apiGet();
            if (data.success) { senders = data.senders || []; render(); }
        } catch (_) {}
        loadLogs();
    }

    // ── Init ──
    var savedStrategy = sessionStorage.getItem('es_strategy');
    if (savedStrategy) document.getElementById('esStrategy').value = savedStrategy;

    document.getElementById('esAddBtn').addEventListener('click', function () { openForm(null); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeForm(); });

    refresh();
})();
