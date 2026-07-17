// ════════════════════════════════════════════════════════════════════════════════
// MUNICIPAL TOURIST SPOTS - API & UTILITIES (ES Module)
// Scoped to the user's municipality — backend handles filtering by session.
// ════════════════════════════════════════════════════════════════════════════════

if (window.API_CONFIG && typeof window.API_CONFIG.getCsrfToken !== 'function') {
    window.API_CONFIG.getCsrfToken = function () {
        const match = document.cookie.split(';').find(c => c.trim().startsWith('XSRF-TOKEN='));
        if (match) return decodeURIComponent(match.trim().split('=').slice(1).join('='));
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    };
}

const API_BASE = `${window.API_CONFIG?.BASE_URL || ('http://' + (window.location.hostname || '127.0.0.1') + ':8000')}/api/municipal/tourist-spots`;

// ── In-Memory Spots Cache ────────────────────────────────────────────────────
// Survives SPA tab switches so returning to Tourist Spots is instant.
const MUNI_SPOTS_CACHE_TTL = 300000; // 5 minutes fresh TTL
const cacheKey = '__MUNI_TOURIST_SPOTS_CACHE__';
window[cacheKey] = window[cacheKey] || { spots: null, munis: null, timestamp: 0 };

function _isMuniCacheFresh() {
    const c = window[cacheKey];
    return c.spots !== null && c.munis !== null && (Date.now() - c.timestamp) < MUNI_SPOTS_CACHE_TTL;
}

/** Invalidate cache after write operations so next read is always fresh. */
window.invalidateMunicipalSpotsCache = function () {
    window[cacheKey].timestamp = 0;
};

// ── Background Auto-Refresh ───────────────────────────────────────────────────
let _muniSpotsRefreshTimer = null;
function _startMuniSpotsAutoRefresh() {
    if (_muniSpotsRefreshTimer) return; // already running
    _muniSpotsRefreshTimer = setInterval(async () => {
        try {
            const fresh = await window.API_CONFIG.get(API_BASE);
            const freshSpots = fresh?.data || fresh || [];
            if (Array.isArray(freshSpots)) {
                window[cacheKey].spots = freshSpots;
                window[cacheKey].timestamp = Date.now();
                // Update in-memory globals used by renders
                if (window.touristSpotsAll) window.touristSpotsAll = freshSpots;
                if (window.touristSpotsData) window.touristSpotsData = freshSpots;
            }
        } catch (_) { /* silently ignore background refresh errors */ }
    }, 90000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', _startMuniSpotsAutoRefresh);
} else {
    _startMuniSpotsAutoRefresh();
}


function getSpotImageUploadUrl() {
    if (window.TOURIST_SPOT_UPLOAD_URL) return window.TOURIST_SPOT_UPLOAD_URL;
    return new URL('../../api/upload-spot-image.php', window.location.href).href;
}

// ── Map/Form State ──────────────────────────────────────────────────────────
let map, markerCluster;
let modalMap, modalMarker;
const mapLayers = {
    street: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap', maxZoom: 18 }),
    satellite: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { attribution: '© Esri', maxZoom: 18 }),
};
let uploadedImages = [];
let pendingSaveData = null;

// ── Boundary Validation Variables & Helpers
let currentBoundaryLayer = null;
window.lastValidSpotCoords = { lat: null, lng: null };

function getSelectedMuniName() {
    return window.municipalityData && window.municipalityData.name ? window.municipalityData.name : null;
}

function injectInvalidLocationModal() {
    if (document.getElementById('invalidLocationModal')) return;
    const modalHTML = `
    <div class="modal" id="invalidLocationModal" style="z-index: 10005;">
        <div class="modal-content" style="max-width: 420px; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);">
            <div style="background: #FEE2E2; padding: 28px 28px 16px 28px; text-align: center;">
                <div style="width: 56px; height: 56px; background: #EF4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;">
                    <i class="fas fa-exclamation-triangle" style="color: white; font-size: 22px;"></i>
                </div>
                <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #991B1B;">Invalid Tourist Spot Location</h3>
            </div>
            <div style="padding: 24px 28px 28px 28px; text-align: center;">
                <p style="color: #4B5563; margin: 0 0 24px 0; font-size: 14px; line-height: 1.5;">
                    The selected location is outside the boundary of the chosen municipality.<br/><br/>
                    Please place the pin within the official boundary of <strong id="invalidLocationMuniName">Selected Municipality</strong>.
                </p>
                <div style="display: flex; justify-content: center;">
                    <button class="btn btn-primary" id="closeInvalidLocationBtn" style="padding: 10px 24px; min-width: 120px; justify-content: center; background: #EF4444; border-color: #EF4444; color: white;">
                        OK
                    </button>
                </div>
            </div>
        </div>
    </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    document.getElementById('closeInvalidLocationBtn').addEventListener('click', () => {
        document.getElementById('invalidLocationModal').classList.remove('active');
    });
}

function showInvalidLocationModal(muniName) {
    injectInvalidLocationModal();
    document.getElementById('invalidLocationMuniName').textContent = muniName;
    document.getElementById('invalidLocationModal').classList.add('active');
}

function injectDuplicateSpotNameModal() {
    if (document.getElementById('duplicateSpotNameModal')) return;
    const modalHTML = `
    <div class="modal" id="duplicateSpotNameModal" style="z-index: 10005;">
        <div class="modal-content" style="max-width: 420px; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);">
            <div style="background: #FEF3C7; padding: 28px 28px 16px 28px; text-align: center;">
                <div style="width: 56px; height: 56px; background: #F59E0B; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;">
                    <i class="fas fa-copy" style="color: white; font-size: 22px;"></i>
                </div>
                <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #92400E;">Duplicate Tourist Spot Name</h3>
            </div>
            <div style="padding: 24px 28px 28px 28px; text-align: center;">
                <p style="color: #4B5563; margin: 0 0 24px 0; font-size: 14px; line-height: 1.5;">
                    A tourist spot with this name already exists. Please enter a different tourist spot name.
                </p>
                <div style="display: flex; justify-content: center;">
                    <button class="btn btn-primary" id="closeDuplicateSpotNameBtn" style="padding: 10px 24px; min-width: 120px; justify-content: center; background: #F59E0B; border-color: #F59E0B; color: white;">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    document.getElementById('closeDuplicateSpotNameBtn').addEventListener('click', () => {
        document.getElementById('duplicateSpotNameModal').classList.remove('active');
    });
}

function showDuplicateSpotNameModal() {
    injectDuplicateSpotNameModal();
    document.getElementById('duplicateSpotNameModal').classList.add('active');
}

function isPointInRing(point, ring) {
    var x = point[0], y = point[1];
    var inside = false;
    for (var i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        var xi = ring[i][0], yi = ring[i][1];
        var xj = ring[j][0], yj = ring[j][1];
        var intersect = ((yi > y) !== (yj > y))
            && (x < (xj - xi) * (y - yi) / (yj - yi) + xi);
        if (intersect) inside = !inside;
    }
    return inside;
}

function isPointInPolygon(point, polygonCoords) {
    if (polygonCoords.length === 0) return false;
    return isPointInRing(point, polygonCoords[0]);
}

function isPointInBoundary(lat, lng, boundary) {
    if (!boundary) return false;
    const point = [lng, lat];
    if (boundary.type === 'Polygon') {
        return isPointInPolygon(point, boundary.coordinates);
    } else if (boundary.type === 'MultiPolygon') {
        return boundary.coordinates.some(polygonCoords => isPointInPolygon(point, polygonCoords));
    }
    return false;
}

// ── Centroid of a GeoJSON boundary polygon (for default marker placement)
function getBoundaryCentroid(boundary) {
    if (!boundary) return null;
    let coords = [];
    if (boundary.type === 'Polygon') {
        coords = boundary.coordinates[0] || [];
    } else if (boundary.type === 'MultiPolygon') {
        boundary.coordinates.forEach(poly => {
            if ((poly[0] || []).length > coords.length) coords = poly[0];
        });
    }
    if (!coords.length) return null;
    let sumLat = 0, sumLng = 0;
    coords.forEach(c => { sumLng += c[0]; sumLat += c[1]; });
    return { lat: sumLat / coords.length, lng: sumLng / coords.length };
}

// ── Reverse-geocode a lat/lng and auto-select the matching barangay (Nominatim)
let _barangayDetectTimer = null;
let _suppressBarangayOnchange = false; // prevents auto-detect from re-triggering autoPinBarangay
function autoDetectBarangayFromCoords(lat, lng) {
    const muniName = getSelectedMuniName();
    if (!muniName) return;
    const boundary = window.laUnionBoundaries && window.laUnionBoundaries[muniName];
    if (boundary && !isPointInBoundary(lat, lng, boundary)) {
        return; // coordinates are outside the selected municipality boundary, keep existing dropdowns
    }

    clearTimeout(_barangayDetectTimer);
    _barangayDetectTimer = setTimeout(async () => {
        const select = document.getElementById('spotBarangay');
        if (!select || select.options.length <= 1) return;
        try {
            const resp = await fetch(
                `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=16&addressdetails=1`,
                { headers: { 'Accept-Language': 'en' } }
            );
            if (!resp.ok) return;
            const data = await resp.json();
            const addr = data.address || {};
            const candidates = [
                addr.village, addr.suburb, addr.neighbourhood,
                addr.hamlet, addr.quarter, addr.city_district, addr.residential
            ].filter(Boolean);
            const norm = s => s.replace(/^(barangay|brgy\.?|bgy\.?)\s+/i, '').trim().toLowerCase();
            const opts = Array.from(select.options).slice(1);
            let matched = null;
            for (const candidate of candidates) {
                const nc = norm(candidate);
                matched = opts.find(o => norm(o.value) === nc);
                if (!matched) matched = opts.find(o => norm(o.value).includes(nc) || nc.includes(norm(o.value)));
                if (matched) break;
            }
            if (matched && select.value !== matched.value) {
                // Suppress the onchange so autoPinBarangay (forward geocode) is NOT triggered
                _suppressBarangayOnchange = true;
                select.value = matched.value;
                _suppressBarangayOnchange = false;
            }
        } catch (_) { /* silently ignore */ }
    }, 650);
}
// Expose on window so tourist-spots-page.js (non-module script) can also call it
window.autoDetectBarangayFromCoords = autoDetectBarangayFromCoords;

window.displayMunicipalityBoundary = function (muniName) {
    if (!modalMap) return;
    if (currentBoundaryLayer) {
        modalMap.removeLayer(currentBoundaryLayer);
        currentBoundaryLayer = null;
    }
    if (!window.laUnionBoundaries) return;
    let boundary = window.laUnionBoundaries[muniName];
    if (!boundary && (muniName === 'San Fernando City' || muniName === 'San Fernando')) {
        boundary = window.laUnionBoundaries['San Fernando City'] || window.laUnionBoundaries['San Fernando'];
    }
    if (boundary) {
        const geoJsonFeature = {
            "type": "Feature",
            "properties": {},
            "geometry": {
                "type": boundary.type,
                "coordinates": boundary.coordinates
            }
        };
        currentBoundaryLayer = L.geoJSON(geoJsonFeature, {
            style: {
                color: '#2563EB',
                weight: 2,
                opacity: 0.8,
                fillColor: '#2563EB',
                fillOpacity: 0.1
            }
        }).addTo(modalMap);
        modalMap.fitBounds(currentBoundaryLayer.getBounds(), { padding: [20, 20] });
    }
};

