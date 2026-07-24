
(function () {
    // Determine API prefix based on normalized role from window.userRole
    var role = (window.userRole || document.body?.dataset?.role || '').toLowerCase();
    var prefix;
    if (role === 'lupto') {
        prefix = 'lupto';
    } else if (role === 'picto' || role === 'pitco') {
        prefix = 'pitco';
    } else {
        prefix = 'municipal';
    }
    var baseUrl = window.API_CONFIG?.BASE_URL || 'http://localhost:8000';
    var API = baseUrl + '/api/' + prefix + '/notifications';
    var currentPage = 1;
    var perPage = 20;
    var searchTimer = null;

    function timeAgo(d) { if(!d)return'';var s=Math.floor((Date.now()-new Date(d))/1000);if(s<60)return'Just now';if(s<3600)return Math.floor(s/60)+' min ago';if(s<86400)return Math.floor(s/3600)+' hr ago';return Math.floor(s/86400)+' day(s) ago'; }
    function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    function loadPage(page) {
        currentPage = page;
        var type = document.getElementById('nc-filter-type').value;
        var isRead = document.getElementById('nc-filter-read').value;
        var search = document.getElementById('nc-filter-search').value;
        var params = '?per_page=' + perPage + '&page=' + page;
        if (type) params += '&type=' + encodeURIComponent(type);
        if (isRead) params += '&is_read=' + isRead;
        if (search) params += '&search=' + encodeURIComponent(search);

        window.API_CONFIG.get(API + params).then(function (data) {
            var badge = document.getElementById('nc-unread-badge');
            if (badge) badge.textContent = (data.unread_count||0) + ' unread';
            renderList(data.notifications || []);
            renderPagination(data.pagination || {});
        }).catch(function () {
            document.getElementById('nc-list').innerHTML = '<div class="nc-empty"><i class="fas fa-exclamation-circle"></i><p>Failed to load notifications</p></div>';
        });
    }

    function renderList(notifications) {
        var el = document.getElementById('nc-list');
        if (!notifications.length) {
            el.innerHTML = '<div class="nc-empty"><i class="fas fa-bell-slash"></i><p>No notifications found</p></div>';
            return;
        }
        var h = '';
        notifications.forEach(function (n) {
            var color = n.type_color || 'gray';
            var icon = n.type_icon || 'fa-bell';
            var typeLabel = (n.type||'').replace(/_/g,' ');
            h += '<div class="nc-card' + (n.is_read?'':' unread') + '" data-id="' + n.id + '" onclick="cardClick(this,' + n.id + ',\'' + esc(n.action_url||'') + '\')">' +
                '<div class="nc-dot"></div>' +
                '<div class="nc-icon ' + color + '"><i class="fas ' + icon + '"></i></div>' +
                '<div class="nc-body">' +
                '<div class="nc-type" style="color:' + ({blue:'#2563EB',green:'#059669',red:'#DC2626',orange:'#D97706',purple:'#7C3AED',gray:'#64748B',yellow:'#A16207'}[color]||'#64748B') + '">' + esc(typeLabel) + '</div>' +
                '<div class="nc-title">' + esc(n.title||'') + '</div>' +
                '<div class="nc-msg">' + esc(n.message||'') + '</div>' +
                '<div class="nc-meta">' +
                (n.actor_name?'<span><i class="fas fa-user-circle"></i> '+esc(n.actor_name)+'</span>':'') +
                (n.municipality_name?'<span><i class="fas fa-map-pin"></i> '+esc(n.municipality_name)+'</span>':'') +
                (n.spot_name?'<span><i class="fas fa-location-dot"></i> '+esc(n.spot_name)+'</span>':'') +
                '<span><i class="far fa-clock"></i> ' + timeAgo(n.created_at) + '</span>' +
                '</div></div>' +
                '<div class="nc-actions-cell">' +
                '<button title="Delete" onclick="event.stopPropagation();delNotif('+n.id+')"><i class="fas fa-trash"></i></button>' +
                '</div>' +
                '<div class="nc-time">' + timeAgo(n.created_at) + '</div>' +
                '</div>';
        });
        el.innerHTML = h;
    }

    function renderPagination(p) {
        var el = document.getElementById('nc-pagination');
        if (!p || p.last_page <= 1) { el.innerHTML = ''; return; }
        var h = '';
        h += '<button ' + (p.current_page <= 1 ? 'disabled' : 'onclick="loadPage(' + (p.current_page-1) + ')"') + '><i class="fas fa-chevron-left"></i></button>';
        for (var i = 1; i <= p.last_page; i++) {
            h += '<button class="' + (i === p.current_page ? 'active' : '') + '" onclick="loadPage(' + i + ')">' + i + '</button>';
        }
        h += '<button ' + (p.current_page >= p.last_page ? 'disabled' : 'onclick="loadPage(' + (p.current_page+1) + ')"') + '><i class="fas fa-chevron-right"></i></button>';
        el.innerHTML = h;
    }

    window.cardClick = function (el, id, url) {
        el.classList.remove('unread');
        var dot = el.querySelector('.nc-dot');
        if (dot) dot.style.display = 'none';
        window.API_CONFIG.patch(API + '/' + id + '/read', {}).then(function () { loadPage(currentPage); }).catch(function () {});
        if (url && url.endsWith('.php')) { window.location.href = url; }
    };

    window.delNotif = function (id) {
        window.API_CONFIG.delete(API + '/' + id).then(function () { loadPage(currentPage); }).catch(function () {});
    };

    window.markAllRead = function () {
        window.API_CONFIG.patch(API + '/read-all', {}).then(function () {
            showToast('All notifications marked as read');
            loadPage(currentPage);
        }).catch(function () {});
    };

    window.clearAllNotifs = function () {
        window.API_CONFIG.delete(API + '/clear-all').then(function () {
            showToast('All notifications cleared');
            loadPage(1);
        }).catch(function () {});
    };

    window.loadPage = loadPage;

    window.debounceSearch = function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { loadPage(1); }, 400);
    };

    function showToast(msg) {
        var t = document.getElementById('nc-toast');
        if (!t) return;
        t.textContent = msg;
        t.classList.add('show');
        setTimeout(function () { t.classList.remove('show'); }, 2500);
    }

    loadPage(1);
})();
