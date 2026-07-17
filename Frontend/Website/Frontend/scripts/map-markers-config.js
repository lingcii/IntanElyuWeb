(function () {
    if (window.__MapMarkersConfigLoaded) return;
    window.__MapMarkersConfigLoaded = true;

    const MAP_CONFIG = {
        CATEGORY_ICON_MAP: {
            'beach': 'umbrella-beach', 'mountain': 'mountain', 'waterfalls': 'water',
            'waterfall': 'water', 'river': 'water', 'lake': 'water', 'island': 'umbrella-beach',
            'cave': 'mountain', 'volcano': 'mountain', 'forest': 'tree',
            'nature park': 'tree', 'marine sanctuary': 'fish', 'wildlife sanctuary': 'paw',
            'historical': 'landmark', 'cultural heritage': 'landmark', 'religious': 'church',
            'museum': 'museum', 'monument': 'monument', 'landmark': 'landmark',
            'viewpoint': 'binoculars', 'adventure': 'hiking', 'hiking': 'hiking',
            'camping': 'campground', 'farm': 'seedling', 'eco-tourism': 'leaf',
            'garden': 'seedling', 'park': 'tree', 'recreation': 'bicycle',
            'hot spring': 'hot-tub-person', 'cold spring': 'snowflake',
            'food destination': 'utensils', 'shopping': 'shopping-cart',
            'festival venue': 'masks-theater', 'resort': 'hotel', 'other': 'star'
        },

        CATEGORY_COLOR_MAP: {
            'beach': '#0EA5E9', 'marine sanctuary': '#0EA5E9', 'island': '#0EA5E9',
            'waterfalls': '#06B6D4', 'waterfall': '#06B6D4', 'river': '#06B6D4', 'lake': '#06B6D4',
            'forest': '#22C55E', 'nature park': '#22C55E', 'wildlife sanctuary': '#22C55E',
            'farm': '#22C55E', 'eco-tourism': '#22C55E', 'garden': '#22C55E', 'park': '#22C55E',
            'mountain': '#8B5CF6', 'cave': '#8B5CF6', 'volcano': '#8B5CF6',
            'hiking': '#8B5CF6', 'camping': '#8B5CF6', 'viewpoint': '#8B5CF6',
            'historical': '#F59E0B', 'cultural heritage': '#F59E0B', 'museum': '#F59E0B',
            'monument': '#F59E0B', 'landmark': '#F59E0B',
            'religious': '#D97706',
            'adventure': '#EC4899', 'recreation': '#EC4899', 'food destination': '#EC4899',
            'shopping': '#EC4899', 'festival venue': '#EC4899', 'resort': '#EC4899',
            'hot spring': '#EF4444', 'cold spring': '#06B6D4', 'other': '#6B7280'
        },

        CLASSIFICATION_COLORS: {
            'EXIST': { color: '#3B82F6', label: 'EXISTING' },
            'EMERGE': { color: '#EF4444', label: 'EMERGING' },
            'POTENTIAL': { color: '#22C55E', label: 'POTENTIAL' }
        },

        CLASSIFICATION_LABEL_MAP: {
            'EXIST': 'EXISTING',
            'EMERGE': 'EMERGING',
            'POTENTIAL': 'POTENTIAL'
        },

        ALL_CATEGORIES: [
            'Beach', 'Mountain', 'Waterfalls', 'River', 'Lake', 'Island',
            'Cave', 'Volcano', 'Forest', 'Nature Park', 'Marine Sanctuary',
            'Wildlife Sanctuary', 'Historical', 'Cultural Heritage', 'Religious',
            'Museum', 'Monument', 'Landmark', 'Viewpoint', 'Adventure', 'Hiking',
            'Camping', 'Farm', 'Eco-Tourism', 'Garden', 'Park', 'Recreation',
            'Hot Spring', 'Cold Spring', 'Food Destination', 'Shopping',
            'Festival Venue', 'Resort', 'Other'
        ],

        ALL_CLASSIFICATIONS: ['EXIST', 'EMERGE', 'POTENTIAL']
    };

    function getCategoryIcon(categoryStr) {
        if (!categoryStr) return 'map-marker-alt';
        var cats = String(categoryStr).split(',').map(function (c) { return c.trim().toLowerCase(); });
        for (var i = 0; i < cats.length; i++) {
            if (MAP_CONFIG.CATEGORY_ICON_MAP[cats[i]]) return MAP_CONFIG.CATEGORY_ICON_MAP[cats[i]];
        }
        return 'map-marker-alt';
    }

    function getCategoryColor(categoryStr) {
        if (!categoryStr) return '#6B7280';
        var cats = String(categoryStr).split(',').map(function (c) { return c.trim().toLowerCase(); });
        for (var i = 0; i < cats.length; i++) {
            if (MAP_CONFIG.CATEGORY_COLOR_MAP[cats[i]]) return MAP_CONFIG.CATEGORY_COLOR_MAP[cats[i]];
        }
        return '#6B7280';
    }

    function getClassificationColor(classification) {
        if (!classification) return '#9CA3AF';
        var cfg = MAP_CONFIG.CLASSIFICATION_COLORS[classification];
        return cfg ? cfg.color : '#9CA3AF';
    }

    function getClassificationLabel(classification) {
        var cfg = MAP_CONFIG.CLASSIFICATION_COLORS[classification];
        return cfg ? cfg.label : (classification || 'Unknown');
    }

    var _dashSpotsById = {};

    function getClassificationBadge(status) {
        if (!status) return '';
        var bg = '#F3F4F6', color = '#374151', label = status;
        var upper = String(status).toUpperCase();
        if (upper === 'EXISTING' || upper === 'EXIST') {
            bg = '#EFF6FF'; color = '#2563EB'; label = 'EXISTING';
        } else if (upper === 'EMERGING' || upper === 'EMERGE') {
            bg = '#FEF2F2'; color = '#DC2626'; label = 'EMERGING';
        } else if (upper === 'POTENTIAL') {
            bg = '#F0FDF4'; color = '#16A34A'; label = 'POTENTIAL';
        }
        return '<span style="display:inline-flex;align-items:center;gap:4px;background:' + bg + ';color:' + color + ';padding:2px 8px;border-radius:4px;font-size:10px;font-weight:600;text-transform:uppercase;">' +
            '<i class="fas fa-tag"></i> ' + label + '</span>';
    }

    function createSpotMarker(spot) {
        var icon = getCategoryIcon(spot.category);
        var catColor = getCategoryColor(spot.category);
        var classColor = getClassificationColor(spot.classification_status);
        var lat = parseFloat(spot.latitude);
        var lng = parseFloat(spot.longitude);
        if (isNaN(lat) || isNaN(lng)) {
            void 0;
            return null;
        }
        var divIcon = L.divIcon({
            html: '<div style="background:' + classColor + ';width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 10px rgba(0,0,0,0.35);cursor:pointer;">' +
                '<div style="background:#ffffff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;">' +
                '<i class="fas fa-' + icon + '" style="color:' + catColor + ' !important; font-size:14px !important;"></i>' +
                '</div></div>',
            className: 'spot-marker-icon',
            iconSize: [40, 40],
            iconAnchor: [20, 20]
        });
        var marker = L.marker([lat, lng], { icon: divIcon });
        marker.spotData = spot;
        _dashSpotsById[spot.id] = spot;

        marker.on('click', function (e) {
            L.DomEvent.stopPropagation(e);
            var m = this._map;
            if (m) {
                m.flyTo([lat, lng], 16, { duration: 0.8 });
                m.once('moveend', function () {
                    showDashboardSpotCard(spot);
                });
            }
        });

        marker.bindTooltip(spot.name || 'Unnamed Spot', {
            direction: 'top',
            offset: [0, -22],
            className: 'spot-marker-tooltip',
            opacity: 0.9
        });
        return marker;
    }

    function showDashboardSpotCard(spot) {
        closeDashboardSpotCard();

        var icon = getCategoryIcon(spot.category);
        var bgColor = getCategoryColor(spot.category);
        var muniName = spot.municipality ? spot.municipality.name : (spot.municipality_name || '');
        var admission = spot.entrance_fee ? '₱' + parseFloat(spot.entrance_fee).toLocaleString() : 'Free';
        var hoursLine = formatTime12(spot.opening_time) + ' - ' + formatTime12(spot.closing_time);
        var hasImage = spot.images && spot.images.length > 0;
        var shortDesc = spot.description && spot.description.length > 80
            ? spot.description.substring(0, 80) + '...'
            : (spot.description || 'No description available.');

        var card = document.createElement('div');
        card.id = 'dash-spot-card';
        card.className = 'dash-spot-card';

        card.innerHTML =
            '<button class="dash-spot-card-close" id="dash-spot-card-close">&times;</button>' +
            '<div class="dash-spot-card-inner">' +
            (hasImage ? '<div class="dash-spot-card-img"><img src="' + escapeHtml(spot.images[0].photo_url) + '" alt="' + escapeHtml(spot.name) + '"></div>' :
                '<div class="dash-spot-card-img dash-spot-card-img-placeholder"><i class="fas fa-' + icon + '" style="font-size:36px;color:' + bgColor + ';"></i></div>') +
            '<h3 class="dash-spot-card-name">' + escapeHtml(spot.name || 'Unnamed Spot') + '</h3>' +
            '<div class="dash-spot-card-loc"><i class="fas fa-map-marker-alt"></i> ' + escapeHtml(muniName) + ', La Union</div>' +
            '<div class="dash-spot-card-badges">' +
            '<span class="dash-spot-card-badge" style="background:' + bgColor + ';color:white;"><i class="fas fa-' + icon + '"></i> ' + escapeHtml(spot.category || 'N/A') + '</span>' +
            getClassificationBadge(spot.classification_status) +
            '</div>' +
            '<p class="dash-spot-card-desc">' + escapeHtml(shortDesc) + '</p>' +
            '<div class="dash-spot-card-meta">' +
            '<span><i class="fas fa-ticket-alt"></i> ' + admission + '</span>' +
            '<span><i class="fas fa-clock"></i> ' + hoursLine + '</span>' +
            '</div>' +
            '</div>';

        document.getElementById('dashboard-map').appendChild(card);

        document.getElementById('dash-spot-card-close').addEventListener('click', function (e) {
            e.stopPropagation();
            closeDashboardSpotCard();
        });

        card.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        setTimeout(function () { card.classList.add('open'); }, 10);
    }

    function closeDashboardSpotCard() {
        var card = document.getElementById('dash-spot-card');
        if (card) {
            card.classList.remove('open');
            setTimeout(function () { card.remove(); }, 250);
        }
    }

    function calculateSpotStatus(spot) {
        if (spot.is_maintenance) return 'maintenance';
        if (!spot.opening_time || !spot.closing_time) return 'unknown';
        var now = new Date();
        var currentMinutes = now.getHours() * 60 + now.getMinutes();
        var openParts = String(spot.opening_time).split(':');
        var closeParts = String(spot.closing_time).split(':');
        var openMinutes = parseInt(openParts[0]) * 60 + parseInt(openParts[1]);
        var closeMinutes = parseInt(closeParts[0]) * 60 + parseInt(closeParts[1]);
        if (currentMinutes >= openMinutes && currentMinutes < closeMinutes) return 'open';
        return 'closed';
    }

    function getStatusLabel(status) {
        var map = { open: 'Open Now', closed: 'Closed', maintenance: 'Under Maintenance', unknown: 'Status Unknown' };
        return map[status] || 'Status Unknown';
    }

    function getStatusColor(status) {
        var map = { open: '#22C55E', closed: '#EF4444', maintenance: '#F59E0B', unknown: '#6B7280' };
        return map[status] || '#6B7280';
    }

    function formatTime12(timeStr) {
        if (!timeStr) return 'Not specified';
        var parts = String(timeStr).split(':');
        var h = parseInt(parts[0]);
        var m = parts[1] || '00';
        var ampm = h >= 12 ? 'PM' : 'AM';
        var h12 = h % 12 || 12;
        return h12 + ':' + m + ' ' + ampm;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function _findSpotById(id) {
        return _dashSpotsById[id] || null;
    }

    function openDashboardSpotDetail(spot) {
        closeDashboardSpotDetail();
        var overlay = document.createElement('div');
        overlay.id = 'dash-spot-overlay';
        overlay.className = 'dash-spot-overlay';
        overlay.addEventListener('click', closeDashboardSpotDetail);

        var sidebar = document.createElement('div');
        sidebar.id = 'dash-spot-sidebar';
        sidebar.className = 'dash-spot-sidebar';

        sidebar.innerHTML = '<div class="dash-spot-header">' +
            '<button class="dash-spot-close-btn" id="dash-spot-close">&times;</button>' +
            '<h3 class="dash-spot-title">' + escapeHtml(spot.name || 'Spot Details') + '</h3>' +
            '</div>' +
            '<div class="dash-spot-body" id="dash-spot-body"></div>';

        document.body.appendChild(overlay);
        document.body.appendChild(sidebar);

        document.getElementById('dash-spot-close').addEventListener('click', function (e) {
            e.stopPropagation();
            closeDashboardSpotDetail();
        });

        sidebar.addEventListener('click', function (e) { e.stopPropagation(); });

        document.addEventListener('keydown', dashSpotKeyHandler);

        renderDashboardSpotDetail(spot);

        setTimeout(function () { sidebar.classList.add('open'); overlay.classList.add('open'); }, 10);
    }

    function dashSpotKeyHandler(e) {
        if (e.key === 'Escape') {
            var lb = document.getElementById('dash-lightbox');
            if (lb) { closeDashboardLightbox(); return; }
            closeDashboardSpotDetail();
        }
    }

    function closeDashboardSpotDetail() {
        var sidebar = document.getElementById('dash-spot-sidebar');
        var overlay = document.getElementById('dash-spot-overlay');
        if (sidebar) sidebar.remove();
        if (overlay) overlay.remove();
        document.removeEventListener('keydown', dashSpotKeyHandler);
    }

    function renderDashboardSpotDetail(spot) {
        var body = document.getElementById('dash-spot-body');
        if (!body) return;

        var icon = getCategoryIcon(spot.category);
        var bgColor = getCategoryColor(spot.category);
        var classLabel = getClassificationLabel(spot.classification_status);
        var classColor = getClassificationColor(spot.classification_status);
        var status = calculateSpotStatus(spot);
        var statusLabel = getStatusLabel(status);
        var statusColor = getStatusColor(status);
        var muniName = spot.municipality ? spot.municipality.name : (spot.municipality_name || '');

        var imagesHtml = '';
        var spotImages = (spot.images && spot.images.length > 0) ? spot.images : (spot.photo_url ? [{ photo_url: spot.photo_url }] : []);
        window._dashSpotImages = spotImages;

        if (spotImages.length > 0) {
            imagesHtml += '<div class="dash-spot-gallery">' +
                '<div class="dash-gallery-main"><img src="' + escapeHtml(spotImages[0].photo_url) + '" alt="' + escapeHtml(spot.name) + '" id="dash-gallery-main-img" onclick="MapMarkersConfig.openDashboardLightbox(0)"></div>';
            if (spotImages.length > 1) {
                imagesHtml += '<div class="dash-gallery-thumbs">';
                spotImages.forEach(function (img, i) {
                    imagesHtml += '<div class="dash-gallery-thumb' + (i === 0 ? ' active' : '') + '" onclick="MapMarkersConfig.setDashMainImage(' + i + ')">' +
                        '<img src="' + escapeHtml(img.photo_url) + '" alt="">' +
                        '</div>';
                });
                imagesHtml += '</div>';
            }
            imagesHtml += '</div>';
        } else {
            imagesHtml += '<div class="dash-spot-gallery">' +
                '<div class="dash-gallery-placeholder"><i class="fas fa-' + icon + '" style="font-size:48px;color:' + bgColor + ';"></i></div>' +
                '</div>';
        }

        var descText = spot.description || 'No description available.';
        var admission = spot.entrance_fee ? '₱' + parseFloat(spot.entrance_fee).toLocaleString() : 'Free';
        var hoursDisplay = formatTime12(spot.opening_time) + ' - ' + formatTime12(spot.closing_time);
        var coordsDisplay = parseFloat(spot.latitude).toFixed(6) + ', ' + parseFloat(spot.longitude).toFixed(6);

        body.innerHTML =
            imagesHtml +
            '<div class="dash-spot-meta">' +
            '<div class="dash-spot-location"><i class="fas fa-map-marker-alt"></i> ' + escapeHtml(muniName) + ', La Union</div>' +
            '<div class="dash-spot-badges">' +
            '<span class="dash-badge dash-badge-cat" style="background:' + bgColor + ';"><i class="fas fa-' + icon + '"></i> ' + escapeHtml(spot.category || 'N/A') + '</span>' +
            '<span class="dash-badge dash-badge-class" style="background:' + classColor + ';">' + classLabel + '</span>' +
            '<span class="dash-badge dash-badge-status" style="background:' + statusColor + ';">' + statusLabel + '</span>' +
            '</div>' +
            '</div>' +
            '<div class="dash-spot-desc">' + escapeHtml(descText) + '</div>' +
            '<div class="dash-spot-info-grid">' +
            '<div class="dash-info-card"><div class="dash-info-icon"><i class="fas fa-clock"></i></div><div class="dash-info-label">Operating Hours</div><div class="dash-info-value">' + hoursDisplay + '</div></div>' +
            '<div class="dash-info-card"><div class="dash-info-icon"><i class="fas fa-ticket-alt"></i></div><div class="dash-info-label">Entrance Fee</div><div class="dash-info-value">' + admission + '</div></div>' +
            '<div class="dash-info-card"><div class="dash-info-icon"><i class="fas fa-map-pin"></i></div><div class="dash-info-label">Address</div><div class="dash-info-value">' + escapeHtml(spot.barangay || 'N/A') + ', ' + escapeHtml(muniName) + '</div></div>' +
            '<div class="dash-info-card"><div class="dash-info-icon"><i class="fas fa-globe"></i></div><div class="dash-info-label">Coordinates</div><div class="dash-info-value">' + coordsDisplay + '</div></div>' +
            '<div class="dash-info-card"><div class="dash-info-icon"><i class="fas fa-tag"></i></div><div class="dash-info-label">Category</div><div class="dash-info-value">' + escapeHtml(spot.category || 'N/A') + '</div></div>' +
            '<div class="dash-info-card" style="background:' + classColor + '; color: white;"><div class="dash-info-icon" style="color: white;"><i class="fas fa-layer-group"></i></div><div class="dash-info-label" style="color: rgba(255, 255, 255, 0.8);">Classification</div><div class="dash-info-value" style="font-weight: 700;">' + classLabel + '</div></div>' +
            '</div>' +
            '<a class="dash-spot-directions-btn" href="https://www.google.com/maps/dir/?api=1&destination=' + spot.latitude + ',' + spot.longitude + '" target="_blank" rel="noopener">' +
            '<i class="fas fa-directions"></i> Get Google Maps Directions <i class="fas fa-external-link-alt" style="font-size:10px;margin-left:4px;"></i>' +
            '</a>';
    }

    window.setDashMainImage = function (index) {
        var imgs = window._dashSpotImages || [];
        if (!imgs[index]) return;
        var mainImg = document.getElementById('dash-gallery-main-img');
        if (mainImg) mainImg.src = imgs[index].photo_url;
        var thumbs = document.querySelectorAll('.dash-gallery-thumb');
        thumbs.forEach(function (t, i) { t.classList.toggle('active', i === index); });
        window._dashCurrentImageIndex = index;
    };

    function openDashboardLightbox(index) {
        closeDashboardLightbox();
        var imgs = window._dashSpotImages || [];
        if (!imgs.length) return;
        window._dashLightboxIndex = index;

        var lb = document.createElement('div');
        lb.id = 'dash-lightbox';
        lb.className = 'dash-lightbox';
        lb.innerHTML =
            '<button class="dash-lightbox-close" onclick="MapMarkersConfig.closeDashboardLightbox()">&times;</button>' +
            '<button class="dash-lightbox-nav dash-lightbox-prev" onclick="MapMarkersConfig.navigateDashboardLightbox(-1)"><i class="fas fa-chevron-left"></i></button>' +
            '<div class="dash-lightbox-img-wrap"><img src="' + escapeHtml(imgs[index].photo_url) + '" id="dash-lightbox-img"></div>' +
            '<button class="dash-lightbox-nav dash-lightbox-next" onclick="MapMarkersConfig.navigateDashboardLightbox(1)"><i class="fas fa-chevron-right"></i></button>' +
            '<div class="dash-lightbox-counter">' + (index + 1) + ' / ' + imgs.length + '</div>';
        document.body.appendChild(lb);
        document.body.style.overflow = 'hidden';

        lb.addEventListener('click', function (e) {
            if (e.target === lb) closeDashboardLightbox();
        });

        document.addEventListener('keydown', dashLightboxKeyHandler);
        setTimeout(function () { lb.classList.add('open'); }, 10);
    }

    function closeDashboardLightbox() {
        var lb = document.getElementById('dash-lightbox');
        if (lb) lb.remove();
        document.body.style.overflow = '';
        document.removeEventListener('keydown', dashLightboxKeyHandler);
    }

    function navigateDashboardLightbox(direction) {
        var imgs = window._dashSpotImages || [];
        if (!imgs.length) return;
        var newIdx = (window._dashLightboxIndex + direction + imgs.length) % imgs.length;
        window._dashLightboxIndex = newIdx;
        var lbImg = document.getElementById('dash-lightbox-img');
        if (lbImg) lbImg.src = imgs[newIdx].photo_url;
        var counter = document.querySelector('.dash-lightbox-counter');
        if (counter) counter.textContent = (newIdx + 1) + ' / ' + imgs.length;
    }

    function dashLightboxKeyHandler(e) {
        if (e.key === 'Escape') { closeDashboardLightbox(); }
        if (e.key === 'ArrowLeft') { navigateDashboardLightbox(-1); }
        if (e.key === 'ArrowRight') { navigateDashboardLightbox(1); }
    }

    var LA_UNION_MUNICIPALITIES = [
        'Agoo', 'Aringay', 'Bacnotan', 'Bagulin', 'Balaoan', 'Bangar',
        'Bauang', 'Burgos', 'Caba', 'Luna', 'Naguilian', 'Pugo',
        'Rosario', 'San Fernando City', 'San Gabriel', 'San Juan',
        'Santo Tomas', 'Santol', 'Sudipen', 'Tubao'
    ];

    var MUNICIPALITY_CENTERS = {
        'Agoo': { lat: 16.3261, lng: 120.3638, zoom: 13 },
        'Aringay': { lat: 16.3989, lng: 120.3603, zoom: 13 },
        'Bacnotan': { lat: 16.6083, lng: 120.3464, zoom: 13 },
        'Bagulin': { lat: 16.5694, lng: 120.4667, zoom: 13 },
        'Balaoan': { lat: 16.8286, lng: 120.4083, zoom: 13 },
        'Bangar': { lat: 16.8900, lng: 120.4344, zoom: 13 },
        'Bauang': { lat: 16.5266, lng: 120.3347, zoom: 13 },
        'Burgos': { lat: 16.9478, lng: 120.5011, zoom: 13 },
        'Caba': { lat: 16.2683, lng: 120.3511, zoom: 13 },
        'Luna': { lat: 16.8681, lng: 120.3764, zoom: 13 },
        'Naguilian': { lat: 16.5333, lng: 120.3939, zoom: 13 },
        'Pugo': { lat: 16.4233, lng: 120.4278, zoom: 13 },
        'Rosario': { lat: 16.2131, lng: 120.4506, zoom: 13 },
        'San Fernando City': { lat: 16.6158, lng: 120.3169, zoom: 13 },
        'San Gabriel': { lat: 16.7031, lng: 120.4161, zoom: 13 },
        'San Juan': { lat: 16.6711, lng: 120.4533, zoom: 13 },
        'Santo Tomas': { lat: 16.3497, lng: 120.4261, zoom: 13 },
        'Santol': { lat: 16.7681, lng: 120.4533, zoom: 13 },
        'Sudipen': { lat: 16.7344, lng: 120.4378, zoom: 13 },
        'Tubao': { lat: 16.3747, lng: 120.4022, zoom: 13 }
    };

    function applyFilters(spots, selectedCategories, selectedClassifications, selectedMunicipality) {
        var hasCatFilter = selectedCategories && selectedCategories.length > 0;
        var hasClassFilter = selectedClassifications && selectedClassifications.length > 0;
        var hasMuniFilter = selectedMunicipality && selectedMunicipality !== '';
        return spots.filter(function (spot) {
            if (!spot.latitude || !spot.longitude) return false;
            if (hasCatFilter) {
                var spotCats = String(spot.category || '').split(',').map(function (c) { return c.trim().toLowerCase(); });
                var matchesCat = selectedCategories.some(function (selCat) {
                    return spotCats.indexOf(selCat.toLowerCase()) !== -1;
                });
                if (!matchesCat) return false;
            }
            if (hasClassFilter) {
                if (selectedClassifications.indexOf(spot.classification_status) === -1) return false;
            }
            if (hasMuniFilter) {
                var spotMuni = (spot.municipality_name || (spot.municipality && spot.municipality.name) || '').trim();
                if (spotMuni.toLowerCase() !== selectedMunicipality.toLowerCase()) return false;
            }
            return true;
        });
    }

    function createMapLegend(map) {
        var legend = L.control({ position: 'bottomleft' });
        legend.onAdd = function () {
            var container = L.DomUtil.create('div', 'map-legend-collapsible');


            var header = document.createElement('div');
            header.className = 'mlc-header';
            header.innerHTML = '<span class="mlc-header-icon"><i class="fas fa-list-ul"></i></span>' +
                '<span class="mlc-header-text">Legend</span>' +
                '<span class="mlc-header-chevron"><i class="fas fa-chevron-down"></i></span>';
            container.appendChild(header);

            var body = document.createElement('div');
            body.className = 'mlc-body';

            var classSection = document.createElement('div');
            classSection.className = 'mlc-section';
            classSection.innerHTML = '<div class="mlc-section-title">Classification</div>';
            MAP_CONFIG.ALL_CLASSIFICATIONS.forEach(function (cls) {
                var cfg = MAP_CONFIG.CLASSIFICATION_COLORS[cls];
                var item = document.createElement('div');
                item.className = 'mlc-item';
                item.innerHTML = '<span class="mlc-dot" style="background:' + cfg.color + ';"></span>' +
                    '<span>' + cfg.label + '</span>';
                classSection.appendChild(item);
            });
            body.appendChild(classSection);

            var catSection = document.createElement('div');
            catSection.className = 'mlc-section mlc-section-categories';
            catSection.innerHTML = '<div class="mlc-section-title">Categories</div>';

            var catList = document.createElement('div');
            catList.className = 'mlc-cat-list';
            var seen = {};
            MAP_CONFIG.ALL_CATEGORIES.forEach(function (cat) {
                var icon = getCategoryIcon(cat);
                var color = getCategoryColor(cat);
                var key = cat.toLowerCase();
                if (seen[key]) return;
                seen[key] = true;
                var item = document.createElement('div');
                item.className = 'mlc-item';
                item.innerHTML = '<span class="mlc-icon" style="background:' + color + ';">' +
                    '<i class="fas fa-' + icon + '"></i></span>' +
                    '<span>' + cat + '</span>';
                catList.appendChild(item);
            });
            catSection.appendChild(catList);
            body.appendChild(catSection);

            container.appendChild(body);

            header.addEventListener('click', function (e) {
                e.stopPropagation();
                var isOpen = container.classList.contains('mlc-open');
                if (isOpen) {
                    container.classList.remove('mlc-open');
                } else {
                    container.classList.add('mlc-open');
                }
            });

            container.addEventListener('click', function (e) {
                e.stopPropagation();
            });

            document.addEventListener('click', function () {
                container.classList.remove('mlc-open');
            });

            return container;
        };
        legend.addTo(map);
        return legend;
    }

    function initDashboardMapWithSpots(mapEl, spots) {
        if (!mapEl) return null;
        void 0;

        if (mapEl._leaflet_map) {
            var map = mapEl._leaflet_map;
            if (map._spotCluster) {
                map._spotCluster.clearLayers();
            }
            var filtered = applyFilters(spots,
                map._selectedCategories || [],
                map._selectedClassifications || [],
                map._selectedMunicipality || '');
            void 0;
            filtered.forEach(function (spot) {
                if (map._spotCluster) {
                    var m = createSpotMarker(spot);
                    if (m) map._spotCluster.addLayer(m);
                }
            });
            return map;
        }

        var laUnionBounds;
        if (spots && spots.length > 0) {
            var validSpots = spots.filter(function (s) { return s.latitude && s.longitude; });
            if (validSpots.length > 0) {
                laUnionBounds = L.latLngBounds(validSpots.map(function (s) {
                    return [parseFloat(s.latitude), parseFloat(s.longitude)];
                }));
            } else {
                laUnionBounds = L.latLngBounds([[16.2, 120.2], [16.8, 120.5]]);
            }
        } else {
            laUnionBounds = L.latLngBounds([[16.2, 120.2], [16.8, 120.5]]);
        }
        laUnionBounds = laUnionBounds.pad(0.08);

        var map = L.map(mapEl, {
            maxBounds: laUnionBounds.pad(0.08),
            maxBoundsViscosity: 1.0,
            minZoom: 10,
            worldCopyJump: false
        });

        mapEl._leaflet_map = map;

        var baseLayers = {
            "Street Map": L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19
            }),
            "Satellite View": L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles &copy; Esri',
                maxZoom: 18
            })
        };

        baseLayers["Street Map"].addTo(map);
        L.control.layers(baseLayers).addTo(map);

        map.fitBounds(laUnionBounds);

        var cluster = L.featureGroup();
        map.addLayer(cluster);
        map._spotCluster = cluster;

        map._selectedCategories = [];
        map._selectedClassifications = [];
        map._selectedMunicipality = '';

        var filtered = applyFilters(spots, [], [], '');
        void 0;

        var markerCount = 0;
        filtered.forEach(function (spot) {
            var m = createSpotMarker(spot);
            if (m) {
                cluster.addLayer(m);
                markerCount++;
            }
        });
        void 0;

        createMapLegend(map);

        map._initialBounds = laUnionBounds;
        map.on('click', function () {
            closeDashboardSpotCard();
            if (map._initialBounds) {
                map.fitBounds(map._initialBounds, { padding: [20, 20] });
            }
        });

        return map;
    }

    function updateMapSpots(mapEl, spots) {
        if (!mapEl || !mapEl._leaflet_map) return;
        var map = mapEl._leaflet_map;
        var cluster = map._spotCluster;
        if (!cluster) {
            void 0;
            return;
        }

        var prevZoom = map.getZoom();
        var prevCenter = map.getCenter();

        cluster.clearLayers();

        var filtered = applyFilters(spots,
            map._selectedCategories || [],
            map._selectedClassifications || [],
            map._selectedMunicipality || '');

        void 0;

        var mc = 0;
        filtered.forEach(function (spot) {
            var m = createSpotMarker(spot);
            if (m) { cluster.addLayer(m); mc++; }
        });
        void 0;

        if (!map._selectedMunicipality) {
            map.setView(prevCenter, prevZoom, { animate: false });
        }
    }

    function zoomToMunicipality(map, muniName, spots) {
        if (!muniName) {
            if (map._initialBounds) map.fitBounds(map._initialBounds, { padding: [20, 20], animate: true, duration: 0.8 });
            return;
        }
        // First try to compute bounds from filtered spot markers
        var muniSpots = (spots || []).filter(function (s) {
            var spotMuni = (s.municipality_name || (s.municipality && s.municipality.name) || '').trim();
            return spotMuni.toLowerCase() === muniName.toLowerCase() && s.latitude && s.longitude;
        });
        if (muniSpots.length > 0) {
            var bounds = L.latLngBounds(muniSpots.map(function (s) {
                return [parseFloat(s.latitude), parseFloat(s.longitude)];
            }));
            map.flyToBounds(bounds.pad(0.15), { duration: 0.8 });
        } else if (MUNICIPALITY_CENTERS[muniName]) {
            var c = MUNICIPALITY_CENTERS[muniName];
            map.flyTo([c.lat, c.lng], c.zoom, { duration: 0.8 });
        }
    }

    function buildFilterControls(container, onChange, options) {
        if (!container) return;
        var opts = options || {};
        var hideMunicipality = opts.hideMunicipality || false;

        var muniOptionsHTML = LA_UNION_MUNICIPALITIES.map(function (m) {
            return '<option value="' + m + '">' + m + '</option>';
        }).join('');

        var muniGroupHTML = hideMunicipality ? '' : (
            '  <div class="dm-filter-group">' +
            '    <label class="dm-filter-label"><i class="fas fa-city"></i> Municipality</label>' +
            '    <div class="dm-municipality-wrap" id="dm-muni-wrap">' +
            '      <button class="dm-dropdown-trigger dm-muni-trigger" id="dm-muni-trigger">' +
            '        <i class="fas fa-map-marker-alt" style="font-size:11px;opacity:0.7;"></i>' +
            '        <span id="dm-muni-label">All Municipalities</span>' +
            '        <i class="fas fa-chevron-down"></i>' +
            '      </button>' +
            '      <div class="dm-dropdown-menu dm-muni-menu" id="dm-muni-menu">' +
            '        <div class="dm-muni-search-wrap"><i class="fas fa-search"></i><input type="text" id="dm-muni-search" class="dm-muni-search" placeholder="Search municipality…"></div>' +
            '        <div class="dm-muni-list" id="dm-muni-list">' +
            '          <label class="dm-muni-option dm-muni-all selected" data-muni=""><i class="fas fa-globe" style="margin-right:6px;opacity:0.6;"></i>All Municipalities</label>' +
            muniOptionsHTML.replace(/<option value="([^"]*)">([^<]*)<\/option>/g, function (_, val, label) {
                return '<label class="dm-muni-option" data-muni="' + val + '"><i class="fas fa-map-pin" style="margin-right:6px;opacity:0.5;"></i>' + label + '</label>';
            }) +
            '        </div>' +
            '      </div>' +
            '    </div>' +
            '  </div>'
        );

        var filterHTML =
            '<div class="dashboard-map-filter-bar">' +
            '  <div class="dm-filter-group">' +
            '    <label class="dm-filter-label"><i class="fas fa-layer-group"></i> Classification</label>' +
            '    <div class="dm-class-dropdown" id="dm-class-dropdown">' +
            '      <button class="dm-dropdown-trigger" id="dm-class-trigger">' +
            '        <span>All Classifications</span>' +
            '        <i class="fas fa-chevron-down"></i>' +
            '      </button>' +
            '      <div class="dm-dropdown-menu" id="dm-class-menu">' +
            '        <label class="dm-cat-option" id="dm-class-all">' +
            '          <input type="checkbox" id="dm-class-cb-all" checked> ' +
            '          <span>All Classifications</span>' +
            '        </label>' +
            '        <label class="dm-cat-option">' +
            '          <input type="checkbox" value="EXIST" checked> ' +
            '          <span class="dm-cat-icon" style="background:#3B82F6;"></span>' +
            '          <span>Existing</span>' +
            '        </label>' +
            '        <label class="dm-cat-option">' +
            '          <input type="checkbox" value="EMERGE" checked> ' +
            '          <span class="dm-cat-icon" style="background:#EF4444;"></span>' +
            '          <span>Emerging</span>' +
            '        </label>' +
            '        <label class="dm-cat-option">' +
            '          <input type="checkbox" value="POTENTIAL" checked> ' +
            '          <span class="dm-cat-icon" style="background:#22C55E;"></span>' +
            '          <span>Potential</span>' +
            '        </label>' +
            '      </div>' +
            '    </div>' +
            '  </div>' +
            '  <div class="dm-filter-group">' +
            '    <label class="dm-filter-label"><i class="fas fa-tag"></i> Category</label>' +
            '    <div class="dm-category-dropdown" id="dm-category-dropdown">' +
            '      <button class="dm-dropdown-trigger" id="dm-cat-trigger">' +
            '        <span>All Categories</span>' +
            '        <i class="fas fa-chevron-down"></i>' +
            '      </button>' +
            '      <div class="dm-dropdown-menu" id="dm-cat-menu"></div>' +
            '    </div>' +
            '  </div>' +
            muniGroupHTML +
            '  <div class="dm-filter-group dm-filter-group-actions">' +
            '    <button class="dm-clear-btn" id="dm-clear-filters">' +
            '      <i class="fas fa-times"></i> Clear' +
            '    </button>' +
            '    <span class="dm-filter-count" id="dm-filter-count"></span>' +
            '  </div>' +
            '</div>';

        container.innerHTML = filterHTML;

        var catMenu = document.getElementById('dm-cat-menu');
        var catTrigger = document.getElementById('dm-cat-trigger');
        var selectedCats = [];
        var selectedClasses = ['EXIST', 'EMERGE', 'POTENTIAL'];
        var selectedMunicipality = '';

        MAP_CONFIG.ALL_CATEGORIES.forEach(function (cat) {
            var wrapper = document.createElement('label');
            wrapper.className = 'dm-cat-option';
            wrapper.innerHTML = '<input type="checkbox" value="' + cat + '"> ' +
                '<span class="dm-cat-icon" style="background:' + getCategoryColor(cat) + ';"><i class="fas fa-' + getCategoryIcon(cat) + '"></i></span>' +
                '<span>' + cat + '</span>';
            catMenu.appendChild(wrapper);
        });

        catTrigger.addEventListener('click', function (e) {
            e.stopPropagation();
            var muniMenu = document.getElementById('dm-muni-menu');
            var classMenu = document.getElementById('dm-class-menu');
            if (muniMenu) muniMenu.classList.remove('show');
            if (classMenu) classMenu.classList.remove('show');
            catMenu.classList.toggle('show');
        });

        document.addEventListener('click', function () {
            catMenu.classList.remove('show');
            var muniMenu = document.getElementById('dm-muni-menu');
            if (muniMenu) muniMenu.classList.remove('show');
            var classMenu = document.getElementById('dm-class-menu');
            if (classMenu) classMenu.classList.remove('show');
        });

        catMenu.addEventListener('click', function (e) {
            e.stopPropagation();
            selectedCats = [];
            catMenu.querySelectorAll('input[type="checkbox"]:checked').forEach(function (cb) {
                selectedCats.push(cb.value);
            });
            updateCatTrigger();
            if (typeof onChange === 'function') onChange(selectedCats, selectedClasses, selectedMunicipality);
        });

        function updateCatTrigger() {
            var span = catTrigger.querySelector('span');
            if (selectedCats.length === 0 || selectedCats.length === MAP_CONFIG.ALL_CATEGORIES.length) {
                span.textContent = 'All Categories';
            } else if (selectedCats.length <= 2) {
                span.textContent = selectedCats.join(', ');
            } else {
                span.textContent = selectedCats.length + ' categories';
            }
            updateFilterCount();
        }

        // ── Classification dropdown ──
        var classTrigger = document.getElementById('dm-class-trigger');
        var classMenu = document.getElementById('dm-class-menu');
        var classAllCb = document.getElementById('dm-class-cb-all');

        classTrigger.addEventListener('click', function (e) {
            e.stopPropagation();
            catMenu.classList.remove('show');
            var muniMenu = document.getElementById('dm-muni-menu');
            if (muniMenu) muniMenu.classList.remove('show');
            classMenu.classList.toggle('show');
        });

        classMenu.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        function syncClassState() {
            var checked = [];
            classMenu.querySelectorAll('input[type="checkbox"]:not(#dm-class-cb-all):checked').forEach(function (cb) {
                checked.push(cb.value);
            });
            // If all 3 checked or none checked → treat as "all"
            if (checked.length === 0 || checked.length === 3) {
                selectedClasses = ['EXIST', 'EMERGE', 'POTENTIAL'];
                if (classAllCb) classAllCb.checked = true;
            } else {
                selectedClasses = checked;
                if (classAllCb) classAllCb.checked = false;
            }
            updateClassTrigger();
            if (typeof onChange === 'function') onChange(selectedCats, selectedClasses, selectedMunicipality);
            updateFilterCount();
        }

        // Handle "All Classifications" master checkbox
        if (classAllCb) {
            classAllCb.addEventListener('change', function (e) {
                e.stopPropagation();
                var checked = classAllCb.checked;
                classMenu.querySelectorAll('input[type="checkbox"]:not(#dm-class-cb-all)').forEach(function (cb) {
                    cb.checked = checked;
                });
                selectedClasses = ['EXIST', 'EMERGE', 'POTENTIAL'];
                updateClassTrigger();
                if (typeof onChange === 'function') onChange(selectedCats, selectedClasses, selectedMunicipality);
                updateFilterCount();
            });
        }

        // Handle individual class checkboxes
        classMenu.querySelectorAll('input[type="checkbox"]:not(#dm-class-cb-all)').forEach(function (cb) {
            cb.addEventListener('change', function (e) {
                e.stopPropagation();
                syncClassState();
            });
        });

        function updateClassTrigger() {
            var span = classTrigger.querySelector('span');
            if (selectedClasses.length === 3) {
                span.textContent = 'All Classifications';
            } else if (selectedClasses.length === 0) {
                span.textContent = 'All Classifications';
            } else {
                var LABELS = { EXIST: 'Existing', EMERGE: 'Emerging', POTENTIAL: 'Potential' };
                span.textContent = selectedClasses.map(function (c) { return LABELS[c] || c; }).join(', ');
            }
        }

        // Municipality dropdown
        if (!hideMunicipality) {
            var muniTrigger = document.getElementById('dm-muni-trigger');
            var muniMenu = document.getElementById('dm-muni-menu');
            var muniList = document.getElementById('dm-muni-list');
            var muniSearch = document.getElementById('dm-muni-search');

            muniTrigger.addEventListener('click', function (e) {
                e.stopPropagation();
                catMenu.classList.remove('show');
                muniMenu.classList.toggle('show');
                if (muniMenu.classList.contains('show') && muniSearch) {
                    setTimeout(function () { muniSearch.focus(); }, 50);
                }
            });

            muniMenu.addEventListener('click', function (e) {
                e.stopPropagation();
                var opt = e.target.closest('.dm-muni-option');
                if (!opt) return;
                muniList.querySelectorAll('.dm-muni-option').forEach(function (o) { o.classList.remove('selected'); });
                opt.classList.add('selected');
                selectedMunicipality = opt.getAttribute('data-muni') || '';
                var muniLabel = document.getElementById('dm-muni-label');
                if (muniLabel) muniLabel.textContent = selectedMunicipality || 'All Municipalities';
                muniMenu.classList.remove('show');
                updateFilterCount();
                if (typeof onChange === 'function') onChange(selectedCats, selectedClasses, selectedMunicipality);
            });

            if (muniSearch) {
                muniSearch.addEventListener('input', function (e) {
                    e.stopPropagation();
                    var q = muniSearch.value.toLowerCase();
                    muniList.querySelectorAll('.dm-muni-option').forEach(function (opt) {
                        var name = (opt.getAttribute('data-muni') || 'All Municipalities').toLowerCase();
                        opt.style.display = name.includes(q) || opt.classList.contains('dm-muni-all') ? '' : 'none';
                    });
                });
                muniSearch.addEventListener('click', function (e) { e.stopPropagation(); });
                muniSearch.addEventListener('keydown', function (e) { e.stopPropagation(); });
            }
        }

        document.getElementById('dm-clear-filters').addEventListener('click', function () {
            catMenu.querySelectorAll('input[type="checkbox"]').forEach(function (cb) { cb.checked = false; });
            selectedCats = [];
            selectedClasses = ['EXIST', 'EMERGE', 'POTENTIAL'];
            selectedMunicipality = '';
            // Reset classification checkboxes
            classMenu.querySelectorAll('input[type="checkbox"]').forEach(function (cb) { cb.checked = true; });
            updateClassTrigger();
            updateCatTrigger();
            if (!hideMunicipality) {
                var muniLabel = document.getElementById('dm-muni-label');
                if (muniLabel) muniLabel.textContent = 'All Municipalities';
                var muniList = document.getElementById('dm-muni-list');
                if (muniList) {
                    muniList.querySelectorAll('.dm-muni-option').forEach(function (o) { o.classList.remove('selected'); });
                    var allOpt = muniList.querySelector('.dm-muni-all');
                    if (allOpt) allOpt.classList.add('selected');
                }
            }
            if (typeof onChange === 'function') onChange(selectedCats, selectedClasses, selectedMunicipality);
        });

        function updateFilterCount() {
            var countEl = document.getElementById('dm-filter-count');
            if (!countEl) return;
            var hasCat = selectedCats.length > 0 && selectedCats.length < MAP_CONFIG.ALL_CATEGORIES.length;
            var hasClass = selectedClasses.length < 3;
            var hasMuni = selectedMunicipality !== '';
            if (hasCat || hasClass || hasMuni) {
                var parts = [];
                if (hasMuni) parts.push(selectedMunicipality);
                else if (hasCat || hasClass) parts.push('Filtered');
                countEl.textContent = parts.join(' · ');
                countEl.classList.add('active');
            } else {
                countEl.textContent = '';
                countEl.classList.remove('active');
            }
        }
    }

    window.MapMarkersConfig = {
        MAP_CONFIG: MAP_CONFIG,
        LA_UNION_MUNICIPALITIES: LA_UNION_MUNICIPALITIES,
        MUNICIPALITY_CENTERS: MUNICIPALITY_CENTERS,
        getCategoryIcon: getCategoryIcon,
        getCategoryColor: getCategoryColor,
        getClassificationColor: getClassificationColor,
        getClassificationLabel: getClassificationLabel,
        createSpotMarker: createSpotMarker,
        applyFilters: applyFilters,
        createMapLegend: createMapLegend,
        initDashboardMapWithSpots: initDashboardMapWithSpots,
        updateMapSpots: updateMapSpots,
        buildFilterControls: buildFilterControls,
        zoomToMunicipality: zoomToMunicipality,
        openDashboardSpotDetail: openDashboardSpotDetail,
        closeDashboardSpotDetail: closeDashboardSpotDetail,
        openDashboardLightbox: openDashboardLightbox,
        closeDashboardLightbox: closeDashboardLightbox,
        navigateDashboardLightbox: navigateDashboardLightbox,
        closeDashboardSpotCard: closeDashboardSpotCard,
        showDashboardSpotCard: showDashboardSpotCard,
        setDashMainImage: window.setDashMainImage,
        _findSpotById: _findSpotById
    };
})();