window.validateAndMovePin = function (lat, lng, skipBoundaryCheck = false, updateInputs = true) {
    const muniName = getSelectedMuniName();
    if (!muniName) {
        showToast('Municipality not configured', 'warning');
        if (modalMarker && modalMap) {
            modalMap.removeLayer(modalMarker);
            modalMarker = null;
        }
        if (updateInputs) {
            document.getElementById('spotLatitude').value = '';
            document.getElementById('spotLongitude').value = '';
        }
        window.lastValidSpotCoords = { lat: null, lng: null };
        return false;
    }

    const isEditMode = !!document.getElementById('spotId')?.value;
    const boundary = window.laUnionBoundaries && window.laUnionBoundaries[muniName];
    if (skipBoundaryCheck || isEditMode || !boundary || isPointInBoundary(lat, lng, boundary)) {
        if (updateInputs) {
            document.getElementById('spotLatitude').value = lat.toFixed(6);
            document.getElementById('spotLongitude').value = lng.toFixed(6);
        }
        window.lastValidSpotCoords = { lat: lat, lng: lng };
        if (modalMap) {
            if (!modalMarker) {
                const icon = L.divIcon({
                    html: `<div style="background:#2563EB;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;border:3px solid white;box-shadow:0 3px 10px rgba(37,99,235,.45);cursor:grab;"><i class="fas fa-map-marker-alt" style="font-size:14px;"></i></div>`,
                    iconSize: [32, 32],
                    iconAnchor: [16, 32]
                });
                modalMarker = L.marker([lat, lng], { icon, draggable: true }).addTo(modalMap);
                setupMarkerDragEvents();
            } else {
                modalMarker.setLatLng([lat, lng]);
            }
            if (updateInputs) {
                modalMap.setView([lat, lng], 16);
            }
        }
        return true;
    } else {
        showToast('The entered coordinates are outside the selected municipality. Please verify the location or select the correct municipality.', 'warning');
        if (updateInputs) {
            document.getElementById('spotLatitude').value = lat.toFixed(6);
            document.getElementById('spotLongitude').value = lng.toFixed(6);
        }
        if (modalMap) {
            if (!modalMarker) {
                const icon = L.divIcon({
                    html: `<div style="background:#2563EB;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;border:3px solid white;box-shadow:0 3px 10px rgba(37,99,235,.45);cursor:grab;"><i class="fas fa-map-marker-alt" style="font-size:14px;"></i></div>`,
                    iconSize: [32, 32],
                    iconAnchor: [16, 32]
                });
                modalMarker = L.marker([lat, lng], { icon, draggable: true }).addTo(modalMap);
                setupMarkerDragEvents();
            } else {
                modalMarker.setLatLng([lat, lng]);
            }
            if (updateInputs) {
                modalMap.setView([lat, lng], 16);
            }
        }
        return true;
    }
};

function setupMarkerDragEvents() {
    if (!modalMarker) return;
    modalMarker.off('drag');
    modalMarker.off('dragend');
    modalMarker.on('drag', function (e) {
        const pos = e.target.getLatLng();
        document.getElementById('spotLatitude').value = pos.lat.toFixed(6);
        document.getElementById('spotLongitude').value = pos.lng.toFixed(6);
    });
    modalMarker.on('dragend', function (e) {
        const pos = e.target.getLatLng();
        const isValid = window.validateAndMovePin(pos.lat, pos.lng, false, true);
        // Req 3: auto-detect barangay on every valid drag-end
        if (isValid && getSelectedMuniName()) {
            autoDetectBarangayFromCoords(pos.lat, pos.lng);
        }
    });
}

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

function getFilePreviewUrl(file) {
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = (e) => resolve(e.target.result);
        reader.onerror = () => resolve(null);
        reader.readAsDataURL(file);
    });
}

// ── API CALLS ────────────────────────────────────────────────────────────────
export const getSpots = async () => await window.API_CONFIG.get(`${API_BASE}`);
export const getSpot = async (id) => await window.API_CONFIG.get(`${API_BASE}/${id}`);
export const createSpot = async (data) => await window.API_CONFIG.post(`${API_BASE}`, data);
export const updateSpot = async (id, data) => await window.API_CONFIG.put(`${API_BASE}/${id}`, data);
export const deleteSpot = async (id) => await window.API_CONFIG.delete(`${API_BASE}/${id}`);

window.getSpots = getSpots;
window.getSpot = getSpot;
window.createSpot = createSpot;
window.updateSpot = updateSpot;
window.deleteSpot = deleteSpot;

// ── Image Upload ─────────────────────────────────────────────────────────────
const compressImage = async (file, maxWidth = 1280, maxHeight = 720, quality = 0.7) => {
    return new Promise((resolve) => {
        if (!file.type.startsWith('image/')) { resolve(file); return; }
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                let { width, height } = img;
                if (width > maxWidth) { height = (height * maxWidth) / width; width = maxWidth; }
                if (height > maxHeight) { width = (width * maxHeight) / height; height = maxHeight; }
                const canvas = document.createElement('canvas');
                canvas.width = width; canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                canvas.toBlob((blob) => {
                    if (!blob) { resolve(file); return; }
                    resolve(new File([blob], file.name, { type: 'image/jpeg', lastModified: Date.now() }));
                }, 'image/jpeg', quality);
            };
            img.onerror = () => resolve(file);
            img.src = e.target.result;
        };
        reader.onerror = () => resolve(file);
        reader.readAsDataURL(file);
    });
};

export const uploadImage = async (file) => {
    let processedFile = file;
    try { processedFile = await compressImage(file); } catch (err) { processedFile = file; }
    const formData = new FormData();
    formData.append('image', processedFile);
    const response = await fetch(getSpotImageUploadUrl(), {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }, body: formData
    });
    const text = await response.text();
    let data;
    try { data = JSON.parse(text); } catch { throw new Error(`Invalid server response (HTTP ${response.status})`); }
    if (!response.ok) throw new Error(data.error || data.message || `Upload failed: HTTP ${response.status}`);
    if (!data.success || !data.photo_url) throw new Error(data.error || 'Upload failed');
    if (window.API_CONFIG && typeof window.API_CONFIG.normalizeImageUrl === 'function') {
        data.photo_url = window.API_CONFIG.normalizeImageUrl(data.photo_url);
    }
    return data;
};

// ── CATEGORY HELPERS ─────────────────────────────────────────────────────────
const statusDisplayMap = { 'EXIST': 'EXISTING', 'EMERGE': 'EMERGING', 'POTENTIAL': 'POTENTIAL' };
const statusReverseMap = { 'EXISTING': 'EXIST', 'EMERGING': 'EMERGE', 'POTENTIAL': 'POTENTIAL' };

export function getClassificationStyle(status) {
    const styles = {
        'EXIST': { bg: '#10B981', text: '#FFFFFF', label: 'EXISTING' },
        'EMERGE': { bg: '#8B5CF6', text: '#FFFFFF', label: 'EMERGING' },
        'POTENTIAL': { bg: '#F59E0B', text: '#1E293B', label: 'POTENTIAL' },
        'default': { bg: '#9CA3AF', text: '#FFFFFF', label: 'UNKNOWN' }
    };
    return styles[status] || styles['default'];
}

// ── TOAST ────────────────────────────────────────────────────────────────────
export function showToast(msg, type = 'success') {
    const colors = { success: '#16A34A', danger: '#DC2626', info: '#4338CA', warning: '#F59E0B' };
    const icons = { success: 'fa-check-circle', danger: 'fa-times-circle', info: 'fa-info-circle', warning: 'fa-exclamation-circle' };
    const toast = document.createElement('div');
    toast.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:99999;padding:14px 20px;border-radius:10px;font-size:14px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.2);display:flex;align-items:center;gap:10px;max-width:360px;animation:slideIn 0.3s ease;';
    toast.style.background = colors[type] || '#1E293B';
    toast.style.color = 'white';
    toast.innerHTML = `<i class="fas ${icons[type] || 'fa-bell'}"></i> ${msg}`;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.4s'; setTimeout(() => toast.remove(), 400); }, 3000);
}

// ── CATEGORY ICON / COLOR ───────────────────────────────────────────────────
export function getCategoryIcon(catStr) {
    if (window.MapMarkersConfig && typeof window.MapMarkersConfig.getCategoryIcon === 'function') {
        return window.MapMarkersConfig.getCategoryIcon(catStr);
    }
    if (!catStr) return 'map-marker-alt';
    const cats = catStr.split(',').map(c => c.trim().toLowerCase());
    const map = { 'beach': 'umbrella-beach', 'mountain': 'mountain', 'waterfall': 'water', 'waterfalls': 'water', 'river': 'water', 'lake': 'water', 'island': 'umbrella-beach', 'cave': 'mountain', 'volcano': 'mountain', 'forest': 'tree', 'nature park': 'tree', 'marine sanctuary': 'fish', 'wildlife sanctuary': 'paw', 'historical': 'landmark', 'cultural heritage': 'landmark', 'religious': 'church', 'museum': 'museum', 'monument': 'monument', 'landmark': 'landmark', 'viewpoint': 'binoculars', 'adventure': 'hiking', 'hiking': 'hiking', 'camping': 'campground', 'farm': 'seedling', 'eco-tourism': 'leaf', 'garden': 'seedling', 'park': 'tree', 'recreation': 'bicycle', 'hot spring': 'hot-tub-person', 'cold spring': 'snowflake', 'food destination': 'utensils', 'shopping': 'shopping-cart', 'festival venue': 'masks-theater', 'resort': 'hotel', 'other': 'star' };
    for (const c of cats) { if (map[c]) return map[c]; }
    return 'map-marker-alt';
}

export function getCategoryColor(catStr) {
    if (window.MapMarkersConfig && typeof window.MapMarkersConfig.getCategoryColor === 'function') {
        return window.MapMarkersConfig.getCategoryColor(catStr);
    }
    if (!catStr) return '#3B82F6';
    const cat = catStr.split(',')[0].trim().toLowerCase();
    const colors = { 'beach': '#0EA5E9', 'waterfalls': '#06B6D4', 'waterfall': '#06B6D4', 'nature park': '#10B981', 'forest': '#059669', 'cultural heritage': '#F59E0B', 'historical': '#D97706', 'museum': '#8B5CF6', 'religious': '#EC4899', 'farm': '#84CC16', 'eco-tourism': '#10B981', 'cold spring': '#06B6D4', 'hot spring': '#EF4444', 'resort': '#6366F1' };
    return colors[cat] || '#3B82F6';
}

