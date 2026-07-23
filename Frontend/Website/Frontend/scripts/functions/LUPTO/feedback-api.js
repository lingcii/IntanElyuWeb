(function () {
    'use strict';

    if (window.__luptoFeedbackLoaded) {
        if (typeof window.initFeedbackModule === 'function') window.initFeedbackModule();
        return;
    }
    window.__luptoFeedbackLoaded = true;

    const BASE = window.API_CONFIG?.LUPTO || 'http://127.0.0.1:8000/api/lupto';

    let currentView   = 'gallery';
    let galleryPage   = 1;
    let tablePage     = 1;
    let detailPage    = 1;
    let detailSpotId  = null;
    let charts        = {};
    let searchTimeout = null;
    let galleryData   = { municipalities: [], categories: [] };
    let detailSort    = 'newest';

    function imgUrl(path) {
        if (!path) return null;
        if (/^https?:\/\//i.test(path)) return path;
        if (path.startsWith('/api/serve-image')) return (window.API_CONFIG?.BASE_URL || '') + path;
        return (window.API_CONFIG?.BASE_URL || '') + '/storage/' + path;
    }

    function renderStars(rating) {
        const r = Math.round(parseFloat(rating) || 0);
        let html = '<span class="fb-stars">';
        for (let i = 1; i <= 5; i++) html += `<i class="fas fa-star fb-star${i > r ? ' empty' : ''}"></i>`;
        return html + '</span>';
    }

    function avatarHtml(name, avatarPath, size = 36) {
        const url = imgUrl(avatarPath);
        if (url) return `<img src="${escHtml(url)}" class="fb-reviewer-avatar" style="width:${size}px;height:${size}px;" onerror="this.style.display='none';this.nextSibling.style.display='flex';" alt="${escHtml(name)}"><span class="fb-reviewer-avatar-placeholder" style="width:${size}px;height:${size}px;display:none;">${escHtml((name||'A')[0].toUpperCase())}</span>`;
        return `<span class="fb-reviewer-avatar-placeholder" style="width:${size}px;height:${size}px;">${escHtml((name||'A')[0].toUpperCase())}</span>`;
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function ratingBadge(r) {
        const n = parseInt(r) || 0;
        return `<span class="fb-rating-badge star-${n}"><i class="fas fa-star"></i> ${n}</span>`;
    }

    function skeletonGallery(n = 8) {
        return Array.from({length: n}, () => `<div class="fb-skeleton-card"><div class="fb-skeleton fb-skeleton-img"></div><div style="padding:14px 16px;"><div class="fb-skeleton fb-skeleton-text w80"></div><div class="fb-skeleton fb-skeleton-text w40" style="margin-top:10px;"></div><div class="fb-skeleton fb-skeleton-text w60" style="margin-top:14px;"></div></div></div>`).join('');
    }

    const apiCacheMap = new Map();
    async function apiFetch(url, useCache = true) {
        if (useCache && apiCacheMap.has(url)) {
            const entry = apiCacheMap.get(url);
            if (Date.now() - entry.time < 30000) return entry.data;
        }
        const res = await fetch(url, { credentials: 'include', headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        if (useCache) apiCacheMap.set(url, { time: Date.now(), data });
        return data;
    }

    async function loadDashboardStats(silent = false) {
        const tab = document.getElementById('spa-tab-feedback.php') || document;
        if (!silent) tab.querySelectorAll('[data-fb-kpi]').forEach(el => { el.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:12px;color:#9CA3AF;"></i>'; });
        try {
            const d = await apiFetch(`${BASE}/feedback/dashboard-stats`);
            renderKpis(tab, d.stats);
            renderCharts(tab, d);
        } catch(e) {
            if (!silent) renderKpis(tab, { spots_reviewed:0, total_feedback:0, avg_rating:0, five_star:0, four_star:0, three_star:0, two_star:0, one_star:0 });
        }
    }

    function renderKpis(tab, stats) {
        const set = (k, v) => { const el = tab.querySelector(`[data-fb-kpi="${k}"]`); if (el) el.textContent = v; };
        set('spots_reviewed', (stats.spots_reviewed||0).toLocaleString());
        set('total_feedback', (stats.total_feedback||0).toLocaleString());
        set('avg_rating',     (stats.avg_rating||0).toFixed(1));
        set('five_star',      (stats.five_star||0).toLocaleString());
        set('four_star',      (stats.four_star||0).toLocaleString());
        set('three_star',     (stats.three_star||0).toLocaleString());
        set('two_star',       (stats.two_star||0).toLocaleString());
        set('one_star',       (stats.one_star||0).toLocaleString());
    }

    function destroyChart(key) { if (charts[key]) { try { charts[key].destroy(); } catch(e){} delete charts[key]; } }

    function renderCharts(tab, d) {
        destroyChart('dist');
        const distCanvas = tab.querySelector('#fb-chart-distribution');
        if (distCanvas && window.Chart) {
            const br = d.rating_breakdown || {};
            charts.dist = new Chart(distCanvas, {
                type: 'doughnut',
                data: { labels: ['5 ★','4 ★','3 ★','2 ★','1 ★'], datasets: [{ data: [br[5]||0,br[4]||0,br[3]||0,br[2]||0,br[1]||0], backgroundColor: ['#059669','#0B5394','#F59E0B','#EA580C','#DC2626'], borderWidth: 2, borderColor: '#fff' }] },
                options: { cutout:'65%', responsive:true, maintainAspectRatio:true, plugins: { legend: { position:'right', labels: { font:{size:12}, padding:12 } } } }
            });
        }
        destroyChart('muni');
        const muniCanvas = tab.querySelector('#fb-chart-municipality');
        if (muniCanvas && window.Chart && d.municipality_comparison?.length) {
            const muni = d.municipality_comparison.slice(0,10);
            charts.muni = new Chart(muniCanvas, {
                type:'bar', data:{ labels:muni.map(m=>m.name), datasets:[{ label:'Average Rating', data:muni.map(m=>m.avg_rating), backgroundColor:muni.map((_,i)=>`hsla(${210+i*10},70%,${45+i*2}%,0.85)`), borderRadius:6, borderSkipped:false }] },
                options:{ indexAxis:'y', responsive:true, maintainAspectRatio:false, scales:{ x:{min:0,max:5,ticks:{font:{size:11}},grid:{color:'#f1f5f9'}}, y:{ticks:{font:{size:11}},grid:{display:false}} }, plugins:{legend:{display:false}} }
            });
        }
        destroyChart('trend');
        const trendCanvas = tab.querySelector('#fb-chart-monthly');
        if (trendCanvas && window.Chart && d.monthly_trend?.length) {
            const trend = d.monthly_trend;
            charts.trend = new Chart(trendCanvas, {
                type:'line',
                data:{ labels:trend.map(t=>t.month), datasets:[
                    { label:'Reviews', data:trend.map(t=>t.count), borderColor:'#0B5394', backgroundColor:'rgba(11,83,148,0.08)', fill:true, tension:0.4, pointRadius:4, yAxisID:'y' },
                    { label:'Avg Rating', data:trend.map(t=>t.avg_rating), borderColor:'#D97706', backgroundColor:'transparent', tension:0.4, pointRadius:4, borderDash:[5,4], yAxisID:'y1' }
                ]},
                options:{ responsive:true, maintainAspectRatio:false, scales:{ y:{position:'left',title:{display:true,text:'Reviews',font:{size:11}},grid:{color:'#f1f5f9'},ticks:{font:{size:11}}}, y1:{position:'right',title:{display:true,text:'Avg ★',font:{size:11}},min:0,max:5,grid:{display:false},ticks:{font:{size:11}}}, x:{grid:{display:false},ticks:{font:{size:11}}} }, plugins:{legend:{labels:{font:{size:12}}}} }
            });
        }
        renderTopList(tab,'#fb-top-rated-list',    d.top_rated_spots||[],   'avg_rating');
        renderTopList(tab,'#fb-most-reviewed-list', d.most_reviewed_spots||[],'total_reviews');
    }

    function renderTopList(tab, selector, items, metric) {
        const el = tab.querySelector(selector);
        if (!el) return;
        if (!items.length) { el.innerHTML = '<div class="fb-empty-state"><i class="fas fa-info-circle"></i><p>No data yet</p></div>'; return; }
        el.innerHTML = items.map((s,i) => `<div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f1f5f9;cursor:pointer;" onclick="window.openFeedbackSpotDetail(${s.id})"><span style="font-size:11px;font-weight:800;color:#94a3b8;width:18px;text-align:center;">${i+1}</span><div style="flex:1;min-width:0;"><div style="font-size:13px;font-weight:600;color:#073B6B;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escHtml(s.name)}</div><div style="font-size:11px;color:#94a3b8;">${escHtml(s.municipality||'')}</div></div><span style="font-size:13px;font-weight:700;color:#0B5394;flex-shrink:0;">${metric==='avg_rating'?`⭐ ${s.avg_rating.toFixed(2)}`:`${(s.total_reviews).toLocaleString()} <span style="font-weight:400;color:#94a3b8;font-size:11px;">reviews</span>`}</span></div>`).join('');
    }

    function getGalleryFilters() {
        return {
            search:       document.getElementById('fb-search-input')?.value.trim()||'',
            municipality: document.getElementById('fb-filter-municipality')?.value||'',
            category:     document.getElementById('fb-filter-category')?.value||'',
            min_rating:   document.getElementById('fb-filter-rating')?.value||'',
            sort:         document.getElementById('fb-sort-select')?.value||'most_reviewed',
        };
    }

    async function loadGallery(page = 1, silent = false) {
        galleryPage = page;
        const grid = document.getElementById('fb-gallery-grid');
        if (!grid) return;
        if (!silent && !grid.querySelector('.fb-spot-card')) {
            grid.innerHTML = skeletonGallery(15);
        }
        const params = new URLSearchParams({ page, per_page:15, ...getGalleryFilters() });
        try {
            const d = await apiFetch(`${BASE}/feedback/gallery?${params}`);
            galleryData.municipalities = d.municipalities||[];
            galleryData.categories     = d.categories||[];
            populateFilterDropdowns();
            renderGallery(grid, d);
        } catch(e) {
            if (!grid.querySelector('.fb-spot-card')) {
                grid.innerHTML = `<div class="fb-empty-state" style="grid-column:1/-1"><i class="fas fa-exclamation-circle"></i><h3>Failed to load</h3><p>Please refresh the page.</p></div>`;
            }
        }
    }

    function populateFilterDropdowns() {
        const tab = document.getElementById('spa-tab-feedback.php') || document;
        const muniSel = tab.querySelector('#fb-filter-municipality') || document.getElementById('fb-filter-municipality');
        if (muniSel && galleryData.municipalities?.length) {
            const currentVal = muniSel.value;
            const existing = new Set(Array.from(muniSel.options).map(o => o.value));
            galleryData.municipalities.forEach(m => {
                const val = String(m.id);
                if (!existing.has(val) && !existing.has(m.name)) {
                    muniSel.appendChild(new Option(m.name, m.id));
                }
            });
            if (currentVal) muniSel.value = currentVal;
        }
        const catSel = tab.querySelector('#fb-filter-category') || document.getElementById('fb-filter-category');
        if (catSel && galleryData.categories?.length) {
            const currentVal = catSel.value;
            const existing = new Set(Array.from(catSel.options).map(o => o.value));
            galleryData.categories.forEach(c => {
                if (!existing.has(c)) {
                    catSel.appendChild(new Option(c, c));
                }
            });
            if (currentVal) catSel.value = currentVal;
        }
    }

    function renderGallery(grid, d) {
        if (!d.data?.length) {
            grid.innerHTML = `<div class="fb-empty-state" style="grid-column:1/-1;"><i class="fas fa-comments"></i><h3>No Feedback Found</h3><p>No tourist spots have received reviews matching your filters.</p></div>`;
            renderPagination('fb-gallery-pagination', 0, 0, loadGallery);
            return;
        }
        grid.innerHTML = d.data.map(spot => {
            const img = imgUrl(spot.photo_url);
            const imgTag = img ? `<img src="${escHtml(img)}" alt="${escHtml(spot.name)}" loading="lazy" onerror="this.parentElement.innerHTML='<div class=\\'fb-spot-img-placeholder\\'><i class=\\'fas fa-mountain-sun\\'></i></div>';">` : `<div class="fb-spot-img-placeholder"><i class="fas fa-mountain-sun"></i></div>`;
            return `<div class="fb-spot-card" onclick="window.openFeedbackSpotDetail(${spot.id})" role="button" tabindex="0"><div class="fb-spot-img-wrap">${imgTag}<span class="fb-spot-category-badge">${escHtml(spot.category||'Spot')}</span></div><div class="fb-spot-card-body"><h3 class="fb-spot-name">${escHtml(spot.name)}</h3><div class="fb-spot-municipality"><i class="fas fa-location-dot"></i> ${escHtml(spot.municipality||'Unknown')}</div><div class="fb-spot-rating-row"><div>${renderStars(spot.avg_rating)}<span class="fb-avg-rating" style="margin-left:6px;">${(spot.avg_rating||0).toFixed(1)}</span></div><span class="fb-review-count">${(spot.total_reviews||0).toLocaleString()} reviews</span></div></div><div class="fb-spot-card-footer"><i class="fas fa-eye"></i> View Feedback</div></div>`;
        }).join('');
        renderPagination('fb-gallery-pagination', d.current_page, d.last_page, loadGallery);
    }

    function getTableFilters() {
        return {
            search:       document.getElementById('fb-search-input')?.value.trim()||'',
            municipality: document.getElementById('fb-filter-municipality')?.value||'',
            category:     document.getElementById('fb-filter-category')?.value||'',
            rating:       document.getElementById('fb-filter-rating')?.value||'',
            sort:         document.getElementById('fb-sort-select')?.value||'newest',
            date_from:    document.getElementById('fb-date-from')?.value||'',
            date_to:      document.getElementById('fb-date-to')?.value||'',
        };
    }

    async function loadTable(page = 1) {
        tablePage = page;
        const tbody = document.getElementById('fb-table-body');
        if (!tbody) return;
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:30px;color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i> Loading…</td></tr>`;
        const params = new URLSearchParams({ page, per_page:20, ...getTableFilters() });
        try {
            const d = await apiFetch(`${BASE}/feedback/table?${params}`);
            renderTable(tbody, d);
        } catch(e) {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:30px;color:#EF4444;"><i class="fas fa-exclamation-circle"></i> Failed to load data.</td></tr>`;
        }
    }

    function renderTable(tbody, d) {
        if (!d.data?.length) {
            tbody.innerHTML = `<tr><td colspan="7"><div class="fb-empty-state"><i class="fas fa-comments"></i><h3>No Reviews Found</h3><p>Try adjusting your filters.</p></div></td></tr>`;
            renderPagination('fb-table-pagination', 0, 0, loadTable);
            return;
        }
        tbody.innerHTML = d.data.map(row => `<tr><td><span class="fb-table-spot-name" title="${escHtml(row.tourist_spot)}">${escHtml(row.tourist_spot)}</span></td><td><span style="font-size:12px;">${escHtml(row.municipality)}</span></td><td>${ratingBadge(row.rating)}</td><td><span class="fb-table-comment" title="${escHtml(row.comment||'')}">${escHtml(row.comment||'—')}</span></td><td><div style="display:flex;align-items:center;gap:6px;">${avatarHtml(row.user_name, row.user_avatar, 28)}<span>${escHtml(row.user_name)}</span></div></td><td style="font-size:12px;color:#64748b;white-space:nowrap;">${escHtml(row.date)}</td><td><button class="fb-page-btn" onclick="window.openFeedbackSpotDetail(null,${row.id})" style="font-size:11px;padding:4px 10px;height:28px;"><i class="fas fa-eye"></i></button></td></tr>`).join('');
        renderPagination('fb-table-pagination', d.current_page, d.last_page, loadTable);
    }

    async function openSpotDetail(spotId) {
        if (!spotId) return;
        detailSpotId = spotId; detailPage = 1; detailSort = 'newest';
        let overlay = document.getElementById('fb-modal-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'fb-modal-overlay'; overlay.className = 'fb-modal-overlay';
            overlay.innerHTML = `<div class="fb-modal" id="fb-modal-inner" role="dialog" aria-modal="true"><div class="fb-modal-header"><h2 class="fb-modal-header-title"><i class="fas fa-comments"></i> Tourist Site Feedback</h2><button class="fb-modal-close" onclick="window.closeFeedbackModal()" aria-label="Close"><i class="fas fa-times"></i></button></div><div class="fb-modal-body" id="fb-modal-body"><div class="fb-empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading…</p></div></div></div>`;
            document.body.appendChild(overlay);
            overlay.addEventListener('click', e => { if (e.target === overlay) window.closeFeedbackModal(); });
        } else {
            overlay.style.display = 'flex';
            document.getElementById('fb-modal-body').innerHTML = `<div class="fb-empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading…</p></div>`;
        }
        document.body.style.overflow = 'hidden';
        try {
            const d = await apiFetch(`${BASE}/feedback/spot-details/${spotId}?per_page=10&sort=${detailSort}`);
            renderModal(d);
        } catch(e) {
            document.getElementById('fb-modal-body').innerHTML = `<div class="fb-empty-state"><i class="fas fa-exclamation-circle"></i><h3>Error</h3><p>Could not load spot details.</p></div>`;
        }
    }

    async function loadMoreReviews(page) {
        detailPage = page;
        const container = document.getElementById('fb-modal-reviews-list');
        if (!container) return;
        container.innerHTML = `<div class="fb-empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading…</p></div>`;
        try {
            const d = await apiFetch(`${BASE}/feedback/spot-details/${detailSpotId}?per_page=10&page=${page}&sort=${detailSort}`);
            container.innerHTML = renderReviews(d.reviews);
            renderPagination('fb-modal-pagination', d.current_page, d.last_page, loadMoreReviews);
        } catch (e) {}
    }

    function buildSpotSliderHtml(spot) {
        let galleryList = [];
        if (spot.gallery && spot.gallery.length) {
            galleryList = spot.gallery.map(imgUrl).filter(Boolean);
        }
        if (!galleryList.length && spot.cover_image) {
            const c = imgUrl(spot.cover_image);
            if (c) galleryList.push(c);
        }

        window._currentSpotGallery = galleryList;
        window._currentSpotSlideIndex = 0;

        if (!galleryList.length) {
            return `<div class="fb-modal-slider-wrap"><div class="fb-modal-cover-placeholder"><i class="fas fa-mountain-sun"></i></div></div>`;
        }

        const firstImg = galleryList[0];
        const hasMultiple = galleryList.length > 1;

        let html = `<div class="fb-modal-slider-wrap">`;
        html += `<div class="fb-slider-main" onclick="window.openFeedbackLightbox(window._currentSpotGallery[window._currentSpotSlideIndex||0])" title="Click to view full size">`;
        html += `<img id="fb-slider-img" src="${escHtml(firstImg)}" alt="${escHtml(spot.name)}" onerror="this.src='';this.parentElement.innerHTML='<div class=\\'fb-modal-cover-placeholder\\'><i class=\\'fas fa-mountain-sun\\'></i></div>';">`;
        if (hasMultiple) {
            html += `<button type="button" class="fb-slider-arrow prev" onclick="event.stopPropagation(); window.changeSpotSlide(-1);" aria-label="Previous image"><i class="fas fa-chevron-left"></i></button>`;
            html += `<button type="button" class="fb-slider-arrow next" onclick="event.stopPropagation(); window.changeSpotSlide(1);" aria-label="Next image"><i class="fas fa-chevron-right"></i></button>`;
            html += `<span class="fb-slider-counter" id="fb-slider-counter">1 / ${galleryList.length}</span>`;
        }
        html += `</div>`;

        if (hasMultiple) {
            html += `<div class="fb-slider-thumbs-strip">`;
            galleryList.forEach((url, i) => {
                html += `<img src="${escHtml(url)}" class="fb-slider-thumb${i === 0 ? ' active' : ''}" onclick="window.setSpotSlide(${i})" alt="Thumbnail ${i+1}">`;
            });
            html += `</div>`;
        }

        html += `</div>`;
        return html;
    }

    function renderModal(d) {
        const spot = d.spot;
        const sliderHtml = buildSpotSliderHtml(spot);
        const breakdown = d.rating_breakdown || {}; const total = d.total_reviews || 0;
        const breakdownHtml = [5,4,3,2,1].map(star => { const c = breakdown[star]||0; const pct = total>0?Math.round((c/total)*100):0; return `<li class="fb-breakdown-item"><span class="fb-breakdown-label">${star}★</span><div class="fb-breakdown-bar-wrap"><div class="fb-breakdown-bar" style="width:${pct}%;"></div></div><span class="fb-breakdown-count">${c.toLocaleString()}</span></li>`; }).join('');
        document.getElementById('fb-modal-body').innerHTML = `<div class="fb-modal-spot-hero">${sliderHtml}<div class="fb-modal-spot-info"><h2 class="fb-modal-spot-name">${escHtml(spot.name)}</h2><div class="fb-modal-spot-meta"><span class="fb-meta-badge muni"><i class="fas fa-location-dot"></i> ${escHtml(spot.municipality||'Unknown')}</span><span class="fb-meta-badge cat"><i class="fas fa-tag"></i> ${escHtml(spot.category||'Spot')}</span></div><div class="fb-modal-big-rating"><span class="fb-modal-big-num">${(spot.avg_rating||0).toFixed(1)}</span><div><div class="fb-modal-big-stars">${[1,2,3,4,5].map(i=>`<i class="fas fa-star fb-modal-big-star${i>Math.round(spot.avg_rating||0)?' empty':''}"></i>`).join('')}</div><div class="fb-modal-review-count">${(spot.total_reviews||0).toLocaleString()} reviews</div></div></div><ul class="fb-breakdown-list">${breakdownHtml}</ul></div></div><div class="fb-reviews-header"><h3 class="fb-reviews-title"><i class="fas fa-list-ul"></i> All Reviews</h3><select id="fb-modal-sort" class="fb-filter-select" style="min-width:130px;font-size:12px;padding:6px 10px;"><option value="newest">Newest First</option><option value="oldest">Oldest First</option><option value="highest_rated">Highest Rated</option><option value="lowest_rated">Lowest Rated</option></select></div><div id="fb-modal-reviews-list">${renderReviews(d.reviews)}</div><div id="fb-modal-pagination" style="margin-top:12px;"></div>`;
        renderPagination('fb-modal-pagination', d.current_page, d.last_page, loadMoreReviews);
        const sortSel = document.getElementById('fb-modal-sort');
        if (sortSel) sortSel.addEventListener('change', () => { detailSort = sortSel.value; loadMoreReviews(1); });
    }

    function renderReviews(reviews) {
        if (!reviews?.length) return `<div class="fb-empty-state"><i class="fas fa-comment-slash"></i><h3>No Reviews</h3><p>This spot has no reviews yet.</p></div>`;
        return reviews.map(r => {
            const imagesHtml = r.images?.length ? `<div class="fb-review-images">${r.images.map(img => { const url = imgUrl(img); return `<img src="${escHtml(url)}" class="fb-review-img" onclick="window.openFeedbackLightbox('${escHtml(url)}')" alt="Review photo">`; }).join('')}</div>` : '';
            const tagsHtml = [r.crowd_level?`<span class="fb-review-tag crowd"><i class="fas fa-users"></i> ${r.crowd_level}</span>`:'', r.cleanliness?`<span class="fb-review-tag cleanliness"><i class="fas fa-broom"></i> ${r.cleanliness}</span>`:'', r.safety?`<span class="fb-review-tag safety"><i class="fas fa-shield-halved"></i> ${r.safety}</span>`:''].filter(Boolean).join('');
            return `<div class="fb-review-card"><div class="fb-review-header"><div class="fb-reviewer-info">${avatarHtml(r.user_name,r.user_avatar,36)}<div><div class="fb-reviewer-name">${escHtml(r.user_name)}</div><div class="fb-review-date">${escHtml(r.date)}</div></div></div><div class="fb-review-stars">${[1,2,3,4,5].map(i=>`<i class="fas fa-star fb-review-star${i>(r.rating||0)?' empty':''}"></i>`).join('')}</div></div>${r.comment?`<p class="fb-review-comment">${escHtml(r.comment)}</p>`:''}${tagsHtml?`<div class="fb-review-tags">${tagsHtml}</div>`:''}${imagesHtml}</div>`;
        }).join('');
    }

    function renderPagination(containerId, current, last, cb) {
        const el = document.getElementById(containerId);
        if (!el) return;
        if (!last || last <= 1) { el.innerHTML = ''; return; }
        const pages = [];
        for (let p = Math.max(1, current - 2); p <= Math.min(last, current + 2); p++) pages.push(p);
        el.innerHTML = `<div class="fb-pagination"><span class="fb-pagination-info">Page ${current} of ${last}</span><div class="fb-pagination-btns"><button class="fb-page-btn" ${current <= 1 ? 'disabled' : ''} data-page="${current - 1}"><i class="fas fa-chevron-left"></i></button>${pages.map(p => `<button class="fb-page-btn${p === current ? ' active' : ''}" data-page="${p}">${p}</button>`).join('')}<button class="fb-page-btn" ${current >= last ? 'disabled' : ''} data-page="${current + 1}"><i class="fas fa-chevron-right"></i></button></div></div>`;

        el.querySelectorAll('.fb-page-btn[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                const targetPage = parseInt(btn.getAttribute('data-page'), 10);
                if (targetPage && typeof cb === 'function') {
                    cb(targetPage);
                }
            });
        });
    }

    function switchView(view) {
        currentView = view;
        const gs = document.getElementById('fb-gallery-section'), ts = document.getElementById('fb-table-section');
        const bg = document.getElementById('fb-btn-gallery'), bt = document.getElementById('fb-btn-table');
        const df = document.getElementById('fb-date-filters');
        if (gs) gs.style.display = view==='gallery'?'':'none';
        if (ts) ts.style.display = view==='table'?'':'none';
        if (bg) bg.classList.toggle('active', view==='gallery');
        if (bt) bt.classList.toggle('active', view==='table');
        if (df) df.style.display = view==='table'?'':'none';
        if (view==='gallery') loadGallery(1); else loadTable(1);
    }

    function onSearchInput() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => { if (currentView==='gallery') loadGallery(1); else loadTable(1); }, 350);
    }

    function openLightbox(url) {
        let lb = document.getElementById('fb-lightbox');
        if (!lb) {
            lb = document.createElement('div'); lb.id='fb-lightbox'; lb.className='fb-lightbox-overlay';
            lb.innerHTML = `<img class="fb-lightbox-img" src="" alt="Review photo">`;
            lb.addEventListener('click', () => lb.style.display='none');
            lb.querySelector('.fb-lightbox-img').addEventListener('click', e => e.stopPropagation());
            document.body.appendChild(lb);
        }
        lb.querySelector('.fb-lightbox-img').src = url;
        lb.style.display = 'flex';
    }

    window.openFeedbackSpotDetail = openSpotDetail;
    window.closeFeedbackModal = function() { const o=document.getElementById('fb-modal-overlay'); if(o) o.style.display='none'; document.body.style.overflow=''; };
    window.openFeedbackLightbox = openLightbox;
    window.switchFeedbackView   = switchView;
    window.setSpotSlide = function (index) {
        if (!window._currentSpotGallery || !window._currentSpotGallery.length) return;
        index = (index + window._currentSpotGallery.length) % window._currentSpotGallery.length;
        window._currentSpotSlideIndex = index;
        const imgEl = document.getElementById('fb-slider-img');
        const counterEl = document.getElementById('fb-slider-counter');
        if (imgEl) imgEl.src = window._currentSpotGallery[index];
        if (counterEl) counterEl.textContent = (index + 1) + ' / ' + window._currentSpotGallery.length;
        document.querySelectorAll('.fb-slider-thumb').forEach((t, i) => t.classList.toggle('active', i === index));
    };
    window.changeSpotSlide = function (dir) {
        window.setSpotSlide((window._currentSpotSlideIndex || 0) + dir);
    };

    let isInitialized = false;

    function init(force = false) {
        const tab = document.getElementById('spa-tab-feedback.php') || document;
        const grid = tab.querySelector('#fb-gallery-grid');
        if (!grid) return;

        if (!isInitialized) {
            const si = tab.querySelector('#fb-search-input');
            if (si) si.addEventListener('input', onSearchInput);
            ['fb-filter-municipality','fb-filter-category','fb-filter-rating','fb-sort-select'].forEach(id => {
                const el = tab.querySelector(`#${id}`);
                if (el) el.addEventListener('change', () => { if (currentView==='gallery') loadGallery(1); else loadTable(1); });
            });
            ['fb-date-from','fb-date-to'].forEach(id => {
                const el = tab.querySelector(`#${id}`);
                if (el) el.addEventListener('change', () => { if (currentView==='table') loadTable(1); });
            });
        }

        const hasExistingContent = grid.querySelector('.fb-spot-card') !== null;
        if (isInitialized && hasExistingContent && !force) {
            loadDashboardStats(true);
            if (currentView === 'gallery') loadGallery(galleryPage, true);
            else                           loadTable(tablePage);
            return;
        }

        isInitialized = true;
        Promise.all([loadDashboardStats(), loadGallery(1)]);
        switchView('gallery');
    }

    window.initFeedbackModule = init;
    document.addEventListener('spa:page:shown', e => { if (e.detail?.page==='feedback.php') init(); });
    if (document.readyState==='loading') { document.addEventListener('DOMContentLoaded', init); } else { setTimeout(init, 50); }
})();
