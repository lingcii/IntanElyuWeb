
// map-view-api.js for MUNICIPAL — shows single-municipality view with its spots
// works both on fresh page load AND SPA injection
function initMapView() {
    if (!document.getElementById('lupto-map')) return;

    if (typeof window.L === 'undefined') {
        let attempts = 0;
        const waitForLeaflet = setInterval(function() {
            attempts++;
            if (typeof window.L !== 'undefined') {
                clearInterval(waitForLeaflet);
                _runMapView();
            } else if (attempts > 40) {
                clearInterval(waitForLeaflet);
                console.error('[muni-map-view] Leaflet failed to load');
            }
        }, 100);
        return;
    }
    _runMapView();
}

function _runMapView() {
    if (!document.getElementById('lupto-map')) return;

    function calculateSpotStatus(spot) {
        if (spot.is_maintenance) return 'maintenance';
        if (!spot.opening_time || !spot.closing_time) return 'unknown';
        const now = new Date();
        const ct = now.getHours() * 60 + now.getMinutes();
        const [oh, om] = spot.opening_time.split(':').map(Number);
        const [ch, cm] = spot.closing_time.split(':').map(Number);
        const ot = oh * 60 + om;
        const cct = ch * 60 + cm;
        return (ct >= ot && ct < cct) ? 'open' : 'closed';
    }

    function formatTime(timeStr) {
        if (!timeStr) return 'Not specified';
        const [hours, minutes] = timeStr.split(':');
        const h = parseInt(hours), m = parseInt(minutes);
        const ampm = h >= 12 ? 'PM' : 'AM';
        return `${h % 12 || 12}:${m.toString().padStart(2, '0')} ${ampm}`;
    }

    const statusMap = { 'EXIST': 'EXISTING', 'EMERGE': 'EMERGING', 'POTENTIAL': 'POTENTIAL' };
    const categoryIconMap = {
        'beach': 'umbrella-beach', 'mountain': 'mountain', 'waterfall': 'water', 'waterfalls': 'water',
        'river': 'water', 'lake': 'water', 'island': 'umbrella-beach', 'cave': 'mountain', 'volcano': 'mountain',
        'forest': 'tree', 'nature park': 'tree', 'marine sanctuary': 'fish', 'wildlife sanctuary': 'paw',
        'historical': 'landmark', 'cultural heritage': 'landmark', 'religious': 'church',
        'museum': 'museum', 'monument': 'monument', 'landmark': 'landmark', 'viewpoint': 'binoculars',
        'adventure': 'hiking', 'hiking': 'hiking', 'camping': 'campground', 'farm': 'seedling',
        'eco-tourism': 'leaf', 'garden': 'seedling', 'park': 'tree', 'recreation': 'bicycle',
        'hot spring': 'hot-tub-person', 'cold spring': 'snowflake', 'food destination': 'utensils',
        'shopping': 'shopping-cart', 'festival venue': 'masks-theater', 'resort': 'hotel', 'other': 'star'
    };

    function getCategoryIcon(catStr) {
        if (window.MapMarkersConfig && typeof window.MapMarkersConfig.getCategoryIcon === 'function') {
            return window.MapMarkersConfig.getCategoryIcon(catStr);
        }
        if (!catStr) return 'map-marker-alt';
        const cats = catStr.split(',').map(c => c.trim().toLowerCase());
        for (const c of cats) { if (categoryIconMap[c]) return categoryIconMap[c]; }
        return 'map-marker-alt';
    }

    const categoryColorMap = {
        'beach': '#0EA5E9', 'marine sanctuary': '#0EA5E9', 'island': '#0EA5E9',
        'waterfall': '#06B6D4', 'waterfalls': '#06B6D4', 'river': '#06B6D4', 'lake': '#06B6D4',
        'forest': '#22C55E', 'nature park': '#22C55E', 'wildlife sanctuary': '#22C55E', 'farm': '#22C55E',
        'eco-tourism': '#22C55E', 'garden': '#22C55E', 'park': '#22C55E',
        'mountain': '#8B5CF6', 'cave': '#8B5CF6', 'volcano': '#8B5CF6', 'hiking': '#8B5CF6',
        'camping': '#8B5CF6', 'viewpoint': '#8B5CF6',
        'historical': '#F59E0B', 'cultural heritage': '#F59E0B', 'museum': '#F59E0B',
        'monument': '#F59E0B', 'landmark': '#F59E0B',
        'religious': '#D97706',
        'adventure': '#EC4899', 'recreation': '#EC4899', 'food destination': '#EC4899',
        'shopping': '#EC4899', 'festival venue': '#EC4899', 'resort': '#EC4899'
    };

    function getCategoryColor(catStr) {
        if (window.MapMarkersConfig && typeof window.MapMarkersConfig.getCategoryColor === 'function') {
            return window.MapMarkersConfig.getCategoryColor(catStr);
        }
        if (!catStr) return '#6B7280';
        const cats = catStr.split(',').map(c => c.trim().toLowerCase());
        for (const c of cats) { if (categoryColorMap[c]) return categoryColorMap[c]; }
        return '#6B7280';
    }

    function getStatusColor(status) {
        const colors = { 'open': '#22C55E', 'closed': '#EF4444', 'maintenance': '#F59E0B', 'unknown': '#6B7280' };
        return colors[status] || '#6B7280';
    }

    let touristSpots = (window.touristSpotsData || [])
        .filter(spot => spot.status === 'approved')
        .map(spot => {
        let images = [];
        if (spot.images && spot.images.length > 0) images = spot.images;
        else if (spot.photo_url) images = [{ photo_url: spot.photo_url }];
        return {
            id: spot.id, name: spot.name,
            municipality: spot.municipality_name,
            lat: spot.latitude, lng: spot.longitude,
            description: spot.description || 'No description provided',
            category: spot.category, photo_url: spot.photo_url,
            images: images,
            admissionFee: (() => { const ft = Array.isArray(spot.fee_types) ? spot.fee_types : []; if (!ft || ft.length === 0) return 'Free'; let p = []; if (ft.includes('entrance') && Number(spot.entrance_fee||0) > 0) p.push('₱'+Number(spot.entrance_fee).toLocaleString()); if (ft.includes('environmental') && Number(spot.environmental_fee||0) > 0) p.push('EF:₱'+Number(spot.environmental_fee).toLocaleString()); return p.length ? p.join(' | ') : 'Free'; })(),
            rating: spot.rating !== undefined && spot.rating !== null ? parseFloat(spot.rating) : 0.0,
            classification_status: statusMap[spot.classification_status] || spot.classification_status,
            opening_time: spot.opening_time, closing_time: spot.closing_time,
            is_maintenance: spot.is_maintenance,
            status: calculateSpotStatus(spot)
        };
    }).filter(s => s.lat && s.lng);

    // Single-municipality data
    const muniData = (window.municipalitiesData || [])[0] || window.municipalityData || {};
    const municipality = {
        name: muniData.name || 'Your Municipality',
        lat: parseFloat(muniData.latitude) || 16.5,
        lng: parseFloat(muniData.longitude) || 120.3,
        count: touristSpots.length,
        description: `${muniData.name || 'Your Municipality'} is one of the municipalities of La Union in Region 1.`,
        history: `${muniData.name || 'Your Municipality'} contributes to the historical development of La Union through its local settlements, community institutions, and municipal heritage.`,
        geography: `${muniData.name || 'Your Municipality'} lies within La Union's coastal-upland corridor.`,
        culture: `${muniData.name || 'Your Municipality'} supports local traditions, community tourism, and municipal events.`
    };

    let map, allMarkers = [], spotMarkers = [], currentRouteLayer = null;
    let selectedMunicipality = null, selectedSpot = null, sidebarState = 'municipality';
    let currentBaseLayer = 'street', currentMapLayer = null, satelliteLabelsLayer = null;
    let activeSpotMarker = null;

    const muniLat = municipality.lat, muniLng = municipality.lng;
    const bounds = L.latLngBounds([[muniLat - 0.1, muniLng - 0.1], [muniLat + 0.1, muniLng + 0.1]]);

    const mapLayers = {
        street: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors', maxZoom: 19
        }),
        satellite: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri', maxZoom: 18
        })
    };

    satelliteLabelsLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Reference_Overlay/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 18
    });

    function setMapLayer(view) {
        if (currentMapLayer && map.hasLayer(currentMapLayer)) map.removeLayer(currentMapLayer);
        if (satelliteLabelsLayer && map.hasLayer(satelliteLabelsLayer)) map.removeLayer(satelliteLabelsLayer);
        currentBaseLayer = view;
        currentMapLayer = mapLayers[view];
        currentMapLayer.addTo(map);
        if (view === 'satellite') satelliteLabelsLayer.addTo(map);
    }

    function initMap() {
        map = L.map('lupto-map', { minZoom: 10, worldCopyJump: false });
        setMapLayer('street');
        map.setView([muniLat, muniLng], 13);

        // addMunicipalityMarker(); // Removed as requested
        showAllSpotMarkers();
        setupEventListeners();
    }

    function addMunicipalityMarker() {
        const icon = L.divIcon({
            html: `<div style="background:#DC2626; width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-weight:bold; font-size:14px; border:3px solid white; box-shadow:0 2px 8px rgba(0,0,0,0.3);">${municipality.count}</div>`,
            className: 'custom-div-icon', iconSize: [40, 40], iconAnchor: [20, 20]
        });
        const marker = L.marker([muniLat, muniLng], { icon, muniData: municipality }).addTo(map);
        marker.on('click', () => handleMunicipalityClick(municipality));
        marker.bindTooltip(municipality.name, { permanent: true, direction: 'bottom', offset: [0, 25], opacity: 0.9 });
        allMarkers.push(marker);
    }

    function handleMunicipalityClick(muni) {
        selectedMunicipality = muni;
        selectedSpot = null;
        sidebarState = 'municipality';
        document.getElementById('sidebarBackBtn').classList.add('hidden');
        openSidebar('municipality', muni);
    }

    function showAllSpotMarkers() {
        spotMarkers.forEach(m => map.removeLayer(m));
        spotMarkers = [];
        activeSpotMarker = null;

        touristSpots.forEach(spot => {
            const iconColor = getCategoryColor(spot.category);
            const icon = L.divIcon({
                html: `<div class="spot-marker" data-spot-id="${spot.id}" style="background:${iconColor}; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-weight:bold; border:3px solid white; box-shadow:0 3px 8px rgba(0,0,0,0.3); transition:all 0.2s ease;">
                    <i class="fas fa-${getCategoryIcon(spot.category)}" style="font-size:14px;"></i>
                </div>`,
                className: 'spot-marker-icon', 
                iconSize: [32, 32], 
                iconAnchor: [16, 32],
                popupAnchor: [0, -32]
            });
            const marker = L.marker([spot.lat, spot.lng], { icon, spotData: spot }).addTo(map);
            const hasImg = spot.images && spot.images.length > 0;
            const shortDesc = spot.description && spot.description.length > 60 ? spot.description.substring(0, 60) + '...' : spot.description;
            const popupHtml = `<div class="map-popup-card" style="font-family: inherit; width: 180px; padding: 2px;">
                ${hasImg ? `<img src="${spot.images[0].photo_url}" style="width:100%; height:80px; object-fit:cover; border-radius:6px; margin-bottom:6px;" alt="${spot.name}">` : ''}
                <h4 style="margin:0 0 2px;font-size:12.5px;font-weight:700;color:#1E293B;">${spot.name}</h4>
                <div style="display:flex;gap:3px;flex-wrap:wrap;margin-bottom:6px;">
                    <div style="display:inline-flex;align-items:center;gap:3px;background:#EEF2FF;color:#2563EB;padding:1.5px 5px;border-radius:4px;font-size:9px;font-weight:600;text-transform:uppercase;">
                        <i class="fas fa-${getCategoryIcon(spot.category)}" style="font-size: 8px;"></i> ${spot.category}
                    </div>
                </div>
                <p style="margin:0 0 6px;font-size:11px;color:#4B5563;line-height:1.3;">${shortDesc}</p>
                <div style="font-size:10.5px;color:#6B7280;margin-bottom:6px;line-height:1.3;">
                    <i class="fas fa-ticket-alt" style="margin-right:4px;"></i> ${spot.admissionFee}<br>
                    <i class="fas fa-clock" style="margin-right:4px;"></i> ${formatTime(spot.opening_time)} - ${formatTime(spot.closing_time)}
                </div>
                <button class="popup-detail-btn" onclick="window.viewSpotDetailsFromMap(${spot.id})" style="width:100%;padding:4px 6px;background:#2563EB;color:white;border:none;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;">
                    <i class="fas fa-info-circle"></i> View Full Details
                </button>
            </div>`;
            marker.bindPopup(popupHtml, { maxWidth: 200, className: 'custom-map-popup' });
            marker.on('click', () => handleSpotClick(spot));
            spotMarkers.push(marker);
        });
    }

    window.viewSpotDetailsFromMap = function(spotId) {
        const spot = touristSpots.find(s => s.id === spotId);
        if (spot) handleSpotClick(spot);
    };

    function handleSpotClick(spot) {
        selectedSpot = spot;
        sidebarState = 'spot';
        map.setView([spot.lat, spot.lng], 15);
        if (activeSpotMarker) activeSpotMarker.setZIndexOffset(0);
        activeSpotMarker = spotMarkers.find(m => m.options.spotData.id === spot.id);
        if (activeSpotMarker) {
            activeSpotMarker.setZIndexOffset(1000);
            if (activeSpotMarker._icon) {
                activeSpotMarker._icon.style.transform = 'scale(1.3)';
                setTimeout(() => { if (activeSpotMarker && activeSpotMarker._icon) activeSpotMarker._icon.style.transform = 'scale(1)'; }, 300);
            }
        }
        document.getElementById('sidebarBackBtn').classList.remove('hidden');
        populateSpotDetail(spot);
    }

    function openSidebar(type, data) {
        const sidebar = document.getElementById('sidebarContainer');
        const overlay = document.getElementById('sidebarOverlay');
        const content = document.getElementById('sidebarContent');
        document.getElementById('sidebarTitle').textContent = data.name;
        content.innerHTML = `<div class="loading-state"><div class="loading-spinner"></div><p style="color:#6B7280;">Loading...</p></div>`;
        setTimeout(() => {
            sidebar.classList.add('active');
            overlay.classList.add('active');
            setTimeout(() => {
                if (type === 'municipality') populateMunicipalityDetail(data);
                else populateSpotDetail(data);
            }, 300);
        }, 100);
    }

    function populateMunicipalityDetail(muni) {
        const content = document.getElementById('sidebarContent');
        const spots = touristSpots;
        let allImages = [];
        spots.forEach(s => {
            if (s.images) s.images.forEach(img => allImages.push({ ...img, spotName: s.name }));
        });

        content.innerHTML = `
            <div class="muni-image-container"><i class="fas fa-city"></i></div>
            <div class="muni-description">${muni.description}</div>
            ${allImages.length > 0 ? `
            <div class="section-header">Tourist Spot Images (${allImages.length})</div>
            <div class="spot-images-grid">
                ${allImages.map((img, idx) => `
                    <div class="spot-image-container">
                        <img src="${img.photo_url}" alt="${img.spotName}" class="spot-gallery-image" data-index="${idx}" loading="lazy">
                    </div>
                `).join('')}
            </div>` : ''}
            <div class="section-header">Tourist Spots (${spots.length})</div>
            <div class="spot-list">
                ${spots.map(spot => `
                    <div class="spot-card" data-spot-id="${spot.id}">
                        <div class="spot-thumbnail">
                            ${spot.photo_url ? `<img src="${spot.photo_url}" style="width:100%;height:100%;object-fit:cover;" alt="${spot.name}" loading="lazy">` : `<i class="fas fa-${getCategoryIcon(spot.category)}"></i>`}
                            <div class="spot-category-tag">${spot.category}</div>
                        </div>
                        <div class="spot-info">
                            <h4>${spot.name}</h4>
                            <p>${spot.description}</p>
                            <div class="spot-meta">
                                <div class="spot-meta-item"><i class="fas fa-star" style="color:#F59E0B;"></i><span>${spot.rating}</span></div>
                                <div class="spot-meta-item"><i class="fas fa-ticket-alt"></i><span>${spot.admissionFee}</span></div>
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;

        document.querySelectorAll('.spot-card').forEach(card => {
            const spotId = parseInt(card.dataset.spotId);
            const marker = spotMarkers.find(m => m.options.spotData.id === spotId);
            card.addEventListener('click', () => { const s = touristSpots.find(sp => sp.id === spotId); if (s) handleSpotClick(s); });
            card.addEventListener('mouseenter', () => { if (marker && marker._icon) { marker._icon.style.transform = 'scale(1.2)'; marker.setZIndexOffset(500); } });
            card.addEventListener('mouseleave', () => { if (marker && marker._icon && activeSpotMarker !== marker) { marker._icon.style.transform = 'scale(1)'; marker.setZIndexOffset(0); } });
        });
    }

    function populateSpotDetail(spot) {
        const content = document.getElementById('sidebarContent');
        let imagesGallery = '';
        if (spot.images && spot.images.length > 0) {
            imagesGallery = `
                <div class="spot-image-gallery">
                    <div class="spot-main-image">
                        <img src="${spot.images[0].photo_url}" alt="${spot.name}" class="main-gallery-image">
                    </div>
                </div>`;
        } else {
            imagesGallery = `<div class="spot-detail-image"><i class="fas fa-${getCategoryIcon(spot.category)}"></i></div>`;
        }

        content.innerHTML = `
            ${imagesGallery}
            <h2 class="spot-detail-title">${spot.name}</h2>
            <div class="spot-detail-location"><i class="fas fa-map-marker-alt" style="color:#2563EB;"></i> ${spot.municipality}, La Union</div>
            <div style="display:inline-block;padding:6px 16px;border-radius:20px;background:${getStatusColor(spot.status)};color:white;font-weight:600;font-size:14px;margin-bottom:16px;">
                ${spot.status === 'open' ? 'Open' : spot.status === 'closed' ? 'Closed' : spot.status === 'maintenance' ? 'Under Maintenance' : 'Status Unknown'}
            </div>
            <div style="background:#F3F4F6;padding:16px;border-radius:8px;margin-bottom:16px;">
                <div style="font-weight:600;margin-bottom:8px;display:flex;align-items:center;gap:8px;"><i class="fas fa-clock" style="color:#374151;"></i> Operating Hours</div>
                <div style="color:#374151;">${formatTime(spot.opening_time)} - ${formatTime(spot.closing_time)}</div>
            </div>
            <div class="spot-detail-description">${spot.description}</div>
            <div class="info-grid">
                <div class="info-card"><div class="info-card-label">Category</div><div class="info-card-value" style="display:flex;align-items:center;gap:6px;"><i class="fas fa-${getCategoryIcon(spot.category)}" style="color:#2563EB;"></i> ${spot.category}</div></div>
                <div class="info-card"><div class="info-card-label">Admission</div><div class="info-card-value">${spot.admissionFee}</div></div>
                <div class="info-card"><div class="info-card-label">Coordinates</div><div class="info-card-value" style="font-size:12px;font-family:var(--font-mono);">${parseFloat(spot.lat).toFixed(6)}, ${parseFloat(spot.lng).toFixed(6)}</div></div>
                ${spot.classification_status ? `<div class="info-card"><div class="info-card-label">Classification</div><div class="info-card-value">${spot.classification_status}</div></div>` : ''}
            </div>
        `;
    }

    function goBack() {
        if (sidebarState === 'spot' && selectedMunicipality) {
            sidebarState = 'municipality';
            selectedSpot = null;
            document.getElementById('sidebarBackBtn').classList.add('hidden');
            populateMunicipalityDetail(selectedMunicipality);
            map.setView([muniLat, muniLng], 13);
        }
    }

    function closeSidebar() {
        document.getElementById('sidebarContainer').classList.remove('active');
        document.getElementById('sidebarOverlay').classList.remove('active');
        allMarkers.forEach(m => m.setOpacity(1));
        showAllSpotMarkers();
        if (currentRouteLayer) { map.removeLayer(currentRouteLayer); currentRouteLayer = null; }
        selectedMunicipality = null;
        selectedSpot = null;
        sidebarState = 'municipality';
    }

    function setupEventListeners() {
        document.getElementById('sidebarCloseBtn').addEventListener('click', closeSidebar);
        document.getElementById('sidebarBackBtn').addEventListener('click', goBack);
        document.getElementById('sidebarOverlay').addEventListener('click', closeSidebar);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (sidebarState === 'spot' && selectedMunicipality) goBack();
                else closeSidebar();
            }
        });

        document.querySelectorAll('.map-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const view = tab.dataset.view;
                if (!view || view === currentBaseLayer) return;
                document.querySelectorAll('.map-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                setMapLayer(view);
            });
        });
        window.refreshLuptoMap = function() {
            void 0;
            const rawSpots = Array.isArray(window.touristSpotsData) ? window.touristSpotsData : [];
            touristSpots = rawSpots
                .filter(spot => spot.status === 'approved')
                .map(spot => {
                    let images = [];
                    if (spot.images && spot.images.length > 0) images = spot.images;
                    else if (spot.photo_url) images = [{ photo_url: spot.photo_url }];
                    return {
                        id: spot.id, name: spot.name,
                        municipality: spot.municipality_name,
                        lat: spot.latitude, lng: spot.longitude,
                        description: spot.description || 'No description provided',
                        category: spot.category, photo_url: spot.photo_url,
                        images: images,
                        admissionFee: (() => { const ft = Array.isArray(spot.fee_types) ? spot.fee_types : []; if (!ft || ft.length === 0) return 'Free'; let p = []; if (ft.includes('entrance') && Number(spot.entrance_fee||0) > 0) p.push('₱'+Number(spot.entrance_fee).toLocaleString()); if (ft.includes('environmental') && Number(spot.environmental_fee||0) > 0) p.push('EF:₱'+Number(spot.environmental_fee).toLocaleString()); return p.length ? p.join(' | ') : 'Free'; })(),
                        rating: spot.rating !== undefined && spot.rating !== null ? parseFloat(spot.rating) : 0.0,
                        classification_status: statusMap[spot.classification_status] || spot.classification_status,
                        opening_time: spot.opening_time, closing_time: spot.closing_time,
                        is_maintenance: spot.is_maintenance,
                        status: calculateSpotStatus(spot)
                    };
                }).filter(s => s.lat && s.lng);

            municipality.count = touristSpots.length;

            if (map) {
                showAllSpotMarkers();
                if (sidebarState === 'municipality') {
                    populateMunicipalityDetail(municipality);
                }
            }
        };
    }

    initMap();
}

if (document.getElementById('lupto-map')) {
    initMapView();
} else {
    document.addEventListener('DOMContentLoaded', initMapView);
}