// ── MAIN MAP ─────────────────────────────────────────────────────────────────
export function initMap(spotsData, municipalData) {
    if (!document.getElementById('touristMap')) return;

    const muni = municipalData[0] || window.municipalityData || {};
    const muniLat = parseFloat(muni.latitude) || 16.5;
    const muniLng = parseFloat(muni.longitude) || 120.3;
    const bounds = L.latLngBounds([[muniLat - 0.15, muniLng - 0.15], [muniLat + 0.15, muniLng + 0.15]]);

    if (map) {
        if (markerCluster) markerCluster.clearLayers();
        map.eachLayer(layer => { if (layer !== mapLayers.street && layer !== mapLayers.satellite) map.removeLayer(layer); });
    } else {
        map = L.map('touristMap', { minZoom: 10 });
        mapLayers.street.addTo(map);
    }

    document.getElementById('touristMap')._leaflet_map = map;
    map.fitBounds(bounds);
    markerCluster = L.featureGroup();
    map.addLayer(markerCluster);

    // Municipality marker removed as requested

    // Spot markers via cluster — only approved spots on the map
    spotsData.filter(s => s && s.latitude && s.longitude && s.status === 'approved').forEach(s => {
        const icon = L.divIcon({
            html: `<div style="background:${getCategoryColor(s.category)};width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;border:2px solid white;box-shadow:0 2px 6px rgba(0,0,0,.3);"><i class="fas fa-${getCategoryIcon(s.category)}" style="font-size:13px;"></i></div>`,
            iconSize: [28, 28], iconAnchor: [14, 28]
        });
        L.marker([parseFloat(s.latitude), parseFloat(s.longitude)], { icon })
            .bindPopup(`<strong>${s.name}</strong><br><small>${s.category}</small>`)
            .addTo(markerCluster);
    });

    setTimeout(() => map.invalidateSize(), 300);
}

export function setupMapLayerToggle() {
    document.querySelectorAll('.map-tab').forEach(tab => {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.map-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            Object.values(mapLayers).forEach(l => { if (map && map.hasLayer(l)) map.removeLayer(l); });
            if (map) mapLayers[this.dataset.view].addTo(map);
        });
    });
}

// ── FILTERING ────────────────────────────────────────────────────────────────
export function filterSpots(searchValue = '', selectedCats = [], statusValue = '') {
    let visibleCount = 0;
    const mappedStatus = statusReverseMap[statusValue] || statusValue;

    function matchesCat(cardCat) {
        if (!selectedCats || selectedCats.length === 0) return true;
        const spotCats = (cardCat || '').split(',').map(s => s.trim());
        return selectedCats.some(fc => spotCats.includes(fc));
    }

    document.querySelectorAll('#cardsView .spot-card').forEach(card => {
        const nameMatch = !searchValue || card.dataset.name.includes(searchValue.toLowerCase());
        const catMatch = matchesCat(card.dataset.category);
        const statusMatch = !statusValue || card.dataset.status === mappedStatus;
        const show = nameMatch && catMatch && statusMatch;
        card.style.display = show ? 'block' : 'none';
        if (show) visibleCount++;
    });

    document.querySelectorAll('#tableView tbody tr').forEach(row => {
        const nameMatch = !searchValue || row.dataset.name.includes(searchValue.toLowerCase());
        const catMatch = matchesCat(row.dataset.category);
        const statusMatch = !statusValue || row.dataset.status === mappedStatus;
        row.style.display = (nameMatch && catMatch && statusMatch) ? '' : 'none';
    });

    const countEl = document.getElementById('spotCount');
    if (countEl) countEl.textContent = visibleCount;
    return visibleCount;
}

export function toggleDropdown(menuId) {
    const menu = document.getElementById(menuId);
    const isOpen = menu.style.display === 'block';
    document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none');
    if (!isOpen) menu.style.display = 'block';
}

export function setupViewToggle() {
    document.getElementById('viewCards')?.addEventListener('click', function () {
        this.classList.add('active');
        document.getElementById('viewTable').classList.remove('active');
        document.getElementById('cardsView').style.display = 'grid';
        document.getElementById('tableView').style.display = 'none';
    });
    document.getElementById('viewTable')?.addEventListener('click', function () {
        this.classList.add('active');
        document.getElementById('viewCards').classList.remove('active');
        document.getElementById('cardsView').style.display = 'none';
        document.getElementById('tableView').style.display = 'block';
    });
}

export function setupFilterListeners() {
    const applyFilters = () => {
        const searchValue = document.getElementById('searchInput')?.value || '';
        const selectedCats = Array.from(document.querySelectorAll('.cat-filter-chk:checked')).map(c => c.value);
        const statusValue = document.getElementById('filterStatus')?.value || '';

        const sortValue = document.getElementById('sortSpots')?.value || '';
        const munName = window.municipalityData ? window.municipalityData.name : '';
        if (sortValue) {
            window.touristSpotsData.sort((a, b) => {
                const pA = a.points !== undefined ? a.points : 0;
                const pB = b.points !== undefined ? b.points : 0;
                if (sortValue === 'points_desc') return pB - pA;
                if (sortValue === 'points_asc') return pA - pB;
                return 0;
            });
            if (typeof renderCardsGrid === 'function') renderCardsGrid(window.touristSpotsData, munName);
            if (typeof renderTableRows === 'function') renderTableRows(window.touristSpotsData, munName);
        } else {
            if (typeof renderCardsGrid === 'function') renderCardsGrid(window.touristSpotsData, munName);
            if (typeof renderTableRows === 'function') renderTableRows(window.touristSpotsData, munName);
        }

        filterSpots(searchValue, selectedCats, statusValue);
    };

    document.getElementById('searchInput')?.addEventListener('input', applyFilters);
    document.getElementById('filterStatus')?.addEventListener('change', applyFilters);
    document.getElementById('sortSpots')?.addEventListener('change', applyFilters);
    document.querySelectorAll('.cat-filter-chk').forEach(chk => chk.addEventListener('change', applyFilters));
}

export function setupDropdownListeners() {
    document.querySelectorAll('[id^="card-dropdown-"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const spotId = btn.id.replace('card-dropdown-', '');
            toggleDropdown('card-menu-' + spotId);
        });
    });
    document.querySelectorAll('[id^="tbl-dropdown-"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const spotId = btn.id.replace('tbl-dropdown-', '');
            toggleDropdown('tbl-menu-' + spotId);
        });
    });
    document.addEventListener('click', e => {
        if (!e.target.closest('.card-actions-dropdown') && !e.target.closest('.table-actions-dropdown'))
            document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none');
    });
}

