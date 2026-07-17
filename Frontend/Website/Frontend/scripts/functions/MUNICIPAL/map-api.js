// LUPTO Municipal Map View
// Expects two globals to be defined by an inline <script> in the PHP page before this file loads:
//   window.municipalityData -> the municipality row (object)
//   window.touristSpotsData -> the raw spots array (with images) from the DB

document.addEventListener('DOMContentLoaded', function() {
    // Normalize data inputs to prevent script crashes on backend error responses
    window.touristSpotsData = Array.isArray(window.touristSpotsData) ? window.touristSpotsData : [];
    window.municipalityData = window.municipalityData || {};

    // Data from database (set by inline script in the PHP page)
    const municipality = window.municipalityData;
    const statusMap = {
        'EXIST': 'EXISTING',
        'EMERGE': 'EMERGING',
        'POTENTIAL': 'POTENTIAL'
    };

    let touristSpots = window.touristSpotsData
        .filter(spot => spot.status === 'approved')
        .map(spot => {
        let normalizedImages = [];
        if (spot.images && spot.images.length > 0) {
            normalizedImages = spot.images;
        } else if (spot.photo_url) {
            normalizedImages = [{ photo_url: spot.photo_url }];
        }
        return {
            id: spot.id,
            name: spot.name,
            municipality: spot.municipality_name,
            lat: spot.latitude || municipality.latitude,
            lng: spot.longitude || municipality.longitude,
            description: spot.description || 'No description available.',
            history: '',
            geography: '',
            category: spot.category,
            image: 'municipality',
            images: normalizedImages,
            openingHours: spot.opening_time && spot.closing_time ? `${spot.opening_time.substring(0, 5)} - ${spot.closing_time.substring(0, 5)}` : 'Contact for details',
            admissionFee: (() => { const ft = Array.isArray(spot.fee_types) ? spot.fee_types : []; if (!ft || ft.length === 0) return 'Free'; let p = []; if (ft.includes('entrance') && Number(spot.entrance_fee||0) > 0) p.push('₱'+spot.entrance_fee); if (ft.includes('environmental') && Number(spot.environmental_fee||0) > 0) p.push('EF:₱'+spot.environmental_fee); return p.length ? p.join(' | ') : 'Free'; })(),
            rating: spot.rating || 0,
            isMaintenance: spot.is_maintenance == 1,
            classification_status: statusMap[spot.classification_status] || spot.classification_status,
            amenities: [],
            reviews: []
        };
    });

    let map, markerCluster, spotMarkers = [], currentRouteLayer = null, userLocation = null;
    let selectedSpot = null, currentTravelMode = 'driving';
    let sidebarState = 'spots'; // 'spots' or 'spot'
    let currentBaseLayer = 'street';
    let currentMapLayer = null;
    let satelliteLabelsLayer = null;

    window.refreshMunicipalMap = function() {
        void 0;
        const rawSpots = Array.isArray(window.touristSpotsData) ? window.touristSpotsData : [];
        touristSpots = rawSpots
            .filter(spot => spot.status === 'approved')
            .map(spot => {
                let normalizedImages = [];
                if (spot.images && spot.images.length > 0) {
                    normalizedImages = spot.images;
                } else if (spot.photo_url) {
                    normalizedImages = [{ photo_url: spot.photo_url }];
                }
                return {
                    id: spot.id,
                    name: spot.name,
                    municipality: spot.municipality_name,
                    lat: spot.latitude || municipality.latitude,
                    lng: spot.longitude || municipality.longitude,
                    description: spot.description || 'No description available.',
                    history: '',
                    geography: '',
                    category: spot.category,
                    image: 'municipality',
                    images: normalizedImages,
                    openingHours: spot.opening_time && spot.closing_time ? `${spot.opening_time.substring(0, 5)} - ${spot.closing_time.substring(0, 5)}` : 'Contact for details',
                    admissionFee: (() => { const ft = Array.isArray(spot.fee_types) ? spot.fee_types : []; if (!ft || ft.length === 0) return 'Free'; let p = []; if (ft.includes('entrance') && Number(spot.entrance_fee||0) > 0) p.push('₱'+spot.entrance_fee); if (ft.includes('environmental') && Number(spot.environmental_fee||0) > 0) p.push('EF:₱'+spot.environmental_fee); return p.length ? p.join(' | ') : 'Free'; })(),
                    rating: spot.rating || 0,
                    isMaintenance: spot.is_maintenance == 1,
                    classification_status: statusMap[spot.classification_status] || spot.classification_status,
                    amenities: [],
                    reviews: []
                };
            });

        if (map) {
            addSpotMarkers();
        }
    };

    const fallbackLat = 16.3278;
    const fallbackLng = 120.3663;
    const muniLat = municipality.latitude || fallbackLat;
    const muniLng = municipality.longitude || fallbackLng;
    const muniBounds = L.latLngBounds([[muniLat - 0.2, muniLng - 0.2], [muniLat + 0.2, muniLng + 0.2]]);

    const mapLayers = {
        street: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19
        }),
        satellite: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri',
            maxZoom: 18
        })
    };

    satelliteLabelsLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Reference_Overlay/MapServer/tile/{z}/{y}/{x}', {
        attribution: '',
        maxZoom: 18
    });

    // Initialize Map
    function initMap() {
        if (map) {
            // Already initialized. Clear markers and redraw.
            if (markerCluster) {
                markerCluster.clearLayers();
            }
            addSpotMarkers();
            setTimeout(() => map.invalidateSize(), 300);
            return;
        }

        // Fallback coordinates if municipality doesn't have them
        const fallbackLat = 16.3278;
        const fallbackLng = 120.3663;

        const muniLat = municipality.latitude || fallbackLat;
        const muniLng = municipality.longitude || fallbackLng;

        map = L.map('lupto-map', {
            minZoom: 8,
            worldCopyJump: false
        });
        setMapLayer('street');
        map.setView([muniLat, muniLng], 12);

        // Save map instance for the SPA router
        document.getElementById('lupto-map')._leaflet_map = map;

        // Initialize cluster group
        markerCluster = L.featureGroup();
        map.addLayer(markerCluster);

        // Add spot markers
        addSpotMarkers();

        // Get user location
        getUserLocation();

        // Add event listeners
        setupEventListeners();

        // Lazy load popup images when they open
        map.on('popupopen', function(e) {
            const popupNode = e.popup.getElement();
            if (popupNode) {
                const lazyImgs = popupNode.querySelectorAll('.popup-lazy-img');
                lazyImgs.forEach(img => {
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                });
            }
        });

        // Show sidebar with spots list
        openSidebar('spots', null);
    }

    function getClassificationBadge(status) {
        if (!status) return '';
        let bg = '#F3F4F6', color = '#374151';
        const upper = status.toUpperCase();
        if (upper === 'EXISTING' || upper === 'EXIST') {
            bg = '#F0FDF4'; color = '#16A34A'; status = 'EXISTING';
        } else if (upper === 'EMERGING' || upper === 'EMERGE') {
            bg = '#EFF6FF'; color = '#2563EB'; status = 'EMERGING';
        } else if (upper === 'POTENTIAL') {
            bg = '#FFFBEB'; color = '#D97706'; status = 'POTENTIAL';
        }
        return `<div style="display: inline-flex; align-items: center; gap: 3px; background: ${bg}; color: ${color}; padding: 1.5px 5px; border-radius: 4px; font-size: 9px; font-weight: 600; text-transform: uppercase;">
            <i class="fas fa-tag" style="font-size: 8px;"></i> ${status}
        </div>`;
    }

    function addSpotMarkers() {
        // Clear existing spot markers from cluster and map
        if (markerCluster) {
            markerCluster.clearLayers();
        }
        spotMarkers.forEach(marker => map.removeLayer(marker));
        spotMarkers = [];
        activeSpotMarker = null;

        // Progressive rendering of spots in chunks
        let index = 0;
        const chunkSize = 50;

        function loadNextChunk() {
            const chunk = touristSpots.slice(index, index + chunkSize);
            chunk.forEach(spot => {
                const iconColor = getCategoryColor(spot.category);
                const icon = L.divIcon({
                    html: `<div class="spot-marker" style="background:${iconColor}; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-weight:bold; border:3px solid white; box-shadow:0 2px 8px rgba(0,0,0,0.3);"><i class="fas fa-${getCategoryIcon(spot.category)}" style="font-size:16px;"></i></div>`,
                    className: 'spot-marker-icon',
                    iconSize: [32, 32],
                    iconAnchor: [16, 32],
                    popupAnchor: [0, -32]
                });

                const marker = L.marker([spot.lat, spot.lng], { icon: icon, spotData: spot });

                // Build rich popup content (lazy images)
                const hasImage = spot.images && spot.images.length > 0;
                const shortDesc = spot.description && spot.description.length > 60 
                    ? spot.description.substring(0, 60) + '...' 
                    : spot.description || 'No description available.';
                const popupHtml = `
                    <div class="map-popup-card" style="font-family: inherit; width: 180px; padding: 2px;">
                        ${hasImage ? `<img data-src="${spot.images[0].photo_url}" class="popup-lazy-img" style="width:100%; height:80px; object-fit:cover; border-radius:6px; margin-bottom:6px; background:#F3F4F6;" alt="${spot.name}">` : ''}
                        <h4 style="margin: 0 0 2px 0; font-size: 12.5px; font-weight: 700; color: #1E293B;">${spot.name}</h4>
                        <div style="display: flex; gap: 3px; flex-wrap: wrap; margin-bottom: 6px;">
                            <div style="display: inline-flex; align-items: center; gap: 3px; background: #EEF2FF; color: #2563EB; padding: 1.5px 5px; border-radius: 4px; font-size: 9px; font-weight: 600; text-transform: uppercase;">
                                <i class="fas fa-${getCategoryIcon(spot.category)}" style="font-size: 8px;"></i> ${spot.category}
                            </div>
                            ${getClassificationBadge(spot.classification_status)}
                        </div>
                        <p style="margin: 0 0 6px 0; font-size: 11px; color: #4B5563; line-height: 1.3;">${shortDesc}</p>
                        <div style="font-size: 10.5px; color: #4B5563; margin-bottom: 6px; line-height: 1.3;">
                            <i class="fas fa-ticket-alt" style="margin-right: 4px; color: #6B7280;"></i> ${spot.admissionFee}<br>
                            <i class="fas fa-clock" style="margin-right: 4px; color: #6B7280;"></i> ${spot.openingHours}
                        </div>
                        <button class="popup-detail-btn" onclick="window.viewSpotDetailsFromMap(${spot.id})" style="width: 100%; padding: 4px 6px; background: #2563EB; color: white; border: none; border-radius: 6px; font-size: 11px; font-weight: 600; cursor: pointer; transition: background 0.2s;">
                            <i class="fas fa-info-circle"></i> View Full Details
                        </button>
                    </div>
                `;

                marker.bindPopup(popupHtml, {
                    maxWidth: 200,
                    className: 'custom-map-popup'
                });

                marker.on('click', () => handleSpotClick(spot));
                
                if (markerCluster) {
                    markerCluster.addLayer(marker);
                } else {
                    marker.addTo(map);
                }
                spotMarkers.push(marker);
            });

            index += chunkSize;
            if (index < touristSpots.length) {
                setTimeout(loadNextChunk, 50);
            }
        }

        loadNextChunk();
    }

    // Custom global callback to bridge Leaflet popup button clicks to the JS sidebar behavior
    window.viewSpotDetailsFromMap = function(spotId) {
        const spot = touristSpots.find(s => s.id === spotId);
        if (spot) {
            handleSpotClick(spot);
        }
    };

    function getUserLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    userLocation = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };
                    // Add user location marker
                    const userIcon = L.divIcon({
                        html: `<div style="background:#22C55E; width:24px; height:24px; border-radius:50%; border:3px solid white; box-shadow:0 2px 8px rgba(0,0,0,0.3);"></div>`,
                        className: 'user-location',
                        iconSize: [24, 24],
                        iconAnchor: [12, 12]
                    });
                    L.marker([userLocation.lat, userLocation.lng], { icon: userIcon }).addTo(map);
                },
                () => {
                    void 0;
                }
            );
        }
    }

    function setMapLayer(view) {
        if (currentMapLayer && map.hasLayer(currentMapLayer)) {
            map.removeLayer(currentMapLayer);
        }

        if (satelliteLabelsLayer && map.hasLayer(satelliteLabelsLayer)) {
            map.removeLayer(satelliteLabelsLayer);
        }

        currentBaseLayer = view;
        currentMapLayer = mapLayers[view];
        currentMapLayer.addTo(map);

        if (view === 'satellite') {
            satelliteLabelsLayer.addTo(map);
        }
    }

    function getCategoryColor(categoryStr) {
        if (window.MapMarkersConfig && typeof window.MapMarkersConfig.getCategoryColor === 'function') {
            return window.MapMarkersConfig.getCategoryColor(categoryStr);
        }
        if (!categoryStr) return '#6B7280';
        const categories = categoryStr.split(',').map(c => c.trim().toLowerCase());
        const blue = '#0EA5E9'; const cyan = '#06B6D4'; const green = '#22C55E';
        const purple = '#8B5CF6'; const amber = '#F59E0B'; const pink = '#EC4899'; const gold = '#D97706';
        const mapping = {
            'beach': blue, 'beaches': blue, 'island': blue, 'marine sanctuary': blue, 'hot spring': blue, 'cold spring': blue,
            'waterfall': cyan, 'waterfalls': cyan, 'river': cyan, 'lake': cyan,
            'forest': green, 'nature park': green, 'wildlife sanctuary': green, 'farm': green,
            'eco-tourism': green, 'garden': green, 'park': green,
            'mountain': purple, 'mountains': purple, 'cave': purple, 'volcano': purple,
            'hiking': purple, 'camping': purple, 'viewpoint': purple, 'binoculars': purple,
            'historical': amber, 'cultural heritage': amber, 'museum': amber, 'monument': amber, 'landmark': amber,
            'religious': gold, 'church': gold,
            'adventure': pink, 'recreation': pink, 'food destination': pink,
            'shopping': pink, 'festival venue': pink, 'resort': pink, 'resorts': pink
        };
        for (const cat of categories) { if (mapping[cat]) return mapping[cat]; }
        return '#6B7280';
    }

    function getCategoryIcon(categoryStr) {
        if (window.MapMarkersConfig && typeof window.MapMarkersConfig.getCategoryIcon === 'function') {
            return window.MapMarkersConfig.getCategoryIcon(categoryStr);
        }
        if (!categoryStr) return 'map-marker-alt';
        const categories = categoryStr.split(',').map(c => c.trim().toLowerCase());
        const mapping = {
            'beach': 'umbrella-beach', 'beaches': 'umbrella-beach',
            'mountain': 'mountain', 'mountains': 'mountain',
            'waterfall': 'water', 'waterfalls': 'water', 'river': 'water', 'lake': 'water',
            'island': 'umbrella-beach', 'cave': 'mountain', 'volcano': 'mountain',
            'forest': 'tree', 'nature park': 'tree', 'marine sanctuary': 'fish', 'wildlife sanctuary': 'paw',
            'historical': 'landmark', 'cultural heritage': 'landmark', 'religious': 'church',
            'museum': 'museum', 'monument': 'monument', 'landmark': 'landmark', 'viewpoint': 'binoculars',
            'adventure': 'hiking', 'hiking': 'hiking', 'camping': 'campground', 'farm': 'seedling',
            'eco-tourism': 'leaf', 'garden': 'seedling', 'park': 'tree', 'recreation': 'bicycle',
            'hot spring': 'hot-tub-person', 'cold spring': 'snowflake',
            'food destination': 'utensils', 'shopping': 'shopping-cart',
            'festival venue': 'masks-theater', 'resort': 'hotel', 'resorts': 'hotel', 'other': 'star'
        };
        for (const cat of categories) { if (mapping[cat]) return mapping[cat]; }
        return 'map-marker-alt';
    }

    function openSidebar(type, data) {
        const sidebar = document.getElementById('sidebarContainer');
        const overlay = document.getElementById('sidebarOverlay');
        const content = document.getElementById('sidebarContent');
        const title = document.getElementById('sidebarTitle');

        if (type === 'spots') {
            title.textContent = 'Tourist Spots (' + touristSpots.length + ')';
        } else {
            title.textContent = data.name;
        }

        content.innerHTML = `
            <div class="loading-state">
                <div class="loading-spinner"></div>
                <p style="color:#6B7280;">Loading...</p>
            </div>
        `;

        setTimeout(() => {
            sidebar.classList.add('active');
            overlay.classList.add('active');

            setTimeout(() => {
                if (type === 'spots') {
                    populateSpotsList();
                } else {
                    populateSpotDetail(data);
                }
            }, 200);
        }, 100);
    }

    function populateSpotsList() {
        const content = document.getElementById('sidebarContent');
        content.innerHTML = `
            <div class="spot-list">
                ${touristSpots.map(spot => {
                    const hasImage = spot.images && spot.images.length > 0;
                    return `
                    <div class="spot-card" data-spot-id="${spot.id}" style="cursor: pointer;">
                        <div class="spot-thumbnail">
                            ${hasImage ? `<img src="${spot.images[0].photo_url}" alt="${spot.name}">` : `<i class="fas fa-${getCategoryIcon(spot.category)}"></i>`}
                            <div class="spot-category-tag">${spot.category}</div>
                        </div>
                        <div class="spot-info">
                            <h4>${spot.name}</h4>
                            <p>${spot.description.substring(0, 100)}...</p>
                            <div class="spot-meta">
                                <div class="spot-meta-item">
                                    <i class="fas fa-star" style="color: #F59E0B;"></i>
                                    <span>${spot.rating}</span>
                                </div>
                                <div class="spot-meta-item">
                                    <i class="fas fa-ticket-alt"></i>
                                    <span>${spot.admissionFee}</span>
                                </div>
                                ${spot.isMaintenance ? `
                                <div class="spot-meta-item" style="color:#F59E0B;">
                                    <i class="fas fa-tools"></i>
                                    <span>Maintenance</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `}).join('')}
            </div>
        `;

        document.querySelectorAll('.spot-card').forEach(card => {
            card.addEventListener('click', () => {
                const spotId = parseInt(card.dataset.spotId);
                const spot = touristSpots.find(s => s.id === spotId);
                if (spot) {
                    handleSpotClick(spot);
                }
            });
        });
    }

    function handleSpotClick(spot) {
        selectedSpot = spot;
        sidebarState = 'spot';

        map.setView([spot.lat, spot.lng], 15);

        document.getElementById('sidebarBackBtn').classList.remove('hidden');

        // Just update sidebar with spot info (removed polyline routing line)
        populateSpotDetail(spot);
    }

    function populateSpotDetail(spot) {
        const content = document.getElementById('sidebarContent');
        // Check if we have images
        const hasImages = spot.images && spot.images.length > 0;

        content.innerHTML = `
            ${hasImages ? `
            <div class="spot-image-gallery">
                <div class="spot-main-image">
                    <img src="${spot.images[0].photo_url}" alt="${spot.name}" class="main-gallery-image" onclick="openLightbox(0)">
                </div>
                ${spot.images.length > 1 ? `
                <div class="spot-thumbnails">
                    ${spot.images.map((img, idx) => `
                        <div class="spot-thumbnail ${idx === 0 ? 'active' : ''}" onclick="setMainImage(${idx}, this)">
                            <img src="${img.photo_url}" alt="Thumbnail">
                        </div>
                    `).join('')}
                </div>
                ` : ''}
            </div>
            ` : `
            <div class="spot-detail-image">
                <i class="fas fa-${getCategoryIcon(spot.category)}"></i>
            </div>
            `}

            <h2 class="spot-detail-title">${spot.name}</h2>
            <div class="spot-detail-location">
                <i class="fas fa-map-marker-alt" style="color: #2563EB;"></i>
                ${spot.municipality}, La Union
            </div>

            ${spot.isMaintenance ? `
            <div style="background:#FFFBEB;border:1px solid #F59E0B;border-radius:8px;padding:10px;margin-bottom:16px;display:flex;gap:10px;align-items:center;">
                <i class="fas fa-tools" style="color:#F59E0B;font-size:18px;"></i>
                <div style="font-size:13px;color:#92400E;">
                    <strong>Under Maintenance</strong><br>
                    This spot is temporarily unavailable.
                </div>
            </div>
            ` : ''}

            <div class="spot-detail-description">${spot.description}</div>

            <div class="info-grid">
                <div class="info-card">
                    <div class="info-card-label">Opening Hours</div>
                    <div class="info-card-value">${spot.openingHours}</div>
                </div>
                <div class="info-card">
                    <div class="info-card-label">Admission</div>
                    <div class="info-card-value">${spot.admissionFee}</div>
                </div>
                ${spot.classification_status ? `
                <div class="info-card">
                    <div class="info-card-label">Classification</div>
                    <div class="info-card-value">${spot.classification_status}</div>
                </div>
                ` : ''}
            </div>

            <div class="section-header">Amenities</div>
            <div class="amenities-grid">
                ${spot.amenities.length > 0 ? spot.amenities.map(amenity => `
                    <div class="amenity-item">
                        <i class="fas fa-check-circle"></i>
                        <div>${amenity}</div>
                    </div>
                `).join('') : '<p style="color:#9CA3AF;">No amenities listed</p>'}
            </div>

            <div class="reviews-section">
                <div class="section-header">
                    <i class="fas fa-star" style="color: #F59E0B;"></i>
                    Rating (${spot.rating})
                </div>
                ${spot.reviews.length > 0 ? spot.reviews.map(review => `
                    <div class="review-card">
                        <div class="review-header">
                            <div class="reviewer-name">${review.author}</div>
                            <div class="review-rating">
                                ${'★'.repeat(Math.floor(spot.rating))}${'☆'.repeat(5 - Math.floor(spot.rating))}
                            </div>
                        </div>
                        <div class="review-text">${review.text}</div>
                    </div>
                `).join('') : '<p style="color:#9CA3AF;">No reviews yet</p>'}
            </div>

            ${userLocation ? `
                <div class="route-info">
                    <div class="route-info-header">
                        <h4><i class="fas fa-directions"></i> Directions</h4>
                    </div>
                    <div class="travel-modes">
                        <button class="travel-mode-btn ${currentTravelMode === 'driving' ? 'active' : ''}" data-mode="driving">
                            <i class="fas fa-car"></i>
                            Driving
                        </button>
                        <button class="travel-mode-btn ${currentTravelMode === 'walking' ? 'active' : ''}" data-mode="walking">
                            <i class="fas fa-walking"></i>
                            Walking
                        </button>
                    </div>
                    <div class="travel-details">
                        <div class="travel-detail">
                            <div class="travel-detail-label">Distance</div>
                            <div class="travel-detail-value">${calculateDistance(userLocation, spot).toFixed(1)} km</div>
                        </div>
                        <div class="travel-detail">
                            <div class="travel-detail-label">Est. Time</div>
                            <div class="travel-detail-value">
                                ${currentTravelMode === 'driving' ? Math.round(calculateDistance(userLocation, spot) * 3) : Math.round(calculateDistance(userLocation, spot) * 15)} min
                            </div>
                        </div>
                    </div>
                </div>
            ` : `
                <div class="route-info">
                    <p style="text-align: center; color: #9CA3AF; font-size: 13px;">
                        <i class="fas fa-info-circle"></i> Enable location services to get directions
                    </p>
                </div>
            `}
        `;

        // Set currentImages and main image index for lightbox
        window.currentSpotImages = spot.images;
        window.currentImageIndex = 0;

        document.querySelectorAll('.travel-mode-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                currentTravelMode = btn.dataset.mode;
                if (userLocation) {
                    showRoute(spot);
                } else {
                    populateSpotDetail(spot);
                }
            });
        });
    }

    window.setMainImage = function(index, thumbnail) {
        // Update main image
        const mainImg = document.querySelector('.main-gallery-image');
        if (mainImg) {
            mainImg.src = window.currentSpotImages[index].photo_url;
        }
        // Update active thumbnail
        document.querySelectorAll('.spot-thumbnail').forEach(el => el.classList.remove('active'));
        if (thumbnail) {
            thumbnail.classList.add('active');
        }
        window.currentImageIndex = index;
    };

    window.openLightbox = function(index) {
        window.currentLightboxImages = window.currentSpotImages;
        window.currentLightboxIndex = index;
        // Create lightbox
        const lightboxDiv = document.createElement('div');
        lightboxDiv.id = 'imageLightbox';
        lightboxDiv.className = 'lightbox-overlay';
        lightboxDiv.innerHTML = `
            <button class="lightbox-close" onclick="closeLightbox()"><i class="fas fa-times"></i></button>
            <button class="lightbox-nav lightbox-prev" onclick="navigateLightbox(-1)"><i class="fas fa-chevron-left"></i></button>
            <div class="lightbox-image-container">
                <img id="lightboxImage" src="${window.currentSpotImages[index].photo_url}" alt="Full size image">
            </div>
            <button class="lightbox-nav lightbox-next" onclick="navigateLightbox(1)"><i class="fas fa-chevron-right"></i></button>
            <div class="lightbox-counter">${index + 1} / ${window.currentSpotImages.length}</div>
        `;
        document.body.appendChild(lightboxDiv);
        document.body.style.overflow = 'hidden';
        // Close on background click
        lightboxDiv.addEventListener('click', function(e) {
            if (e.target === lightboxDiv) closeLightbox();
        });
        // Keyboard navigation
        document.addEventListener('keydown', lightboxKeyHandler);
    };

    window.navigateLightbox = function(direction) {
        window.currentLightboxIndex = (window.currentLightboxIndex + direction + window.currentLightboxImages.length) % window.currentLightboxImages.length;
        document.getElementById('lightboxImage').src = window.currentLightboxImages[window.currentLightboxIndex].photo_url;
        document.querySelector('.lightbox-counter').textContent = `${window.currentLightboxIndex + 1} / ${window.currentLightboxImages.length}`;
    };

    window.closeLightbox = function() {
        const lightbox = document.getElementById('imageLightbox');
        if (lightbox) {
            lightbox.remove();
            document.body.style.overflow = '';
            document.removeEventListener('keydown', lightboxKeyHandler);
        }
    };

    function lightboxKeyHandler(e) {
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft') navigateLightbox(-1);
        if (e.key === 'ArrowRight') navigateLightbox(1);
    }

    function showRoute(spot) {
        if (currentRouteLayer) {
            map.removeLayer(currentRouteLayer);
        }

        const routeCoords = [
            [userLocation.lat, userLocation.lng],
            [spot.lat, spot.lng]
        ];

        currentRouteLayer = L.polyline(routeCoords, {
            color: '#2563EB',
            weight: 4,
            opacity: 0.8,
            smoothFactor: 1
        }).addTo(map);

        populateSpotDetail(spot);
    }

    function calculateDistance(coord1, coord2) {
        const R = 6371;
        const dLat = (coord2.lat - coord1.lat) * Math.PI / 180;
        const dLng = (coord2.lng - coord1.lng) * Math.PI / 180;
        const a =
            Math.sin(dLat/2) * Math.sin(dLat/2) +
            Math.cos(coord1.lat * Math.PI / 180) * Math.cos(coord2.lat * Math.PI / 180) *
            Math.sin(dLng/2) * Math.sin(dLng/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c;
    }

    function goBack() {
        if (sidebarState === 'spot') {
            sidebarState = 'spots';
            selectedSpot = null;
            document.getElementById('sidebarBackBtn').classList.add('hidden');
            populateSpotsList();
            map.setView([municipality.latitude, municipality.longitude], 12);

            if (currentRouteLayer) {
                map.removeLayer(currentRouteLayer);
                currentRouteLayer = null;
            }
        }
    }

    function closeSidebar() {
        const sidebar = document.getElementById('sidebarContainer');
        const overlay = document.getElementById('sidebarOverlay');

        sidebar.classList.remove('active');
        overlay.classList.remove('active');

        if (currentRouteLayer) {
            map.removeLayer(currentRouteLayer);
            currentRouteLayer = null;
        }

        selectedSpot = null;
        sidebarState = 'spots';
    }

    function setupEventListeners() {
        const closeBtn = document.getElementById('sidebarCloseBtn');
        const backBtn = document.getElementById('sidebarBackBtn');
        const overlay = document.getElementById('sidebarOverlay');
        const mapTabs = document.querySelectorAll('.map-tab');

        closeBtn.addEventListener('click', closeSidebar);
        backBtn.addEventListener('click', goBack);
        overlay.addEventListener('click', closeSidebar);

        mapTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.map-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                setMapLayer(tab.dataset.view);
            });
        });
    }

    initMap();
});