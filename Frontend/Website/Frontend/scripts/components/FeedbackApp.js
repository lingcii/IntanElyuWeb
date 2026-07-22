(function () {
    /**
     * FeedbackApp.js
     * Frontend logic for the Feedback Management Module (PICTO, LUPTO, Municipal).
     */

    if (window.__FEEDBACK_APP_LOADED__) return;
    window.__FEEDBACK_APP_LOADED__ = true;

    class FeedbackApp {
        constructor() {
            this.currentView = 'gallery'; // 'gallery' | 'table'
            this.galleryPage = 1;
            this.tablePage = 1;
            this.detailPage = 1;
            this.currentModalSpotId = null;
            this.searchDebounceTimer = null;
            this.isInitialized = false;
            this.modalCache = {};
            this.carouselIndex = 0;

            this.charts = {
                ratingDist: null,
                muniComp: null,
                topRated: null,
                mostReviewed: null,
                monthlyTrend: null
            };

            this.init();
        }

        /**
         * Resolves API base endpoint based on user role path
         */
        getApiEndpoint(path = '') {
            const currentPath = window.location.pathname.toLowerCase();
            let base = window.API_CONFIG.LUPTO;

            if (currentPath.includes('/picto/')) {
                base = window.API_CONFIG.PICTO;
            } else if (currentPath.includes('/municipal/')) {
                base = window.API_CONFIG.MUNICIPAL;
            } else if (currentPath.includes('/lupto/')) {
                base = window.API_CONFIG.LUPTO;
            }

            return `${base}/feedback${path}`;
        }

        init() {
            const run = () => {
                this.closeSpotModal();
                this.setupEventListeners();
                const promises = [this.loadDashboardStats()];
                if (this.currentView === 'gallery') {
                    promises.push(this.loadGallery(this.galleryPage));
                } else {
                    promises.push(this.loadTable(this.tablePage));
                }
                Promise.all(promises).catch(err => console.error('Feedback load error:', err));
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => run());
            } else {
                run();
            }

            // Listen for SPA tab switch events to refresh view data
            document.addEventListener('tabshow', (e) => {
                if (window.location.pathname.includes('feedback.php')) {
                    run();
                }
            });
            window.addEventListener('popstate', () => {
                if (window.location.pathname.includes('feedback.php')) {
                    run();
                }
            });
        }

        setupEventListeners() {
            if (this.isInitialized) return;
            this.isInitialized = true;

            // View Switcher (Gallery vs Table)
            document.addEventListener('click', (e) => {
                const btnGallery = e.target.closest('#btn-view-gallery');
                const btnTable = e.target.closest('#btn-view-table');

                if (btnGallery) {
                    e.preventDefault();
                    this.switchView('gallery');
                } else if (btnTable) {
                    e.preventDefault();
                    this.switchView('table');
                }
            });

            // Search input
            document.addEventListener('input', (e) => {
                if (e.target && e.target.id === 'feedback-search-input') {
                    clearTimeout(this.searchDebounceTimer);
                    this.searchDebounceTimer = setTimeout(() => {
                        this.galleryPage = 1;
                        this.tablePage = 1;
                        if (this.currentView === 'gallery') this.loadGallery();
                        else this.loadTable();
                    }, 350);
                }
            });

            // Filters & Sorting
            document.addEventListener('change', (e) => {
                if (e.target && ['filter-municipality', 'filter-category', 'filter-rating', 'sort-select'].includes(e.target.id)) {
                    this.galleryPage = 1;
                    this.tablePage = 1;
                    if (this.currentView === 'gallery') this.loadGallery();
                    else this.loadTable();
                }
            });

            // Reset filters
            document.addEventListener('click', (e) => {
                const btnReset = e.target.closest('#btn-reset-filters');
                if (btnReset) {
                    e.preventDefault();
                    const filterMuni = document.getElementById('filter-municipality');
                    const filterCat = document.getElementById('filter-category');
                    const filterRating = document.getElementById('filter-rating');
                    const sortSelect = document.getElementById('sort-select');
                    const searchInput = document.getElementById('feedback-search-input');

                    if (filterMuni) filterMuni.value = '';
                    if (filterCat) filterCat.value = '';
                    if (filterRating) filterRating.value = '';
                    if (sortSelect) sortSelect.value = 'newest';
                    if (searchInput) searchInput.value = '';

                    this.galleryPage = 1;
                    this.tablePage = 1;
                    this.loadGallery();
                    this.loadTable();
                }
            });

            // Modal Close Buttons (with preventDefault & stopPropagation)
            document.addEventListener('click', (e) => {
                const closeBtn = e.target.closest('#spot-modal-close, .modal-close-btn');
                const overlay = document.getElementById('spot-modal-overlay');

                if (closeBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.closeSpotModal();
                } else if (overlay && e.target === overlay) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.closeSpotModal();
                }
            });
        }

        switchView(view) {
            this.currentView = view;
            const gallerySection = document.getElementById('gallery-view-section');
            const tableSection = document.getElementById('table-view-section');
            const btnGallery = document.getElementById('btn-view-gallery');
            const btnTable = document.getElementById('btn-view-table');

            if (view === 'gallery') {
                if (gallerySection) gallerySection.style.display = 'block';
                if (tableSection) tableSection.style.display = 'none';
                if (btnGallery) btnGallery.classList.add('active');
                if (btnTable) btnTable.classList.remove('active');
                this.loadGallery(this.galleryPage);
            } else {
                if (gallerySection) gallerySection.style.display = 'none';
                if (tableSection) tableSection.style.display = 'block';
                if (btnTable) btnTable.classList.add('active');
                if (btnGallery) btnGallery.classList.remove('active');
                this.loadTable(this.tablePage);
            }
        }

        /**
         * Fetch & render Dashboard Statistics + Charts
         */
        async loadDashboardStats() {
            try {
                const response = await window.API_CONFIG.get(this.getApiEndpoint('/dashboard-stats'));
                if (!response || response.status !== 'success') return;

                const stats = response.data;

                // Set KPI values
                const totalReviewedEl = document.getElementById('kpi-total-reviewed');
                const totalFeedbackEl = document.getElementById('kpi-total-feedback');
                const avgRatingEl = document.getElementById('kpi-avg-rating');
                const fiveStarEl = document.getElementById('kpi-5star-reviews');

                if (totalReviewedEl) totalReviewedEl.textContent = stats.total_reviewed_spots || 0;
                if (totalFeedbackEl) totalFeedbackEl.textContent = stats.total_feedback || 0;
                if (avgRatingEl) avgRatingEl.textContent = stats.average_rating ? `${stats.average_rating.toFixed(1)} ★` : '0.0 ★';
                if (fiveStarEl) fiveStarEl.textContent = stats.rating_breakdown ? stats.rating_breakdown[5] || 0 : 0;

                // Render Charts
                this.renderCharts(stats);
            } catch (err) {
                console.error('Failed to load feedback dashboard stats:', err);
            }
        }

        renderCharts(stats) {
            if (typeof Chart === 'undefined') return;

            // 1. Rating Distribution Chart
            const ctxDist = document.getElementById('chart-rating-dist');
            if (ctxDist && stats.rating_breakdown) {
                if (this.charts.ratingDist) this.charts.ratingDist.destroy();
                this.charts.ratingDist = new Chart(ctxDist, {
                    type: 'bar',
                    data: {
                        labels: ['5 Stars', '4 Stars', '3 Stars', '2 Stars', '1 Star'],
                        datasets: [{
                            label: 'Review Count',
                            data: [
                                stats.rating_breakdown[5] || 0,
                                stats.rating_breakdown[4] || 0,
                                stats.rating_breakdown[3] || 0,
                                stats.rating_breakdown[2] || 0,
                                stats.rating_breakdown[1] || 0,
                            ],
                            backgroundColor: ['#10B981', '#3B82F6', '#F59E0B', '#F97316', '#EF4444'],
                            borderRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });
            }

            // 2. Municipality Comparison Chart (PICTO / LUPTO)
            const ctxMuni = document.getElementById('chart-muni-comparison');
            const muniList = Array.isArray(stats.municipality_comparison)
                ? stats.municipality_comparison
                : (stats.municipality_comparison ? Object.values(stats.municipality_comparison) : []);

            if (ctxMuni && muniList.length > 0) {
                if (this.charts.muniComp) this.charts.muniComp.destroy();
                const labels = muniList.map(m => m.municipality);
                const ratings = muniList.map(m => parseFloat(m.avg_rating));

                this.charts.muniComp = new Chart(ctxMuni, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Average Rating',
                            data: ratings,
                            backgroundColor: '#3B82F6',
                            borderRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: { legend: { display: false } },
                        scales: { x: { min: 0, max: 5 } }
                    }
                });
            }

            // 3. Top Rated Spots Chart
            const ctxTop = document.getElementById('chart-top-spots');
            const topSpotsList = Array.isArray(stats.top_rated_spots)
                ? stats.top_rated_spots
                : (stats.top_rated_spots ? Object.values(stats.top_rated_spots) : []);

            if (ctxTop && topSpotsList.length > 0) {
                if (this.charts.topRated) this.charts.topRated.destroy();
                const labels = topSpotsList.map(s => s.name);
                const data = topSpotsList.map(s => s.avg_rating);

                this.charts.topRated = new Chart(ctxTop, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Average Rating',
                            data: data,
                            backgroundColor: '#8B5CF6',
                            borderRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { min: 0, max: 5 } }
                    }
                });
            }

            // 4. Monthly Trend Chart
            const ctxTrend = document.getElementById('chart-monthly-trend');
            const trendList = Array.isArray(stats.monthly_trend)
                ? stats.monthly_trend
                : (stats.monthly_trend ? Object.values(stats.monthly_trend) : []);

            if (ctxTrend && trendList.length > 0) {
                if (this.charts.monthlyTrend) this.charts.monthlyTrend.destroy();
                const labels = trendList.map(t => t.label);
                const counts = trendList.map(t => t.total);

                this.charts.monthlyTrend = new Chart(ctxTrend, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Feedback Submissions',
                            data: counts,
                            borderColor: '#2563EB',
                            backgroundColor: 'rgba(37, 99, 235, 0.1)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });
            }
        }

        /**
         * Fetch & render Gallery View
         */
        async loadGallery(page = 1) {
            this.galleryPage = page;
            const container = document.getElementById('gallery-cards-container');
            const pagContainer = document.getElementById('gallery-pagination');

            if (!container) return;
            container.innerHTML = '<div class="empty-state-box col-12" style="grid-column:1/-1;"><i class="fas fa-spinner fa-spin"></i><p>Loading tourist spot reviews...</p></div>';

            const params = this.getFilterParams();
            params.page = page;
            params.per_page = 12;

            try {
                const response = await window.API_CONFIG.get(this.getApiEndpoint('/gallery'), params);
                if (!response || response.status !== 'success') return;

                const spots = response.data;
                const pagination = response.pagination;

                if (!spots || spots.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state-box col-12" style="grid-column: 1 / -1;">
                            <i class="fas fa-comment-slash"></i>
                            <h3>No Reviewed Tourist Spots Found</h3>
                            <p>Try adjusting your search query or filters.</p>
                        </div>`;
                    if (pagContainer) pagContainer.innerHTML = '';
                    return;
                }

                container.innerHTML = spots.map(spot => this.renderGalleryCardHtml(spot)).join('');

                // Attach click handlers to open modal
                container.querySelectorAll('.spot-gallery-card').forEach(card => {
                    card.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        const spotId = card.getAttribute('data-id');
                        if (spotId) this.openSpotModal(spotId);
                    });
                });

                // Render Pagination
                if (pagContainer) this.renderPagination(pagContainer, pagination, (p) => this.loadGallery(p));
            } catch (err) {
                console.error('Failed to load gallery view:', err);
                container.innerHTML = '<div class="empty-state-box col-12" style="grid-column:1/-1;"><i class="fas fa-exclamation-triangle"></i><p>Error loading feedback data.</p></div>';
            }
        }

        renderGalleryCardHtml(spot) {
            const photoUrl = spot.photo_url || '../../images/placeholder.jpg';
            const ratingStars = this.generateStarsHtml(spot.average_rating);

            return `
                <div class="spot-gallery-card" data-id="${spot.id}">
                    <div class="spot-card-media">
                        <img src="${photoUrl}" alt="${this.escapeHtml(spot.name)}" onerror="this.src='../../images/placeholder.jpg'">
                        <span class="spot-card-badge">${this.escapeHtml(spot.municipality)}</span>
                        <span class="spot-card-category">${this.escapeHtml(spot.category)}</span>
                    </div>
                    <div class="spot-card-body">
                        <h3 class="spot-card-title">${this.escapeHtml(spot.name)}</h3>
                        <div class="spot-card-rating-row">
                            <div class="spot-rating-stars">
                                ${ratingStars}
                                <span style="color:#111827; margin-left:0.25rem;">${spot.average_rating.toFixed(1)}</span>
                            </div>
                            <span class="spot-review-count">${spot.total_reviews} ${spot.total_reviews === 1 ? 'Review' : 'Reviews'}</span>
                        </div>
                    </div>
                </div>`;
        }

        /**
         * Fetch & render Table View
         */
        async loadTable(page = 1) {
            this.tablePage = page;
            const tbody = document.getElementById('feedback-table-tbody');
            const pagContainer = document.getElementById('table-pagination');

            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="6" class="empty-state-box"><i class="fas fa-spinner fa-spin"></i> Loading feedback records...</td></tr>';

            const params = this.getFilterParams();
            params.page = page;
            params.per_page = 15;

            try {
                const response = await window.API_CONFIG.get(this.getApiEndpoint('/table'), params);
                if (!response || response.status !== 'success') return;

                const spots = response.data;
                const pagination = response.pagination;

                if (!spots || spots.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="empty-state-box"><i class="fas fa-comment-dots"></i><br>No tourist spot feedback records found matching criteria.</td></tr>';
                    if (pagContainer) pagContainer.innerHTML = '';
                    return;
                }

                tbody.innerHTML = spots.map(spot => {
                    const spotName = spot.name;
                    const muniName = spot.municipality;
                    const ratingBadge = spot.average_rating > 0 
                        ? `<span class="rating-badge"><i class="fas fa-star"></i> ${spot.average_rating.toFixed(1)}</span>`
                        : '<span style="color:#9ca3af; font-size:0.85rem;">No ratings</span>';

                    const latest = spot.latest_feedback || {};
                    const commentText = latest.comment || 'No written reviews yet';
                    const userName = latest.user_name || 'N/A';
                    const avatar = latest.user_avatar || '../../images/default-avatar.png';
                    const dateStr = latest.date || 'N/A';

                    return `
                        <tr class="spot-table-row" data-id="${spot.id}" style="cursor:pointer;" title="Click to view full spot feedback modal">
                            <td><strong>${this.escapeHtml(spotName)}</strong></td>
                            <td>${this.escapeHtml(muniName)}</td>
                            <td>${ratingBadge}</td>
                            <td><div class="comment-snippet" title="${this.escapeHtml(commentText)}">${this.escapeHtml(commentText)}</div></td>
                            <td>
                                <div class="user-cell">
                                    <img src="${avatar}" class="user-avatar-img" onerror="this.src='../../images/default-avatar.png'">
                                    <span class="user-name-text">${this.escapeHtml(userName)}</span>
                                </div>
                            </td>
                            <td>${dateStr}</td>
                        </tr>`;
                }).join('');

                // Attach click listeners to open spot details modal from table rows
                tbody.querySelectorAll('.spot-table-row').forEach(row => {
                    row.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        const spotId = row.getAttribute('data-id');
                        if (spotId) this.openSpotModal(spotId);
                    });
                });

                if (pagContainer) this.renderPagination(pagContainer, pagination, (p) => this.loadTable(p));
            } catch (err) {
                console.error('Failed to load table view:', err);
                tbody.innerHTML = '<tr><td colspan="6" class="empty-state-box">Error loading data.</td></tr>';
            }
        }

        getFilterParams() {
            const params = {};
            const searchInput = document.getElementById('feedback-search-input');
            const filterMuni = document.getElementById('filter-municipality');
            const filterCat = document.getElementById('filter-category');
            const filterRating = document.getElementById('filter-rating');
            const sortSelect = document.getElementById('sort-select');

            if (searchInput && searchInput.value.trim()) params.search = searchInput.value.trim();
            if (filterMuni && filterMuni.value) params.municipality_id = filterMuni.value;
            if (filterCat && filterCat.value) params.category = filterCat.value;
            if (filterRating && filterRating.value) params.rating = filterRating.value;
            if (sortSelect && sortSelect.value) params.sort = sortSelect.value;

            return params;
        }

        /**
         * Open Spot Detail Modal with 50% | 50% Split Layout & Instant Memory Cache
         */
        async openSpotModal(spotId) {
            this.currentModalSpotId = spotId;
            const overlay = document.getElementById('spot-modal-overlay');
            const content = document.getElementById('spot-modal-content');

            if (!overlay || !content) return;
            overlay.classList.add('active');

            // Render instantly from in-memory cache if available
            if (this.modalCache[spotId]) {
                content.innerHTML = this.modalCache[spotId].html;
                this.setupCarousel(this.modalCache[spotId].spot.images);
            } else {
                content.innerHTML = `
                    <div class="empty-state-box" style="padding:6rem; width:100%;">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <p>Loading tourist spot feedback details...</p>
                    </div>`;
            }

            try {
                const response = await window.API_CONFIG.get(this.getApiEndpoint(`/spot-details/${spotId}`));
                if (!response || response.status !== 'success') return;

                const html = this.buildModalHtml(response.spot, response.reviews);
                this.modalCache[spotId] = {
                    html: html,
                    spot: response.spot
                };
                content.innerHTML = html;
                this.setupCarousel(response.spot.images);
            } catch (err) {
                if (!this.modalCache[spotId]) {
                    content.innerHTML = '<div class="empty-state-box" style="padding:6rem; width:100%;"><p>Error loading spot details.</p></div>';
                }
            }
        }

        buildModalHtml(spot, reviews) {
            const starsHtml = this.generateStarsHtml(spot.average_rating);
            const total = spot.total_reviews > 0 ? spot.total_reviews : 1;
            const getPct = (cnt) => Math.round(((cnt || 0) / total) * 100);

            // Build reviews list HTML
            let reviewsListHtml = '';
            if (!reviews || reviews.length === 0) {
                reviewsListHtml = `
                    <div class="empty-state-box" style="padding:2rem 1rem;">
                        <i class="fas fa-comment-slash" style="font-size:2rem; color:#cbd5e1;"></i>
                        <p style="margin-top:0.5rem; font-size:0.9rem; color:#64748b;">No written reviews yet for this spot.</p>
                    </div>`;
            } else {
                reviewsListHtml = reviews.map(rev => {
                    const userName = rev.user ? rev.user.name : 'Anonymous Tourist';
                    const avatar = rev.user && rev.user.avatar ? rev.user.avatar : '../../images/default-avatar.png';
                    const dateStr = rev.created_at ? new Date(rev.created_at).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : '';
                    const itemStars = this.generateStarsHtml(rev.rating || 0);

                    let photosHtml = '';
                    if (rev.images && rev.images.length > 0) {
                        photosHtml = `
                            <div class="review-photos-thumbnails">
                                ${rev.images.map(img => `
                                    <img src="${img.image_path}" class="review-thumb-img" alt="Review photo" onclick="window.open('${img.image_path}', '_blank')">
                                `).join('')}
                            </div>`;
                    }

                    return `
                        <div class="review-item-card">
                            <div class="review-user-header">
                                <div class="review-user-info">
                                    <img src="${avatar}" class="review-user-avatar" onerror="this.src='../../images/default-avatar.png'">
                                    <div>
                                        <div class="review-author-name">${this.escapeHtml(userName)}</div>
                                        <div class="review-date-text">${dateStr}</div>
                                    </div>
                                </div>
                                <div style="color:#f59e0b; font-size:0.85rem;">${itemStars}</div>
                            </div>
                            <div class="review-comment-text">${this.escapeHtml(rev.testimony || 'No comment provided.')}</div>
                            ${photosHtml}
                        </div>`;
                }).join('');
            }

            // Right Panel Image / Carousel HTML
            const images = spot.images && spot.images.length > 0 
                ? spot.images 
                : [{ photo_url: spot.photo_url || '../../images/placeholder.jpg' }];

            const firstImgUrl = images[0].photo_url || '../../images/placeholder.jpg';
            const isMulti = images.length > 1;

            let carouselControlsHtml = '';
            if (isMulti) {
                carouselControlsHtml = `
                    <span class="carousel-counter-badge" id="carousel-counter">1 of ${images.length}</span>
                    <button class="carousel-nav-btn carousel-nav-prev" id="carousel-btn-prev" type="button"><i class="fas fa-chevron-left"></i></button>
                    <button class="carousel-nav-btn carousel-nav-next" id="carousel-btn-next" type="button"><i class="fas fa-chevron-right"></i></button>
                    <div class="carousel-thumbnails-bar" id="carousel-thumbs-bar">
                        ${images.map((img, idx) => `
                            <img src="${img.photo_url}" class="carousel-thumb-item ${idx === 0 ? 'active' : ''}" data-idx="${idx}" alt="Thumbnail">
                        `).join('')}
                    </div>`;
            }

            return `
                <div class="modal-split-container">
                    <!-- LEFT PANEL (50%): Info, Rating Summary, Rating Breakdown, Reviews -->
                    <div class="modal-left-panel">
                        <div class="spot-info-header">
                            <h2 class="spot-info-title">${this.escapeHtml(spot.name)}</h2>
                            <div class="spot-meta-tags">
                                <span class="meta-tag meta-tag-muni">
                                    <i class="fas fa-location-dot"></i> ${this.escapeHtml(spot.municipality)}
                                </span>
                                <span class="meta-tag meta-tag-cat">
                                    <i class="fas fa-layer-group"></i> ${this.escapeHtml(spot.category)}
                                </span>
                                <span class="meta-tag meta-tag-class">
                                    <i class="fas fa-tag"></i> ${this.escapeHtml(spot.classification || 'Existing Destination')}
                                </span>
                            </div>
                        </div>

                        <div class="spot-summary-box">
                            <div class="rating-score-badge">
                                <div class="score-num">${spot.average_rating.toFixed(1)}</div>
                                <div class="score-stars">${starsHtml}</div>
                                <div class="score-total-count">${spot.total_reviews} ${spot.total_reviews === 1 ? 'Review' : 'Reviews'}</div>
                            </div>
                            <div class="rating-bars-container">
                                <div class="rating-bar-row">
                                    <span class="bar-star-label">5 Stars</span>
                                    <div class="bar-track"><div class="bar-fill" style="width:${getPct(spot.rating_breakdown[5])}%"></div></div>
                                    <span class="bar-count-num">${spot.rating_breakdown[5]}</span>
                                </div>
                                <div class="rating-bar-row">
                                    <span class="bar-star-label">4 Stars</span>
                                    <div class="bar-track"><div class="bar-fill" style="width:${getPct(spot.rating_breakdown[4])}%"></div></div>
                                    <span class="bar-count-num">${spot.rating_breakdown[4]}</span>
                                </div>
                                <div class="rating-bar-row">
                                    <span class="bar-star-label">3 Stars</span>
                                    <div class="bar-track"><div class="bar-fill" style="width:${getPct(spot.rating_breakdown[3])}%"></div></div>
                                    <span class="bar-count-num">${spot.rating_breakdown[3]}</span>
                                </div>
                                <div class="rating-bar-row">
                                    <span class="bar-star-label">2 Stars</span>
                                    <div class="bar-track"><div class="bar-fill" style="width:${getPct(spot.rating_breakdown[2])}%"></div></div>
                                    <span class="bar-count-num">${spot.rating_breakdown[2]}</span>
                                </div>
                                <div class="rating-bar-row">
                                    <span class="bar-star-label">1 Star</span>
                                    <div class="bar-track"><div class="bar-fill" style="width:${getPct(spot.rating_breakdown[1])}%"></div></div>
                                    <span class="bar-count-num">${spot.rating_breakdown[1]}</span>
                                </div>
                            </div>
                        </div>

                        <h3 class="reviews-section-header">
                            <i class="fas fa-comments"></i> Visitor Reviews & Testimonials
                        </h3>
                        <div class="reviews-list-container">
                            ${reviewsListHtml}
                        </div>
                    </div>

                    <!-- RIGHT PANEL (50%): Large Spot Cover Image / Carousel -->
                    <div class="modal-right-panel">
                        <div class="carousel-main-wrapper">
                            <img src="${firstImgUrl}" class="carousel-main-img" id="carousel-main-img" alt="${this.escapeHtml(spot.name)}" onerror="this.src='../../images/placeholder.jpg'">
                            ${carouselControlsHtml}
                        </div>
                    </div>
                </div>`;
        }

        setupCarousel(spotImages) {
            const images = spotImages && spotImages.length > 0 ? spotImages : [];
            if (images.length <= 1) return;

            this.carouselIndex = 0;
            const mainImg = document.getElementById('carousel-main-img');
            const counter = document.getElementById('carousel-counter');
            const prevBtn = document.getElementById('carousel-btn-prev');
            const nextBtn = document.getElementById('carousel-btn-next');
            const thumbs = document.querySelectorAll('.carousel-thumb-item');

            const updateView = (index) => {
                if (!images.length) return;
                this.carouselIndex = (index + images.length) % images.length;
                const imgObj = images[this.carouselIndex];

                if (mainImg) {
                    mainImg.style.opacity = '0.4';
                    setTimeout(() => {
                        mainImg.src = imgObj.photo_url || '../../images/placeholder.jpg';
                        mainImg.style.opacity = '1';
                    }, 100);
                }
                if (counter) counter.textContent = `${this.carouselIndex + 1} of ${images.length}`;

                thumbs.forEach((thumb, idx) => {
                    if (idx === this.carouselIndex) thumb.classList.add('active');
                    else thumb.classList.remove('active');
                });
            };

            if (prevBtn) {
                prevBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    updateView(this.carouselIndex - 1);
                });
            }
            if (nextBtn) {
                nextBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    updateView(this.carouselIndex + 1);
                });
            }
            thumbs.forEach((thumb) => {
                thumb.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const idx = parseInt(thumb.getAttribute('data-idx'));
                    if (!isNaN(idx)) updateView(idx);
                });
            });
        }

        closeSpotModal() {
            const overlay = document.getElementById('spot-modal-overlay');
            if (overlay) {
                overlay.classList.remove('active');
            }
        }

        renderPagination(container, pagination, onPageChange) {
            if (!pagination || pagination.last_page <= 1) {
                container.innerHTML = '';
                return;
            }

            const current = pagination.current_page;
            const last = pagination.last_page;

            let btnsHtml = `
                <button class="page-btn" ${current === 1 ? 'disabled' : ''} data-page="${current - 1}">
                    <i class="fas fa-chevron-left"></i> Prev
                </button>`;

            for (let p = 1; p <= last; p++) {
                if (p === 1 || p === last || (p >= current - 1 && p <= current + 1)) {
                    btnsHtml += `<button class="page-btn ${p === current ? 'active' : ''}" data-page="${p}">${p}</button>`;
                } else if (p === current - 2 || p === current + 2) {
                    btnsHtml += `<span style="padding:0 0.25rem; color:#9ca3af;">...</span>`;
                }
            }

            btnsHtml += `
                <button class="page-btn" ${current === last ? 'disabled' : ''} data-page="${current + 1}">
                    Next <i class="fas fa-chevron-right"></i>
                </button>`;

            container.innerHTML = `
                <div class="feedback-pagination">
                    <span class="pagination-info">Showing page ${current} of ${last} (${pagination.total} total)</span>
                    <div class="pagination-btns">${btnsHtml}</div>
                </div>`;

            container.querySelectorAll('.page-btn[data-page]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const page = parseInt(btn.getAttribute('data-page'));
                    if (page && page !== current && page >= 1 && page <= last) {
                        onPageChange(page);
                    }
                });
            });
        }

        generateStarsHtml(rating) {
            const rounded = Math.round(rating);
            let html = '';
            for (let i = 1; i <= 5; i++) {
                if (i <= rounded) {
                    html += '<i class="fas fa-star"></i>';
                } else {
                    html += '<i class="far fa-star"></i>';
                }
            }
            return html;
        }

        escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    }

    // Instantiate app
    window.feedbackApp = new FeedbackApp();
})();