// ── SPOT DETAIL MODAL ────────────────────────────────────────────────────────
window.openSpotModal = async function (spotId) {
    const modal = document.getElementById('spotModal');
    if (!modal) return;
    modal.classList.add('active');
    document.getElementById('modalTitle').textContent = 'Loading...';
    document.getElementById('modalBody').innerHTML = '<div style="text-align:center;padding:40px;color:#9CA3AF;"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i></div>';

    try {
        let spot = window.touristSpotsAll?.find(s => s.id == spotId);
        if (!spot) spot = await window.getSpot(spotId);
        document.getElementById('modalTitle').textContent = spot.name;
        const style = spot.classification_status ? getClassificationStyle(spot.classification_status) : null;
        const formattedDate = new Date(spot.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

        function fmTime(t) {
            if (!t) return 'N/A';
            const [h, m] = t.split(':').map(Number);
            return `${h % 12 || 12}:${String(m).padStart(2, '0')} ${h >= 12 ? 'PM' : 'AM'}`;
        }

        document.getElementById('modalBody').innerHTML = `
            ${spot.photo_url ? `<div style="height:200px;border-radius:10px;overflow:hidden;margin-bottom:16px;"><img src="${escapeHtml(spot.photo_url)}" alt="${escapeHtml(spot.name)}" style="width:100%;height:100%;object-fit:cover;" onerror="this.parentElement.style.display='none';"></div>` : ''}
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:16px;flex-wrap:wrap;">
                <span style="font-size:13px;color:#6B7280;"><i class="fas fa-map-marker-alt"></i> ${escapeHtml(spot.municipality_name)}, La Union</span>
                ${style ? `<span style="font-size:13px;font-weight:700;padding:4px 12px;border-radius:20px;background:${style.bg};color:${style.text};">${style.label}</span>` : ''}
                ${spot.status ? `<span style="font-size:13px;font-weight:700;padding:4px 12px;border-radius:20px;background:${spot.status === 'approved' ? '#10B981' : spot.status === 'pending' ? '#F59E0B' : '#DC2626'};color:#FFFFFF;">${spot.status === 'approved' ? 'Approved' : spot.status === 'pending' ? 'Pending' : 'Rejected'}</span>` : ''}
                ${spot.is_maintenance ? '<span style="font-size:13px;font-weight:700;padding:4px 12px;border-radius:20px;background:#F59E0B;color:#92400E;"><i class="fas fa-exclamation-triangle"></i> Under Maintenance</span>' : ''}
            </div>
            ${spot.status === 'rejected' && spot.rejection_reason ? `
                <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:8px;padding:12px;margin-bottom:16px;">
                    <div style="font-size:11px;color:#B91C1C;font-weight:700;text-transform:uppercase;margin-bottom:4px;"><i class="fas fa-exclamation-circle"></i> Rejection Reason</div>
                    <p style="font-size:13px;color:#991B1B;margin:0;line-height:1.5;">${escapeHtml(spot.rejection_reason)}</p>
                </div>
            ` : ''}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                <div style="background:#F8FAFC;border-radius:8px;padding:12px;"><div style="font-size:11px;color:#6B7280;font-weight:700;text-transform:uppercase;margin-bottom:6px;">Category</div><div style="display:flex;flex-wrap:wrap;gap:5px;">${(spot.category || 'Other').split(',').map(c => c.trim()).filter(Boolean).map(c => `<span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#DBEAFE;color:#2563EB;">${escapeHtml(c)}</span>`).join('')}</div></div>
                <div style="background:#F8FAFC;border-radius:8px;padding:12px;"><div style="font-size:11px;color:#6B7280;font-weight:700;text-transform:uppercase;margin-bottom:4px;">Fees</div>${formatFeesDisplay(spot)}</div>
                <div style="background:#FEF3C7;border-radius:8px;padding:12px;border:1px solid #FDE68A;">
                    <div style="font-size:11px;color:#D97706;font-weight:700;text-transform:uppercase;margin-bottom:4px;">⭐ Points</div>
                    <div style="font-size:14px;font-weight:700;color:#D97706;">${spot.points !== undefined ? spot.points : 0} Points</div>
                </div>
                <div style="background:#F8FAFC;border-radius:8px;padding:12px;"><div style="font-size:11px;color:#6B7280;font-weight:700;text-transform:uppercase;margin-bottom:4px;">Opening Time</div><div style="font-size:14px;font-weight:600;">${fmTime(spot.opening_time)}</div></div>
                <div style="background:#F8FAFC;border-radius:8px;padding:12px;"><div style="font-size:11px;color:#6B7280;font-weight:700;text-transform:uppercase;margin-bottom:4px;">Closing Time</div><div style="font-size:14px;font-weight:600;">${fmTime(spot.closing_time)}</div></div>
                ${spot.latitude ? `<div style="background:#F8FAFC;border-radius:8px;padding:12px;"><div style="font-size:11px;color:#6B7280;font-weight:700;text-transform:uppercase;margin-bottom:4px;">Latitude</div><div style="font-size:14px;font-weight:600;"><i class="fas fa-map-pin"></i> ${parseFloat(spot.latitude).toFixed(6)}</div></div>` : ''}
                ${spot.longitude ? `<div style="background:#F8FAFC;border-radius:8px;padding:12px;"><div style="font-size:11px;color:#6B7280;font-weight:700;text-transform:uppercase;margin-bottom:4px;">Longitude</div><div style="font-size:14px;font-weight:600;"><i class="fas fa-map-pin"></i> ${parseFloat(spot.longitude).toFixed(6)}</div></div>` : ''}
                <div style="background:#F8FAFC;border-radius:8px;padding:12px;"><div style="font-size:11px;color:#6B7280;font-weight:700;text-transform:uppercase;margin-bottom:4px;">Submitted</div><div style="font-size:14px;font-weight:600;">${formattedDate}</div></div>
                ${spot.status === 'approved' ? `
                <div style="background:#ECFDF5;border:1px solid #A7F3D0;border-radius:8px;padding:12px;">
                    <div style="font-size:11px;color:#065F46;font-weight:700;text-transform:uppercase;margin-bottom:4px;"><i class="fas fa-user-check"></i> Approved By</div>
                    <div style="font-size:14px;font-weight:600;color:#065F46;">${escapeHtml(spot.approver?.name || (spot.approved_by ? 'User #' + spot.approved_by : 'N/A'))}</div>
                </div>
                <div style="background:#ECFDF5;border:1px solid #A7F3D0;border-radius:8px;padding:12px;">
                    <div style="font-size:11px;color:#065F46;font-weight:700;text-transform:uppercase;margin-bottom:4px;"><i class="fas fa-calendar-check"></i> Approved At</div>
                    <div style="font-size:14px;font-weight:600;color:#065F46;">${spot.approved_at ? new Date(spot.approved_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A'}</div>
                </div>
                ` : ''}
            </div>
            <div style="margin-bottom:20px;"><div style="font-size:11px;color:#6B7280;font-weight:700;text-transform:uppercase;margin-bottom:8px;">Description</div><p style="color:#4B5563;line-height:1.6;margin:0;">${escapeHtml(spot.description) || 'No description provided.'}</p></div>`;
    } catch (err) {
        document.getElementById('modalBody').innerHTML = '<p style="color:#DC2626;">Failed to load spot details.</p>';
    }
};

export function closeSpotModal() { document.getElementById('spotModal')?.classList.remove('active'); }

export function setupModalListeners() {
    document.getElementById('closeSpotModal')?.addEventListener('click', closeSpotModal);
    document.getElementById('spotModal')?.addEventListener('click', e => { if (e.target.id === 'spotModal') closeSpotModal(); });
}

export function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ── IMAGE HANDLING ───────────────────────────────────────────────────────────
window.handleImageSelect = async function (e) {
    const files = Array.from(e.target.files);
    e.stopPropagation();
    e.target.value = '';
    await processImageFiles(files);
};

window.handleImageDrop = async function (e) {
    e.preventDefault(); e.stopPropagation();
    const area = document.getElementById('imageUploadArea');
    if (area) { area.style.borderColor = '#D1D5DB'; area.style.background = '#F9FAFB'; }
    await processImageFiles(Array.from(e.dataTransfer.files));
};

window.handleDragOver = function (e) {
    e.preventDefault(); e.stopPropagation();
    const area = document.getElementById('imageUploadArea');
    if (area) { area.style.borderColor = '#2563EB'; area.style.background = '#EEF2FF'; }
};

window.handleDragLeave = function (e) {
    e.preventDefault(); e.stopPropagation();
    const area = document.getElementById('imageUploadArea');
    if (area) { area.style.borderColor = '#D1D5DB'; area.style.background = '#F9FAFB'; }
};

async function processImageFiles(files) {
    const validFiles = [];
    for (const file of files) {
        if (!['image/jpeg', 'image/jpg', 'image/png'].includes(file.type)) { showToast(`Invalid file: ${file.name}`, 'danger'); continue; }
        if (file.size > 10 * 1024 * 1024) { showToast(`File too large: ${file.name}`, 'danger'); continue; }
        validFiles.push(file);
    }
    if (validFiles.length === 0) return;

    for (const file of validFiles) {
        const previewUrl = await getFilePreviewUrl(file);
        uploadedImages.push({ photo_url: previewUrl, filename: file.name, isLoading: true, id: `${Date.now()}-${Math.random().toString(36).slice(2)}` });
    }
    renderImagePreviews();

    const results = await Promise.allSettled(validFiles.map(async (file, idx) => {
        const tempId = uploadedImages[uploadedImages.length - validFiles.length + idx].id;
        try { const r = await uploadImage(file); return { result: r, tempId, success: true }; }
        catch (err) { return { error: err, tempId, success: false }; }
    }));

    let successCount = 0;
    for (const settled of results) {
        if (settled.status !== 'fulfilled') continue;
        const item = settled.value;
        const index = uploadedImages.findIndex(img => img.id === item.tempId);
        if (index === -1) continue;
        if (item.success) {
            uploadedImages[index] = { photo_url: item.result.photo_url, filename: item.result.filename || 'file' };
            successCount++;
        } else uploadedImages.splice(index, 1);
    }
    renderImagePreviews();
    const stuck = uploadedImages.filter(img => img.isLoading);
    if (stuck.length > 0) { uploadedImages = uploadedImages.filter(img => !img.isLoading); renderImagePreviews(); }
    if (successCount > 0) showToast(`${successCount} image(s) uploaded`, 'success');
}

function renderImagePreviews() {
    const container = document.getElementById('imagePreviews');
    if (!container) return;
    container.innerHTML = uploadedImages.map((img, idx) => `
        <div style="position:relative;border-radius:8px;overflow:hidden;width:100px;height:100px;border:2px solid #E5E7EB;">
            <img src="${img.photo_url}" alt="Preview" style="width:100%;height:100%;object-fit:cover;${img.isLoading ? 'filter:brightness(0.7)' : ''}">
            ${img.isLoading ? '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.3);z-index:10;"><i class="fas fa-spinner fa-spin" style="font-size:24px;color:white;"></i></div>' : ''}
            <button type="button" onclick="removeImage(${idx})" style="position:absolute;top:4px;right:4px;background:#DC2626;color:white;border:none;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center;cursor:pointer;${img.isLoading ? 'display:none' : ''}"><i class="fas fa-times"></i></button>
        </div>`).join('');
}

window.removeImage = function (index) { uploadedImages.splice(index, 1); renderImagePreviews(); };

// ── CATEGORY CHIP FORM LOGIC ─────────────────────────────────────────────────
function initCategoryChips() {
    document.addEventListener('click', function (e) {
        const formDd = document.getElementById('formCatDropdown'), formBtn = document.getElementById('formCatDropdownBtn');
        if (formDd && formBtn && !formBtn.contains(e.target) && !formDd.contains(e.target)) {
            formDd.style.display = 'none';
            const chevron = document.getElementById('formCatChevron');
            if (chevron) chevron.style.transform = 'rotate(0deg)';
        }

        const feeBtn = document.getElementById('feeTypesBtn'), feeDd = document.getElementById('feeTypesDropdown');
        if (feeDd && feeBtn && !feeBtn.contains(e.target) && !feeDd.contains(e.target)) {
            feeDd.style.display = 'none';
            const chevron = document.getElementById('feeTypesChevron');
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
    if (chk) chk.checked = !chk.checked;
    syncCategoryHiddenInput();
};

function syncCategoryHiddenInput() {
    const selected = Array.from(document.querySelectorAll('.form-cat-chk:checked')).map(c => c.value);
    document.getElementById('spotCategory').value = selected.join(',');
    const label = document.getElementById('formCatDropdownLabel');
    if (selected.length > 0) { label.textContent = selected.join(', '); label.style.color = '#1E293B'; }
    else { label.textContent = 'Select Categories...'; label.style.color = '#9CA3AF'; }
}

function setSelectedCategories(catStr) {
    document.querySelectorAll('.form-cat-chk').forEach(c => c.checked = false);
    if (!catStr) { syncCategoryHiddenInput(); return; }
    catStr.split(',').map(s => s.trim()).forEach(cat => {
        const chk = document.querySelector(`.form-cat-chk[value="${cat}"]`);
        if (chk) chk.checked = true;
    });
    syncCategoryHiddenInput();
}

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

function formatFeesDisplay(spot) {
    const feeTypes = Array.isArray(spot.fee_types) ? spot.fee_types : (spot.fee_types ? String(spot.fee_types).split(',').map(s => s.trim()).filter(Boolean) : []);
    if (feeTypes.length === 0) return '<span style="font-size:13px;color:#9CA3AF;">No Fees</span>';

    const entranceFee = Number(spot.entrance_fee || 0);
    const environmentalFee = Number(spot.environmental_fee || 0);
    let parts = [];
    if (feeTypes.includes('entrance') && entranceFee > 0) {
        parts.push('Entrance Fee: ₱' + entranceFee.toLocaleString(undefined, { minimumFractionDigits: 0 }));
    }
    if (feeTypes.includes('environmental') && environmentalFee > 0) {
        parts.push('Environmental Fee: ₱' + environmentalFee.toLocaleString(undefined, { minimumFractionDigits: 0 }));
    }
    if (feeTypes.includes('entrance') && entranceFee === 0) {
        parts.push('Entrance Fee: Free');
    }
    if (feeTypes.includes('environmental') && environmentalFee === 0) {
        parts.push('Environmental Fee: Free');
    }
    if (parts.length === 0) return '<span style="font-size:13px;color:#9CA3AF;">No Fees</span>';
    return parts.map(p => '<span style="display:block;font-size:13px;font-weight:600;color:#1E293B;">' + p + '</span>').join('');
}

function formatFeesShort(spot) {
    const feeTypes = Array.isArray(spot.fee_types) ? spot.fee_types : (spot.fee_types ? String(spot.fee_types).split(',').map(s => s.trim()).filter(Boolean) : []);
    if (feeTypes.length === 0) return 'No Fees';

    const entranceFee = Number(spot.entrance_fee || 0);
    const environmentalFee = Number(spot.environmental_fee || 0);
    let parts = [];
    if (feeTypes.includes('entrance')) {
        parts.push('₱' + entranceFee.toLocaleString(undefined, { minimumFractionDigits: 0 }));
    }
    if (feeTypes.includes('environmental')) {
        parts.push('EF: ₱' + environmentalFee.toLocaleString(undefined, { minimumFractionDigits: 0 }));
    }
    return parts.join(' | ');
}

function populateBarangayDropdown(selectedValue) {
    const muniId = window.municipalityData?.id;
    const select = document.getElementById('spotBarangay');
    if (!select || !muniId) return;

    select.innerHTML = '<option value="">— Select Barangay —</option>';
    const barangays = barangaysByMunicipality[parseInt(muniId)] || [];
    barangays.forEach(b => {
        const opt = document.createElement('option');
        opt.value = b;
        opt.textContent = b;
        if (selectedValue && b === selectedValue) opt.selected = true;
        select.appendChild(opt);
    });
}

async function autoPinBarangay(barangay, muniName) {
    if (_suppressBarangayOnchange) return;

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
            const resp = await fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(q)}`, {
                headers: { 'Accept-Language': 'en' }
            });
            const results = await resp.json();
            if (results.length > 0) {
                found = { lat: parseFloat(results[0].lat), lng: parseFloat(results[0].lon) };
                break;
            }
        } catch (_) { /* try next query */ }
    }

    if (found) {
        const muniName2 = getSelectedMuniName();
        if (muniName2) {
            const boundary = window.laUnionBoundaries && (window.laUnionBoundaries[muniName2] || window.laUnionBoundaries['San Fernando City']);
            const isEditMode = !!document.getElementById('spotId')?.value;
            if (!isEditMode && boundary && !isPointInBoundary(found.lat, found.lng, boundary)) {
                showToast(`The detected location for "${barangay}" is outside ${muniName2} boundary. Pin retained at current position.`, 'info');
                return;
            }
        }

        latInput.value = found.lat.toFixed(6);
        lngInput.value = found.lng.toFixed(6);

        if (modalMap && modalMarker) {
            modalMarker.setLatLng([found.lat, found.lng]);
            modalMap.setView([found.lat, found.lng], 16);
        } else {
            initModalMap();
            setTimeout(function () { placeOrMoveDraggableMarker(found.lat, found.lng); }, 300);
        }
        window.lastValidSpotCoords = { lat: found.lat, lng: found.lng };
    } else {
        showToast('Could not locate this barangay. Drag the pin to set the location.', 'info');
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

window.autoPinBarangay = autoPinBarangay;

// ── FORM OPEN / EDIT ─────────────────────────────────────────────────────────
window.openCreateForm = function () {
    uploadedImages = [];
    pendingSaveData = null;
    document.getElementById('formModalTitle').textContent = 'Add New Spot';
    document.getElementById('spotId').value = '';
    document.getElementById('spotName').value = '';
    document.getElementById('nameCharCount').textContent = '0';
    document.getElementById('spotPoints').value = '0';
    setSelectedCategories('');
    document.getElementById('spotClassification').value = '';
    document.getElementById('spotFee').value = '0';
    document.getElementById('environmentalFee').value = '0';
    document.querySelectorAll('.fee-type-chk').forEach(chk => chk.checked = false);
    document.getElementById('feeTypes').value = '';
    const label = document.getElementById('feeTypesLabel');
    if (label) { label.textContent = 'No Fees'; label.style.color = '#9CA3AF'; }
    document.getElementById('entranceFeeField').style.display = 'none';
    document.getElementById('environmentalFeeField').style.display = 'none';
    document.getElementById('spotLatitude').value = '';
    document.getElementById('spotLongitude').value = '';
    document.getElementById('spotDescription').value = '';
    document.getElementById('descCharCount').textContent = '0';
    document.getElementById('spotOpeningTime').value = '08:00';
    document.getElementById('spotClosingTime').value = '17:00';
    document.getElementById('spotIsMaintenance').checked = false;
    populateBarangayDropdown();
    document.getElementById('imagePreviews').innerHTML = '';
    document.getElementById('maintenance-field').style.display = 'none';

    window.lastValidSpotCoords = { lat: null, lng: null };
    if (currentBoundaryLayer && modalMap) {
        modalMap.removeLayer(currentBoundaryLayer);
        currentBoundaryLayer = null;
    }

    document.getElementById('spotFormModal').classList.add('active');
    setTimeout(initModalMap, 200);
};

window.editSpot = async function (spotId) {
    try {
        let spot = window.touristSpotsAll?.find(s => s.id == spotId);
        if (!spot) spot = await window.getSpot(spotId);
        uploadedImages = spot.images && spot.images.length > 0 ? spot.images : (spot.photo_url ? [{ photo_url: spot.photo_url }] : []);

        document.getElementById('formModalTitle').textContent = 'Edit Spot';
        document.getElementById('spotId').value = spot.id;
        document.getElementById('spotName').value = spot.name;
        document.getElementById('nameCharCount').textContent = spot.name.length;
        document.getElementById('spotPoints').value = spot.points !== undefined ? spot.points : '0';
        setSelectedCategories(spot.category || '');
        document.getElementById('spotClassification').value = statusDisplayMap[spot.classification_status] || spot.classification_status;
        document.getElementById('spotFee').value = spot.entrance_fee || 0;
        document.getElementById('environmentalFee').value = spot.environmental_fee || 0;
        setFeeTypesFromData(spot.fee_types || []);
        document.getElementById('spotLatitude').value = spot.latitude || '';
        document.getElementById('spotLongitude').value = spot.longitude || '';
        document.getElementById('spotDescription').value = spot.description || '';
        document.getElementById('descCharCount').textContent = (spot.description || '').length;
        document.getElementById('spotOpeningTime').value = spot.opening_time || '';
        document.getElementById('spotClosingTime').value = spot.closing_time || '';
        document.getElementById('spotIsMaintenance').checked = spot.is_maintenance ? true : false;
        populateBarangayDropdown(spot.barangay);
        document.getElementById('maintenance-field').style.display = 'block';
        renderImagePreviews();
        document.getElementById('spotFormModal').classList.add('active');
        setTimeout(initModalMap, 200);

    } catch (err) {
        showToast('Failed to load spot for editing', 'danger');
    }
};

// ── MODAL MAP ────────────────────────────────────────────────────────────────
function initModalMap() {
    if (!document.getElementById('modalMap')) return;
    if (modalMap) { modalMap.remove(); modalMap = null; modalMarker = null; }
    modalMap = L.map('modalMap', { minZoom: 10, maxZoom: 18 });
    const modalStreet = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '\u00a9 OpenStreetMap', maxZoom: 18 });
    const modalSatellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { attribution: '\u00a9 Esri', maxZoom: 18 });
    modalStreet.addTo(modalMap);
    L.control.layers({ "Street Map": modalStreet, "Satellite Map": modalSatellite }, null, { position: 'topright' }).addTo(modalMap);
    modalMap.setView([16.5, 120.3], 10);

    [100, 250, 500].forEach(d => setTimeout(() => { if (modalMap) modalMap.invalidateSize(); }, d));

    modalMap.on('click', function (e) {
        placeOrMoveDraggableMarker(e.latlng.lat, e.latlng.lng);
    });

    // Req 2: re-show municipality boundary (always visible throughout the form)
    const muniName = getSelectedMuniName();
    if (muniName) {
        displayMunicipalityBoundary(muniName);
    }

    // Req 1: place a default draggable marker immediately — no click required
    //   Priority: existing lat/lng (edit mode) → municipality centroid → La Union center
    setTimeout(() => {
        if (!modalMap) return;

        const existingLat = parseFloat(document.getElementById('spotLatitude')?.value);
        const existingLng = parseFloat(document.getElementById('spotLongitude')?.value);

        let startLat, startLng;
        let isExisting = false;
        if (!isNaN(existingLat) && !isNaN(existingLng)) {
            startLat = existingLat;
            startLng = existingLng;
            isExisting = true;
        } else if (muniName) {
            const boundary = window.laUnionBoundaries && (window.laUnionBoundaries[muniName] || (muniName === 'San Fernando City' || muniName === 'San Fernando' ? (window.laUnionBoundaries['San Fernando City'] || window.laUnionBoundaries['San Fernando']) : null));
            const centroid = getBoundaryCentroid(boundary);
            const muniLat = parseFloat(window.municipalityData?.latitude);
            const muniLng = parseFloat(window.municipalityData?.longitude);
            const fallback = (!isNaN(muniLat) && !isNaN(muniLng)) ? { lat: muniLat, lng: muniLng } : null;
            const target = centroid || fallback;
            startLat = target ? target.lat : 16.5;
            startLng = target ? target.lng : 120.3;
        } else {
            startLat = 16.5;
            startLng = 120.3;
        }

        if (!modalMarker) {
            const icon = L.divIcon({
                html: `<div style="background:#2563EB;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;border:3px solid white;box-shadow:0 3px 10px rgba(37,99,235,.45);cursor:grab;"><i class="fas fa-map-marker-alt" style="font-size:14px;"></i></div>`,
                iconSize: [32, 32],
                iconAnchor: [16, 32]
            });
            modalMarker = L.marker([startLat, startLng], { icon, draggable: true }).addTo(modalMap);
            setupMarkerDragEvents();
            window.lastValidSpotCoords = { lat: startLat, lng: startLng };
            document.getElementById('spotLatitude').value = startLat.toFixed(6);
            document.getElementById('spotLongitude').value = startLng.toFixed(6);
            modalMap.setView([startLat, startLng], isExisting ? 15 : (muniName ? 13 : 10));
            // Req 3: auto-detect barangay from the default position
            if (muniName) autoDetectBarangayFromCoords(startLat, startLng);
        }
    }, 180);
}

