
// map-view-api.js initializer — works both on fresh page load AND SPA injection
// (DOMContentLoaded may have already fired when this script is injected by the SPA router)
function initMapView() {
    if (!document.getElementById('lupto-map')) return; // guard: element not in DOM yet

    // If Leaflet hasn't loaded yet (CDN async), wait for it
    if (typeof window.L === 'undefined') {
        let attempts = 0;
        const waitForLeaflet = setInterval(function() {
            attempts++;
            if (typeof window.L !== 'undefined') {
                clearInterval(waitForLeaflet);
                _runMapView();
            } else if (attempts > 40) { // 4 second timeout
                clearInterval(waitForLeaflet);
                console.error('[map-view-api] Leaflet failed to load');
            }
        }, 100);
        return;
    }
    _runMapView();
}

function _runMapView() {
    if (!document.getElementById('lupto-map')) return;
    // Helper function to calculate spot status
    function calculateSpotStatus(spot) {
        if (spot.is_maintenance) {
            return 'maintenance';
        }
        if (!spot.opening_time || !spot.closing_time) {
            return 'unknown';
        }

        const now = new Date();
        const currentHours = now.getHours();
        const currentMinutes = now.getMinutes();
        const currentTotalMinutes = currentHours * 60 + currentMinutes;

        // Parse opening and closing times
        const [openHours, openMinutes] = spot.opening_time.split(':').map(Number);
        const [closeHours, closeMinutes] = spot.closing_time.split(':').map(Number);
        const openTotalMinutes = openHours * 60 + openMinutes;
        const closeTotalMinutes = closeHours * 60 + closeMinutes;

        if (currentTotalMinutes >= openTotalMinutes && currentTotalMinutes < closeTotalMinutes) {
            return 'open';
        } else {
            return 'closed';
        }
    }

    // Helper function to format time for display
    function formatTime(timeStr) {
        if (!timeStr) return 'Not specified';
        const [hours, minutes] = timeStr.split(':');
        const h = parseInt(hours);
        const m = parseInt(minutes);
        const ampm = h >= 12 ? 'PM' : 'AM';
        const hour12 = h % 12 || 12;
        return `${hour12}:${m.toString().padStart(2, '0')} ${ampm}`;
    }

    // Status map for displaying classification
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
            barangay: spot.barangay || null,
            lat: spot.latitude,
            lng: spot.longitude,
            description: spot.description || 'No description provided',
            category: spot.category,
            photo_url: spot.photo_url,
            images: normalizedImages,
            admissionFee: (() => { var ft = Array.isArray(spot.fee_types) ? spot.fee_types : []; if (!ft || ft.length === 0) return 'Free'; var p = []; if (ft.includes('entrance') && Number(spot.entrance_fee||0) > 0) p.push('₱'+Number(spot.entrance_fee).toLocaleString()); if (ft.includes('environmental') && Number(spot.environmental_fee||0) > 0) p.push('EF:₱'+Number(spot.environmental_fee).toLocaleString()); return p.length ? p.join(' | ') : 'Free'; })(),
            rating: spot.rating !== undefined && spot.rating !== null ? parseFloat(spot.rating) : 0.0,
            classification_status: statusMap[spot.classification_status] || spot.classification_status,
            opening_time: spot.opening_time,
            closing_time: spot.closing_time,
            is_maintenance: spot.is_maintenance,
            status: calculateSpotStatus(spot)
        };
    }).filter(spot => spot.lat && spot.lng); // Filter out spots without coordinates

    // Municipality coordinates from database
    let municipalityCoordinates = window.municipalitiesData.map(muni => ({
        name: muni.name,
        lat: muni.latitude,
        lng: muni.longitude,
        attraction_count: muni.attraction_count
    }));

    const municipalityProfiles = {
        'San Juan': {
            description: 'San Juan is the surfing capital of the Northern Philippines and one of the most visited tourism hubs in La Union.',
            history: 'San Juan contributes to the historical development of La Union through its local settlements, community institutions, and municipal heritage.',
            geography: 'San Juan lies within La Union\'s coastal-upland corridor and connects neighboring municipalities through the province\'s road and river systems.',
            culture: 'San Juan supports local traditions, community tourism, and municipal events that reflect the wider identity of La Union.'
        },
        'San Gabriel': {
            description: 'San Gabriel is an inland municipality recognized for waterfalls, rivers, and mountain eco-adventures.',
            history: 'San Gabriel contributes to the historical development of La Union through its local settlements, community institutions, and municipal heritage.',
            geography: 'San Gabriel lies within La Union\'s coastal-upland corridor and connects neighboring municipalities through the province\'s road and river systems.',
            culture: 'San Gabriel supports local traditions, community tourism, and municipal events that reflect the wider identity of La Union.'
        },
        'Luna': {
            description: 'Luna is famous for its pebble shoreline, stonecraft heritage, and coastal heritage attractions.',
            history: 'Luna contributes to the historical development of La Union through its local settlements, community institutions, and municipal heritage.',
            geography: 'Luna lies within La Union\'s coastal-upland corridor and connects neighboring municipalities through the province\'s road and river systems.',
            culture: 'Luna supports local traditions, community tourism, and municipal events that reflect the wider identity of La Union.'
        },
        'Bacnotan': {
            description: 'Bacnotan combines coastal communities, inland green spaces, and growing eco-tourism sites.',
            history: 'Bacnotan contributes to the historical development of La Union through its local settlements, community institutions, and municipal heritage.',
            geography: 'Bacnotan lies within La Union\'s coastal-upland corridor and connects neighboring municipalities through the province\'s road and river systems.',
            culture: 'Bacnotan supports local traditions, community tourism, and municipal events that reflect the wider identity of La Union.'
        },
        'Balaoan': {
            description: 'Balaoan offers access to mountain scenery, lagoons, and adventure-oriented tourism spots.',
            history: 'Balaoan contributes to the historical development of La Union through its local settlements, community institutions, and municipal heritage.',
            geography: 'Balaoan lies within La Union\'s coastal-upland corridor and connects neighboring municipalities through the province\'s road and river systems.',
            culture: 'Balaoan supports local traditions, community tourism, and municipal events that reflect the wider identity of La Union.'
        },
        'San Fernando City': {
            description: 'San Fernando City is the provincial capital and a major gateway for visitors entering La Union.',
            history: 'San Fernando City contributes to the historical development of La Union through its local settlements, community institutions, and municipal heritage.',
            geography: 'San Fernando City lies within La Union\'s coastal-upland corridor and connects neighboring municipalities through the province\'s road and river systems.',
            culture: 'San Fernando City supports local traditions, community tourism, and municipal events that reflect the wider identity of La Union.'
        },
        'Agoo': {
            description: 'Agoo is one of the municipalities of La Union in Region 1 and forms part of the province\'s tourism and administrative landscape.',
            history: 'Agoo contributes to the historical development of La Union through its local settlements, community institutions, and municipal heritage.',
            geography: 'Agoo lies within La Union\'s coastal-upland corridor and connects neighboring municipalities through the province\'s road and river systems.',
            culture: 'Agoo supports local traditions, community tourism, and municipal events that reflect the wider identity of La Union.'
        }
    };

    function buildMunicipalityProfile(name) {
        return municipalityProfiles[name] || {
            description: `${name} is one of the municipalities of La Union in Region 1 and forms part of the province's tourism and administrative landscape.`,
            history: `${name} contributes to the historical development of La Union through its local settlements, community institutions, and municipal heritage.`,
            geography: `${name} lies within La Union's coastal-upland corridor and connects neighboring municipalities through the province's road and river systems.`,
            culture: `${name} supports local traditions, community tourism, and municipal events that reflect the wider identity of La Union.`
        };
    }

    let municipalitySpotCounts = touristSpots.reduce((counts, spot) => {
        counts[spot.municipality] = (counts[spot.municipality] || 0) + 1;
        return counts;
    }, {});

    let municipalities = municipalityCoordinates.map((municipality) => ({
        ...municipality,
        count: municipalitySpotCounts[municipality.name] || 0,
        ...buildMunicipalityProfile(municipality.name)
    }));

    let map, allMarkers = [], spotMarkers = [], currentRouteLayer = null, userLocation = null;
    let selectedMunicipality = null, selectedSpot = null, currentTravelMode = 'driving';
    let sidebarState = 'municipality';
    let currentBaseLayer = 'street';
    let currentMapLayer = null;
    let satelliteLabelsLayer = null;
    let activeSpotMarker = null;
    let statusInterval = null;
    let laUnionBounds;
    if (municipalities && municipalities.length > 0) {
        laUnionBounds = L.latLngBounds(municipalities.map((municipality) => [municipality.lat, municipality.lng])).pad(0.08);
    } else {
        laUnionBounds = L.latLngBounds([[16.2, 120.2], [16.8, 120.5]]);
    }

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

    function updateAllStatuses() {
        if (!document.getElementById('lupto-map')) return;
        touristSpots.forEach(spot => {
            const newStatus = calculateSpotStatus(spot);
            if (spot.status !== newStatus) {
                spot.status = newStatus;
            }
        });
        
        // Update spot markers if they are currently shown
        if (selectedMunicipality) {
            spotMarkers.forEach(marker => {
                const spot = marker.options.spotData;
                const newStatusColor = getStatusColor(spot.status);
                if (marker._icon) {
                    const markerDiv = marker._icon.querySelector('.spot-marker');
                    if (markerDiv) {
                        markerDiv.style.borderColor = newStatusColor;
                    }
                }
            });
            
            // If a spot is selected, update the sidebar
            if (selectedSpot) {
                populateSpotDetail(selectedSpot);
            }
        }
    }

    // Initialize Map
    function initMap() {
        map = L.map('lupto-map', {
            maxBounds: laUnionBounds.pad(0.08),
            maxBoundsViscosity: 1.0,
            minZoom: 10,
            worldCopyJump: false
        });
        setMapLayer('street');
        map.fitBounds(laUnionBounds);

        // Add municipality markers
        // addMunicipalityMarkers(); // Removed as requested

        // Show all tourist spots initially on the map
        showSpotMarkers();
        
        // Add event listeners
        setupEventListeners();
        
        // Start real-time status updates (check every 60 seconds)
        statusInterval = setInterval(updateAllStatuses, 60000);
    }

    function rebuildTouristSpotsData() {
        touristSpots = window.touristSpotsData
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
                barangay: spot.barangay || null,
                lat: spot.latitude,
                lng: spot.longitude,
                description: spot.description || 'No description provided',
                category: spot.category,
                photo_url: spot.photo_url,
                images: normalizedImages,
                admissionFee: (() => { var ft = Array.isArray(spot.fee_types) ? spot.fee_types : []; if (!ft || ft.length === 0) return 'Free'; var p = []; if (ft.includes('entrance') && Number(spot.entrance_fee||0) > 0) p.push('₱'+Number(spot.entrance_fee).toLocaleString()); if (ft.includes('environmental') && Number(spot.environmental_fee||0) > 0) p.push('EF:₱'+Number(spot.environmental_fee).toLocaleString()); return p.length ? p.join(' | ') : 'Free'; })(),
                rating: spot.rating !== undefined && spot.rating !== null ? parseFloat(spot.rating) : 0.0,
                classification_status: statusMap[spot.classification_status] || spot.classification_status,
                opening_time: spot.opening_time,
                closing_time: spot.closing_time,
                is_maintenance: spot.is_maintenance,
                status: calculateSpotStatus(spot)
            };
        }).filter(spot => spot.lat && spot.lng);

        municipalityCoordinates = window.municipalitiesData.map(muni => ({
            name: muni.name,
            lat: muni.latitude,
            lng: muni.longitude,
            attraction_count: muni.attraction_count
        }));

        municipalitySpotCounts = touristSpots.reduce((counts, spot) => {
            counts[spot.municipality] = (counts[spot.municipality] || 0) + 1;
            return counts;
        }, {});

        municipalities = municipalityCoordinates.map(m => ({
            ...m,
            count: municipalitySpotCounts[m.name] || 0,
            ...buildMunicipalityProfile(m.name)
        }));
    }

    window.refreshLuptoMap = function() {
        clearInterval(statusInterval);
        rebuildTouristSpotsData();

        allMarkers.forEach(m => map.removeLayer(m));
        allMarkers = [];
        spotMarkers.forEach(m => map.removeLayer(m));
        spotMarkers = [];
        activeSpotMarker = null;

        if (currentRouteLayer) {
            map.removeLayer(currentRouteLayer);
            currentRouteLayer = null;
        }

        // addMunicipalityMarkers(); // Removed as requested
        showSpotMarkers(selectedMunicipality ? selectedMunicipality.name : null);

        statusInterval = setInterval(updateAllStatuses, 60000);
    };

    function addMunicipalityMarkers() {
        municipalities.forEach(muni => {
            const color = '#DC2626'; // Red color as requested
            const icon = L.divIcon({
                html: `<div style="background:${color}; width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-weight:bold; font-size:14px; border:3px solid white; box-shadow:0 2px 8px rgba(0,0,0,0.3);">${muni.count}</div>`,
                className: 'custom-div-icon',
                iconSize: [40, 40],
                iconAnchor: [20, 20]
            });

            const marker = L.marker([muni.lat, muni.lng], { icon: icon, muniData: muni });
            marker.addTo(map);
            marker.on('click', () => handleMunicipalityClick(muni));
            
            // Show municipality name permanently on marker
            marker.bindTooltip(muni.name, {
                permanent: true,
                direction: 'bottom',
                offset: [0, 25],
                className: 'muni-tooltip',
                opacity: 0.9
            });
            
            allMarkers.push(marker);
        });
    }

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
                        html: `<div style="background:#22C55E; width:20px; height:20px; border-radius:50%; border:3px solid white; box-shadow:0 2px 6px rgba(0,0,0,0.3);"></div>`,
                        className: 'user-location',
                        iconSize: [20, 20],
                        iconAnchor: [10, 10]
                    });
                    L.marker([userLocation.lat, userLocation.lng], { icon: userIcon }).addTo(map);
                },
                () => {
                    // Fallback if geolocation is denied
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

    function selectMunicipality(muni) {
        selectedMunicipality = muni;
        selectedSpot = null;
        sidebarState = 'municipality';

        // Hide all municipality markers except selected
        allMarkers.forEach(marker => {
            if (marker.options.muniData.name !== muni.name) {
                marker.setOpacity(0.2);
                if (marker.getTooltip()) {
                    marker.getTooltip().setOpacity(0);
                }
            } else {
                marker.setZIndexOffset(100);
                if (marker.getTooltip()) {
                    marker.getTooltip().setOpacity(1);
                }
            }
        });

        // Show spot markers for this municipality
        showSpotMarkers(muni.name);

        // Zoom to municipality area using its spot markers for bounds
        const muniSpots = touristSpots.filter(s => s.municipality === muni.name);
        if (muniSpots.length > 0) {
            const spotLats = muniSpots.map(s => s.lat).filter(Boolean);
            const spotLngs = muniSpots.map(s => s.lng).filter(Boolean);
            if (spotLats.length > 0) {
                const bounds = L.latLngBounds(
                    [Math.min(...spotLats), Math.min(...spotLngs)],
                    [Math.max(...spotLats), Math.max(...spotLngs)]
                );
                // Include municipality center in bounds
                bounds.extend([muni.lat, muni.lng]);
                map.fitBounds(bounds.pad(0.15), { maxZoom: 14 });
            } else {
                map.setView([muni.lat, muni.lng], 13);
            }
        } else {
            map.setView([muni.lat, muni.lng], 13);
        }

        // Update selected municipality badge
        const badge = document.getElementById('selectedMuniBadge');
        const nameEl = document.getElementById('selectedMuniName');
        if (badge && nameEl) {
            nameEl.textContent = muni.name;
            badge.style.display = 'flex';
        }

        // Close sidebar if it was open
        const sidebar = document.getElementById('sidebarContainer');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }
    }

    function deselectMunicipality() {
        selectedMunicipality = null;
        selectedSpot = null;
        sidebarState = 'municipality';

        // Reset all municipality markers
        allMarkers.forEach(marker => {
            marker.setOpacity(1);
            marker.setZIndexOffset(0);
            if (marker.getTooltip()) {
                marker.getTooltip().setOpacity(1);
            }
        });

        // Show all spot markers
        showSpotMarkers();

        // Hide badge
        const badge = document.getElementById('selectedMuniBadge');
        if (badge) {
            badge.style.display = 'none';
        }

        // Close sidebar if open
        const sidebar = document.getElementById('sidebarContainer');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar) {
            sidebar.classList.remove('active');
        }
        if (overlay) {
            overlay.classList.remove('active');
        }

        // Reset map view
        map.fitBounds(laUnionBounds);

        // Clear route
        if (currentRouteLayer) {
            map.removeLayer(currentRouteLayer);
            currentRouteLayer = null;
        }
    }

    function handleMunicipalityClick(muni) {
        if (selectedMunicipality && selectedMunicipality.name === muni.name) {
            // Clicking the same municipality deselects it
            deselectMunicipality();
        } else {
            selectMunicipality(muni);
        }
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

    function showSpotMarkers(muniName = null) {
        // Clear existing spot markers
        spotMarkers.forEach(marker => map.removeLayer(marker));
        spotMarkers = [];
        activeSpotMarker = null;

        const spots = muniName 
            ? touristSpots.filter(spot => spot.municipality === muniName)
            : touristSpots;

        spots.forEach(spot => {
            const categoryColor = getCategoryColor(spot.category);
            const icon = L.divIcon({
                html: `<div class="spot-marker" data-spot-id="${spot.id}" style="background:${categoryColor}; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-weight:bold; border:3px solid white; box-shadow:0 3px 8px rgba(0,0,0,0.3); transition: all 0.2s ease;">
                    <i class="fas fa-${getCategoryIcon(spot.category)}" style="font-size:14px;"></i>
                </div>`,
                className: 'spot-marker-icon',
                iconSize: [32, 32],
                iconAnchor: [16, 32],
                popupAnchor: [0, -32]
            });

            const marker = L.marker([spot.lat, spot.lng], { icon: icon, spotData: spot });
            
            // Build rich popup content
            const hasImage = spot.images && spot.images.length > 0;
            const shortDesc = spot.description && spot.description.length > 60 
                ? spot.description.substring(0, 60) + '...' 
                : spot.description || 'No description available.';
            const popupHtml = `
                <div class="map-popup-card" style="font-family: inherit; width: 180px; padding: 2px;">
                    ${hasImage ? `<img src="${spot.images[0].photo_url}" style="width:100%; height:80px; object-fit:cover; border-radius:6px; margin-bottom:6px;" alt="${spot.name}">` : ''}
                    <h4 style="margin: 0 0 2px 0; font-size: 12.5px; font-weight: 700; color: #1E293B;">${spot.name}</h4>
                    <div style="font-size: 10px; color: #6B7280; margin-bottom: 4px;">
                        <i class="fas fa-map-marker-alt" style="margin-right: 3px; color: #2563EB;"></i> ${spot.municipality}, La Union
                    </div>
                    <div style="display: flex; gap: 3px; flex-wrap: wrap; margin-bottom: 6px;">
                        <div style="display: inline-flex; align-items: center; gap: 3px; background: #EEF2FF; color: #2563EB; padding: 1.5px 5px; border-radius: 4px; font-size: 9px; font-weight: 600; text-transform: uppercase;">
                            <i class="fas fa-${getCategoryIcon(spot.category)}" style="font-size: 8px;"></i> ${spot.category}
                        </div>
                        ${getClassificationBadge(spot.classification_status)}
                    </div>
                    <p style="margin: 0 0 6px 0; font-size: 11px; color: #4B5563; line-height: 1.3;">${shortDesc}</p>
                    <div style="font-size: 10.5px; color: #6B7280; margin-bottom: 6px; line-height: 1.3;">
                        <i class="fas fa-ticket-alt" style="margin-right: 4px; color: #6B7280;"></i> ${spot.admissionFee}<br>
                        <i class="fas fa-clock" style="margin-right: 4px; color: #6B7280;"></i> ${formatTime(spot.opening_time)} - ${formatTime(spot.closing_time)}
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

            marker.on('click', () => {
                map.setView(marker.getLatLng(), map.getZoom());
            });

            marker.addTo(map);
            spotMarkers.push(marker);
        });
    }


    // Custom global callback to bridge Leaflet popup button clicks to the JS sidebar behavior
    window.viewSpotDetailsFromMap = function(spotId) {
        const spot = touristSpots.find(s => s.id === spotId);
        if (spot) {
            handleSpotClick(spot);
        }
    };

    function getCategoryColor(categoryStr) {
        if (window.MapMarkersConfig && typeof window.MapMarkersConfig.getCategoryColor === 'function') {
            return window.MapMarkersConfig.getCategoryColor(categoryStr);
        }
        if (!categoryStr) return '#6B7280';
        const categories = categoryStr.split(',').map(c => c.trim().toLowerCase());
        const blue = '#0EA5E9';
        const cyan = '#06B6D4';
        const green = '#22C55E';
        const purple = '#8B5CF6';
        const amber = '#F59E0B';
        const pink = '#EC4899';
        const gold = '#D97706';
        const mapping = {
            'beach': blue, 'beaches': blue, 'island': blue, 'marine sanctuary': blue,
            'hot spring': blue, 'cold spring': blue,
            'waterfall': cyan, 'waterfalls': cyan, 'river': cyan, 'lake': cyan,
            'forest': green, 'nature park': green, 'wildlife sanctuary': green,
            'farm': green, 'eco-tourism': green, 'garden': green, 'park': green,
            'mountain': purple, 'mountains': purple, 'cave': purple, 'volcano': purple,
            'hiking': purple, 'camping': purple, 'viewpoint': purple, 'binoculars': purple,
            'historical': amber, 'cultural heritage': amber, 'museum': amber,
            'monument': amber, 'landmark': amber,
            'religious': gold, 'church': gold,
            'adventure': pink, 'recreation': pink, 'food destination': pink,
            'shopping': pink, 'festival venue': pink, 'resort': pink, 'resorts': pink
        };
        for (const cat of categories) { if (mapping[cat]) return mapping[cat]; }
        return '#6B7280';
    }

    function getStatusColor(status) {
        const colors = {
            'open': '#22C55E', 'closed': '#EF4444',
            'maintenance': '#F59E0B', 'unknown': '#6B7280'
        };
        return colors[status] || '#6B7280';
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
            'forest': 'tree', 'nature park': 'tree', 'marine sanctuary': 'fish',
            'wildlife sanctuary': 'paw', 'historical': 'landmark',
            'cultural heritage': 'landmark', 'religious': 'church',
            'museum': 'museum', 'monument': 'monument', 'landmark': 'landmark',
            'viewpoint': 'binoculars', 'adventure': 'hiking', 'hiking': 'hiking',
            'camping': 'campground', 'farm': 'seedling', 'eco-tourism': 'leaf',
            'garden': 'seedling', 'park': 'tree', 'recreation': 'bicycle',
            'hot spring': 'hot-tub-person', 'cold spring': 'snowflake',
            'food destination': 'utensils', 'shopping': 'shopping-cart',
            'festival venue': 'masks-theater', 'resort': 'hotel', 'resorts': 'hotel',
            'other': 'star'
        };
        for (const cat of categories) { if (mapping[cat]) return mapping[cat]; }
        return 'map-marker-alt';
    }

    function openSidebar(type, data) {
        const sidebar = document.getElementById('sidebarContainer');
        const overlay = document.getElementById('sidebarOverlay');
        const content = document.getElementById('sidebarContent');
        const title = document.getElementById('sidebarTitle');

        // Set title
        if (type === 'municipality') {
            title.textContent = data.name;
        } else {
            title.textContent = data.name;
        }

        // Show loading
        content.innerHTML = `
            <div class="loading-state">
                <div class="loading-spinner"></div>
                <p style="color:#6B7280;">Loading...</p>
            </div>
        `;

        // Show sidebar
        setTimeout(() => {
            sidebar.classList.add('active');
            overlay.classList.add('active');

            // Simulate loading and populate
            setTimeout(() => {
                if (type === 'municipality') {
                    populateMunicipalityDetail(data);
                } else {
                    populateSpotDetail(data);
                }
            }, 300);
        }, 100);
    }

    function populateMunicipalityDetail(muni) {
        const content = document.getElementById('sidebarContent');
        const spots = touristSpots.filter(spot => spot.municipality === muni.name);
        
        // Collect all images from all tourist spots in this municipality
        let allMuniImages = [];
        spots.forEach(spot => {
            if (spot.images && spot.images.length > 0) {
                spot.images.forEach(img => {
                    allMuniImages.push({
                        ...img,
                        spotName: spot.name
                    });
                });
            } else if (spot.photo_url) {
                allMuniImages.push({
                    photo_url: spot.photo_url,
                    spotName: spot.name
                });
            }
        });

        content.innerHTML = `
            <div class="muni-image-container">
                <i class="fas fa-city"></i>
            </div>
            
            <div class="muni-description">
                ${muni.description}
            </div>
            
            ${allMuniImages.length > 0 ? `
            <div class="section-header">Tourist Spot Images (${allMuniImages.length})</div>
            <div class="spot-images-grid">
                ${allMuniImages.map((img, index) => `
                    <div class="spot-image-container">
                        <div class="spot-image-loading">
                            <div class="loading-spinner"></div>
                        </div>
                        <img src="${img.photo_url}" alt="${img.spotName}" class="spot-gallery-image" data-index="${index}" loading="lazy">
                    </div>
                `).join('')}
            </div>
            ` : ''}
            
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
                                <div class="spot-meta-item">
                                    <i class="fas fa-star" style="color: #F59E0B;"></i>
                                    <span>${spot.rating}</span>
                                </div>
                                <div class="spot-meta-item">
                                    <i class="fas fa-ticket-alt"></i>
                                    <span>${spot.admissionFee}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;

        // Add image load event listeners for municipality gallery
        document.querySelectorAll('.spot-gallery-image').forEach(img => {
            img.addEventListener('load', function() {
                this.style.opacity = '1';
                const container = this.closest('.spot-image-container, .spot-detail-image');
                if (container) {
                    const loader = container.querySelector('.spot-image-loading');
                    if (loader) {
                        loader.style.opacity = '0';
                        setTimeout(() => {
                            loader.style.display = 'none';
                        }, 300);
                    }
                }
            });
            
            img.addEventListener('error', function() {
                this.alt = 'Image not available';
                const container = this.closest('.spot-image-container, .spot-detail-image');
                if (container) {
                    const loader = container.querySelector('.spot-image-loading');
                    if (loader) {
                        loader.innerHTML = '<i class="fas fa-image" style="font-size:24px;color:#9CA3AF;"></i>';
                    }
                }
            });
        });

        // Add listeners to spot cards
        document.querySelectorAll('.spot-card').forEach(card => {
            const spotId = parseInt(card.dataset.spotId);
            const marker = spotMarkers.find(m => m.options.spotData.id === spotId);

            // Hover effects
            card.addEventListener('mouseenter', () => {
                if (marker && marker._icon) {
                    marker._icon.style.transform = 'scale(1.2)';
                    marker.setZIndexOffset(500);
                }
            });

            card.addEventListener('mouseleave', () => {
                if (marker && marker._icon && activeSpotMarker !== marker) {
                    marker._icon.style.transform = 'scale(1)';
                    marker.setZIndexOffset(0);
                }
            });

            // Click handler
            card.addEventListener('click', () => {
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

        // Center map on spot
        map.setView([spot.lat, spot.lng], 15);

        // Highlight active marker
        if (activeSpotMarker) {
            activeSpotMarker.setZIndexOffset(0);
        }
        activeSpotMarker = spotMarkers.find(m => m.options.spotData.id === spot.id);
        if (activeSpotMarker) {
            activeSpotMarker.setZIndexOffset(1000);
            activeSpotMarker._icon.style.transform = 'scale(1.3)';
            setTimeout(() => {
                if (activeSpotMarker && activeSpotMarker._icon) {
                    activeSpotMarker._icon.style.transform = 'scale(1)';
                }
            }, 300);
        }

        // Open sidebar and populate spot detail
        const sidebar = document.getElementById('sidebarContainer');
        const overlay = document.getElementById('sidebarOverlay');
        const title = document.getElementById('sidebarTitle');

        if (title) {
            title.textContent = spot.name;
        }

        document.getElementById('sidebarBackBtn').classList.remove('hidden');

        populateSpotDetail(spot);

        if (sidebar && !sidebar.classList.contains('active')) {
            sidebar.classList.add('active');
            overlay.classList.add('active');
        }
    }

    function populateSpotDetail(spot) {
        const content = document.getElementById('sidebarContent');

        // Build images gallery HTML with loading states using the unified thumbnail + main image gallery structure
        let imagesGallery = '';
        const hasImages = spot.images && spot.images.length > 0;
        if (hasImages) {
            imagesGallery = `
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
            `;
        } else {
            imagesGallery = `
                <div class="spot-detail-image">
                    <i class="fas fa-${getCategoryIcon(spot.category)}"></i>
                </div>
            `;
        }

        window.currentSpotImages = spot.images;
        window.currentImageIndex = 0;

        content.innerHTML = `
            ${imagesGallery}
            
            <h2 class="spot-detail-title">${spot.name}</h2>
            <div class="spot-detail-location">
                <i class="fas fa-map-marker-alt" style="color: #2563EB;"></i>
                ${spot.municipality}, La Union
            </div>

            <div class="spot-detail-tags">
                <span class="spot-detail-category-tag">
                    <i class="fas fa-${getCategoryIcon(spot.category)}"></i> ${spot.category}
                </span>
                ${spot.classification_status ? `<span class="spot-detail-classification-tag">${spot.classification_status}</span>` : ''}
            </div>
            
            <div class="spot-detail-status" style="display: inline-block; padding: 6px 16px; border-radius: 20px; background: ${getStatusColor(spot.status)}; color: white; font-weight: 600; font-size: 14px; margin-bottom: 16px;">
                ${spot.status === 'open' ? 'Open' : spot.status === 'closed' ? 'Closed' : spot.status === 'maintenance' ? 'Under Maintenance' : 'Status Unknown'}
            </div>

            <div class="spot-detail-section">
                <div class="spot-detail-section-title">
                    <i class="fas fa-align-left"></i> Description
                </div>
                <div class="spot-detail-description">${spot.description}</div>
            </div>

            <div class="info-grid">
                <div class="info-card">
                    <div class="info-card-icon"><i class="fas fa-clock"></i></div>
                    <div class="info-card-label">Operating Hours</div>
                    <div class="info-card-value">${formatTime(spot.opening_time)} - ${formatTime(spot.closing_time)}</div>
                </div>
                <div class="info-card">
                    <div class="info-card-icon"><i class="fas fa-ticket-alt"></i></div>
                    <div class="info-card-label">Entrance Fee</div>
                    <div class="info-card-value">${spot.admissionFee}</div>
                </div>
                <div class="info-card">
                    <div class="info-card-icon"><i class="fas fa-map-pin"></i></div>
                    <div class="info-card-label">Address</div>
                    <div class="info-card-value">${spot.barangay ? spot.barangay + ', ' : ''}${spot.municipality}, La Union</div>
                </div>
                <div class="info-card">
                    <div class="info-card-icon"><i class="fas fa-globe"></i></div>
                    <div class="info-card-label">Coordinates</div>
                    <div class="info-card-value spot-detail-coords">${parseFloat(spot.lat).toFixed(6)}, ${parseFloat(spot.lng).toFixed(6)}</div>
                </div>
                <div class="info-card">
                    <div class="info-card-icon"><i class="fas fa-tag"></i></div>
                    <div class="info-card-label">Category</div>
                    <div class="info-card-value">${spot.category}</div>
                </div>
                ${spot.classification_status ? `
                <div class="info-card">
                    <div class="info-card-icon"><i class="fas fa-layer-group"></i></div>
                    <div class="info-card-label">Classification</div>
                    <div class="info-card-value">${spot.classification_status}</div>
                </div>
                ` : ''}
            </div>

            <div class="spot-detail-section">
                <div class="spot-detail-section-title">
                    <i class="fas fa-info-circle"></i> Additional Information
                </div>
                <div class="info-grid info-grid-na">
                    <div class="info-card info-card-na">
                        <div class="info-card-icon"><i class="fas fa-phone"></i></div>
                        <div class="info-card-label">Contact Information</div>
                        <div class="info-card-value-na">Not available</div>
                    </div>
                    <div class="info-card info-card-na">
                        <div class="info-card-icon"><i class="fas fa-water-ladder"></i></div>
                        <div class="info-card-label">Facilities</div>
                        <div class="info-card-value-na">Not available</div>
                    </div>
                    <div class="info-card info-card-na">
                        <div class="info-card-icon"><i class="fas fa-person-hiking"></i></div>
                        <div class="info-card-label">Activities</div>
                        <div class="info-card-value-na">Not available</div>
                    </div>
                    <div class="info-card info-card-na">
                        <div class="info-card-icon"><i class="fas fa-calendar-check"></i></div>
                        <div class="info-card-label">Best Time to Visit</div>
                        <div class="info-card-value-na">Not available</div>
                    </div>
                </div>
            </div>

            <div class="spot-detail-section">
                <a href="https://www.google.com/maps/dir/?api=1&destination=${spot.lat},${spot.lng}" 
                   target="_blank" rel="noopener noreferrer" 
                   class="directions-btn">
                    <i class="fas fa-directions"></i> Get Google Maps Directions
                    <i class="fas fa-external-link-alt" style="font-size:10px;margin-left:4px;"></i>
                </a>
            </div>
        `;

        // Add image load event listeners to show images and hide loading
        document.querySelectorAll('.spot-gallery-image').forEach(img => {
            img.addEventListener('load', function() {
                this.style.opacity = '1';
                const container = this.closest('.spot-image-container, .spot-detail-image');
                if (container) {
                    const loader = container.querySelector('.spot-image-loading');
                    if (loader) {
                        loader.style.opacity = '0';
                        setTimeout(() => {
                            loader.style.display = 'none';
                        }, 300);
                    }
                }
            });
            
            img.addEventListener('error', function() {
                this.alt = 'Image not available';
                const container = this.closest('.spot-image-container, .spot-detail-image');
                if (container) {
                    const loader = container.querySelector('.spot-image-loading');
                    if (loader) {
                        loader.innerHTML = '<i class="fas fa-image" style="font-size:24px;color:#9CA3AF;"></i>';
                    }
                }
            });
        });

        // Add travel mode switch listeners if available
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

    function showRoute(spot) {
        // Clear existing route
        if (currentRouteLayer) {
            map.removeLayer(currentRouteLayer);
        }

        // Simulate route (in real app, use routing API)
        const routeCoords = [
            [userLocation.lat, userLocation.lng],
            [spot.lat, spot.lng]
        ];

        // Draw straight line as fallback
        currentRouteLayer = L.polyline(routeCoords, {
            color: '#2563EB',
            weight: 4,
            opacity: 0.8,
            smoothFactor: 1
        }).addTo(map);

        // Update sidebar
        populateSpotDetail(spot);
    }

    function calculateDistance(coord1, coord2) {
        // Haversine formula
        const R = 6371; // Earth radius in km
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
        closeSidebar();
    }

    function closeSidebar() {
        const sidebar = document.getElementById('sidebarContainer');
        const overlay = document.getElementById('sidebarOverlay');

        sidebar.classList.remove('active');
        overlay.classList.remove('active');

        // Clear route
        if (currentRouteLayer) {
            map.removeLayer(currentRouteLayer);
            currentRouteLayer = null;
        }

        // Reset spot selection but keep municipality selected
        selectedSpot = null;
        sidebarState = 'municipality';

        // Reset active spot marker highlight
        if (activeSpotMarker) {
            activeSpotMarker.setZIndexOffset(0);
            if (activeSpotMarker._icon) {
                activeSpotMarker._icon.style.transform = 'scale(1)';
            }
            activeSpotMarker = null;
        }
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
                <img id="lightboxImage" src="${window.currentLightboxImages[index].photo_url}" alt="Full size image">
            </div>
            <button class="lightbox-nav lightbox-next" onclick="navigateLightbox(1)"><i class="fas fa-chevron-right"></i></button>
            <div class="lightbox-counter">${index + 1} / ${window.currentLightboxImages.length}</div>
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

    function setupEventListeners() {
        const closeBtn = document.getElementById('sidebarCloseBtn');
        const backBtn = document.getElementById('sidebarBackBtn');
        const overlay = document.getElementById('sidebarOverlay');
        const mapTabs = document.querySelectorAll('.map-tab');
        const muniDeselectBtn = document.getElementById('muniDeselectBtn');

        closeBtn.addEventListener('click', closeSidebar);
        backBtn.addEventListener('click', goBack);
        overlay.addEventListener('click', closeSidebar);

        if (muniDeselectBtn) {
            muniDeselectBtn.addEventListener('click', deselectMunicipality);
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const sidebar = document.getElementById('sidebarContainer');
                if (sidebar && sidebar.classList.contains('active')) {
                    closeSidebar();
                } else if (selectedMunicipality) {
                    deselectMunicipality();
                }
            }
        });

        closeBtn.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                closeSidebar();
            }
        });
        
        backBtn.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                goBack();
            }
        });

        mapTabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                const view = tab.dataset.view;
                if (!view || view === currentBaseLayer) {
                    return;
                }

                mapTabs.forEach((item) => item.classList.remove('active'));
                tab.classList.add('active');
                setMapLayer(view);
            });
        });
    }

    // Initialize
    initMap();
}

// Run immediately if the DOM element is already present (SPA injection),
// otherwise wait for DOMContentLoaded (fresh page load).
// Skip auto-init on tourist-spots page — initializeAll() handles it via refreshLuptoMap.
if (document.getElementById('lupto-map') && !document.getElementById('cardsView')) {
    initMapView();
} else {
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('lupto-map') && !document.getElementById('cardsView')) {
            initMapView();
        }
    });
}
