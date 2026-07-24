// MUNICIPAL Tourist Spots - Page Logic
// Classic script (no ES module syntax) — loaded as a plain <script> tag.
// Depends on: tourist-spots-api.js loaded BEFORE this file (provides window.TouristSpotsAPI),
// and Leaflet + Leaflet.markercluster (loaded via CDN in the page).
// Expects window.spotsData and window.municipalityData to be set by the PHP page before this script runs.

(function () {
    // Normalize data inputs to prevent script crashes on backend error responses
    window.spotsData = Array.isArray(window.spotsData) ? window.spotsData : [];
    window.municipalityData = window.municipalityData || {};

    const {
        getMunicipalityInfo,
        getMunicipalitySpots,
        getSpot,
        createSpot,
        updateSpot,
        deleteSpot,
        uploadSpotImage
    } = window.TouristSpotsAPI;

    let map, markerCluster;
    let modalMap, modalMarker;
    let uploadedImages = [];
    let currentOperatingStatus = 'open';
    let currentMaintenanceStatus = false;
    let deleteSpotId = null;
    
    // Generate a preview URL for a file
    function getFilePreviewUrl(file) {
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.onload = (e) => resolve(e.target.result);
            reader.onerror = () => resolve(null);
            reader.readAsDataURL(file);
        });
    }

    const mapLayers = {
        satellite: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles © Esri', maxZoom: 18
        }),
        street: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap', maxZoom: 18
        })
    };

    const fallbackLat = 16.3278;
    const fallbackLng = 120.3663;

    // Get municipality coordinates from data, with fallback
    function getMuniCoords() {
        return {
            lat: window.municipalityData.latitude || fallbackLat,
            lng: window.municipalityData.longitude || fallbackLng
        };
    }

    // Helper function to get category color
    function getCategoryColor(category) {
        if (window.MapMarkersConfig && typeof window.MapMarkersConfig.getCategoryColor === 'function') {
            var result = window.MapMarkersConfig.getCategoryColor(category);
            if (result) return result;
        }
        const colors = {
            'Beach': '#0EA5E9', 'beach': '#0EA5E9',
            'Waterfalls': '#06B6D4', 'waterfalls': '#06B6D4',
            'Eco-Tourism': '#22C55E', 'eco-tourism': '#22C55E',
            'Mountains & Hiking': '#8B5CF6', 'mountains & hiking': '#8B5CF6',
            'Mountain': '#8B5CF6', 'mountain': '#8B5CF6',
            'Resorts': '#EC4899', 'resorts': '#EC4899',
            'Food & Dining': '#F59E0B', 'food & dining': '#F59E0B',
            'Historical': '#F59E0B', 'historical': '#F59E0B',
            'Adventure': '#8B5CF6', 'adventure': '#8B5CF6',
            'Farm': '#22C55E', 'farm': '#22C55E',
            'Religious': '#8B5CF6', 'religious': '#8B5CF6'
        };
        return colors[category] || '#6B7280';
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
            'festival venue': 'masks-theater', 'resort': 'hotel', 'other': 'star'
        };
        for (const cat of categories) { if (mapping[cat]) return mapping[cat]; }
        return 'map-marker-alt';
    }

    // ========== Main Map Functions ==========
    function initMap() {
        if (map) {
            // Already initialized. Clear markers and redraw.
            if (markerCluster) {
                markerCluster.clearLayers();
            }
            addMunicipalMarker();
            addSpotMarkers();
            setTimeout(() => map.invalidateSize(), 300);
            return;
        }

        const { lat: muniLat, lng: muniLng } = getMuniCoords();

        // Initialize map with explicit options
        map = L.map('touristMap', {
            minZoom: 10,
            maxZoom: 19,
            worldCopyJump: false
        });

        // Add base map layers
        mapLayers.street.addTo(map);

        // Set initial view to municipality
        map.setView([muniLat, muniLng], 12);

        // Save map instance on the DOM element for the SPA router
        document.getElementById('touristMap')._leaflet_map = map;

        // Initialize marker cluster
        markerCluster = L.featureGroup();
        map.addLayer(markerCluster);

        // Add markers
        // addMunicipalMarker(); // Removed as requested
        addSpotMarkers();

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

        // Invalidate map size to fix tile loading
        setTimeout(() => {
            map.invalidateSize();
        }, 300);
    }

    function addMunicipalMarker() {
        const { lat: muniLat, lng: muniLng } = getMuniCoords();
        const muniName = window.municipalityData.name || '';
        const spotCount = window.spotsData.length;

        const icon = L.divIcon({
            className: 'municipal-marker',
            html: `<div style="background: #DC2626; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; box-shadow: 0 2px 8px rgba(0,0,0,0.3); border: 3px solid white; font-weight: 700; font-size: 16px;">${spotCount}</div>`,
            iconSize: [40, 40],
            iconAnchor: [20, 40]
        });

        const marker = L.marker([muniLat, muniLng], { icon: icon, zIndexOffset: 1000 });
        marker.bindPopup(`
        <div style="min-width: 180px;">
            <h4 style="margin: 0 0 8px 0;">${muniName}</h4>
            <p style="margin: 4px 0; font-size: 12px;"><i class="fas fa-map-pin"></i> ${spotCount} tourist spots</p>
        </div>
    `);
        marker.bindTooltip(muniName, {
            permanent: true,
            direction: 'bottom',
            offset: [0, 25],
            opacity: 0.9
        });
        marker.on('click', function () {
            map.setView([muniLat, muniLng], 14);
        });
        marker.addTo(map);
    }

    function addSpotMarkers() {
        if (!markerCluster) return;
        markerCluster.clearLayers();

        // Progressive rendering of spots in chunks of 50
        let index = 0;
        const chunkSize = 50;
        const spots = window.spotsData || [];

        function loadNextChunk() {
            const chunk = spots.slice(index, index + chunkSize);
            chunk.forEach(spot => {
                addSpotMarker(spot);
            });

            index += chunkSize;
            if (index < spots.length) {
                setTimeout(loadNextChunk, 50); // Small delay to yield UI thread
            }
        }

        setTimeout(loadNextChunk, 100);
    }

    function addSpotMarker(spot) {
        const { lat: muniLat, lng: muniLng } = getMuniCoords();

        const lat = parseFloat(spot.latitude) || muniLat;
        const lng = parseFloat(spot.longitude) || muniLng;

        const iconColor = getCategoryColor(spot.category);

        const icon = L.divIcon({
            className: 'spot-marker-icon',
            html: `<div style="background: ${iconColor}; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"><i class="fas fa-${getCategoryIcon(spot.category)}" style="font-size: 16px;"></i></div>`,
            iconSize: [32, 32],
            iconAnchor: [16, 32]
        });

        const spotImages = spot.images || (spot.photo_url ? [{ photo_url: spot.photo_url }] : []);
        const firstImage = spotImages.length > 0 ? spotImages[0].photo_url : null;

        const marker = L.marker([lat, lng], { icon: icon });
        marker.bindPopup(`
        <div style="font-family:'Inter', sans-serif; min-width: 240px; max-width: 320px; padding: 4px;">
            ${firstImage ? `<img data-src="${firstImage}?t=${Date.now()}" class="popup-lazy-img" alt="${spot.name}" style="width: 100%; height: 130px; object-fit: cover; border-radius: 6px; margin-bottom: 8px; background:#F3F4F6;">` : ''}
            <h4 style="margin: 0 0 4px 0; font-size: 15px; font-weight: 700; color: #1E293B;">${spot.name}</h4>
            <div style="font-size: 12px; color: #4B5563; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                <span style="background: #E0F2FE; color: #0369A1; padding: 2px 6px; border-radius: 4px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                    <i class="fas fa-${getCategoryIcon(spot.category)}"></i> ${spot.category}
                </span>
            </div>
            <p style="margin: 0 0 8px 0; font-size: 12px; color: #475569; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis;">
                ${spot.description || 'No description available.'}
            </p>
            <div style="border-top: 1px solid #E2E8F0; padding-top: 8px; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 12px; font-weight: 700; color: #0F172A;">
                    Fee: ${(() => { var ft = Array.isArray(spot.fee_types) ? spot.fee_types : []; if (!ft || ft.length === 0) return 'Free'; var p = []; if (ft.includes('entrance') && Number(spot.entrance_fee||0) > 0) p.push('₱'+spot.entrance_fee); if (ft.includes('environmental') && Number(spot.environmental_fee||0) > 0) p.push('EF:₱'+spot.environmental_fee); return p.length ? p.join(' | ') : 'Free'; })()}
                </span>
                <button onclick="openModal('${spot.id}')" style="background: #2563EB; color: white; border: none; padding: 4px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 3px;">
                    <i class="fas fa-eye"></i> View Details
                </button>
            </div>
        </div>
    `, { maxWidth: 320 });
        markerCluster.addLayer(marker);
    }

    // ========== View Toggle ==========
    function initViewToggle() {
        document.getElementById('viewCards').addEventListener('click', function () {
            document.getElementById('viewCards').classList.add('active');
            document.getElementById('viewTable').classList.remove('active');
            document.getElementById('cardsView').style.display = 'grid';
            document.getElementById('tableView').style.display = 'none';
        });

        document.getElementById('viewTable').addEventListener('click', function () {
            document.getElementById('viewTable').classList.add('active');
            document.getElementById('viewCards').classList.remove('active');
            document.getElementById('cardsView').style.display = 'none';
            document.getElementById('tableView').style.display = 'block';
        });
    }

    // ========== Dropdown Toggle ==========
    function initDropdowns() {
        window.toggleDropdown = function (id) {
            const menu = document.getElementById(id);
            const isOpen = menu.style.display === 'block';
            document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none');
            if (!isOpen) menu.style.display = 'block';
        };
        document.addEventListener('click', e => {
            if (!e.target.closest('.card-actions-dropdown') && !e.target.closest('.table-actions-dropdown'))
                document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none');
        });
    }

    // ========== Spot Modal Functions ==========
    window.openModal = async function (spotId) {
        if (typeof window.openSpotModal === 'function') {
            return window.openSpotModal(spotId);
        }
        document.getElementById('spotModal').classList.add('active');
        try {
            const spot = await getSpot(spotId);
            document.getElementById('modalTitle').textContent = spot.name;

            let imagesHtml = '';
            const spotImages = spot.images || (spot.photo_url ? [{ photo_url: spot.photo_url }] : []);
            if (spotImages.length > 0) {
                // Only display the first image
                const firstImage = spotImages[0];
                imagesHtml = '<div style="border-radius:8px;overflow:hidden;margin-bottom:16px;max-height:400px;"><img src="' + firstImage.photo_url + '?t=' + Date.now() + '" alt="' + spot.name + '" style="width:100%;height:100%;object-fit:cover;"></div>';
            }

            // Map classification status
            const statusMap = {
                'EXIST': 'EXISTING',
                'EMERGE': 'EMERGING',
                'POTENTIAL': 'POTENTIAL'
            };
            const statusColor = {
                'EXIST': '#10B981',
                'EMERGE': '#8B5CF6',
                'POTENTIAL': '#F59E0B'
            };
            const displayStatus = statusMap[spot.classification_status] || 'N/A';

            // Format time
            function formatTime(timeStr) {
                if (!timeStr) return 'N/A';
                const [hours, minutes] = timeStr.split(':').map(Number);
                const period = hours >= 12 ? 'PM' : 'AM';
                const displayHours = hours % 12 || 12;
                return `${displayHours}:${String(minutes).padStart(2, '0')} ${period}`;
            }

            document.getElementById('modalBody').innerHTML = `
            ${imagesHtml}
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:16px;flex-wrap:wrap;">
                <span style="font-size:13px;color:#6B7280;"><i class="fas fa-map-marker-alt"></i> ${spot.municipality_name}, La Union</span>
                <span style="font-size:13px;font-weight:700;padding:4px 12px;border-radius:20px;background:${statusColor[spot.classification_status] || '#6B7280'};color:white;">${displayStatus}</span>
                ${spot.is_maintenance ? '<span style="font-size:13px;font-weight:700;padding:4px 12px;border-radius:20px;background:#F59E0B;color:#92400E;"><i class="fas fa-exclamation-triangle"></i> Under Maintenance</span>' : ''}
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                <div style="background:#F8FAFC;border-radius:8px;padding:12px;">
                    <div style="font-size:11px;color:#6B7280;font-weight:700;text-transform:uppercase;margin-bottom:6px;">Category</div>
                    <div style="display:flex;flex-wrap:wrap;gap:5px;">
                        ${(spot.category || 'Other').split(',').map(c => c.trim()).filter(Boolean).map(c =>
                `<span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#DBEAFE;color:#2563EB;">${c}</span>`
            ).join('')}
                    </div>
                </div>
                <div style="background:#F8FAFC;border-radius:8px;padding:12px;">
                    <div style="font-size:11px;color:#6B7280;font-weight:700;text-transform:uppercase;margin-bottom:4px;">Fees</div>
                    ${(() => { var ft = Array.isArray(spot.fee_types) ? spot.fee_types : (spot.fee_types ? String(spot.fee_types).split(',').map(function(s){return s.trim()}).filter(Boolean) : []); if (ft.length === 0) return '<span style="font-size:13px;color:#9CA3AF;">No Fees</span>'; var p = []; if (ft.includes('entrance')) p.push('<span style="display:block;font-size:13px;font-weight:600;color:#1E293B;">Entrance Fee: ₱' + (Number(spot.entrance_fee)||0).toLocaleString(undefined,{minimumFractionDigits:0}) + '</span>'); if (ft.includes('environmental')) p.push('<span style="display:block;font-size:13px;font-weight:600;color:#1E293B;">Environmental Fee: ₱' + (Number(spot.environmental_fee)||0).toLocaleString(undefined,{minimumFractionDigits:0}) + '</span>'); return p.join(''); })()}
                </div>
                <div style="background:#F8FAFC;border-radius:8px;padding:12px;">
                    <div style="font-size:11px;color:#6B7280;font-weight:700;text-transform:uppercase;margin-bottom:4px;">Opening Time</div>
                    <div style="font-size:14px;font-weight:600;">${formatTime(spot.opening_time)}</div>
                </div>
                <div style="background:#F8FAFC;border-radius:8px;padding:12px;">
                    <div style="font-size:11px;color:#6B7280;font-weight:700;text-transform:uppercase;margin-bottom:4px;">Closing Time</div>
                    <div style="font-size:14px;font-weight:600;">${formatTime(spot.closing_time)}</div>
                </div>
                ${spot.latitude ? `
                    <div style="background:#F8FAFC;border-radius:8px;padding:12px;">
                        <div style="font-size:11px;color:#6B7280;font-weight:700;text-transform:uppercase;margin-bottom:4px;">Latitude</div>
                        <div style="font-size:14px;font-weight:600;"><i class="fas fa-map-pin"></i> ${parseFloat(spot.latitude).toFixed(6)}</div>
                    </div>
                ` : ''}
                ${spot.longitude ? `
                    <div style="background:#F8FAFC;border-radius:8px;padding:12px;">
                        <div style="font-size:11px;color:#6B7280;font-weight:700;text-transform:uppercase;margin-bottom:4px;">Longitude</div>
                        <div style="font-size:14px;font-weight:600;"><i class="fas fa-map-pin"></i> ${parseFloat(spot.longitude).toFixed(6)}</div>
                    </div>
                ` : ''}
                <div style="background:#F8FAFC;border-radius:8px;padding:12px;">
                    <div style="font-size:11px;color:#6B7280;font-weight:700;text-transform:uppercase;margin-bottom:4px;">Submitted</div>
                    <div style="font-size:14px;font-weight:600;">${new Date(spot.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</div>
                </div>
            </div>
            <div style="margin-bottom:20px;">
                <div style="font-size:11px;color:#6B7280;font-weight:700;text-transform:uppercase;margin-bottom:8px;">Description</div>
                <p style="color:#4B5563;line-height:1.6;margin:0;">${spot.description || 'No description provided.'}</p>
            </div>
        `;
        } catch (err) {
            document.getElementById('modalBody').innerHTML = '<p style="color:#DC2626;">Failed to load spot details.</p>';
        }
    };

    window.closeModal = function () {
        document.getElementById('spotModal').classList.remove('active');
    };

    // ========== Form Functions ==========
    window.openCreateForm = function () {
        document.getElementById('formModalTitle').textContent = 'Add New Spot';
        document.getElementById('spotId').value = '';
        document.getElementById('spotName').value = '';
        document.getElementById('nameCharCount').textContent = '0';
        setSelectedCategories('');
        document.getElementById('spotClassification').value = '';
        document.getElementById('spotFee').value = '0';
        document.getElementById('environmentalFee').value = '0';
        document.querySelectorAll('.fee-type-chk').forEach(chk => chk.checked = false);
        document.getElementById('feeTypes').value = '';
        var label = document.getElementById('feeTypesLabel');
        if (label) { label.textContent = 'No Fees'; label.style.color = '#9CA3AF'; }
        document.getElementById('entranceFeeField').style.display = 'none';
        document.getElementById('environmentalFeeField').style.display = 'none';
        document.getElementById('spotLatitude').value = '';
        document.getElementById('spotLongitude').value = '';
        if (document.getElementById('spotBarangay')) document.getElementById('spotBarangay').value = '';
        document.getElementById('spotDescription').value = '';
        document.getElementById('descCharCount').textContent = '0';
        document.getElementById('imagePreviews').innerHTML = '';
        uploadedImages = [];
        document.getElementById('spotOpeningTime').value = '';
        document.getElementById('spotClosingTime').value = '';
        if (document.getElementById('spotIsMaintenance')) document.getElementById('spotIsMaintenance').checked = false;
        if (window.setOperatingStatus) window.setOperatingStatus('open');
        if (window.setMaintenanceStatus) window.setMaintenanceStatus(false);
        // Hide Under Maintenance — not applicable when adding a new spot
        const maintenanceField = document.getElementById('maintenance-field');
        if (maintenanceField) maintenanceField.style.display = 'none';
        document.getElementById('spotFormModal').classList.add('active');
        setTimeout(initModalMap, 150);
    };

    window.editSpot = async function (spotId) {
        try {
            const spot = await getSpot(spotId);
            document.getElementById('formModalTitle').textContent = 'Edit Spot';
            // Show Under Maintenance when editing an existing spot
            const maintenanceField = document.getElementById('maintenance-field');
            if (maintenanceField) maintenanceField.style.display = '';
            document.getElementById('spotId').value = spot.id;
            document.getElementById('spotName').value = spot.name;
            document.getElementById('nameCharCount').textContent = spot.name.length;
            setSelectedCategories(spot.category || '');
            // Map DB status values to form values
            const statusMap = {
                'EXIST': 'EXISTING',
                'EMERGE': 'EMERGING',
                'POTENTIAL': 'POTENTIAL'
            };
            document.getElementById('spotClassification').value = statusMap[spot.classification_status] || '';
            document.getElementById('spotFee').value = spot.entrance_fee || 0;
            document.getElementById('environmentalFee').value = spot.environmental_fee || 0;
            setFeeTypesFromData(spot.fee_types || []);
            document.getElementById('spotLatitude').value = spot.latitude || '';
            document.getElementById('spotLongitude').value = spot.longitude || '';
            if (document.getElementById('spotBarangay') && spot.barangay) {
                document.getElementById('spotBarangay').value = spot.barangay;
            }
            document.getElementById('spotDescription').value = spot.description || '';
            document.getElementById('descCharCount').textContent = (spot.description || '').length;
            uploadedImages = spot.images ? spot.images.map(img => img.photo_url) : (spot.photo_url ? [spot.photo_url] : []);
            renderImagePreviews();
            document.getElementById('spotOpeningTime').value = spot.opening_time || '';
            document.getElementById('spotClosingTime').value = spot.closing_time || '';
            if (document.getElementById('spotIsMaintenance')) {
                document.getElementById('spotIsMaintenance').checked = !!spot.is_maintenance;
            }
            if (window.setMaintenanceStatus) window.setMaintenanceStatus(spot.is_maintenance);
            if (window.setOperatingStatus) {
                if (!spot.is_maintenance) {
                    if (spot.opening_time && spot.closing_time) {
                        const now = new Date();
                        const currentTime = now.getHours() * 60 + now.getMinutes();
                        const [openH, openM] = spot.opening_time.split(':').map(Number);
                        const [closeH, closeM] = spot.closing_time.split(':').map(Number);
                        const openTime = openH * 60 + openM;
                        const closeTime = closeH * 60 + closeM;
                        window.setOperatingStatus(currentTime >= openTime && currentTime < closeTime ? 'open' : 'closed');
                    } else {
                        window.setOperatingStatus('open');
                    }
                } else {
                    window.setOperatingStatus('closed');
                }
            }
            document.getElementById('spotFormModal').classList.add('active');
            setTimeout(initModalMap, 150);
        } catch (err) {
            alert('Failed to load spot for editing.');
        }
    };

    window.closeFormModal = function () {
        document.getElementById('spotFormModal')?.classList.remove('active');
        document.getElementById('duplicateSpotNameModal')?.classList.remove('active');
        const spotIdEl = document.getElementById('spotId');
        if (spotIdEl) spotIdEl.value = '';
        const spotNameEl = document.getElementById('spotName');
        if (spotNameEl) spotNameEl.value = '';
    };

    window.toggleFeeTypesDropdown = function (e) {
        e.stopPropagation();
        const dd = document.getElementById('feeTypesDropdown');
        const chevron = document.getElementById('feeTypesChevron');
        if (!dd) return;
        const isVisible = dd.style.display === 'block';
        dd.style.display = isVisible ? 'none' : 'block';
        if (chevron) chevron.style.transform = isVisible ? 'rotate(0deg)' : 'rotate(180deg)';
    };

    window.onFeeTypeChange = function () {
        const selected = Array.from(document.querySelectorAll('.fee-type-chk:checked')).map(c => c.value);
        document.getElementById('feeTypes').value = selected.join(',');
        const label = document.getElementById('feeTypesLabel');
        const names = { entrance: 'Entrance Fee', environmental: 'Environmental Fee' };
        if (selected.length === 0) {
            label.textContent = 'No Fees';
            label.style.color = '#9CA3AF';
        } else {
            label.textContent = selected.map(v => names[v] || v).join(', ');
            label.style.color = '#1E293B';
        }
        const entranceField = document.getElementById('entranceFeeField');
        const envField = document.getElementById('environmentalFeeField');
        const spotFeeInput = document.getElementById('spotFee');
        const envFeeInput = document.getElementById('environmentalFee');
        if (entranceField) entranceField.style.display = selected.includes('entrance') ? '' : 'none';
        if (envField) envField.style.display = selected.includes('environmental') ? '' : 'none';
        if (!selected.includes('entrance') && spotFeeInput) spotFeeInput.value = '0';
        if (!selected.includes('environmental') && envFeeInput) envFeeInput.value = '0';
    };

    function setFeeTypesFromData(feeTypes) {
        const types = Array.isArray(feeTypes) ? feeTypes : (feeTypes ? feeTypes.split(',').map(s => s.trim()).filter(Boolean) : []);
        document.querySelectorAll('.fee-type-chk').forEach(chk => chk.checked = types.includes(chk.value));
        window.onFeeTypeChange();
    }

    function getFeeTypesArray() {
        const val = document.getElementById('feeTypes').value;
        return val ? val.split(',').map(s => s.trim()).filter(Boolean) : [];
    }

    function formatFeesShort(spot) {
        const ft = Array.isArray(spot.fee_types) ? spot.fee_types : (spot.fee_types ? String(spot.fee_types).split(',').map(s => s.trim()).filter(Boolean) : []);
        if (ft.length === 0) return 'No Fees';
        let parts = [];
        if (ft.includes('entrance')) parts.push('₱' + Number(spot.entrance_fee||0).toLocaleString(undefined, {minimumFractionDigits:0}));
        if (ft.includes('environmental')) parts.push('EF: ₱' + Number(spot.environmental_fee||0).toLocaleString(undefined, {minimumFractionDigits:0}));
        return parts.join(' | ');
    }

    window.setOperatingStatus = function (status) {
        currentOperatingStatus = status;
        // These buttons only exist in older modal versions; guard against missing elements
        const openBtn = document.getElementById('statusOpenBtn');
        const closedBtn = document.getElementById('statusClosedBtn');
        if (!openBtn || !closedBtn) return;

        if (status === 'open') {
            openBtn.style.background = '#ECFDF5';
            openBtn.style.borderColor = '#22C55E';
            openBtn.style.color = '#166534';
            closedBtn.style.background = 'white';
            closedBtn.style.borderColor = '#E5E7EB';
            closedBtn.style.color = '#4B5563';
        } else {
            closedBtn.style.background = '#FEF2F2';
            closedBtn.style.borderColor = '#EF4444';
            closedBtn.style.color = '#991B1B';
            openBtn.style.background = 'white';
            openBtn.style.borderColor = '#E5E7EB';
            openBtn.style.color = '#4B5563';
        }
    };

    window.setMaintenanceStatus = function (isMaintenance) {
        currentMaintenanceStatus = isMaintenance;
        // These buttons only exist in older modal versions; guard against missing elements
        const offBtn = document.getElementById('maintenanceOffBtn');
        const onBtn = document.getElementById('maintenanceOnBtn');
        if (!offBtn || !onBtn) return;

        if (isMaintenance) {
            onBtn.style.background = '#FFFBEB';
            onBtn.style.borderColor = '#F59E0B';
            onBtn.style.color = '#92400E';
            offBtn.style.background = 'white';
            offBtn.style.borderColor = '#E5E7EB';
            offBtn.style.color = '#4B5563';
        } else {
            offBtn.style.background = '#F0FDF4';
            offBtn.style.borderColor = '#22C55E';
            offBtn.style.color = '#166534';
            onBtn.style.background = 'white';
            onBtn.style.borderColor = '#E5E7EB';
            onBtn.style.color = '#4B5563';
        }
    };

    // ========== Image Upload Functions ==========
    window.handleDragOver = function (e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('imageUploadArea').style.borderColor = '#2563EB';
        document.getElementById('imageUploadArea').style.backgroundColor = '#DBEAFE';
    };

    window.handleDragLeave = function (e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('imageUploadArea').style.borderColor = '#D1D5DB';
        document.getElementById('imageUploadArea').style.backgroundColor = '#F9FAFB';
    };

    window.handleImageDrop = function (e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('imageUploadArea').style.borderColor = '#D1D5DB';
        document.getElementById('imageUploadArea').style.backgroundColor = '#F9FAFB';
        processFiles(e.dataTransfer.files);
    };

    window.handleImageSelect = function (e) {
        e.preventDefault();
        e.stopPropagation();
        const files = Array.from(e.target.files);
        // Clear input to allow selecting the same files again
        e.target.value = '';
        processFiles(files);
    };

    async function processFiles(files) {
        const validFiles = [];
        for (const file of files) {
            if (uploadedImages.length + validFiles.length >= 3) {
                alert('Maximum 3 images allowed');
                break;
            }
            if (file.type !== 'image/jpeg' && file.type !== 'image/jpg' && file.type !== 'image/png') {
                alert('Only JPEG/PNG files are allowed');
                continue;
            }
            if (file.size > 10 * 1024 * 1024) {
                alert('File size must be less than 10MB');
                continue;
            }
            validFiles.push(file);
        }
        
        if (validFiles.length === 0) return;
        
        // Add files immediately with preview and loading state
        for (const file of validFiles) {
            const previewUrl = await getFilePreviewUrl(file);
            uploadedImages.push({
                photo_url: previewUrl,
                filename: file.name,
                isLoading: true,
                id: Date.now() + Math.random() // Temporary unique ID
            });
        }
        renderImagePreviews();
        
        // Upload all images in parallel
        const results = await Promise.allSettled(
            validFiles.map(async (file, idx) => {
                const tempId = uploadedImages[uploadedImages.length - validFiles.length + idx].id;
                try {
                    const result = await uploadSpotImage(file);
                    return { file, result, tempId, success: true };
                } catch (err) {
                    return { file, error: err, tempId, success: false };
                }
            })
        );
        
        // Process results
        for (const item of results) {
            const index = uploadedImages.findIndex(img => img.id === item.tempId);
            if (index !== -1) {
                if (item.success) {
                    uploadedImages[index] = item.result;
                } else {
                    uploadedImages.splice(index, 1);
                    const filename = item.file?.name || 'file';
                    alert(`Failed to upload ${filename}`);
                }
            }
        }
        
        renderImagePreviews();
    }

    function renderImagePreviews() {
        const container = document.getElementById('imagePreviews');
        container.innerHTML = '';
        container.style.display = 'flex';
        container.style.gap = '10px';
        container.style.flexWrap = 'wrap';

        uploadedImages.forEach((img, index) => {
            const div = document.createElement('div');
            div.style.cssText = 'position: relative; border-radius: 8px; overflow: hidden; height: 80px; width: 80px;';

            const imgElement = document.createElement('img');
            imgElement.src = typeof img === 'string' ? img : img.photo_url;
            imgElement.style.cssText = `width: 100%; height: 100%; object-fit: cover; ${img.isLoading ? 'filter: brightness(0.7);' : ''}`;
            
            if (img.isLoading) {
                const overlay = document.createElement('div');
                overlay.style.cssText = 'position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.3); z-index: 10;';
                overlay.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:24px;color:white;"></i>';
                div.appendChild(overlay);
            }

            const removeBtn = document.createElement('button');
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            removeBtn.style.cssText = 'position: absolute; top: 4px; right: 4px; background: rgba(220, 38, 38, 0.9); color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center;';
            if (img.isLoading) {
                removeBtn.style.display = 'none';
            }
            removeBtn.onclick = (e) => {
                e.stopPropagation();
                uploadedImages.splice(index, 1);
                renderImagePreviews();
            };

            div.appendChild(imgElement);
            div.appendChild(removeBtn);
            container.appendChild(div);
        });
    }

    // ========== Modal Map Functions ==========
    function initModalMap() {
        const { lat: muniLat, lng: muniLng } = getMuniCoords();
        const initialLat = parseFloat(document.getElementById('spotLatitude').value) || muniLat;
        const initialLng = parseFloat(document.getElementById('spotLongitude').value) || muniLng;

        if (modalMap) {
            modalMap.remove();
            modalMap = null;
            modalMarker = null;
        }

        modalMap = L.map('modalMap', {
            minZoom: 10,
            maxZoom: 19,
            worldCopyJump: false
        });

        // Dedicated layer instances for the modal map to avoid sharing singletons with main map
        const modalStreet = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap',
            maxZoom: 19
        });
        const modalSatellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: '© Esri',
            maxZoom: 19
        });

        modalStreet.addTo(modalMap);

        const modalBaseMaps = {
            "Street Map": modalStreet,
            "Satellite Map": modalSatellite
        };
        L.control.layers(modalBaseMaps, null, { position: 'topright' }).addTo(modalMap);

        modalMap.setView([initialLat, initialLng], 14);

        const dragIcon = L.divIcon({
            html: `<div style="background:#2563EB;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;border:3px solid white;box-shadow:0 3px 10px rgba(37,99,235,.45);cursor:grab;"><i class="fas fa-map-marker-alt" style="font-size:14px;"></i></div>`,
            iconSize: [32, 32],
            iconAnchor: [16, 32]
        });
        modalMarker = L.marker([initialLat, initialLng], { icon: dragIcon, draggable: true }).addTo(modalMap);
        // Real-time field update while dragging
        modalMarker.on('drag', function (e) {
            const pos = e.target.getLatLng();
            document.getElementById('spotLatitude').value = pos.lat.toFixed(6);
            document.getElementById('spotLongitude').value = pos.lng.toFixed(6);
        });
        modalMarker.on('dragend', function (e) {
            const pos = e.target.getLatLng();
            document.getElementById('spotLatitude').value = pos.lat.toFixed(6);
            document.getElementById('spotLongitude').value = pos.lng.toFixed(6);
            // Auto-detect barangay based on new pin position
            if (typeof autoDetectBarangayFromCoords === 'function') {
                autoDetectBarangayFromCoords(pos.lat, pos.lng);
            } else if (typeof window.autoDetectBarangayFromCoords === 'function') {
                window.autoDetectBarangayFromCoords(pos.lat, pos.lng);
            }
        });

        modalMap.on('click', function (e) {
            modalMarker.setLatLng(e.latlng);
            document.getElementById('spotLatitude').value = e.latlng.lat.toFixed(6);
            document.getElementById('spotLongitude').value = e.latlng.lng.toFixed(6);
            // Auto-detect barangay based on clicked position
            if (typeof autoDetectBarangayFromCoords === 'function') {
                autoDetectBarangayFromCoords(e.latlng.lat, e.latlng.lng);
            } else if (typeof window.autoDetectBarangayFromCoords === 'function') {
                window.autoDetectBarangayFromCoords(e.latlng.lat, e.latlng.lng);
            }
        });

        // Invalidate size multiple times to handle modal animation
        [100, 250, 500].forEach(delay => {
            setTimeout(() => {
                if (modalMap) modalMap.invalidateSize();
            }, delay);
        });
    }

    window.updateMapMarker = function () {
        if (!modalMap || !modalMarker) return;
        const lat = parseFloat(document.getElementById('spotLatitude').value);
        const lng = parseFloat(document.getElementById('spotLongitude').value);
        if (!isNaN(lat) && !isNaN(lng)) {
            modalMarker.setLatLng([lat, lng]);
            modalMap.setView([lat, lng], modalMap.getZoom());
        }
    };

    // Alias for compatibility with new modal oninput handlers
    window.updateMapMarkerFromInput = window.updateMapMarker;

    // ========== Multi-Category Chip Logic ==========
    // ========== Form Category Dropdown Logic ==========
    function initCategoryChips() {
        // Close dropdowns when clicking outside
        document.addEventListener('click', function (e) {
            const formDd = document.getElementById('formCatDropdown');
            const formBtn = document.getElementById('formCatDropdownBtn');
            if (formDd && formBtn && !formBtn.contains(e.target) && !formDd.contains(e.target)) {
                formDd.style.display = 'none';
                const chevron = document.getElementById('formCatChevron');
                if (chevron) chevron.style.transform = 'rotate(0deg)';
            }

            const filterDd = document.getElementById('catFilterDropdown');
            const filterBtn = document.getElementById('catFilterBtn');
            if (filterDd && filterBtn && !filterBtn.contains(e.target) && !filterDd.contains(e.target)) {
                filterDd.style.display = 'none';
                const chevron = document.getElementById('catChevron');
                if (chevron) chevron.style.transform = 'rotate(0deg)';
            }
        });
    }

    window.toggleFormCatDropdown = function (e) {
        e.stopPropagation();
        const dd = document.getElementById('formCatDropdown');
        const chevron = document.getElementById('formCatChevron');
        if (!dd) return;
        const isVisible = dd.style.display === 'block';
        dd.style.display = isVisible ? 'none' : 'block';
        if (chevron) chevron.style.transform = isVisible ? 'rotate(0deg)' : 'rotate(180deg)';
    };

    window.toggleFormCategory = function (itemEl, e) {
        e.stopPropagation();
        const chk = itemEl.querySelector('.form-cat-chk');
        if (chk) {
            chk.checked = !chk.checked;
        }
        syncCategoryHiddenInput();
    };

    function syncCategoryHiddenInput() {
        const selected = Array.from(document.querySelectorAll('.form-cat-chk:checked'))
            .map(c => c.value);
        const hiddenInput = document.getElementById('spotCategory');
        if (hiddenInput) hiddenInput.value = selected.join(',');

        const label = document.getElementById('formCatDropdownLabel');
        if (selected.length > 0) {
            if (label) {
                label.textContent = selected.join(', ');
                label.style.color = '#1E293B';
            }
        } else {
            if (label) {
                label.textContent = 'Select Categories...';
                label.style.color = '#9CA3AF';
            }
        }
    }

    function setSelectedCategories(categoryStr) {
        document.querySelectorAll('.form-cat-chk').forEach(c => c.checked = false);
        if (!categoryStr) { syncCategoryHiddenInput(); return; }
        const cats = categoryStr.split(',').map(s => s.trim());
        cats.forEach(cat => {
            const chk = document.querySelector(`.form-cat-chk[value="${cat}"]`);
            if (chk) chk.checked = true;
        });
        syncCategoryHiddenInput();
    }

    window.useCurrentLocation = function () {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function (pos) {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                document.getElementById('spotLatitude').value = lat.toFixed(6);
                document.getElementById('spotLongitude').value = lng.toFixed(6);
                updateMapMarker();
            });
        }
    };

    // ========== Form Validation & Submit ==========
    let pendingSaveData = null;

    window.submitSpotForm = async function (e) {
        e.preventDefault();

        const stillUploading = uploadedImages.some(img => img.isLoading);
        if (stillUploading) {
            showToast('Please wait for all images to finish uploading before saving', 'danger');
            return;
        }

        const spotId = document.getElementById('spotId').value;
        const name = document.getElementById('spotName').value.trim();
        // Multi-category: get from hidden input populated by chips
        const category = document.getElementById('spotCategory').value;
        const classificationStatus = document.getElementById('spotClassification').value;
        const fee = parseFloat(document.getElementById('spotFee').value) || 0;
        const envFee = parseFloat(document.getElementById('environmentalFee').value) || 0;
        const feeTypes = getFeeTypesArray();
        const lat = document.getElementById('spotLatitude').value || null;
        const lng = document.getElementById('spotLongitude').value || null;
        const barangay = document.getElementById('spotBarangay') ? (document.getElementById('spotBarangay').value || null) : null;
        const desc = document.getElementById('spotDescription').value.trim();
        const images = uploadedImages
            .filter(img => !img.isLoading && (typeof img === 'string' ? !img.startsWith('blob:') : img.photo_url && !img.photo_url.startsWith('blob:')))
            .map(img => {
            const url = typeof img === 'string' ? img : img.photo_url;
            return { photo_url: url };
        });
        const openingTime = document.getElementById('spotOpeningTime').value || null;
        const closingTime = document.getElementById('spotClosingTime').value || null;
        const isMaintenance = document.getElementById('spotIsMaintenance') ? (document.getElementById('spotIsMaintenance').checked ? 1 : 0) : currentMaintenanceStatus;

        if (!name || !category || !classificationStatus || !desc) {
            alert('Please fill in all required fields');
            return;
        }

        pendingSaveData = {
            spotId, name, category, classificationStatus, fee, lat, lng, barangay,
            desc, images, openingTime, closingTime, isMaintenance
        };

        document.getElementById('saveConfirmModal').classList.add('active');
    };

    window.closeSaveConfirmModal = function () {
        document.getElementById('saveConfirmModal').classList.remove('active');
        pendingSaveData = null;
    };

    window.confirmSaveSpot = async function () {
        if (!pendingSaveData) return;

        const confirmBtn = document.getElementById('saveConfirmBtn');
        const confirmIcon = document.getElementById('confirmBtnIcon');
        const confirmSpinner = document.getElementById('confirmBtnSpinner');
        const confirmLabel = document.getElementById('confirmBtnLabel');
        const noBtn = document.getElementById('saveConfirmNoBtn');

        if (confirmBtn) confirmBtn.disabled = true;
        if (confirmIcon) confirmIcon.style.display = 'none';
        if (confirmSpinner) confirmSpinner.style.display = 'inline-block';
        if (confirmLabel) confirmLabel.textContent = 'Saving...';
        if (noBtn) noBtn.disabled = true;

        const resetConfirmBtn = () => {
            if (confirmBtn) confirmBtn.disabled = false;
            if (confirmIcon) confirmIcon.style.display = 'inline-block';
            if (confirmSpinner) confirmSpinner.style.display = 'none';
            if (confirmLabel) confirmLabel.textContent = 'Yes, Save';
            if (noBtn) noBtn.disabled = false;
        };

        const { spotId, name, category, classificationStatus, fee, lat, lng, barangay, desc, images, openingTime, closingTime, isMaintenance } = pendingSaveData;

        try {
            if (spotId) {
                await updateSpot({
                    id: spotId,
                    name,
                    category,
                    classification_status: classificationStatus,
                    entrance_fee: fee,
                    environmental_fee: envFee,
                    fee_types: feeTypes,
                    description: desc,
                    images,
                    latitude: lat,
                    longitude: lng,
                    barangay: barangay,
                    opening_time: openingTime,
                    closing_time: closingTime,
                    is_maintenance: isMaintenance
                });
                sessionStorage.setItem('save_success_toast', 'Spot updated successfully!');
            } else {
                await createSpot({
                    name,
                    category,
                    classification_status: classificationStatus,
                    entrance_fee: fee,
                    environmental_fee: envFee,
                    fee_types: feeTypes,
                    description: desc,
                    images,
                    latitude: lat,
                    longitude: lng,
                    barangay: barangay,
                    opening_time: openingTime,
                    closing_time: closingTime,
                    is_maintenance: isMaintenance
                });
                sessionStorage.setItem('save_success_toast', 'Spot created successfully!');
            }
            window.closeSaveConfirmModal();
            window.closeFormModal();
            showToast(spotId ? 'Spot updated successfully!' : 'Spot created successfully!', 'success');
            if (typeof window.refreshActiveTab === 'function') {
                window.refreshActiveTab();
            }
        } catch (err) {
            resetConfirmBtn();
            showToast('Failed to save spot: ' + (err.message || 'Unknown error'), 'danger');
            window.closeSaveConfirmModal();
        }
    };

    // ========== Delete Functions ==========
    window.deleteSpot = function (spotId) {
        deleteSpotId = spotId;
        const spot = window.spotsData.find(s => s.id === spotId);
        document.getElementById('deleteSpotName').textContent = spot ? spot.name : 'this spot';
        document.getElementById('deleteConfirmModal').classList.add('active');
    };

    window.closeDeleteModal = function () {
        document.getElementById('deleteConfirmModal').classList.remove('active');
        deleteSpotId = null;
    };

    window.confirmDeleteSpot = async function () {
        if (!deleteSpotId) return;
        try {
            await deleteSpot(deleteSpotId);
            window.closeDeleteModal();
            // Show success toast inline — no page reload needed
            showToast('✅ Spot deleted successfully!', 'success');
            // Refresh only the active SPA tab data (no full page reload)
            if (typeof window.refreshActiveTab === 'function') {
                window.refreshActiveTab();
            }
        } catch (err) {
            showToast('❌ Failed to delete spot: ' + (err.message || 'Unknown error'), 'danger');
        }
    };

    // ========== Map Tabs ==========
    function initMapTabs() {
        document.querySelectorAll('.map-tab').forEach(tab => {
            tab.addEventListener('click', function () {
                document.querySelectorAll('.map-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                Object.values(mapLayers).forEach(l => { if (map.hasLayer(l)) map.removeLayer(l); });
                mapLayers[this.dataset.view].addTo(map);
            });
        });
    }

    // ========== Filter Spots ==========
    window.filterSpots = function () {
        const search = document.getElementById('searchInput').value.toLowerCase().trim();
        // Multi-category: collect all checked values
        const selectedCats = Array.from(document.querySelectorAll('.cat-filter-chk:checked')).map(c => c.value);
        const status = document.getElementById('filterStatus').value;

        // Map filter status to DB status for comparison
        const statusMap = {
            'EXISTING': 'EXIST',
            'EMERGING': 'EMERGE',
            'POTENTIAL': 'POTENTIAL'
        };

        // Helper: does a spot match the selected categories?
        // spot.category may be a comma-separated string like "Beach,Mountain"
        function matchesCat(spot) {
            if (selectedCats.length === 0) return true;
            const spotCats = (spot.category || '').split(',').map(s => s.trim());
            return selectedCats.some(fc => spotCats.includes(fc));
        }

        // Filter cards
        const cards = document.querySelectorAll('#cardsView .spot-card');
        let visible = 0;
        cards.forEach(card => {
            const id = card.dataset.spotId;
            const spot = window.spotsData.find(s => String(s.id) === String(id));
            if (!spot) return;
            const matchSearch = !search || spot.name.toLowerCase().includes(search) || (spot.description || '').toLowerCase().includes(search);
            const matchStatus = !status || (spot.classification_status || '').toUpperCase() === statusMap[status];
            const show = matchSearch && matchesCat(spot) && matchStatus;
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        // Filter table rows
        const rows = document.querySelectorAll('#tableView tbody tr');
        rows.forEach(row => {
            const id = row.dataset.spotId;
            const spot = window.spotsData.find(s => String(s.id) === String(id));
            if (!spot) return;
            const matchSearch = !search || spot.name.toLowerCase().includes(search) || (spot.description || '').toLowerCase().includes(search);
            const matchStatus = !status || (spot.classification_status || '').toUpperCase() === statusMap[status];
            row.style.display = (matchSearch && matchesCat(spot) && matchStatus) ? '' : 'none';
        });

        document.getElementById('filterCount').textContent = visible + ' spot(s)';
    };

    // ========== Barangays Data ==========
    const barangaysByMunicipality = {
        1: ['Allangigan', 'Aludaid', 'Bacsayan', 'Balballosa', 'Bambanay', 'Bugbugcao', 'Caarusipan', 'Cabaroan', 'Cabugnayan', 'Cacapian', 'Caculangan', 'Casilagan', 'Catdongan', 'Dangdangla', 'Dasay', 'Dinanum', 'Duplas', 'Guinguinabang', 'Ili Norte (Poblacion)', 'Ili Sur (Poblacion)', 'Legleg', 'Lubing', 'Nadsaag', 'Nagsabaran', 'Naguirangan', 'Naguituban', 'Nagyubuyuban', 'Oaquing', 'Pacpacac', 'Pagdildilan', 'Panicsican', 'Quidem', 'Santa Rosa', 'Saracat', 'Santo Rosario', 'Taboc', 'Talogtog', 'Urbiztondo'],
        2: ['Abut', 'Apaleng', 'Bacsil', 'Baraoas', 'Bato', 'Biday', 'Bangbangolan', 'Bangcusay', 'Barangay I (Poblacion)', 'Barangay II (Poblacion)', 'Barangay III (Poblacion)', 'Barangay IV (Poblacion)', 'Birunget', 'Bungro', 'Cabarsican', 'Cadaclan', 'Calabugao', 'Camansi', 'Canaoay', 'Carlatan', 'Cabaroan (Negro)', 'Cadapli', 'Dallangayan Este', 'Dallangayan Oeste', 'Dalumpinas Este', 'Dalumpinas Oeste', 'Ilocanos Norte', 'Ilocanos Sur', 'Langcuas', 'Lingsat', 'Madayegdeg', 'Mameltac', 'Masicong', 'Narra Este', 'Narra Oeste', 'Namtutan', 'Pagdaldagan', 'Pagdaraoan', 'Pao Norte', 'Pao Sur', 'Pacpaco', 'Pian', 'Poro', 'Puspus', 'San Agustin', 'San Francisco', 'Sagayad', 'Santiago Norte', 'Santiago Sur', 'San Vicente', 'Saoay', 'Siboan-Otong', 'Tanquigan', 'Tanqui', 'Sevilla'],
        3: ['Acao', 'Bagbag', 'Ballay', 'Baccuit Norte', 'Baccuit Sur', 'Boy-utan', 'Bucayab', 'Cabalayangan', 'Cabisilan', 'Casilagan', 'Central East (Poblacion)', 'Central West (Poblacion)', 'Dili', 'Disso-or', 'Guerrero', 'Jimenez', 'Jimenez West', 'Lower San Agustin', 'Nagrebcan', 'Pagdalagan Sur', 'Paliguasan', 'Palingulang', 'Parian Este', 'Parian Oeste', 'Paringao', 'Payocpoc Norte Este', 'Payocpoc Norte Oeste', 'Payocpoc Sur', 'Pilar', 'Pottot', 'Pudoc', 'Pugo', 'Quinavite', 'Santa Monica', 'Santiago', 'Taberna', 'Upper San Agustin', 'Urayong'],
        4: ['Ambitacay', 'Balawarte', 'Capas', 'Consolacion (Poblacion)', 'San Agustin East', 'San Agustin Norte', 'San Agustin Sur', 'San Antonino', 'San Antonio', 'San Francisco', 'San Isidro', 'San Java Norte', 'San Juan', 'San Jose Norte', 'San Jose Sur', 'San Julian Central', 'San Julian East', 'San Julian Norte', 'San Julian West', 'San Manuel Norte', 'San Manuel Sur', 'San Marcos', 'San Miguel', 'San Nicolas Central (Poblacion)', 'San Nicolas East', 'San Nicolas Norte (Poblacion)', 'San Nicolas Sur (Poblacion)', 'San Nicolas West', 'San Pedro', 'San Roque East', 'San Roque West', 'San Vicente Norte', 'San Vicente Sur', 'Santa Ana', 'Santa Barbara (Poblacion)', 'Santa Fe', 'Santa Maria', 'Santa Monica', 'Santa Rita (Nalinac)', 'Santa Rita East', 'Santa Rita Norte', 'Santa Rita Sur', 'Santa Rita West', 'Nazareno', 'Macalva Central', 'Macalva Norte', 'Macalva Sur', 'Purok'],
        5: ['Alcala (Poblacion)', 'Ayaoan', 'Barangobong', 'Barrientos', 'Bungro', 'Buselbusel', 'Cabalitocan', 'Cantoria No. 1', 'Cantoria No. 2', 'Cantoria No. 3', 'Cantoria No. 4', 'Carisquis', 'Darigayos', 'Magallanes (Poblacion)', 'Magsiping', 'Mamay', 'Nalvo Norte', 'Nalvo Sur', 'Nagrebcan', 'Napaset', 'Oaqui No. 1', 'Oaqui No. 2', 'Oaqui No. 3', 'Oaqui No. 4', 'Pila', 'Pitpitac', 'Rimos No. 1', 'Rimos No. 2', 'Rimos No. 3', 'Rimos No. 4', 'Rimos No. 5', 'Rissing', 'Salcedo (Poblacion)', 'Santo Domingo Norte', 'Santo Domingo Sur', 'Sucoc Norte', 'Sucoc Sur', 'Suyo', 'Tallaoen', 'Victoria (Poblacion)'],
        6: ['Amontoc', 'Apayao', 'Bayabas', 'Balbalayang', 'Bucao', 'Bumbuneg', 'Daking', 'Lacong', 'Lipay Este', 'Lipay Norte', 'Lipay Proper', 'Lipay Sur', 'Lon-oy', 'Poblacion', 'Polipol'],
        7: ['Almeida', 'Antonino', 'Apatut', 'Ar-arampang', 'Baracbac Este', 'Baracbac Oeste', 'Bet-ang', 'Bulbulala', 'Bungol', 'Butubut Este', 'Butubut Norte', 'Butubut Oeste', 'Butubut Sur', 'Cabuaan Oeste (Poblacion)', 'Calliat', 'Camiling', 'Calumbaya', 'Calungbuyan', 'Dr. Camilo Osias Poblacion (Cabuaan Este)', 'Guinaburan', 'Nagsabaran Norte', 'Nagsabaran Sur', 'Nalasin', 'Napaset', 'Pagbennecan', 'Pagleddegan', 'Paraoir', 'Patpata', 'Sablut', 'San Pablo', 'Sinapangan Norte', 'Sinapangan Sur', 'Tallipugo'],
        8: ['Alaska', 'Basca', 'Dulao', 'Gallano', 'Macabato', 'Manga', 'Pangao-aoan East', 'Pangao-aoan West', 'Poblacion', 'Samara', 'San Antonio', 'San Benito Norte', 'San Benito Sur', 'San Eugenio', 'San Juan East', 'San Juan West', 'San Simon East', 'San Simon West', 'Santa Cecilia', 'Santa Lucia', 'Santo Rosario East', 'Santo Rosario West', 'Santa Rita East', 'Santa Rita West'],
        9: ['Alipang', 'Amlang', 'Ambangonan', 'Bacani', 'Bangar', 'Bani', 'Benteng-Sapilang', 'Camp One', 'Carunuan East', 'Carunuan West', 'Casilagan', 'Cataguingtingan', 'Concepcion', 'Damortis', 'Gumot-Nagcolaran', 'Inabaan Norte', 'Inabaan Sur', 'Marcos', 'Nagtagaan', 'Nancamotian', 'Parasapas', 'Poblacion East', 'Poblacion West', 'San Jose', 'Subusub', 'Tabtabungao', 'Tay-ac', 'Tanglag', 'Udiao', 'Vila'],
        10: ['Agtipal', 'Arosip', 'Bacqui', 'Bacsil', 'Bagutot', 'Ballogo', 'Baroro', 'Bitalag', 'Burayoc', 'Bussaoit', 'Cabaroan', 'Cabarsican', 'Cabugao', 'Calautit', 'Carcarmay', 'Casiaman', 'Santa Cruz', 'Galongen', 'Guinabang', 'Legleg', 'Lisqueb', 'Mabanengbeng 1st', 'Mabanengbeng 2nd', 'Maragayap', 'Nagatiran', 'Nangalisan', 'Narra', 'Nagsaraboan', 'Nagsimbaanan', 'Oya-oy', 'Paagan', 'Pagan', 'Pandan', 'Pang-Pang', 'Poblacion', 'Quirino', 'Raois', 'Sagapan', 'Salincob', 'San Martin', 'Santa Rita', 'Sapilang', 'Sayoan', 'Sipulo', 'Ubbog', 'Zaragosa'],
        11: ['Al-alinao Norte', 'Al-alinao Sur', 'Aguioas', 'Ambaracao Norte', 'Ambaracao Sur', 'Angin', 'Baraoas Norte', 'Baraoas Sur', 'Bariquir', 'Bato', 'Balecbec', 'Bancagan', 'Bimmotobot', 'Dal-lipaoen', 'Daramuangan', 'Guesset', 'Gusing Norte', 'Gusing Sur', 'Imelda', 'Lioac Norte', 'Lioac Sur', 'Magungunay', 'Mamat-ing Norte', 'Mamat-ing Sur', 'Natividad (Poblacion)', 'Ortiz (Poblacion)', 'Ribsuan', 'San Antonio', 'San Isidro', 'Sili', 'Suguidan Norte', 'Suguidan Sur', 'Teddingan'],
        12: ['Amallapay', 'Anduyan', 'Caoigue', 'Francia Sur', 'Francia West', 'Garcia', 'Gonzales', 'Halog East', 'Halog West', 'Leones East', 'Leones West', 'Linapew', 'Lloren', 'Magsaysay', 'Pideg', 'Poblacion', 'Rizal', 'Santa Teresa'],
        13: ['Ambalite', 'Ambangonan', 'Cares', 'Cuenca', 'Duplas', 'Maoasoas Norte', 'Maoasoas Sur', 'Palina', 'Poblacion East', 'Poblacion West', 'Saytan', 'San Luis', 'Tavora East', 'Tavora Proper'],
        14: ['Bautista', 'Gana', 'Juan Cartas', 'Las-ud', 'Liquicia', 'Poblacion Norte', 'Poblacion Sur', 'San Carlos', 'San Cornelio', 'San Fermin', 'San Gregorio', 'San Jose', 'Santiago Norte', 'Santiago Sur', 'Sobredillo', 'Urayong', 'Wenceslao'],
        15: ['Ambitacay', 'Bail', 'Balaoc', 'Balsaan', 'Baybay', 'Cabaruan', 'Casilagan', 'Casantaan', 'Cupang', 'Damortis', 'Fernando', 'Linong', 'Lomboy', 'Malabago', 'Namboongan', 'Namonitan', 'Narvacan', 'Patac', 'Poblacion', 'Pongpong', 'Raois', 'Tococ', 'Tubod', 'Ubagan'],
        16: ['Agdeppa', 'Alzate', 'Bangaoilan East', 'Bangaoilan West', 'Barraca', 'Central East No. 1 (Poblacion)', 'Central East No. 2 (Poblacion)', 'Central West No. 1 (Poblacion)', 'Central West No. 2 (Poblacion)', 'Central West No. 3 (Poblacion)', 'Consuegra', 'General Prim East', 'General Prim West', 'General Terrero', 'Luzong Norte', 'Luzong Sur', 'Maria Cristina East', 'Maria Cristina West', 'Mindoro', 'Nagsabaran', 'Nagsidorisan', 'Quintarong', 'Reyna Regente', 'Rissing', 'San Blas', 'San Cristobal', 'Sinapangan Norte', 'Sinapangan Sur', 'Ubbog'],
        17: ['Agpay', 'Bilis', 'Caoayan', 'Dalacdac', 'Delles', 'Imelda', 'Libtong', 'Linuan', 'Lower Tumapoc', 'New Poblacion', 'Old Poblacion', 'Upper Tumapoc'],
        18: ['Alibangsay', 'Baay', 'Cambaly', 'Cardiz', 'Dagup', 'Libbo', 'Suyo (Poblacion)', 'Tagudtud', 'Tio-angan', 'Wallayan'],
        19: ['Corrooy', 'Lettac Norte', 'Lettac Sur', 'Mangaan', 'Paagan', 'Poblacion', 'Puguil', 'Ramot', 'Sasaba', 'Sapdaan', 'Tubaday'],
        20: ['Bigbiga', 'Bulalaan', 'Castro', 'Duplas', 'Ipet', 'Ilocano', 'Maliclico', 'Namaltugan', 'Old Central', 'Poblacion', 'Porporiket', 'San Francisco Norte', 'San Francisco Sur', 'San Jose', 'Sengngat', 'Turod', 'Up-uplas']
    };

    function populateBarangayDropdown() {
        const muniId = window.municipalityData?.id;
        const select = document.getElementById('spotBarangay');
        if (!select || !muniId) return;

        select.innerHTML = '<option value="">— Select Barangay —</option>';
        const barangays = barangaysByMunicipality[parseInt(muniId)] || [];
        barangays.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b;
            opt.textContent = b;
            select.appendChild(opt);
        });
    }

    function showToast(msg, type = 'success') {
        const colors = {
            success: '#16A34A',
            danger: '#DC2626',
            info: '#4338CA',
            warning: '#F59E0B'
        };
        const icons = {
            success: 'fa-check-circle',
            danger: 'fa-times-circle',
            info: 'fa-info-circle',
            warning: 'fa-exclamation-circle'
        };

        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 99999;
            background: ${colors[type] || '#1E293B'};
            color: white;
            padding: 14px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 8px 24px rgba(0,0,0,.2);
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: 360px;
            animation: slideIn 0.3s ease;
        `;
        toast.innerHTML = `<i class="fas ${icons[type] || 'fa-bell'}"></i> ${msg}`;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.4s';
            setTimeout(() => toast.remove(), 400);
        }, 3000);
    }

    // ========== Initialize ==========
    function init() {
        // Check if there is a pending success toast
        const pendingToast = sessionStorage.getItem('save_success_toast');
        if (pendingToast) {
            showToast(pendingToast, 'success');
            sessionStorage.removeItem('save_success_toast');
        }

        try { initViewToggle(); } catch (e) { console.error('initViewToggle failed:', e); }
        try { initDropdowns(); } catch (e) { console.error('initDropdowns failed:', e); }
        try { initMapTabs(); } catch (e) { console.error('initMapTabs failed:', e); }
        try { initMap(); } catch (e) { console.error('initMap failed:', e); }
        try { initCategoryChips(); } catch (e) { console.error('initCategoryChips failed:', e); }
        try { populateBarangayDropdown(); } catch (e) { console.error('populateBarangayDropdown failed:', e); }

        // ── Modal close / cancel buttons
        document.getElementById('sfmCloseBtn')
            ?.addEventListener('click', window.closeFormModal);
        document.getElementById('sfmCancelBtn')
            ?.addEventListener('click', window.closeFormModal);

        // ── Backdrop click closes modal
        document.getElementById('spotFormModal')
            ?.addEventListener('click', e => {
                if (e.target.id === 'spotFormModal') window.closeFormModal();
            });

        // ── Form submit
        document.getElementById('spotForm')
            ?.addEventListener('submit', window.submitSpotForm);

        // ── Use Current Location
        document.getElementById('sfmUseLocationBtn')
            ?.addEventListener('click', window.useCurrentLocation);

        // Close fee types dropdown on outside click
        document.addEventListener('click', function(e) {
            var feeBtn = document.getElementById('feeTypesBtn'), feeDd = document.getElementById('feeTypesDropdown');
            if (feeDd && feeBtn && !feeBtn.contains(e.target) && !feeDd.contains(e.target)) {
                feeDd.style.display = 'none';
                var chevron = document.getElementById('feeTypesChevron');
                if (chevron) chevron.style.transform = 'rotate(0deg)';
            }
        });

        // ── Lat/Lng inputs → move map pin
        document.getElementById('spotLatitude')
            ?.addEventListener('input', window.updateMapMarker);
        document.getElementById('spotLongitude')
            ?.addEventListener('input', window.updateMapMarker);

        // ── Title char counter
        document.getElementById('spotName')
            ?.addEventListener('input', function () {
                document.getElementById('nameCharCount').textContent = this.value.length;
            });

        // ── Description char counter
        document.getElementById('spotDescription')
            ?.addEventListener('input', function () {
                document.getElementById('descCharCount').textContent = this.value.length;
            });

        // ── Image upload area: click + drag-and-drop
        const uploadArea = document.getElementById('imageUploadArea');
        if (uploadArea) {
            uploadArea.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                document.getElementById('spotImages').click();
            });
            uploadArea.addEventListener('dragover', window.handleDragOver);
            uploadArea.addEventListener('dragleave', window.handleDragLeave);
            uploadArea.addEventListener('drop', window.handleImageDrop);
        }
        const fileInput = document.getElementById('spotImages');
        if (fileInput) {
            fileInput.addEventListener('click', (e) => {
                e.stopPropagation(); // Prevent click from bubbling to uploadArea and reopening dialog
            });
            fileInput.addEventListener('change', window.handleImageSelect);
        }

        // ── Save confirm modal buttons
        document.getElementById('saveConfirmNoBtn')
            ?.addEventListener('click', window.closeSaveConfirmModal);
        document.getElementById('saveConfirmBtn')
            ?.addEventListener('click', window.confirmSaveSpot);
        document.getElementById('saveConfirmModal')
            ?.addEventListener('click', e => {
                if (e.target.id === 'saveConfirmModal') window.closeSaveConfirmModal();
            });

        // ── Barangay auto-pin on map
        document.getElementById('spotBarangay')
            ?.addEventListener('change', function () {
                const barangay = this.value;
                const muniName = window.municipalityData?.name || '';
                if (!barangay || !muniName) return;
                autoPinBarangay(barangay, muniName);
            });
    }

    async function autoPinBarangay(barangay, muniName) {
        const latInput = document.getElementById('spotLatitude');
        const lngInput = document.getElementById('spotLongitude');
        if (!latInput || !lngInput) return;

        const queries = [
            `Barangay ${barangay}, ${muniName}, La Union, Philippines`,
            `${barangay}, ${muniName}, La Union`,
            `${barangay}, La Union, Philippines`,
            `${barangay} ${muniName}`,
        ];

        let found = null;
        for (const q of queries) {
            try {
                const resp = await fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(q)}`);
                const results = await resp.json();
                if (results.length > 0) {
                    found = { lat: parseFloat(results[0].lat), lng: parseFloat(results[0].lon) };
                    break;
                }
            } catch (_) {}
        }

        if (found) {
            latInput.value = found.lat.toFixed(6);
            lngInput.value = found.lng.toFixed(6);
            if (modalMap && modalMarker) {
                modalMarker.setLatLng([found.lat, found.lng]);
                modalMap.setView([found.lat, found.lng], 16);
            } else {
                initModalMap();
            }
        } else {
            showToast('Could not locate this barangay. Pin set to municipal center — drag to refine.', 'info');
            if (window.municipalityData?.latitude && window.municipalityData?.longitude) {
                latInput.value = parseFloat(window.municipalityData.latitude).toFixed(6);
                lngInput.value = parseFloat(window.municipalityData.longitude).toFixed(6);
                if (modalMap && modalMarker) {
                    modalMarker.setLatLng([parseFloat(window.municipalityData.latitude), parseFloat(window.municipalityData.longitude)]);
                    modalMap.setView([parseFloat(window.municipalityData.latitude), parseFloat(window.municipalityData.longitude)], 14);
                } else {
                    initModalMap();
                }
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();