window.placeOrMoveDraggableMarker = function (lat, lng, skipBoundaryCheck = false, updateInputs = true) {
    if (!modalMap) return;
    const isValid = window.validateAndMovePin(lat, lng, skipBoundaryCheck, updateInputs);
    if (isValid) {
        modalMap.setView([lat, lng], 16);
        if (updateInputs) {
            const muniName = getSelectedMuniName();
            if (muniName) autoDetectBarangayFromCoords(lat, lng);
        }
    }
};

window.updateMapMarkerFromInput = function () {
    const lat = parseFloat(document.getElementById('spotLatitude').value);
    const lng = parseFloat(document.getElementById('spotLongitude').value);
    if (isNaN(lat) || isNaN(lng)) {
        return;
    }
    if (lat > 16.0 && lat < 17.0 && lng > 120.0 && lng < 121.0) {
        placeOrMoveDraggableMarker(lat, lng, true, false);
    }
};

window.closeFormModal = function () {
    uploadedImages = [];
    pendingSaveData = null;
    document.getElementById('spotFormModal').classList.remove('active');
    if (modalMap) { modalMap.remove(); modalMap = null; modalMarker = null; }
};

// ── FORM SUBMIT ──────────────────────────────────────────────────────────────
window.submitSpotForm = async function (e) {
    e.preventDefault();
    const saveBtn = document.getElementById('saveSpotBtn');
    const saveIcon = document.getElementById('saveSpotIcon');
    const saveSpinner = document.getElementById('saveSpotSpinner');
    const saveLabel = document.getElementById('saveSpotLabel');
    if (saveBtn) { saveBtn.disabled = true; if (saveIcon) saveIcon.style.display = 'none'; if (saveSpinner) saveSpinner.style.display = 'inline-block'; if (saveLabel) saveLabel.textContent = 'Validating...'; }
    const resetSaveBtn = () => { if (saveBtn) { saveBtn.disabled = false; if (saveIcon) saveIcon.style.display = 'inline-block'; if (saveSpinner) saveSpinner.style.display = 'none'; if (saveLabel) saveLabel.textContent = 'Save Spot'; } };

    const spotNameVal = (document.getElementById('spotName')?.value || '').trim();
    if (!spotNameVal) {
        showToast('Please enter a tourist spot name', 'danger');
        resetSaveBtn();
        return;
    }

    const currentSpotId = document.getElementById('spotId').value;
    const isDuplicate = (window.touristSpotsAll || []).some(spot => {
        if (currentSpotId && String(spot.id) === String(currentSpotId)) {
            return false;
        }
        return (spot.name || '').trim().toLowerCase() === spotNameVal.toLowerCase();
    });

    if (isDuplicate) {
        showDuplicateSpotNameModal();
        resetSaveBtn();
        return;
    }

    const catVal = document.getElementById('spotCategory').value;
    if (!catVal) { showToast('Please select at least one category', 'danger'); resetSaveBtn(); return; }
    const classVal = document.getElementById('spotClassification').value;
    if (!classVal) { showToast('Please select a classification status', 'danger'); resetSaveBtn(); return; }

    const pointsInput = document.getElementById('spotPoints');
    const pointsValue = pointsInput ? pointsInput.value.trim() : '0';

    const stillUploading = uploadedImages.some(img => img.isLoading);
    if (stillUploading) {
        showToast('Please wait for all images to finish uploading before saving', 'danger');
        resetSaveBtn();
        return;
    }

    const cleanImages = uploadedImages
        .filter(img => !img.isLoading && img.photo_url && !img.photo_url.startsWith('blob:'))
        .map(img => ({ photo_url: img.photo_url, filename: img.filename || '' }));

    const spotIdVal = document.getElementById('spotId').value;
    const dbStatus = statusReverseMap[classVal] || classVal;

    const latVal = parseFloat(document.getElementById('spotLatitude').value) || null;
    const lngVal = parseFloat(document.getElementById('spotLongitude').value) || null;
    const muniName = getSelectedMuniName();
    const isEditMode = !!spotIdVal;
    if (!isEditMode && latVal !== null && lngVal !== null && muniName) {
        const boundary = window.laUnionBoundaries && window.laUnionBoundaries[muniName];
        if (boundary && !isPointInBoundary(latVal, lngVal, boundary)) {
            showInvalidLocationModal(muniName);
            resetSaveBtn();
            return;
        }
    }

    const wasRejected = spotIdVal
        ? (window.touristSpotsAll || []).find(s => s.id == spotIdVal)?.status === 'rejected'
        : false;

    pendingSaveData = {
        id: spotIdVal ? parseInt(spotIdVal) : null,
        name: document.getElementById('spotName').value,
        category: catVal,
        classification_status: dbStatus,
        entrance_fee: parseFloat(document.getElementById('spotFee').value) || 0,
        environmental_fee: parseFloat(document.getElementById('environmentalFee').value) || 0,
        fee_types: getFeeTypesArray(),
        latitude: parseFloat(document.getElementById('spotLatitude').value) || null,
        longitude: parseFloat(document.getElementById('spotLongitude').value) || null,
        barangay: document.getElementById('spotBarangay').value || null,
        description: document.getElementById('spotDescription').value,
        municipality_id: parseInt(document.getElementById('municipalityId').value),
        images: cleanImages,
        opening_time: document.getElementById('spotOpeningTime').value || null,
        closing_time: document.getElementById('spotClosingTime').value || null,
        is_maintenance: document.getElementById('spotIsMaintenance').checked ? 1 : 0,
        wasRejected: wasRejected,
        points: parseInt(pointsValue)
    };

    const rejectedNote = document.getElementById('saveConfirmRejectedNote');
    const confirmMsg = document.getElementById('saveConfirmMessage');
    const confirmBtns = document.getElementById('saveConfirmBtns');
    if (wasRejected) {
        if (rejectedNote) rejectedNote.style.display = 'block';
        if (confirmBtns) confirmBtns.style.marginTop = '24px';
    } else {
        if (rejectedNote) rejectedNote.style.display = 'none';
        if (confirmBtns) confirmBtns.style.marginTop = '';
    }

    resetSaveBtn();
    setConfirmLoading(false);
    document.getElementById('saveConfirmModal').classList.add('active');
};

const setConfirmLoading = (loading, isEdit = false) => {
    const confirmBtn = document.getElementById('saveConfirmBtn');
    const confirmIcon = document.getElementById('confirmBtnIcon');
    const confirmSpinner = document.getElementById('confirmBtnSpinner');
    const confirmLabel = document.getElementById('confirmBtnLabel');
    const noBtn = document.querySelector('[data-action="close-save-confirm"]');

    if (!confirmBtn) return;
    confirmBtn.disabled = loading;
    if (noBtn) noBtn.disabled = loading;
    if (loading) {
        if (confirmIcon) confirmIcon.style.display = 'none';
        if (confirmSpinner) confirmSpinner.style.display = 'inline-block';
        if (confirmLabel) confirmLabel.textContent = isEdit ? 'Updating...' : 'Saving...';
    } else {
        if (confirmIcon) confirmIcon.style.display = 'inline-block';
        if (confirmSpinner) confirmSpinner.style.display = 'none';
        if (confirmLabel) confirmLabel.textContent = 'Yes, Save';
    }
};

window.closeSaveConfirmModal = function () {
    document.getElementById('saveConfirmModal').classList.remove('active');
    const rejectedNote = document.getElementById('saveConfirmRejectedNote');
    if (rejectedNote) rejectedNote.style.display = 'none';
    const confirmBtns = document.getElementById('saveConfirmBtns');
    if (confirmBtns) confirmBtns.style.marginTop = '';
    setConfirmLoading(false);
};

window.deleteSpot = async function (spotId) {
    const spot = window.touristSpotsAll?.find(s => s.id == spotId);
    const spotName = spot?.name || `Spot #${spotId}`;
    if (!confirm(`Are you sure you want to delete "${spotName}"? This action cannot be undone.`)) {
        return;
    }
    try {
        const res = await window.API_CONFIG.delete(`${API_BASE}/${spotId}`);
        if (res) {
            showToast(`Tourist spot deleted successfully!`, 'success');
            if (typeof window.notifyTouristSpotChanged === 'function') {
                window.notifyTouristSpotChanged();
            }
        }
    } catch (err) {
        showToast(`Error: ${err.message || 'Failed to delete spot'}`, 'danger');
    }
};

window.confirmSaveSpot = async function () {
    if (!pendingSaveData) { showToast('No data to save', 'danger'); return; }

    const isEdit = !!pendingSaveData.id;
    setConfirmLoading(true, isEdit);

    try {
        let res;
        if (isEdit) res = await updateSpot(pendingSaveData.id, pendingSaveData);
        else res = await createSpot(pendingSaveData);

        if (res && (res.success || res.message)) {
            const saveData = pendingSaveData;
            const wasRejected = saveData ? saveData.wasRejected : false;
            setConfirmLoading(false);
            closeSaveConfirmModal();
            closeFormModal();

            // Update local memory data instantly
            const spotId = isEdit ? parseInt(saveData.id) : (res.id || null);
            if (isEdit) {
                [window.touristSpotsData, window.touristSpotsAll].forEach(arr => {
                    if (arr) {
                        const spot = arr.find(s => s.id === spotId);
                        if (spot) {
                            Object.assign(spot, saveData);
                        }
                    }
                });
            } else if (spotId) {
                const newSpot = {
                    ...saveData,
                    id: spotId,
                    status: saveData.status || (window.userRole === 'municipal' ? 'pending' : 'approved'),
                    images: saveData.images || [],
                    photo_url: saveData.images && saveData.images[0] ? saveData.images[0].photo_url : '',
                    municipality_name: window.municipalityData ? window.municipalityData.name : ''
                };
                if (window.touristSpotsData) window.touristSpotsData.unshift(newSpot);
                if (window.touristSpotsAll) window.touristSpotsAll.unshift(newSpot);
            }

            if (isEdit && wasRejected) {
                showToast('Spot updated and re-submitted for LUPTO review!', 'success');
            } else {
                showToast(isEdit ? 'Spot updated successfully!' : 'Spot created successfully!', 'success');
            }

            try {
                const munName = window.municipalityData ? window.municipalityData.name : '';
                if (typeof renderCardsGrid === 'function' && window.touristSpotsData) {
                    renderCardsGrid(window.touristSpotsData, munName);
                }
                if (typeof renderTableRows === 'function' && window.touristSpotsData) {
                    renderTableRows(window.touristSpotsData, munName);
                }
                if (typeof updateKpiCards === 'function' && window.touristSpotsData && window.municipalitiesData) {
                    updateKpiCards(window.touristSpotsData, window.municipalitiesData);
                }
                if (document.getElementById('touristMap') && typeof initMap === 'function' && window.touristSpotsData && window.municipalitiesData) {
                    initMap(window.touristSpotsData, window.municipalitiesData);
                }
            } catch (renderErr) {
                console.error('Post-save render failed:', renderErr);
            }

            try {
                if (typeof window.notifyTouristSpotChanged === 'function') window.notifyTouristSpotChanged();
            } catch (e) {
                console.error('notifyTouristSpotChanged failed:', e);
            }
        } else {
            throw new Error(res.message || 'Unknown error');
        }
    } catch (err) {
        setConfirmLoading(false);
        showToast(`Error: ${err.message || 'Failed to save'}`, 'danger');
        closeSaveConfirmModal();
    }
};

// ── INITIALIZE ALL ───────────────────────────────────────────────────────────
export async function initializeAll(spotsData, municipalData) {
    loadCachedMuniKpis();

    // Check window cache first for instantaneous loading
    const cacheKey = '__MUNI_TOURIST_SPOTS_CACHE__';
    window[cacheKey] = window[cacheKey] || { spots: null, munis: null, timestamp: 0 };
    const cached = window[cacheKey];
    const isFresh = cached.spots && cached.munis && (Date.now() - cached.timestamp < 300000); // 5 minutes fresh TTL

    // Render instantly from cache if available
    if ((!spotsData || !spotsData.length) && cached.spots && cached.munis) {
        spotsData = cached.spots;
        municipalData = cached.munis;

        window.touristSpotsData = spotsData;
        window.municipalitiesData = municipalData;
        window.touristSpotsAll = spotsData;
        window.municipalitiesAll = municipalData;

        const munName = (municipalData[0] && municipalData[0].name) || 'Your Municipality';
        renderCardsGrid(spotsData, munName);
        renderTableRows(spotsData, munName);
        updateKpiCards(spotsData, municipalData);

        const pendingToast = sessionStorage.getItem('save_success_toast');
        if (pendingToast) { showToast(pendingToast, 'success'); sessionStorage.removeItem('save_success_toast'); }

        try { if (document.getElementById('touristMap')) initMap(spotsData, municipalData); } catch (e) { console.error('Map init failed:', e); }
        try {
            if (document.getElementById('lupto-map') && typeof initMapView === 'function') {
                var existing = document.getElementById('lupto-map')._leaflet_map;
                if (existing) { existing.remove(); delete document.getElementById('lupto-map')._leaflet_map; }
                initMapView();
            }
        } catch (e) { }
        try { setupMapLayerToggle(); } catch (e) { }
        try { setupViewToggle(); } catch (e) { }
        try { setupFilterListeners(); } catch (e) { }
        try { setupDropdownListeners(); } catch (e) { }
        try { setupModalListeners(); } catch (e) { }
        try { initCategoryChips(); } catch (e) { }

        document.querySelectorAll('[data-action="open-create-form"]').forEach(btn => btn.addEventListener('click', () => openCreateForm()));
        document.querySelectorAll('[data-action="close-form-modal"]').forEach(el => el.addEventListener('click', closeFormModal));

        startKpiAutoRefresh();

        // If cache is stale, trigger a silent fetch in background
        if (!isFresh) {
            softRefreshSpots();
        }
        return;
    }

    // Traditional load (no cache available)
    if (!spotsData || !spotsData.length || !municipalData || !municipalData.length) {
        try {
            const baseUrl = window.API_CONFIG?.BASE_URL || (`http://${window.location.hostname || '127.0.0.1'}:8000`);
            const spotsRes = await window.API_CONFIG.get(`${baseUrl}/api/municipal/tourist-spots`);
            spotsData = spotsRes.data || spotsRes || [];
            municipalData = window.municipalityData ? [window.municipalityData] : [{ id: 0, name: 'Your Municipality' }];

            // Save to cache
            cached.spots = spotsData;
            cached.munis = municipalData;
            cached.timestamp = Date.now();
        } catch (err) {
            console.error('Failed to fetch municipal tourist spots:', err);
            spotsData = [];
            municipalData = [{ id: 0, name: 'Your Municipality' }];
        }
    } else {
        // If data was passed in, update cache
        cached.spots = spotsData;
        cached.munis = municipalData;
        cached.timestamp = Date.now();
    }

    window.touristSpotsData = spotsData;
    window.municipalitiesData = municipalData;
    window.touristSpotsAll = spotsData;
    window.municipalitiesAll = municipalData;

    const munName = (municipalData[0] && municipalData[0].name) || 'Your Municipality';
    renderCardsGrid(spotsData, munName);
    renderTableRows(spotsData, munName);
    updateKpiCards(spotsData, municipalData);

    const pendingToast = sessionStorage.getItem('save_success_toast');
    if (pendingToast) { showToast(pendingToast, 'success'); sessionStorage.removeItem('save_success_toast'); }

    try { if (document.getElementById('touristMap')) initMap(spotsData, municipalData); } catch (e) { console.error('Map init failed:', e); }
    try {
        if (document.getElementById('lupto-map') && typeof initMapView === 'function') {
            var existing = document.getElementById('lupto-map')._leaflet_map;
            if (existing) { existing.remove(); delete document.getElementById('lupto-map')._leaflet_map; }
            initMapView();
        }
    } catch (e) { }
    try { setupMapLayerToggle(); } catch (e) { }
    try { setupViewToggle(); } catch (e) { }
    try { setupFilterListeners(); } catch (e) { }
    try { setupDropdownListeners(); } catch (e) { }
    try { setupModalListeners(); } catch (e) { }
    try { initCategoryChips(); } catch (e) { }

    document.querySelectorAll('[data-action="open-create-form"]').forEach(btn => btn.addEventListener('click', () => openCreateForm()));
    document.querySelectorAll('[data-action="close-form-modal"]').forEach(el => el.addEventListener('click', closeFormModal));
    document.getElementById('spotFormModal')?.addEventListener('click', e => { if (e.target.id === 'spotFormModal') closeFormModal(); });
    document.getElementById('spotForm')?.addEventListener('submit', submitSpotForm);
    document.getElementById('saveConfirmModal')?.addEventListener('click', e => { if (e.target.id === 'saveConfirmModal') closeSaveConfirmModal(); });
    document.querySelector('[data-action="close-save-confirm"]')?.addEventListener('click', closeSaveConfirmModal);
    document.querySelector('[data-action="confirm-save-spot"]')?.addEventListener('click', confirmSaveSpot);
    document.getElementById('spotLatitude')?.addEventListener('input', updateMapMarkerFromInput);
    document.getElementById('spotLongitude')?.addEventListener('input', updateMapMarkerFromInput);

    const triggerBoundaryCheckOnBlur = function () {
        const lat = parseFloat(document.getElementById('spotLatitude').value);
        const lng = parseFloat(document.getElementById('spotLongitude').value);
        if (!isNaN(lat) && !isNaN(lng)) {
            window.validateAndMovePin(lat, lng, false, true);
        }
    };
    document.getElementById('spotLatitude')?.addEventListener('change', triggerBoundaryCheckOnBlur);
    document.getElementById('spotLongitude')?.addEventListener('change', triggerBoundaryCheckOnBlur);
    document.getElementById('spotName')?.addEventListener('input', function () { document.getElementById('nameCharCount').textContent = this.value.length; });

    const checkDuplicateName = () => {
        const spotNameVal = (document.getElementById('spotName')?.value || '').trim();
        if (!spotNameVal) return;

        const currentSpotId = document.getElementById('spotId').value;
        const isDuplicate = (window.touristSpotsAll || []).some(spot => {
            if (currentSpotId && String(spot.id) === String(currentSpotId)) {
                return false;
            }
            return (spot.name || '').trim().toLowerCase() === spotNameVal.toLowerCase();
        });

        if (isDuplicate) {
            const modal = document.getElementById('duplicateSpotNameModal');
            if (!modal || !modal.classList.contains('active')) {
                showDuplicateSpotNameModal();
            }
        }
    };
    document.getElementById('spotName')?.addEventListener('blur', checkDuplicateName);
    document.getElementById('spotName')?.addEventListener('change', checkDuplicateName);
    document.getElementById('spotDescription')?.addEventListener('input', function () { document.getElementById('descCharCount').textContent = this.value.length; });

    const uploadArea = document.getElementById('imageUploadArea');
    const fileInput = document.getElementById('spotImages');
    if (uploadArea) {
        uploadArea.addEventListener('dragover', handleDragOver);
        uploadArea.addEventListener('dragleave', handleDragLeave);
        uploadArea.addEventListener('drop', handleImageDrop);
    }
    if (fileInput) fileInput.addEventListener('change', handleImageSelect);

    document.getElementById('spotBarangay')?.addEventListener('change', function () {
        const barangay = this.value;
        const muniName = window.municipalityData?.name || '';
        if (!barangay || !muniName) return;
        autoPinBarangay(barangay, muniName);
    });

    startKpiAutoRefresh();
}

let kpiRefreshTimer = null;

function startKpiAutoRefresh() {
    stopKpiAutoRefresh();
    kpiRefreshTimer = setInterval(() => {
        softRefreshSpots();
    }, 30000);
}

function stopKpiAutoRefresh() {
    if (kpiRefreshTimer) {
        clearInterval(kpiRefreshTimer);
        kpiRefreshTimer = null;
    }
}

window.startKpiAutoRefresh = startKpiAutoRefresh;
window.stopKpiAutoRefresh = stopKpiAutoRefresh;

function updateKpiCards(spotsData, municipalData) {
    const container = document.getElementById('spa-tab-tourist-spots.php') || document;
    const total = spotsData.length;
    const approved = spotsData.filter(s => (s.status || '') === 'approved').length;
    const pending = spotsData.filter(s => (s.status || '') === 'pending').length;
    const declined = spotsData.filter(s => (s.status || '') === 'rejected').length;

    // Compute most visited category
    const catCounts = {};
    spotsData.forEach(s => {
        const cats = (s.category || '').split(',').map(c => c.trim()).filter(Boolean);
        cats.forEach(c => { catCounts[c] = (catCounts[c] || 0) + 1; });
    });
    let topCategory = '—';
    let topCatCount = 0;
    Object.entries(catCounts).forEach(([cat, count]) => {
        if (count > topCatCount) { topCatCount = count; topCategory = cat; }
    });

    const elTotal = container.querySelector('[data-kpi="total-spots"] .lupto-kpi-value');
    const elApproved = container.querySelector('[data-kpi="approved-spots"] .lupto-kpi-value');
    const elPending = container.querySelector('[data-kpi="pending-spots"] .lupto-kpi-value');
    const elDeclined = container.querySelector('[data-kpi="declined-spots"] .lupto-kpi-value');
    const elCategory = container.querySelector('[data-kpi="most-visited-category"] .lupto-kpi-value');

    // Helper: flash the KPI element to signal a value change
    function flashKpi(el) {
        if (!el) return;
        el.classList.remove('kpi-updated');
        void el.offsetWidth; // force reflow to restart animation
        el.classList.add('kpi-updated');
        setTimeout(() => el.classList.remove('kpi-updated'), 600);
    }

    if (elTotal) { flashKpi(elTotal); window.animateKpiValue(elTotal, total); elTotal.style.color = ''; }
    if (elApproved) { flashKpi(elApproved); window.animateKpiValue(elApproved, approved); elApproved.style.color = ''; }
    if (elPending) { flashKpi(elPending); window.animateKpiValue(elPending, pending); elPending.style.color = ''; }
    if (elDeclined) { flashKpi(elDeclined); window.animateKpiValue(elDeclined, declined); elDeclined.style.color = ''; }
    if (elCategory) { flashKpi(elCategory); window.animateKpiValue(elCategory, topCategory); elCategory.style.color = ''; }

    // Update trend subtexts with real-time counts
    const trendTotal = container.querySelector('#kpi-trend-total');
    const trendApproved = container.querySelector('#kpi-trend-approved');
    const trendPending = container.querySelector('#kpi-trend-pending');
    const trendDeclined = container.querySelector('#kpi-trend-declined');
    const trendCategory = container.querySelector('#kpi-trend-category');
    if (trendTotal) trendTotal.innerHTML = '<i class="fas fa-layer-group"></i> All spots';
    if (trendApproved) trendApproved.innerHTML = '<i class="fas fa-check"></i> ' + approved + ' approved';
    if (trendPending) trendPending.innerHTML = '<i class="fas fa-clock"></i> ' + pending + ' pending';
    if (trendDeclined) trendDeclined.innerHTML = '<i class="fas fa-times"></i> ' + declined + ' rejected';
    if (trendCategory) trendCategory.innerHTML = '<i class="fas fa-crown"></i> ' + topCatCount + ' spots';

    const spotCount = document.getElementById('spotCount');
    if (spotCount) spotCount.textContent = total;
    try { sessionStorage.setItem('ts_kpis_municipal', JSON.stringify({ total, approved, pending, declined, topCategory, topCatCount })); } catch (e) { }
}

function loadCachedMuniKpis() {
    try {
        const raw = sessionStorage.getItem('ts_kpis_municipal');
        if (!raw) return;
        const v = JSON.parse(raw);
        const container = document.getElementById('spa-tab-tourist-spots.php') || document;

        const elTotal = container.querySelector('[data-kpi="total-spots"] .lupto-kpi-value');
        const elApproved = container.querySelector('[data-kpi="approved-spots"] .lupto-kpi-value');
        const elPending = container.querySelector('[data-kpi="pending-spots"] .lupto-kpi-value');
        const elDeclined = container.querySelector('[data-kpi="declined-spots"] .lupto-kpi-value');
        const elCategory = container.querySelector('[data-kpi="most-visited-category"] .lupto-kpi-value');

        if (elTotal) { elTotal.textContent = v.total; elTotal.style.color = '#1E293B'; }
        if (elApproved) { elApproved.textContent = v.approved; elApproved.style.color = '#1E293B'; }
        if (elPending) { elPending.textContent = v.pending || 0; elPending.style.color = '#1E293B'; }
        if (elDeclined) { elDeclined.textContent = v.declined; elDeclined.style.color = '#1E293B'; }
        if (elCategory) { elCategory.textContent = v.topCategory || '—'; elCategory.style.color = '#1E293B'; }

        const spotCount = document.getElementById('spotCount');
        if (spotCount) spotCount.textContent = v.total;
    } catch (e) { }
}

function renderCardsGrid(spotsData, munName) {
    const grid = document.getElementById('cardsView');
    if (!grid) return;

    const previousIds = new Set(Array.from(grid.querySelectorAll('.spot-card')).map(card => card.dataset.spotId));

    let html = '';
    spotsData.forEach(spot => {
        const desc = (spot.description || '').substring(0, 100);
        const status = spot.classification_status || '';
        const statusClass = status === 'EXIST' ? 'EXISTING' : status === 'EMERGE' ? 'EMERGING' : 'POTENTIAL';
        const statusBg = status === 'EXIST' ? '#10B981' : status === 'EMERGE' ? '#8B5CF6' : status === 'POTENTIAL' ? '#F59E0B' : '#9CA3AF';
        const statusColor = status === 'POTENTIAL' ? '#1E293B' : '#FFFFFF';
        const approvalStatus = spot.status || '';
        const approvalBg = approvalStatus === 'approved' ? '#10B981' : approvalStatus === 'pending' ? '#F59E0B' : approvalStatus === 'rejected' ? '#DC2626' : '#9CA3AF';
        const approvalLabel = approvalStatus === 'approved' ? 'Approved' : approvalStatus === 'pending' ? 'Pending' : approvalStatus === 'rejected' ? 'Rejected' : approvalStatus || '—';
        const approvalTextColor = '#FFFFFF';
        const cats = (spot.category || 'Other').split(',').map(c => c.trim()).filter(Boolean);
        const catTags = cats.map(c => `<span class="tag" style="background:#DBEAFE;color:#2563EB;">${c}</span>`).join('');
        const photoUrl = spot.photo_url || '';

        const isNew = previousIds.size > 0 && !previousIds.has(String(spot.id));
        const animateClass = isNew ? ' new-card-animate' : '';

        html += `<div class="spot-card${animateClass}" data-spot-id="${spot.id}" data-municipality="${munName}" data-category="${spot.category || ''}" data-status="${statusClass}" data-name="${(spot.name || '').toLowerCase()}" style="cursor: pointer;">`;
        html += `<div class="spot-image">`;
        if (photoUrl) {
            html += `<img src="${escapeHtml(photoUrl)}" alt="${escapeHtml(spot.name || '')}" loading="lazy" style="width:100%;height:100%;object-fit:cover;display:block;" onerror="var p=this.parentElement;this.style.display='none';var ph=p.querySelector('.spot-image-placeholder');if(ph)ph.style.display='flex';">`;
            html += `<div class="spot-image-placeholder" style="display:none;"><i class="fas fa-image"></i><span>Image unavailable</span></div>`;
        } else {
            html += `<div class="spot-image-placeholder"><i class="fas fa-image"></i><span>No image yet</span></div>`;
        }
        html += `</div>`;
        html += `<div class="card-actions-dropdown">`;
        html += `<button class="dropdown-toggle" id="card-dropdown-${spot.id}"><i class="fas fa-ellipsis-v"></i></button>`;
        html += `<div class="dropdown-menu" id="card-menu-${spot.id}">`;
        html += `<button class="dropdown-item" data-action="edit-spot" data-spot-id="${spot.id}"><i class="fas fa-pen-to-square" style="color:#F59E0B;"></i> Edit</button>`;
        html += `</div></div>`;
        html += `<div class="spot-body">`;
        html += `<h3>${spot.name || ''}</h3>`;
        html += `<div class="muni"><i class="fas fa-map-marker-alt"></i> ${munName}, La Union</div>`;
        html += `<div class="tags">`;
        html += catTags;
        html += `<span class="tag" style="background:#F8FAFC;color:#4B5563;">${formatFeesShort(spot)}</span>`;
        if (status) {
            html += `<span class="tag" style="background:${statusBg};color:${statusColor};">${statusClass}</span>`;
        }
        const pointsVal = spot.points !== undefined ? spot.points : 0;
        html += `<span class="tag" style="background:#FEF3C7;color:#D97706;font-weight:600;">🏆 ${pointsVal} Points</span>`;
        if (approvalStatus) {
            html += `<span class="tag" style="background:${approvalBg};color:${approvalTextColor};">${approvalLabel}</span>`;
        }
        html += `</div>`;
        html += `<p>${desc}${(spot.description || '').length > 100 ? '...' : ''}</p>`;
        html += `</div></div>`;
    });
    grid.innerHTML = html;
    document.getElementById('spotCount').textContent = spotsData.length;

    grid.querySelectorAll('.spot-card.new-card-animate').forEach(card => {
        void card.offsetWidth;
    });

    // Bind click listener to cards (except actions/buttons)
    grid.querySelectorAll('.spot-card').forEach(card => {
        card.addEventListener('click', (e) => {
            if (e.target.closest('.card-actions-dropdown')) {
                return;
            }
            openSpotModal(card.dataset.spotId);
        });
    });

    // Bind click listener to edit/delete buttons
    grid.querySelectorAll('[data-action="edit-spot"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            editSpot(btn.dataset.spotId);
        });
    });
    grid.querySelectorAll('[data-action="delete-spot"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            window.deleteSpot(btn.dataset.spotId);
        });
    });
}

function renderTableRows(spotsData, munName) {
    const tbody = document.querySelector('#tableView tbody');
    if (!tbody) return;

    const previousIds = new Set(Array.from(tbody.querySelectorAll('tr')).map(r => r.dataset.spotId));

    let html = '';
    spotsData.forEach(spot => {
        const status = spot.classification_status || '';
        const statusClass = status === 'EXIST' ? 'EXISTING' : status === 'EMERGE' ? 'EMERGING' : 'POTENTIAL';
        const statusBg = status === 'EXIST' ? '#10B981' : status === 'EMERGE' ? '#8B5CF6' : status === 'POTENTIAL' ? '#F59E0B' : '#9CA3AF';
        const statusColor = status === 'POTENTIAL' ? '#1E293B' : '#FFFFFF';
        const approvalStatus = spot.status || '';
        const approvalBg = approvalStatus === 'approved' ? '#10B981' : approvalStatus === 'pending' ? '#F59E0B' : approvalStatus === 'rejected' ? '#DC2626' : '#9CA3AF';
        const approvalLabel = approvalStatus === 'approved' ? 'Approved' : approvalStatus === 'pending' ? 'Pending' : approvalStatus === 'rejected' ? 'Rejected' : approvalStatus || '—';
        const approvalTextColor = '#FFFFFF';
        const cats = (spot.category || 'Other').split(',').map(c => c.trim()).filter(Boolean);
        const catTags = cats.map(c => `<span class="tag" style="background:#DBEAFE;color:#2563EB;font-size:11px;">${c}</span>`).join(' ');
        const date = spot.created_at ? new Date(spot.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
        const feeDisplay = formatFeesShort(spot);
        const spotId = String(spot.id).padStart(4, '0');
        html += `<tr data-spot-id="${spot.id}" data-municipality="${munName}" data-category="${spot.category || ''}" data-status="${statusClass}" data-name="${(spot.name || '').toLowerCase()}" style="cursor: pointer;">`;
        html += `<td style="font-family:'Courier New',monospace;color:#6B7280;">TS-${spotId}</td>`;
        html += `<td><strong>${spot.name || ''}</strong></td>`;
        html += `<td>${catTags}</td>`;
        html += `<td>${status ? `<span class="tag" style="background:${statusBg};color:${statusColor};">${statusClass}</span>` : ''}</td>`;
        html += `<td style="font-weight: 600; color: #D97706;">${spot.points !== undefined ? spot.points : 0} pts</td>`;
        html += `<td>${approvalStatus ? `<span class="tag" style="background:${approvalBg};color:${approvalTextColor};">${approvalLabel}</span>` : '—'}</td>`;
        html += `<td>${feeDisplay}</td>`;
        html += `<td>${date}</td>`;
        html += `<td style="text-align:right;"><div class="table-actions-dropdown">`;
        html += `<button class="dropdown-toggle" id="tbl-dropdown-${spot.id}"><i class="fas fa-ellipsis-v"></i></button>`;
        html += `<div class="dropdown-menu" id="tbl-menu-${spot.id}">`;
        html += `<button class="dropdown-item" data-action="edit-spot" data-spot-id="${spot.id}"><i class="fas fa-pen-to-square" style="color:#F59E0B;"></i> Edit</button>`;
        html += `</div></div></td></tr>`;
    });
    tbody.innerHTML = html;

    // Bind click listener to table rows (except action dropdowns)
    tbody.querySelectorAll('tr').forEach(row => {
        row.addEventListener('click', (e) => {
            if (e.target.closest('.table-actions-dropdown')) {
                return;
            }
            openSpotModal(row.dataset.spotId);
        });
    });

    // Bind click listener to edit/delete buttons
    tbody.querySelectorAll('[data-action="edit-spot"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            editSpot(btn.dataset.spotId);
        });
    });
    tbody.querySelectorAll('[data-action="delete-spot"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            window.deleteSpot(btn.dataset.spotId);
        });
    });
}

async function softRefreshSpots(spotsData = null, muniData = null) {
    if (!document.getElementById('cardsView')) return; // Guard: only run on tourist-spots page
    loadCachedMuniKpis();
    try {
        let freshSpots;
        if (spotsData) {
            freshSpots = spotsData;
        } else {
            // Check in-memory cache first for instant re-navigation
            if (_isMuniCacheFresh() && window[cacheKey].spots) {
                freshSpots = window[cacheKey].spots;
            } else {
                const baseUrl = window.API_CONFIG?.BASE_URL || (`http://${window.location.hostname || '127.0.0.1'}:8000`);
                const spotsRes = await window.API_CONFIG.get(`${baseUrl}/api/municipal/tourist-spots`);
                freshSpots = spotsRes?.data || spotsRes || [];
                // Cache the result
                window[cacheKey].spots = freshSpots;
                window[cacheKey].timestamp = Date.now();
            }
        }

        let freshMuni;
        if (muniData && Array.isArray(muniData) && muniData.length > 0) {
            freshMuni = muniData[0];
        } else {
            freshMuni = window.municipalityData || { name: 'Your Municipality' };
        }
        const munName = freshMuni.name || 'Your Municipality';

        window.touristSpotsData = freshSpots;
        window.municipalitiesData = [freshMuni];
        window.touristSpotsAll = freshSpots;
        window.municipalitiesAll = [freshMuni];

        // Update the cache object so that isFresh becomes true again
        const cacheObj = window[cacheKey];
        if (cacheObj) {
            cacheObj.spots = freshSpots;
            cacheObj.munis = [freshMuni];
            cacheObj.timestamp = Date.now();
        }

        // Save current filter values before rendering
        const searchInputEl = document.getElementById('searchInput');
        const activeSearch = searchInputEl ? searchInputEl.value : '';
        const filterStatusEl = document.getElementById('filterStatus');
        const activeStatus = filterStatusEl ? filterStatusEl.value : '';
        const selectedCats = Array.from(document.querySelectorAll('.cat-filter-chk:checked')).map(c => c.value);

        renderCardsGrid(freshSpots, munName);
        renderTableRows(freshSpots, munName);

        // Restore filter/search after render
        if (filterStatusEl) filterStatusEl.value = activeStatus;
        if (searchInputEl) searchInputEl.value = activeSearch;

        updateKpiCards(freshSpots, [freshMuni]);
        setupDropdownListeners();

        // Refresh filters so search/category/status results are up-to-date
        filterSpots(activeSearch, selectedCats, activeStatus);

        // Refresh both maps
        if (document.getElementById('touristMap')) {
            initMap(freshSpots, [freshMuni]);
        }
        if (document.getElementById('lupto-map') && typeof window.refreshMunicipalMap === 'function') {
            window.refreshMunicipalMap();
        } else if (document.getElementById('lupto-map') && typeof initMapView === 'function') {
            try {
                const existing = document.getElementById('lupto-map')._leaflet_map;
                if (existing) { existing.remove(); delete document.getElementById('lupto-map')._leaflet_map; }
                initMapView();
            } catch (e) { }
        }

        void 0;
    } catch (err) {
        if (err.name !== 'AbortError') {
            console.error('❌ Municipal soft refresh failed:', err);
        }
    }
}

window.softRefreshTouristSpots = softRefreshSpots;

// ── REAL-TIME HOOK ────────────────────────────────────────────────────────────
// Intercept notifyTouristSpotChanged so ANY CRUD action on this page (Add, Edit,
// Delete, Archive, Restore, Submit, Approve, Reject) instantly re-renders the
// cards/table/filters/map/KPIs without requiring a page refresh.
setTimeout(function () {
    const prev = window.notifyTouristSpotChanged;
    window.notifyTouristSpotChanged = function () {
        // Invalidate in-memory cache so next softRefresh fetches fresh data
        if (typeof window.invalidateMunicipalSpotsCache === 'function') {
            window.invalidateMunicipalSpotsCache();
        }
        if (typeof prev === 'function') prev.apply(this, arguments);
    };
}, 0);
