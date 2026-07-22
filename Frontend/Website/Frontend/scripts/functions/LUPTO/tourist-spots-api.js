// ════════════════════════════════════════════════════════════════════════════════
// LUPTO TOURIST SPOTS - API & UTILITIES
// ════════════════════════════════════════════════════════════════════════════════

// Minimal guard: api-config.js is always loaded before this file.
// Only patch getCsrfToken in case an older cached version is missing it.
if (window.API_CONFIG && typeof window.API_CONFIG.getCsrfToken !== 'function') {
    window.API_CONFIG.getCsrfToken = function () {
        const match = document.cookie.split(';').find(c => c.trim().startsWith('XSRF-TOKEN='));
        if (match) return decodeURIComponent(match.trim().split('=').slice(1).join('='));
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    };
}

const API_BASE = `${window.API_CONFIG?.BASE_URL || ('http://' + (window.location.hostname || '127.0.0.1') + ':8000')}/api/tourist-spots`;

// ── In-Memory Spots Cache ────────────────────────────────────────────────────
// Survives SPA re-navigation so returning to Tourist Spots is instant.
const SPOTS_CACHE_TTL = 300000; // 5 minutes fresh TTL
const cacheKey = '__LUPTO_TOURIST_SPOTS_CACHE__';
window[cacheKey] = window[cacheKey] || { spots: null, munis: null, timestamp: 0 };

function _isCacheFresh() {
    const c = window[cacheKey];
    return c.spots !== null && c.munis !== null && (Date.now() - c.timestamp) < SPOTS_CACHE_TTL;
}

/** Invalidate cache on write operations so next read is always fresh. */
window.invalidateLuptoSpotsCache = function () {
    window[cacheKey].timestamp = 0;
};

// ── Background Auto-Refresh ───────────────────────────────────────────────────
// Silently refresh the cache every 90 seconds so data stays up-to-date.
let _spotsRefreshTimer = null;
function _startSpotsAutoRefresh() {
    if (_spotsRefreshTimer) return; // already running
    _spotsRefreshTimer = setInterval(async () => {
        try {
            const baseUrl = window.API_CONFIG?.BASE_URL || (`http://${window.location.hostname || '127.0.0.1'}:8000`);
            const [spotsRes, muniRes] = await Promise.all([
                window.API_CONFIG.get(`${baseUrl}/api/tourist-spots`),
                window.API_CONFIG.get(`${baseUrl}/api/municipalities`)
            ]);
            const freshSpots = spotsRes.data || spotsRes || [];
            const freshMunis = muniRes.municipalities || muniRes.data || muniRes || [];

            if (freshSpots.length || freshMunis.length) {
                window[cacheKey].spots = freshSpots;
                window[cacheKey].munis = freshMunis;
                window[cacheKey].timestamp = Date.now();

                // Update in-memory global data used by filters/renders
                if (window.touristSpotsAll) window.touristSpotsAll = freshSpots;
                if (window.touristSpotsData) window.touristSpotsData = freshSpots;
                if (window.municipalitiesData) window.municipalitiesData = freshMunis;
            }
        } catch (_) { /* silently ignore network errors on background refresh */ }
    }, 90000);
}

// Start background refresh as soon as the page is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', _startSpotsAutoRefresh);
} else {
    _startSpotsAutoRefresh();
}


function getSpotImageUploadUrl() {
    // Upload directly to Laravel backend — images stored in backend/storage/app/public/tourist_spots/
    return window.API_CONFIG.BASE_URL + '/api/tourist-spots/upload-image';
}

function withTimeout(promise, ms, label = 'Operation') {
    return Promise.race([
        promise,
        new Promise((_, reject) => {
            setTimeout(() => reject(new Error(`${label} timed out after ${Math.round(ms / 1000)}s`)), ms);
        })
    ]);
}

// ── Map Global Variables
let map, markerCluster;
let modalMap, modalMarker;
const mapLayers = {
    street: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
        maxZoom: 18
    }),
    satellite: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: '© Esri',
        maxZoom: 18
    }),
};

// ── Form & Image Variables
let uploadedImages = [];
let pendingSaveData = null;

// ── Boundary Validation Variables & Helpers
let currentBoundaryLayer = null;
window.lastValidSpotCoords = { lat: null, lng: null };

function getSelectedMuniName() {
    const el = document.getElementById('spotMunicipality');
    if (!el) return null;
    const value = el.value;
    if (!value) return null;
    const muni = window.municipalitiesData?.find(m => m.id == value);
    return muni ? muni.name : null;
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

function showInvalidLocationModal(muniName, customMessage = null) {
    injectInvalidLocationModal();
    const muniEl = document.getElementById('invalidLocationMuniName');
    if (muniEl) {
        muniEl.textContent = muniName || 'Selected Municipality';
    }
    const p = document.querySelector('#invalidLocationModal p');
    if (p) {
        if (customMessage) {
            p.innerHTML = customMessage.replace(/\n/g, '<br/>');
        } else {
            p.innerHTML = `The selected location is outside the boundary of the chosen municipality.<br/><br/>Please place the pin within the official boundary of <strong id="invalidLocationMuniName">${escapeHtml(muniName || 'Selected Municipality')}</strong>.`;
        }
    }
    const modal = document.getElementById('invalidLocationModal');
    if (modal) {
        modal.classList.add('active');
    }
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
        // pick the largest polygon ring
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
let _suppressBarangayOnchange = false; // prevents auto-detect loop
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
        if (!select || select.options.length <= 1) return; // no barangay options loaded
        try {
            const resp = await fetch(
                `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=16&addressdetails=1`,
                { headers: { 'Accept-Language': 'en' } }
            );
            if (!resp.ok) return;
            const data = await resp.json();
            const addr = data.address || {};
            // Nominatim may return barangay-level place in any of these fields
            const candidates = [
                addr.village, addr.suburb, addr.neighbourhood,
                addr.hamlet, addr.quarter, addr.city_district, addr.residential
            ].filter(Boolean);

            // Normalise: strip common Filipino barangay prefixes
            const norm = s => s.replace(/^(barangay|brgy\.?|bgy\.?)\s+/i, '').trim().toLowerCase();

            const opts = Array.from(select.options).slice(1); // skip placeholder
            let matched = null;
            for (const candidate of candidates) {
                const nc = norm(candidate);
                matched = opts.find(o => norm(o.value) === nc);
                if (!matched) matched = opts.find(o => norm(o.value).includes(nc) || nc.includes(norm(o.value)));
                if (matched) break;
            }
            if (matched && select.value !== matched.value) {
                // Use suppress flag so onBarangayChange doesn't re-move the pin
                _suppressBarangayOnchange = true;
                select.value = matched.value;
                _suppressBarangayOnchange = false;
            }
        } catch (_) { /* silently ignore — Nominatim is optional */ }
    }, 650); // 650 ms debounce respects Nominatim's usage policy
}

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

window.validateAndMovePin = function (lat, lng, skipBoundaryCheck = false, updateInputs = true, isUserAction = false) {
    const muniName = getSelectedMuniName();
    if (!muniName) {
        showToast('Please select a municipality first', 'warning');
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
        if (isUserAction) {
            showInvalidLocationModal(muniName, "The selected location is outside the boundary of the selected municipality. Please choose a location within the selected municipality before adding the tourist spot.");
        } else {
            showToast('The entered coordinates are outside the selected municipality. Please verify the location or select the correct municipality.', 'warning');
        }
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
        autoDetectBarangayFromCoords(pos.lat, pos.lng);
    });
    modalMarker.on('dragend', function (e) {
        const pos = e.target.getLatLng();
        const isValid = window.validateAndMovePin(pos.lat, pos.lng, false, true, true);
        // Req 3: auto-detect barangay whenever the pin lands inside the selected municipality
        if (isValid && getSelectedMuniName()) {
            autoDetectBarangayFromCoords(pos.lat, pos.lng);
        }
    });
}

// Generate a preview URL for a file
function getFilePreviewUrl(file) {
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = (e) => resolve(e.target.result);
        reader.onerror = () => resolve(null);
        reader.readAsDataURL(file);
    });
}


// API CALLS - PROPERLY MAPPED TO LARAVEL ENDPOINTS


export const getSpots = async () => {
    // Return cached data immediately if still fresh (avoids re-fetching on tab switch)
    if (_isCacheFresh() && window[cacheKey].spots) {
        return window[cacheKey].spots;
    }
    const data = await window.API_CONFIG.get(`${API_BASE}`);
    if (data) {
        window[cacheKey].spots = data;
        window[cacheKey].timestamp = Date.now();
    }
    return data;
};


export const getSpot = async (id) => {
    return await window.API_CONFIG.get(`${API_BASE}/${id}`);
};

export const createSpot = async (data) => {
    return await window.API_CONFIG.post(`${API_BASE}`, data);
};

export const updateSpot = async (id, data) => {
    return await window.API_CONFIG.put(`${API_BASE}/${id}`, data);
};

export const deleteSpot = async (id) => {
    return await window.API_CONFIG.delete(`${API_BASE}/${id}`);
};

// Make API functions available on window for global access
window.getSpots = getSpots;
window.getSpot = getSpot;
window.createSpot = createSpot;
window.updateSpot = updateSpot;
window.deleteSpot = deleteSpot;

// Compress/resize image before upload (huge speedup!)
const compressImage = async (file, maxWidth = 1280, maxHeight = 720, quality = 0.7) => {
    return new Promise((resolve, reject) => {
        if (!file.type.startsWith('image/')) {
            resolve(file);
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                let { width, height } = img;

                // Calculate new dimensions while maintaining aspect ratio
                if (width > maxWidth) {
                    height = (height * maxWidth) / width;
                    width = maxWidth;
                }
                if (height > maxHeight) {
                    width = (width * maxHeight) / height;
                    height = maxHeight;
                }

                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;

                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                canvas.toBlob((blob) => {
                    if (!blob) {
                        resolve(file);
                        return;
                    }
                    const compressedFile = new File([blob], file.name, {
                        type: 'image/jpeg',
                        lastModified: Date.now()
                    });
                    resolve(compressedFile);
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
    try {
        processedFile = await withTimeout(compressImage(file), 12000, 'Image processing');
    } catch (err) {
        void 0;
        processedFile = file;
    }

    const formData = new FormData();
    formData.append('image', processedFile);

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 45000);

    try {
        const response = await fetch(getSpotImageUploadUrl(), {
            method: 'POST',
            credentials: 'include',
            headers: { 'Accept': 'application/json' },
            body: formData,
            signal: controller.signal
        });

        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch {
            throw new Error(`Invalid server response (HTTP ${response.status})`);
        }

        if (!response.ok) {
            throw new Error(data.error || data.message || `Upload failed: HTTP ${response.status}`);
        }

        if (!data.success || !data.photo_url) {
            throw new Error(data.error || 'Upload failed');
        }

        if (window.API_CONFIG && typeof window.API_CONFIG.normalizeImageUrl === 'function') {
            data.photo_url = window.API_CONFIG.normalizeImageUrl(data.photo_url);
        }

        return data;
    } catch (err) {
        if (err.name === 'AbortError') {
            throw new Error('Upload timed out. Check that Laravel is running on port 8000.');
        }
        throw err;
    } finally {
        clearTimeout(timeoutId);
    }
};

// Upload multiple images in parallel
const uploadMultipleImages = async (files) => {
    return Promise.all(files.map(file => uploadImage(file)));
};


// STATUS AND CLASSIFICATION HELPERS


// Database stores: EXIST, EMERGE, POTENTIAL
// Form displays: EXISTING, EMERGING, POTENTIAL
const statusDisplayMap = {
    'EXIST': 'EXISTING',
    'EMERGE': 'EMERGING',
    'POTENTIAL': 'POTENTIAL'
};

const statusReverseMap = {
    'EXISTING': 'EXIST',
    'EMERGING': 'EMERGE',
    'POTENTIAL': 'POTENTIAL'
};

export function getClassificationStyle(status) {
    const styles = {
        'EXIST': { bg: '#10B981', text: '#FFFFFF', label: 'EXISTING' },
        'EMERGE': { bg: '#8B5CF6', text: '#FFFFFF', label: 'EMERGING' },
        'POTENTIAL': { bg: '#F59E0B', text: '#1E293B', label: 'POTENTIAL' },
        'EXISTING': { bg: '#10B981', text: '#FFFFFF', label: 'EXISTING' },
        'EMERGING': { bg: '#8B5CF6', text: '#FFFFFF', label: 'EMERGING' },
        'default': { bg: '#9CA3AF', text: '#FFFFFF', label: 'UNKNOWN' }
    };
    return styles[status] || styles['default'];
}

export function getClassificationBadgeHTML(status) {
    if (!status) return '';
    const style = getClassificationStyle(status);
    return `<span class="tag" style="background:${style.bg};color:${style.text};">${style.label}</span>`;
}

// TOAST NOTIFICATIONS

export function showToast(msg, type = 'success') {
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

// ════════════════════════════════════════════════════════════════════════════════
// MAP INITIALIZATION
// ════════════════════════════════════════════════════════════════════════════════

export function getCategoryIcon(categoryStr) {
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

export function getCategoryColor(categoryStr) {
    if (window.MapMarkersConfig && typeof window.MapMarkersConfig.getCategoryColor === 'function') {
        return window.MapMarkersConfig.getCategoryColor(categoryStr);
    }
    if (!categoryStr) return '#3B82F6';
    const cat = categoryStr.split(',')[0].trim().toLowerCase();
    const colors = {
        'beach': '#0EA5E9', 'beaches': '#0EA5E9',
        'waterfalls': '#06B6D4', 'waterfall': '#06B6D4',
        'nature park': '#10B981', 'forest': '#059669',
        'cultural heritage': '#F59E0B', 'historical': '#D97706',
        'museum': '#8B5CF6', 'religious': '#EC4899',
        'farm': '#84CC16', 'eco-tourism': '#10B981',
        'cold spring': '#06B6D4', 'hot spring': '#EF4444', 'resort': '#6366F1'
    };
    return colors[cat] || '#3B82F6';
}

export function initMap(spotsData, municipalData) {
    if (!document.getElementById('touristMap')) return;

    // Only show approved spots on the map
    const approvedSpots = spotsData.filter(s => s.status === 'approved');

    let bounds;
    if (municipalData && municipalData.length > 0) {
        bounds = L.latLngBounds(municipalData.map(m => [m.latitude, m.longitude])).pad(0.08);
    } else {
        bounds = L.latLngBounds([[16.2, 120.2], [16.8, 120.5]]);
    }

    if (map) {
        // Clear all layers on active map
        if (markerCluster) {
            markerCluster.clearLayers();
        }
        // Remove non-tile layers
        map.eachLayer(layer => {
            if (layer !== mapLayers.street && layer !== mapLayers.satellite) {
                map.removeLayer(layer);
            }
        });
    } else {
        map = L.map('touristMap', { minZoom: 10, maxBoundsViscosity: 1.0 });
        mapLayers.street.addTo(map);
    }

    // Save map instance on the DOM element for the SPA router
    document.getElementById('touristMap')._leaflet_map = map;

    map.fitBounds(bounds);
    markerCluster = L.featureGroup();
    map.addLayer(markerCluster);

    // Function to render spot markers for a specific municipality
    function showMunicipalitySpots(muniName) {
        markerCluster.clearLayers();
        const spots = approvedSpots.filter(s =>
            s.latitude && s.longitude &&
            s.municipality_name &&
            s.municipality_name.toLowerCase().trim() === muniName.toLowerCase().trim()
        );

        spots.forEach(s => {
            const iconColor = getCategoryColor(s.category);
            const icon = L.divIcon({
                className: '',
                html: `<div style="background:${iconColor};width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;border:2px solid white;box-shadow:0 2px 6px rgba(0,0,0,.3);"><i class="fas fa-${getCategoryIcon(s.category)}" style="font-size:13px;"></i></div>`,
                iconSize: [28, 28],
                iconAnchor: [14, 28]
            });
            L.marker([s.latitude, s.longitude], { icon })
                .bindPopup(`<strong>${s.name}</strong><br><small>${s.category}</small>`)
                .addTo(markerCluster);
        });
    }

    // Municipality markers removed as requested

    // Add a custom button to reset view to the whole La Union province
    if (!map.resetControlAdded) {
        const ResetControl = L.Control.extend({
            options: { position: 'topleft' },
            onAdd: function () {
                const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-custom-control');
                container.innerHTML = `
                    <button title="Reset to La Union Province" style="background: white; border: none; width: 34px; height: 34px; border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 1px 5px rgba(0,0,0,0.4); transition: background-color 0.2s;">
                        <i class="fas fa-globe-asia" style="color: #3B82F6; font-size: 16px;"></i>
                    </button>
                `;
                container.onclick = function (e) {
                    e.stopPropagation();
                    map.fitBounds(bounds);
                    if (markerCluster) {
                        markerCluster.clearLayers();
                    }
                };
                return container;
            }
        });
        map.addControl(new ResetControl());
        map.resetControlAdded = true;
    }

    setTimeout(() => map.invalidateSize(), 300);
}

// ── Map Layer Toggle
export function setupMapLayerToggle() {
    document.querySelectorAll('.map-tab').forEach(tab => {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.map-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            Object.values(mapLayers).forEach(l => {
                if (map.hasLayer(l)) map.removeLayer(l);
            });
            mapLayers[this.dataset.view].addTo(map);
        });
    });
}

// ════════════════════════════════════════════════════════════════════════════════
// FILTERING LOGIC
// ════════════════════════════════════════════════════════════════════════════════

export function filterSpots(searchValue = '', municipalityValue = '', selectedCats = [], statusValue = '') {
    let visibleCount = 0;
    const mappedStatus = statusReverseMap[statusValue] || statusValue;

    // Helper: does the spot's category field match any of the selected categories?
    // Handles both single values ("Beach") and comma-separated ("Beach,Mountain").
    function matchesCat(cardCat) {
        if (!selectedCats || selectedCats.length === 0) return true;
        const spotCats = (cardCat || '').split(',').map(s => s.trim());
        return selectedCats.some(fc => spotCats.includes(fc));
    }

    // Filter cards
    document.querySelectorAll('#cardsView .spot-card').forEach(card => {
        const nameMatch = !searchValue || card.dataset.name.includes(searchValue.toLowerCase());
        const muniMatch = !municipalityValue || card.dataset.municipality === municipalityValue;
        const catMatch = matchesCat(card.dataset.category);
        const statusMatch = !statusValue || card.dataset.status === mappedStatus;

        const show = nameMatch && muniMatch && catMatch && statusMatch;
        card.style.display = show ? 'block' : 'none';
        if (show) visibleCount++;
    });

    // Filter table
    document.querySelectorAll('#tableView tbody tr').forEach(row => {
        const nameMatch = !searchValue || row.dataset.name.includes(searchValue.toLowerCase());
        const muniMatch = !municipalityValue || row.dataset.municipality === municipalityValue;
        const catMatch = matchesCat(row.dataset.category);
        const statusMatch = !statusValue || row.dataset.status === mappedStatus;

        const show = nameMatch && muniMatch && catMatch && statusMatch;
        row.style.display = show ? '' : 'none';
    });

    // Update count
    const countEl = document.getElementById('spotCount');
    if (countEl) countEl.textContent = visibleCount;

    return visibleCount;
}

// ── Dropdown Toggle
export function toggleDropdown(menuId) {
    const menu = document.getElementById(menuId);
    const isOpen = menu.style.display === 'block';
    document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none');
    if (!isOpen) menu.style.display = 'block';
}

// ── View Toggle (Cards/Table)
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

// ── Filter Event Listeners
export function setupFilterListeners() {
    const applyFilters = () => {
        const searchValue = document.getElementById('searchInput')?.value || '';
        const municipalityValue = document.getElementById('filterMunicipality')?.value || '';
        const selectedCats = Array.from(document.querySelectorAll('.cat-filter-chk:checked')).map(c => c.value);
        const statusValue = document.getElementById('filterStatus')?.value || '';

        const sortValue = document.getElementById('sortSpots')?.value || '';
        if (sortValue) {
            window.touristSpotsData.sort((a, b) => {
                const pA = a.points !== undefined ? a.points : 0;
                const pB = b.points !== undefined ? b.points : 0;
                if (sortValue === 'points_desc') return pB - pA;
                if (sortValue === 'points_asc') return pA - pB;
                return 0;
            });
            if (typeof renderCardsGrid === 'function') renderCardsGrid(window.touristSpotsData);
            if (typeof renderTableRows === 'function') renderTableRows(window.touristSpotsData);
        } else {
            if (typeof sortSpotsPendingFirst === 'function') sortSpotsPendingFirst(window.touristSpotsData);
            if (typeof renderCardsGrid === 'function') renderCardsGrid(window.touristSpotsData);
            if (typeof renderTableRows === 'function') renderTableRows(window.touristSpotsData);
        }

        filterSpots(searchValue, municipalityValue, selectedCats, statusValue);
    };

    document.getElementById('searchInput')?.addEventListener('input', applyFilters);
    document.getElementById('filterMunicipality')?.addEventListener('change', applyFilters);
    document.getElementById('filterStatus')?.addEventListener('change', applyFilters);
    document.getElementById('sortSpots')?.addEventListener('change', applyFilters);
    document.querySelectorAll('.cat-filter-chk').forEach(chk => chk.addEventListener('change', applyFilters));
}

// ── Dropdown Event Listeners
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
        if (!e.target.closest('.card-actions-dropdown') && !e.target.closest('.table-actions-dropdown')) {
            document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none');
        }
    });
}

// GALLERY SLIDER & LIGHTBOX ENGINE
let activeLightboxKeyHandler = null;
let activeModalKeyHandler = null;

function renderSpotGallery(rightPanelEl, spot) {
    if (!rightPanelEl) return;

    let images = [];
    if (Array.isArray(spot.images) && spot.images.length > 0) {
        images = spot.images.map(img => typeof img === 'string' ? img : (img.photo_url || img.url)).filter(Boolean);
    }
    if (images.length === 0 && spot.photo_url) {
        images.push(spot.photo_url);
    }
    images = Array.from(new Set(images));

    if (images.length === 0) {
        rightPanelEl.innerHTML = `
            <div class="spot-gallery-empty">
                <i class="fas fa-image"></i>
                <p>No Image Available.</p>
            </div>
        `;
        return;
    }

    let currentIndex = 0;
    const hasMultiple = images.length > 1;

    rightPanelEl.innerHTML = `
        <div class="spot-gallery-container ${hasMultiple ? 'has-thumbs' : ''}">
            <div class="spot-gallery-main">
                <span class="spot-gallery-badge">
                    <i class="fas fa-camera"></i> <span class="spot-gallery-idx">1</span> / ${images.length}
                </span>
                <button type="button" class="spot-gallery-fullscreen-btn" title="View Fullscreen">
                    <i class="fas fa-expand"></i>
                </button>
                <img src="${escapeHtml(images[0])}" 
                     alt="${escapeHtml(spot.name || 'Tourist Spot')}" 
                     class="spot-gallery-main-img" 
                     onerror="this.src='../../assets/images/default-spot.jpg';">

                ${hasMultiple ? `
                    <button type="button" class="spot-gallery-arrow prev" aria-label="Previous image">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" class="spot-gallery-arrow next" aria-label="Next image">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                ` : ''}
            </div>

            ${hasMultiple ? `
                <div class="spot-gallery-thumbs">
                    ${images.map((img, idx) => `
                        <div class="spot-thumb-item ${idx === 0 ? 'active' : ''}" data-index="${idx}">
                            <img src="${escapeHtml(img)}" alt="Thumbnail ${idx + 1}" onerror="this.src='../../assets/images/default-spot.jpg';">
                        </div>
                    `).join('')}
                </div>
            ` : ''}
        </div>
    `;

    const mainImgEl = rightPanelEl.querySelector('.spot-gallery-main-img');
    const indexEl = rightPanelEl.querySelector('.spot-gallery-idx');
    const thumbsContainer = rightPanelEl.querySelector('.spot-gallery-thumbs');
    const prevBtn = rightPanelEl.querySelector('.spot-gallery-arrow.prev');
    const nextBtn = rightPanelEl.querySelector('.spot-gallery-arrow.next');
    const expandBtn = rightPanelEl.querySelector('.spot-gallery-fullscreen-btn');

    function updateGallery(newIndex) {
        if (newIndex < 0) newIndex = images.length - 1;
        if (newIndex >= images.length) newIndex = 0;
        currentIndex = newIndex;

        if (mainImgEl) {
            mainImgEl.classList.add('fade-out');
            setTimeout(() => {
                mainImgEl.src = images[currentIndex];
                mainImgEl.classList.remove('fade-out');
            }, 120);
        }

        if (indexEl) {
            indexEl.textContent = currentIndex + 1;
        }

        if (thumbsContainer) {
            const thumbItems = thumbsContainer.querySelectorAll('.spot-thumb-item');
            thumbItems.forEach((t, i) => {
                if (i === currentIndex) {
                    t.classList.add('active');
                    t.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
                } else {
                    t.classList.remove('active');
                }
            });
        }
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            updateGallery(currentIndex - 1);
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            updateGallery(currentIndex + 1);
        });
    }

    if (thumbsContainer) {
        thumbsContainer.addEventListener('click', (e) => {
            const thumb = e.target.closest('.spot-thumb-item');
            if (thumb) {
                const idx = parseInt(thumb.dataset.index, 10);
                if (!isNaN(idx)) updateGallery(idx);
            }
        });
    }

    const openLightbox = () => showSpotLightbox(images, currentIndex, spot.name);
    if (mainImgEl) mainImgEl.addEventListener('click', openLightbox);
    if (expandBtn) expandBtn.addEventListener('click', openLightbox);

    setupGalleryKeyboardNav(images, () => currentIndex, (idx) => updateGallery(idx));
}

function showSpotLightbox(images, initialIndex = 0, title = '') {
    if (!images || images.length === 0) return;

    let lightbox = document.getElementById('spotLightbox');
    if (!lightbox) {
        lightbox = document.createElement('div');
        lightbox.id = 'spotLightbox';
        lightbox.className = 'spot-lightbox-backdrop';
        document.body.appendChild(lightbox);
    }

    let currentIndex = initialIndex;

    const renderLightbox = () => {
        const hasMultiple = images.length > 1;
        lightbox.innerHTML = `
            <button type="button" class="spot-lightbox-close" id="spotLightboxClose" title="Close (Esc)">
                <i class="fas fa-times"></i>
            </button>
            <div class="spot-lightbox-content">
                <img src="${escapeHtml(images[currentIndex])}" alt="${escapeHtml(title || 'Spot View')}" class="spot-lightbox-img" id="spotLightboxImg">
            </div>
            ${hasMultiple ? `
                <button type="button" class="spot-lightbox-arrow prev" id="spotLightboxPrev">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" class="spot-lightbox-arrow next" id="spotLightboxNext">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <div class="spot-lightbox-counter">${currentIndex + 1} / ${images.length}</div>
            ` : ''}
        `;

        lightbox.classList.add('active');

        const closeBtn = lightbox.querySelector('#spotLightboxClose');
        const imgEl = lightbox.querySelector('#spotLightboxImg');
        const prevBtn = lightbox.querySelector('#spotLightboxPrev');
        const nextBtn = lightbox.querySelector('#spotLightboxNext');

        const closeLightbox = () => {
            lightbox.classList.remove('active');
            if (activeLightboxKeyHandler) {
                document.removeEventListener('keydown', activeLightboxKeyHandler);
                activeLightboxKeyHandler = null;
            }
        };

        if (closeBtn) closeBtn.addEventListener('click', closeLightbox);

        lightbox.onclick = (e) => {
            if (e.target === lightbox || e.target.classList.contains('spot-lightbox-content')) {
                closeLightbox();
            }
        };

        if (imgEl) {
            imgEl.addEventListener('click', (e) => {
                e.stopPropagation();
                imgEl.classList.toggle('zoomed');
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                currentIndex = (currentIndex - 1 + images.length) % images.length;
                renderLightbox();
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                currentIndex = (currentIndex + 1) % images.length;
                renderLightbox();
            });
        }

        if (activeLightboxKeyHandler) {
            document.removeEventListener('keydown', activeLightboxKeyHandler);
        }
        activeLightboxKeyHandler = (e) => {
            if (!lightbox.classList.contains('active')) return;
            if (e.key === 'Escape') {
                closeLightbox();
            } else if (e.key === 'ArrowLeft' && images.length > 1) {
                currentIndex = (currentIndex - 1 + images.length) % images.length;
                renderLightbox();
            } else if (e.key === 'ArrowRight' && images.length > 1) {
                currentIndex = (currentIndex + 1) % images.length;
                renderLightbox();
            }
        };
        document.addEventListener('keydown', activeLightboxKeyHandler);
    };

    renderLightbox();
}

function setupGalleryKeyboardNav(images, getCurrentIndex, updateGalleryFn) {
    if (activeModalKeyHandler) {
        document.removeEventListener('keydown', activeModalKeyHandler);
    }

    activeModalKeyHandler = (e) => {
        const spotModal = document.getElementById('spotModal');
        const lightbox = document.getElementById('spotLightbox');

        if (lightbox && lightbox.classList.contains('active')) return;

        if (spotModal && spotModal.classList.contains('active')) {
            if (e.key === 'ArrowLeft' && images.length > 1) {
                updateGalleryFn(getCurrentIndex() - 1);
            } else if (e.key === 'ArrowRight' && images.length > 1) {
                updateGalleryFn(getCurrentIndex() + 1);
            } else if (e.key === 'Escape') {
                if (typeof window.closeSpotModal === 'function') {
                    window.closeSpotModal();
                } else {
                    spotModal.classList.remove('active');
                }
            }
        }
    };

    document.addEventListener('keydown', activeModalKeyHandler);
}

// MODAL FUNCTIONS

window.openSpotModal = async function openSpotModal(spotId) {
    const modal = document.getElementById('spotModal');
    if (!modal) return;

    modal.classList.add('active');
    const modalTitleEl = document.getElementById('modalTitle');
    if (modalTitleEl) modalTitleEl.textContent = 'Loading...';

    document.getElementById('modalBody').innerHTML = '<div class="spot-modal-loading-box"><i class="fas fa-spinner fa-spin"></i></div>';

    try {
        // First try to find spot in local data
        let spot = window.touristSpotsAll?.find(s => s.id == spotId);
        if (!spot) {
            // If not found, fetch from API
            spot = await window.getSpot(spotId);
        }
        if (modalTitleEl) modalTitleEl.textContent = spot.name;
        const classificationStyle = spot.classification_status ? getClassificationStyle(spot.classification_status) : null;

        const formattedDate = new Date(spot.created_at).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });

        // Format time
        function formatTime(timeStr) {
            if (!timeStr) return 'N/A';
            const [hours, minutes] = timeStr.split(':').map(Number);
            const period = hours >= 12 ? 'PM' : 'AM';
            const displayHours = hours % 12 || 12;
            return `${displayHours}:${String(minutes).padStart(2, '0')} ${period}`;
        }

        document.getElementById('modalBody').innerHTML = `
            <div class="spot-modal-split-container">
                <!-- Left Panel (50%): Details -->
                <div class="spot-modal-left-panel">
                    <div>
                        <h2 class="spot-modal-title">${escapeHtml(spot.name)}</h2>
                        <div class="spot-modal-badges">
                            <span class="spot-modal-badge muni-badge"><i class="fas fa-map-marker-alt"></i> ${escapeHtml(spot.municipality_name || 'La Union')}, La Union</span>
                            ${classificationStyle ? `<span class="spot-modal-badge" style="background:${classificationStyle.bg};color:${classificationStyle.text};font-weight:700;">${classificationStyle.label}</span>` : ''}
                            ${(spot.status && spot.status !== 'approved') ? `<span class="spot-modal-badge" style="background:${spot.status === 'approved' ? '#10B981' : spot.status === 'pending' ? '#F59E0B' : '#DC2626'};color:#FFFFFF;font-weight:700;">${spot.status === 'approved' ? 'Approved' : spot.status === 'pending' ? 'Pending' : 'Rejected'}</span>` : ''}
                            ${spot.is_maintenance ? `<span class="spot-modal-badge" style="background:#F59E0B;color:#92400E;font-weight:700;"><i class="fas fa-exclamation-triangle"></i> Under Maintenance</span>` : ''}
                        </div>
                    </div>

                    ${spot.status === 'rejected' && spot.rejection_reason ? `
                        <div class="spot-rejection-box">
                            <div class="rejection-title"><i class="fas fa-exclamation-circle"></i> Rejection Reason</div>
                            <p class="rejection-text">${escapeHtml(spot.rejection_reason)}</p>
                        </div>
                    ` : ''}

                    <div class="spot-details-grid">
                        <div class="spot-detail-card">
                            <div class="detail-label">Category</div>
                            <div class="category-badges">
                                ${(spot.category || 'Other').split(',').map(c => c.trim()).filter(Boolean).map(c =>
            `<span class="cat-pill">${escapeHtml(c)}</span>`
        ).join('')}
                            </div>
                        </div>
                        <div class="spot-detail-card">
                            <div class="detail-label">Fees</div>
                            <div class="detail-val">${formatFeesDisplay(spot)}</div>
                        </div>
                        <div class="spot-detail-card points-card">
                            <div class="detail-label points-label">⭐ Points</div>
                            <div class="points-val">${spot.points !== undefined ? spot.points : 0} Points</div>
                        </div>
                        <div class="spot-detail-card">
                            <div class="detail-label">Opening Time</div>
                            <div class="detail-val">${formatTime(spot.opening_time)}</div>
                        </div>
                        <div class="spot-detail-card">
                            <div class="detail-label">Closing Time</div>
                            <div class="detail-val">${formatTime(spot.closing_time)}</div>
                        </div>
                        ${spot.latitude ? `
                            <div class="spot-detail-card">
                                <div class="detail-label">Latitude</div>
                                <div class="detail-val"><i class="fas fa-map-pin"></i> ${parseFloat(spot.latitude).toFixed(6)}</div>
                            </div>
                        ` : ''}
                        ${spot.longitude ? `
                            <div class="spot-detail-card">
                                <div class="detail-label">Longitude</div>
                                <div class="detail-val"><i class="fas fa-map-pin"></i> ${parseFloat(spot.longitude).toFixed(6)}</div>
                            </div>
                        ` : ''}
                        <div class="spot-detail-card">
                            <div class="detail-label">Submitted</div>
                            <div class="detail-val">${formattedDate}</div>
                        </div>
                        ${spot.created_by || spot.creator ? `
                            <div class="spot-detail-card added-by-card">
                                <div class="detail-label added-by-label"><i class="fas fa-user-plus"></i> Added Tourist Spot By</div>
                                <div class="added-by-val">
                                    ${(spot.creator_role || '').toUpperCase() || 'LUPTO'}
                                    <span class="added-by-name">(${escapeHtml(spot.creator?.name || 'N/A')})</span>
                                </div>
                            </div>
                        ` : ''}
                        ${spot.status === 'approved' && (spot.approver || spot.approved_by) ? `
                            <div class="spot-detail-card approved-by-card">
                                <div class="detail-label approved-by-label"><i class="fas fa-user-check"></i> Approved By</div>
                                <div class="approved-by-val">${escapeHtml(spot.approver?.name || ('User #' + spot.approved_by))}</div>
                            </div>
                            <div class="spot-detail-card approved-by-card">
                                <div class="detail-label approved-by-label"><i class="fas fa-calendar-check"></i> Approved At</div>
                                <div class="approved-by-val">${spot.approved_at ? new Date(spot.approved_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A'}</div>
                            </div>
                        ` : ''}
                    </div>

                    <div class="spot-description-box">
                        <div class="detail-label">Description</div>
                        <p class="description-text">${escapeHtml(spot.description) || 'No description provided.'}</p>
                    </div>
                </div>

                <!-- Right Panel (50%): Interactive Image Gallery -->
                <div class="spot-modal-right-panel" id="spotModalRightPanel"></div>
            </div>
        `;

        renderSpotGallery(document.getElementById('spotModalRightPanel'), spot);
    } catch (err) {
        console.error(err);
        document.getElementById('modalBody').innerHTML = '<p style="color:#DC2626;padding:20px;">Failed to load spot details.</p>';
        if (typeof showToast === 'function') showToast('Failed to load spot details', 'danger');
    }
};

export function closeSpotModal() {
    const modal = document.getElementById('spotModal');
    if (modal) modal.classList.remove('active');
}

export function setupModalListeners() {
    document.getElementById('closeSpotModal')?.addEventListener('click', closeSpotModal);

    document.getElementById('spotModal')?.addEventListener('click', e => {
        if (e.target.id === 'spotModal') closeSpotModal();
    });
}

// ── Utility: HTML Escape
export function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ════════════════════════════════════════════════════════════════════════════════
// IMAGE HANDLING
// ════════════════════════════════════════════════════════════════════════════════

function getUploadAreaEl() {
    return document.getElementById('imageUploadArea');
}

function isValidImageFile(file) {
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
    if (allowedTypes.includes(file.type)) return true;
    const ext = (file.name.split('.').pop() || '').toLowerCase();
    return ['jpg', 'jpeg', 'png'].includes(ext);
}

window.handleImageSelect = async function (e) {
    void 0;
    const files = Array.from(e.target.files);
    e.stopPropagation();
    // Clear input to allow selecting the same files again
    e.target.value = '';
    await processImageFiles(files);
};

window.handleImageDrop = async function (e) {
    e.preventDefault();
    e.stopPropagation();
    const area = getUploadAreaEl();
    if (area) {
        area.style.borderColor = '#D1D5DB';
        area.style.background = '#F9FAFB';
    }
    const files = Array.from(e.dataTransfer.files);
    void 0;
    await processImageFiles(files);
};

window.handleDragOver = function (e) {
    e.preventDefault();
    e.stopPropagation();
    const area = getUploadAreaEl();
    if (area) {
        area.style.borderColor = '#2563EB';
        area.style.background = '#EEF2FF';
    }
};

window.handleDragLeave = function (e) {
    e.preventDefault();
    e.stopPropagation();
    const area = getUploadAreaEl();
    if (area) {
        area.style.borderColor = '#D1D5DB';
        area.style.background = '#F9FAFB';
    }
};

async function processImageFiles(files) {
    const maxAllowed = 3;
    const currentCount = uploadedImages.length;
    if (currentCount >= maxAllowed) {
        showToast('Maximum of 3 images allowed per tourist spot.', 'danger');
        return;
    }

    let availableSlots = maxAllowed - currentCount;
    if (files.length > availableSlots) {
        showToast(`Maximum of 3 images allowed. Only the first ${availableSlots} image(s) will be added.`, 'warning');
        files = Array.from(files).slice(0, availableSlots);
    }

    // Filter valid files first
    const validFiles = [];
    for (const file of files) {
        if (!isValidImageFile(file)) {
            showToast(`Invalid file type: ${file.name}. Allowed: JPEG, PNG`, 'danger');
            continue;
        }
        if (file.size > 10 * 1024 * 1024) { // Increased limit since we compress
            showToast(`File too large: ${file.name} (max 10MB)`, 'danger');
            continue;
        }
        validFiles.push(file);
    }

    if (validFiles.length === 0) return;

    showToast(`Uploading ${validFiles.length} image(s)...`, 'info');

    const pendingUploads = [];

    // Add files immediately with preview and loading state
    for (const file of validFiles) {
        const previewUrl = await getFilePreviewUrl(file);
        const tempId = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
        uploadedImages.push({
            photo_url: previewUrl,
            filename: file.name,
            isLoading: true,
            id: tempId
        });
        pendingUploads.push({ file, tempId });
    }
    renderImagePreviews();

    try {
        // Upload all images in parallel
        const results = await Promise.allSettled(
            pendingUploads.map(async ({ file, tempId }) => {
                try {
                    const result = await uploadImage(file);
                    return { file, result, tempId, success: true };
                } catch (err) {
                    console.error('[upload] Failed:', file.name, err);
                    return { file, error: err, tempId, success: false };
                }
            })
        );

        // Process results
        let successCount = 0;
        for (const settled of results) {
            if (settled.status !== 'fulfilled') continue;
            const item = settled.value;
            const index = uploadedImages.findIndex(img => img.id === item.tempId);
            if (index === -1) continue;

            if (item.success) {
                uploadedImages[index] = {
                    photo_url: item.result.photo_url,
                    filename: item.result.filename || item.file.name
                };
                successCount++;
            } else {
                uploadedImages.splice(index, 1);
                const filename = item.file?.name || 'file';
                showToast(`Failed to upload ${filename}: ${item.error?.message || 'Unknown error'}`, 'danger');
            }
        }

        renderImagePreviews();
        if (successCount > 0) {
            showToast(`${successCount} image(s) uploaded successfully`, 'success');
        }
    } finally {
        // Safety net: remove any previews stuck in loading state
        const stuckCount = uploadedImages.filter(img => img.isLoading).length;
        if (stuckCount > 0) {
            uploadedImages = uploadedImages.filter(img => !img.isLoading);
            renderImagePreviews();
            showToast('Some uploads did not complete. Please try again.', 'danger');
        }
    }
}

function renderImagePreviews() {
    const container = document.getElementById('imagePreviews');
    if (!container) return;

    container.innerHTML = uploadedImages.map((img, index) => `
        <div style="position:relative;border-radius:8px;overflow:hidden;width:100px;height:100px;border: 2px solid #E5E7EB;">
            <img src="${img.photo_url}" alt="Preview" style="width:100%;height:100%;object-fit:cover;${img.isLoading ? 'filter: brightness(0.7);' : ''}" onerror="this.parentElement.innerHTML='<div style=\\'width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#F3F4F6;color:#9CA3AF;flex-direction:column;\\'><i class=\\'fas fa-exclamation\\'></i><span style=\\'font-size:10px;\\'> Error</span></div>'">
            ${img.isLoading ? `
                <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.3);z-index:10;">
                    <i class="fas fa-spinner fa-spin" style="font-size:32px;color:white;"></i>
                </div>
            ` : ''}
            <button type="button" onclick="removeImage(${index})" style="position:absolute;top:4px;right:4px;background:#DC2626;color:white;border:none;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:14px;padding:0;${img.isLoading ? 'display:none;' : ''}">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `).join('');
}

window.removeImage = function (index) {
    uploadedImages.splice(index, 1);
    renderImagePreviews();
    showToast('Image removed', 'info');
};

// ════════════════════════════════════════════════════════════════════════════════
// FORM HELPERS - BARANGAYS, CATEGORIES, ETC
// ════════════════════════════════════════════════════════════════════════════════

// Municipality coordinates (approximate for La Union)
const municipalityCoordinates = {
    1: { lat: 16.3147, lng: 119.9788 }, // Bacnotan
    2: { lat: 16.6167, lng: 120.3167 }, // San Fernando City
    3: { lat: 16.5500, lng: 120.3333 }, // Bauang
    4: { lat: 16.4833, lng: 120.4167 }, // Naguilian
    5: { lat: 16.3833, lng: 120.2833 }, // Caba
    6: { lat: 16.2833, lng: 120.4833 }, // Tubao
    7: { lat: 16.4167, lng: 120.1000 }, // Balaoan
    8: { lat: 16.3500, lng: 120.5000 }, // Aringay
    9: { lat: 16.4500, lng: 120.5000 }, // Santo Tomas
    10: { lat: 16.3000, lng: 120.5500 }, // Rosario
    11: { lat: 16.2000, lng: 120.4500 }, // Pugo
    12: { lat: 16.5833, lng: 120.6000 }, // Tuba
    13: { lat: 16.6500, lng: 120.5500 }, // Sablan
    14: { lat: 16.5833, lng: 120.3833 }, // Bagulin
    15: { lat: 16.6500, lng: 120.2500 }, // Sudipen
    16: { lat: 16.6833, lng: 120.3500 }, // San Gabriel
    17: { lat: 16.7167, lng: 120.4167 }, // San Juan
    18: { lat: 16.2000, lng: 120.5000 }, // Agoo
    19: { lat: 16.2500, lng: 120.5833 }, // Santa Cruz
    20: { lat: 16.2300, lng: 120.4200 }  // Burgos
};

// Helper to generate barangay entries with default municipality coordinates
function createBarangayList(names, muniId) {
    const coords = municipalityCoordinates[muniId];
    return names.map(name => ({ name, lat: coords.lat, lng: coords.lng }));
}

// Barangays with coordinates (using municipality coordinates as default)
const barangaysByMunicipality = {
    1: createBarangayList(['Allangigan', 'Aludaid', 'Bacsayan', 'Balballosa', 'Bambanay', 'Bugbugcao', 'Caarusipan', 'Cabaroan', 'Cabugnayan', 'Cacapian', 'Caculangan', 'Casilagan', 'Catdongan', 'Dangdangla', 'Dasay', 'Dinanum', 'Duplas', 'Guinguinabang', 'Ili Norte (Poblacion)', 'Ili Sur (Poblacion)', 'Legleg', 'Lubing', 'Nadsaag', 'Nagsabaran', 'Naguirangan', 'Naguituban', 'Nagyubuyuban', 'Oaquing', 'Pacpacac', 'Pagdildilan', 'Panicsican', 'Quidem', 'Santa Rosa', 'Saracat', 'Santo Rosario', 'Taboc', 'Talogtog', 'Urbiztondo'], 1),
    2: createBarangayList(['Abut', 'Apaleng', 'Bacsil', 'Baraoas', 'Bato', 'Biday', 'Bangbangolan', 'Bangcusay', 'Barangay I (Poblacion)', 'Barangay II (Poblacion)', 'Barangay III (Poblacion)', 'Barangay IV (Poblacion)', 'Birunget', 'Bungro', 'Cabarsican', 'Cadaclan', 'Calabugao', 'Camansi', 'Canaoay', 'Carlatan', 'Cabaroan (Negro)', 'Cadapli', 'Dallangayan Este', 'Dallangayan Oeste', 'Dalumpinas Este', 'Dalumpinas Oeste', 'Ilocanos Norte', 'Ilocanos Sur', 'Langcuas', 'Lingsat', 'Madayegdeg', 'Mameltac', 'Masicong', 'Narra Este', 'Narra Oeste', 'Namtutan', 'Pagdaldagan', 'Pagdaraoan', 'Pao Norte', 'Pao Sur', 'Pacpaco', 'Pian', 'Poro', 'Puspus', 'San Agustin', 'San Francisco', 'Sagayad', 'Santiago Norte', 'Santiago Sur', 'San Vicente', 'Saoay', 'Siboan-Otong', 'Tanquigan', 'Tanqui', 'Sevilla'], 2),
    3: createBarangayList(['Acao', 'Bagbag', 'Ballay', 'Baccuit Norte', 'Baccuit Sur', 'Boy-utan', 'Bucayab', 'Cabalayangan', 'Cabisilan', 'Casilagan', 'Central East (Poblacion)', 'Central West (Poblacion)', 'Dili', 'Disso-or', 'Guerrero', 'Jimenez', 'Jimenez West', 'Lower San Agustin', 'Nagrebcan', 'Pagdalagan Sur', 'Paliguasan', 'Palingulang', 'Parian Este', 'Parian Oeste', 'Paringao', 'Payocpoc Norte Este', 'Payocpoc Norte Oeste', 'Payocpoc Sur', 'Pilar', 'Pottot', 'Pudoc', 'Pugo', 'Quinavite', 'Santa Monica', 'Santiago', 'Taberna', 'Upper San Agustin', 'Urayong'], 3),
    4: createBarangayList(['Ambitacay', 'Balawarte', 'Capas', 'Consolacion (Poblacion)', 'San Agustin East', 'San Agustin Norte', 'San Agustin Sur', 'San Antonino', 'San Antonio', 'San Francisco', 'San Isidro', 'San Java Norte', 'San Juan', 'San Jose Norte', 'San Jose Sur', 'San Julian Central', 'San Julian East', 'San Julian Norte', 'San Julian West', 'San Manuel Norte', 'San Manuel Sur', 'San Marcos', 'San Miguel', 'San Nicolas Central (Poblacion)', 'San Nicolas East', 'San Nicolas Norte (Poblacion)', 'San Nicolas Sur (Poblacion)', 'San Nicolas West', 'San Pedro', 'San Roque East', 'San Roque West', 'San Vicente Norte', 'San Vicente Sur', 'Santa Ana', 'Santa Barbara (Poblacion)', 'Santa Fe', 'Santa Maria', 'Santa Monica', 'Santa Rita (Nalinac)', 'Santa Rita East', 'Santa Rita Norte', 'Santa Rita Sur', 'Santa Rita West', 'Nazareno', 'Macalva Central', 'Macalva Norte', 'Macalva Sur', 'Purok'], 4),
    5: createBarangayList(['Alcala (Poblacion)', 'Ayaoan', 'Barangobong', 'Barrientos', 'Bungro', 'Buselbusel', 'Cabalitocan', 'Cantoria No. 1', 'Cantoria No. 2', 'Cantoria No. 3', 'Cantoria No. 4', 'Carisquis', 'Darigayos', 'Magallanes (Poblacion)', 'Magsiping', 'Mamay', 'Nalvo Norte', 'Nalvo Sur', 'Nagrebcan', 'Napaset', 'Oaqui No. 1', 'Oaqui No. 2', 'Oaqui No. 3', 'Oaqui No. 4', 'Pila', 'Pitpitac', 'Rimos No. 1', 'Rimos No. 2', 'Rimos No. 3', 'Rimos No. 4', 'Rimos No. 5', 'Rissing', 'Salcedo (Poblacion)', 'Santo Domingo Norte', 'Santo Domingo Sur', 'Sucoc Norte', 'Sucoc Sur', 'Suyo', 'Tallaoen', 'Victoria (Poblacion)'], 5),
    6: createBarangayList(['Amontoc', 'Apayao', 'Bayabas', 'Balbalayang', 'Bucao', 'Bumbuneg', 'Daking', 'Lacong', 'Lipay Este', 'Lipay Norte', 'Lipay Proper', 'Lipay Sur', 'Lon-oy', 'Poblacion', 'Polipol'], 6),
    7: createBarangayList(['Almeida', 'Antonino', 'Apatut', 'Ar-arampang', 'Baracbac Este', 'Baracbac Oeste', 'Bet-ang', 'Bulbulala', 'Bungol', 'Butubut Este', 'Butubut Norte', 'Butubut Oeste', 'Butubut Sur', 'Cabuaan Oeste (Poblacion)', 'Calliat', 'Camiling', 'Calumbaya', 'Calungbuyan', 'Dr. Camilo Osias Poblacion (Cabuaan Este)', 'Guinaburan', 'Nagsabaran Norte', 'Nagsabaran Sur', 'Nalasin', 'Napaset', 'Pagbennecan', 'Pagleddegan', 'Paraoir', 'Patpata', 'Sablut', 'San Pablo', 'Sinapangan Norte', 'Sinapangan Sur', 'Tallipugo'], 7),
    8: createBarangayList(['Alaska', 'Basca', 'Dulao', 'Gallano', 'Macabato', 'Manga', 'Pangao-aoan East', 'Pangao-aoan West', 'Poblacion', 'Samara', 'San Antonio', 'San Benito Norte', 'San Benito Sur', 'San Eugenio', 'San Juan East', 'San Juan West', 'San Simon East', 'San Simon West', 'Santa Cecilia', 'Santa Lucia', 'Santo Rosario East', 'Santo Rosario West', 'Santa Rita East', 'Santa Rita West'], 8),
    9: createBarangayList(['Alipang', 'Amlang', 'Ambangonan', 'Bacani', 'Bangar', 'Bani', 'Benteng-Sapilang', 'Camp One', 'Carunuan East', 'Carunuan West', 'Casilagan', 'Cataguingtingan', 'Concepcion', 'Damortis', 'Gumot-Nagcolaran', 'Inabaan Norte', 'Inabaan Sur', 'Marcos', 'Nagtagaan', 'Nancamotian', 'Parasapas', 'Poblacion East', 'Poblacion West', 'San Jose', 'Subusub', 'Tabtabungao', 'Tay-ac', 'Tanglag', 'Udiao', 'Vila'], 9),
    10: createBarangayList(['Agtipal', 'Arosip', 'Bacqui', 'Bacsil', 'Bagutot', 'Ballogo', 'Baroro', 'Bitalag', 'Burayoc', 'Bussaoit', 'Cabaroan', 'Cabarsican', 'Cabugao', 'Calautit', 'Carcarmay', 'Casiaman', 'Santa Cruz', 'Galongen', 'Guinabang', 'Legleg', 'Lisqueb', 'Mabanengbeng 1st', 'Mabanengbeng 2nd', 'Maragayap', 'Nagatiran', 'Nangalisan', 'Narra', 'Nagsaraboan', 'Nagsimbaanan', 'Oya-oy', 'Paagan', 'Pagan', 'Pandan', 'Pang-Pang', 'Poblacion', 'Quirino', 'Raois', 'Sagapan', 'Salincob', 'San Martin', 'Santa Rita', 'Sapilang', 'Sayoan', 'Sipulo', 'Ubbog', 'Zaragosa'], 10),
    11: createBarangayList(['Al-alinao Norte', 'Al-alinao Sur', 'Aguioas', 'Ambaracao Norte', 'Ambaracao Sur', 'Angin', 'Baraoas Norte', 'Baraoas Sur', 'Bariquir', 'Bato', 'Balecbec', 'Bancagan', 'Bimmotobot', 'Dal-lipaoen', 'Daramuangan', 'Guesset', 'Gusing Norte', 'Gusing Sur', 'Imelda', 'Lioac Norte', 'Lioac Sur', 'Magungunay', 'Mamat-ing Norte', 'Mamat-ing Sur', 'Natividad (Poblacion)', 'Ortiz (Poblacion)', 'Ribsuan', 'San Antonio', 'San Isidro', 'Sili', 'Suguidan Norte', 'Suguidan Sur', 'Teddingan'], 11),
    12: createBarangayList(['Amallapay', 'Anduyan', 'Caoigue', 'Francia Sur', 'Francia West', 'Garcia', 'Gonzales', 'Halog East', 'Halog West', 'Leones East', 'Leones West', 'Linapew', 'Lloren', 'Magsaysay', 'Pideg', 'Poblacion', 'Rizal', 'Santa Teresa'], 12),
    13: createBarangayList(['Ambalite', 'Ambangonan', 'Cares', 'Cuenca', 'Duplas', 'Maoasoas Norte', 'Maoasoas Sur', 'Palina', 'Poblacion East', 'Poblacion West', 'Saytan', 'San Luis', 'Tavora East', 'Tavora Proper'], 13),
    14: createBarangayList(['Bautista', 'Gana', 'Juan Cartas', 'Las-ud', 'Liquicia', 'Poblacion Norte', 'Poblacion Sur', 'San Carlos', 'San Cornelio', 'San Fermin', 'San Gregorio', 'San Jose', 'Santiago Norte', 'Santiago Sur', 'Sobredillo', 'Urayong', 'Wenceslao'], 14),
    15: createBarangayList(['Ambitacay', 'Bail', 'Balaoc', 'Balsaan', 'Baybay', 'Cabaruan', 'Casilagan', 'Casantaan', 'Cupang', 'Damortis', 'Fernando', 'Linong', 'Lomboy', 'Malabago', 'Namboongan', 'Namonitan', 'Narvacan', 'Patac', 'Poblacion', 'Pongpong', 'Raois', 'Tococ', 'Tubod', 'Ubagan'], 15),
    16: createBarangayList(['Agdeppa', 'Alzate', 'Bangaoilan East', 'Bangaoilan West', 'Barraca', 'Central East No. 1 (Poblacion)', 'Central East No. 2 (Poblacion)', 'Central West No. 1 (Poblacion)', 'Central West No. 2 (Poblacion)', 'Central West No. 3 (Poblacion)', 'Consuegra', 'General Prim East', 'General Prim West', 'General Terrero', 'Luzong Norte', 'Luzong Sur', 'Maria Cristina East', 'Maria Cristina West', 'Mindoro', 'Nagsabaran', 'Nagsidorisan', 'Quintarong', 'Reyna Regente', 'Rissing', 'San Blas', 'San Cristobal', 'Sinapangan Norte', 'Sinapangan Sur', 'Ubbog'], 16),
    17: createBarangayList(['Agpay', 'Bilis', 'Caoayan', 'Dalacdac', 'Delles', 'Imelda', 'Libtong', 'Linuan', 'Lower Tumapoc', 'New Poblacion', 'Old Poblacion', 'Upper Tumapoc'], 17),
    18: createBarangayList(['Alibangsay', 'Baay', 'Cambaly', 'Cardiz', 'Dagup', 'Libbo', 'Suyo (Poblacion)', 'Tagudtud', 'Tio-angan', 'Wallayan'], 18),
    19: createBarangayList(['Corrooy', 'Lettac Norte', 'Lettac Sur', 'Mangaan', 'Paagan', 'Poblacion', 'Puguil', 'Ramot', 'Sasaba', 'Sapdaan', 'Tubaday'], 19),
    20: createBarangayList(['Bigbiga', 'Bulalaan', 'Castro', 'Duplas', 'Ipet', 'Ilocano', 'Maliclico', 'Namaltugan', 'Old Central', 'Poblacion', 'Porporiket', 'San Francisco Norte', 'San Francisco Sur', 'San Jose', 'Sengngat', 'Turod', 'Up-uplas'], 20)
};

window.onMunicipalityChange = function (muniId) {
    const select = document.getElementById('spotBarangay');
    if (!select) return;
    select.innerHTML = '<option value="">— Select Barangay —</option>';
    const barangays = barangaysByMunicipality[parseInt(muniId)] || [];
    barangays.forEach(b => {
        const opt = document.createElement('option');
        opt.value = b.name;
        opt.textContent = b.name;
        opt.dataset.lat = b.lat;
        opt.dataset.lng = b.lng;
        select.appendChild(opt);
    });

    const muniName = getSelectedMuniName();
    if (muniName) {
        // Req 2: show boundary and zoom to the selected municipality
        displayMunicipalityBoundary(muniName);

        // Req 2: move default marker to the municipality centroid so the user starts inside
        if (modalMap) {
            const boundary = window.laUnionBoundaries && (window.laUnionBoundaries[muniName] || (muniName === 'San Fernando City' || muniName === 'San Fernando' ? (window.laUnionBoundaries['San Fernando City'] || window.laUnionBoundaries['San Fernando']) : null));
            const centroid = getBoundaryCentroid(boundary);
            // Fall back to barangay-based fallback coords stored in municipalityCoordinates
            const muniIdInt = parseInt(muniId);
            const fallback = (typeof municipalityCoordinates !== 'undefined') && municipalityCoordinates[muniIdInt];
            const target = centroid || fallback;
            if (target) {
                // Reset last-valid so the centroid is the new anchor for snap-back
                window.lastValidSpotCoords = { lat: target.lat, lng: target.lng };
                document.getElementById('spotLatitude').value = target.lat.toFixed(6);
                document.getElementById('spotLongitude').value = target.lng.toFixed(6);
                if (!modalMarker) {
                    const icon = L.divIcon({
                        html: `<div style="background:#2563EB;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;border:3px solid white;box-shadow:0 3px 10px rgba(37,99,235,.45);cursor:grab;"><i class="fas fa-map-marker-alt" style="font-size:14px;"></i></div>`,
                        iconSize: [32, 32], iconAnchor: [16, 32]
                    });
                    modalMarker = L.marker([target.lat, target.lng], { icon, draggable: true }).addTo(modalMap);
                    setupMarkerDragEvents();
                } else {
                    modalMarker.setLatLng([target.lat, target.lng]);
                }
                // Req 3: detect barangay at the centroid automatically
                autoDetectBarangayFromCoords(target.lat, target.lng);
            } else {
                window.lastValidSpotCoords = { lat: null, lng: null };
            }
        }
    } else {
        if (currentBoundaryLayer && modalMap) {
            modalMap.removeLayer(currentBoundaryLayer);
            currentBoundaryLayer = null;
        }
        window.lastValidSpotCoords = { lat: null, lng: null };
    }
};

window.onBarangayChange = async function (barangayName) {
    // Skip if triggered programmatically by auto-detection (prevents pin from jumping)
    if (_suppressBarangayOnchange) return;

    const select = document.getElementById('spotBarangay');
    if (!select) return;
    const selectedOption = select.options[select.selectedIndex];
    if (!selectedOption || !selectedOption.value) return;

    const muniName = getSelectedMuniName();
    if (!muniName) return;

    const latInput = document.getElementById('spotLatitude');
    const lngInput = document.getElementById('spotLongitude');
    if (!latInput || !lngInput) return;

    // Use Nominatim to find the actual centroid of the selected barangay
    const queries = [
        `Barangay ${barangayName}, ${muniName}, La Union, Philippines`,
        `${barangayName}, ${muniName}, La Union`,
        `${barangayName}, La Union, Philippines`,
        `${barangayName} ${muniName}`,
    ];

    let found = null;
    for (const q of queries) {
        try {
            const resp = await fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(q)}`, {
                headers: { 'Accept-Language': 'en' }
            });
            if (!resp.ok) continue;
            const results = await resp.json();
            if (results && results.length > 0) {
                found = { lat: parseFloat(results[0].lat), lng: parseFloat(results[0].lon) };
                break;
            }
        } catch (_) { /* try next query */ }
    }

    if (found) {
        // Validate if it is inside the municipality boundary
        const boundary = window.laUnionBoundaries && (window.laUnionBoundaries[muniName] || (muniName === 'San Fernando City' || muniName === 'San Fernando' ? (window.laUnionBoundaries['San Fernando City'] || window.laUnionBoundaries['San Fernando']) : null));
        const isEditMode = !!document.getElementById('spotId')?.value;
        if (!isEditMode && boundary && !isPointInBoundary(found.lat, found.lng, boundary)) {
            showToast(`The detected location for "${barangayName}" is outside ${muniName} boundary. Pin retained at current position.`, 'info');
            return;
        }

        latInput.value = found.lat.toFixed(6);
        lngInput.value = found.lng.toFixed(6);

        if (modalMap && modalMarker) {
            modalMarker.setLatLng([found.lat, found.lng]);
            modalMap.setView([found.lat, found.lng], 16);
        } else {
            const tryPlaceMarker = () => {
                if (typeof placeOrMoveDraggableMarker === 'function' && modalMap) {
                    placeOrMoveDraggableMarker(found.lat, found.lng);
                    modalMap.setView([found.lat, found.lng], 16);
                } else {
                    setTimeout(tryPlaceMarker, 100);
                }
            };
            tryPlaceMarker();
        }
        window.lastValidSpotCoords = { lat: found.lat, lng: found.lng };
    } else {
        // Fallback: use coordinates stored in option dataset
        const lat = parseFloat(selectedOption.dataset.lat);
        const lng = parseFloat(selectedOption.dataset.lng);
        if (!isNaN(lat) && !isNaN(lng)) {
            latInput.value = lat.toFixed(6);
            lngInput.value = lng.toFixed(6);
            if (modalMap && modalMarker) {
                modalMarker.setLatLng([lat, lng]);
                modalMap.setView([lat, lng], 14);
            } else {
                const tryPlaceMarker = () => {
                    if (typeof placeOrMoveDraggableMarker === 'function' && modalMap) {
                        placeOrMoveDraggableMarker(lat, lng);
                        modalMap.setView([lat, lng], 14);
                    } else {
                        setTimeout(tryPlaceMarker, 100);
                    }
                };
                tryPlaceMarker();
            }
            window.lastValidSpotCoords = { lat, lng };
        }
    }
};

// ── Multi-Category Chip Logic
// ── Form Category Dropdown Logic
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
    if (chk) {
        chk.checked = !chk.checked;
    }
    syncCategoryHiddenInput();
};

function syncCategoryHiddenInput() {
    const selected = Array.from(document.querySelectorAll('.form-cat-chk:checked'))
        .map(c => c.value);
    document.getElementById('spotCategory').value = selected.join(',');

    const label = document.getElementById('formCatDropdownLabel');
    if (selected.length > 0) {
        label.textContent = selected.join(', ');
        label.style.color = '#1E293B';
    } else {
        label.textContent = 'Select Categories...';
        label.style.color = '#9CA3AF';
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

// ── Fee Types Multi-Select Dropdown
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

// ════════════════════════════════════════════════════════════════════════════════
// FORM OPERATIONS - CREATE/EDIT/SAVE
// ════════════════════════════════════════════════════════════════════════════════

function buildCurrentFormDraftPayload() {
    const spotName = document.getElementById('spotName')?.value || '';
    const spotCategory = document.getElementById('spotCategory')?.value || '';
    const spotClassification = document.getElementById('spotClassification')?.value || 'EXISTING';
    const spotFee = parseFloat(document.getElementById('spotFee')?.value) || 0;
    const environmentalFee = parseFloat(document.getElementById('environmentalFee')?.value) || 0;
    const feeTypes = getFeeTypesArray();
    const lat = parseFloat(document.getElementById('spotLatitude')?.value) || null;
    const lng = parseFloat(document.getElementById('spotLongitude')?.value) || null;
    const barangay = document.getElementById('spotBarangay')?.value || null;
    const description = document.getElementById('spotDescription')?.value || '';
    const municipalityId = parseInt(document.getElementById('spotMunicipality')?.value) || null;
    const openingTime = document.getElementById('spotOpeningTime')?.value || null;
    const closingTime = document.getElementById('spotClosingTime')?.value || null;
    const isMaintenance = document.getElementById('spotIsMaintenance')?.checked ? 1 : 0;
    const points = parseInt(document.getElementById('spotPoints')?.value) || 50;

    const cleanImages = uploadedImages
        .filter(img => !img.isLoading && img.photo_url && !img.photo_url.startsWith('blob:'))
        .map(img => ({ photo_url: img.photo_url, filename: img.filename || '' }));

    return {
        id: window.DraftManager?.getActiveDraftId() || null,
        name: spotName || 'Untitled Draft',
        category: spotCategory || 'Other',
        classification_status: spotClassification,
        entrance_fee: spotFee,
        environmental_fee: environmentalFee,
        fee_types: feeTypes,
        latitude: lat,
        longitude: lng,
        barangay: barangay,
        description: description,
        municipality_id: municipalityId,
        images: cleanImages,
        opening_time: openingTime,
        closing_time: closingTime,
        is_maintenance: isMaintenance,
        points: points
    };
}

async function saveDraftFromForm(silent = false) {
    if (!window.DraftManager) return;
    const payload = buildCurrentFormDraftPayload();
    const res = await window.DraftManager.saveDraft(payload);
    if (res && res.success) {
        window.DraftManager.setDirty(false);
        if (!silent && typeof showToast === 'function') {
            const timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            showToast(`Draft saved successfully at ${timeStr}`, 'success');
        }
    }
    return res;
}

function restoreDraftData(draft) {
    initBlankCreateForm();
    if (!draft) return;

    window.DraftManager?.setActiveDraftId(draft.id);

    if (draft.name && draft.name !== 'Untitled Draft') {
        document.getElementById('spotName').value = draft.name;
        document.getElementById('nameCharCount').textContent = draft.name.length;
    }
    if (draft.category) setSelectedCategories(draft.category);
    if (draft.classification_status) {
        const formStatus = statusDisplayMap[draft.classification_status] || draft.classification_status;
        const sel = document.getElementById('spotClassification');
        if (sel) sel.value = formStatus;
    }

    if (draft.entrance_fee) document.getElementById('spotFee').value = draft.entrance_fee;
    if (draft.environmental_fee) document.getElementById('environmentalFee').value = draft.environmental_fee;
    if (draft.fee_types) setFeeTypesFromData(draft.fee_types);

    if (draft.latitude) document.getElementById('spotLatitude').value = draft.latitude;
    if (draft.longitude) document.getElementById('spotLongitude').value = draft.longitude;
    if (draft.description) {
        document.getElementById('spotDescription').value = draft.description;
        document.getElementById('descCharCount').textContent = draft.description.length;
    }

    if (draft.municipality_id) {
        document.getElementById('spotMunicipality').value = draft.municipality_id;
        onMunicipalityChange(draft.municipality_id);
        if (draft.barangay) {
            setTimeout(() => {
                const bSel = document.getElementById('spotBarangay');
                if (bSel) bSel.value = draft.barangay;
            }, 60);
        }
    }

    if (draft.opening_time) document.getElementById('spotOpeningTime').value = draft.opening_time;
    if (draft.closing_time) document.getElementById('spotClosingTime').value = draft.closing_time;
    if (draft.is_maintenance) document.getElementById('spotIsMaintenance').checked = true;
    if (draft.points !== undefined) document.getElementById('spotPoints').value = draft.points;

    if (draft.images && draft.images.length) {
        uploadedImages = draft.images.map(i => ({ photo_url: i.photo_url, filename: i.filename || '' }));
        renderImagePreviews();
    }

    document.getElementById('spotFormModal').classList.add('active');
    setTimeout(initModalMap, 200);

    window.DraftManager?.setDirty(false);
    attachFormDirtyListeners();
    window.DraftManager?.startAutoSave(() => saveDraftFromForm(true));
}

function initBlankCreateForm() {
    uploadedImages = [];
    pendingSaveData = null;
    window.DraftManager?.setActiveDraftId(null);
    window.DraftManager?.setDirty(false);

    document.getElementById('formModalTitle').textContent = 'Add New Spot';
    document.getElementById('spotId').value = '';
    document.getElementById('spotName').value = '';
    document.getElementById('nameCharCount').textContent = '0';
    document.getElementById('spotPoints').value = '50';
    setSelectedCategories('');

    const classificationSelect = document.getElementById('spotClassification');
    if (classificationSelect) {
        classificationSelect.innerHTML = `<option value="">— Select Status —</option><option value="EXISTING">Existing</option>`;
        classificationSelect.value = 'EXISTING';
    }

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
    document.getElementById('spotMunicipality').value = '';
    document.getElementById('spotBarangay').innerHTML = '<option value="">— Select Barangay —</option>';
    document.getElementById('spotOpeningTime').value = '08:00';
    document.getElementById('spotClosingTime').value = '17:00';
    document.getElementById('spotIsMaintenance').checked = false;
    document.getElementById('imagePreviews').innerHTML = '';
    document.getElementById('maintenance-field').style.display = 'none';

    window.lastValidSpotCoords = { lat: null, lng: null };
    if (currentBoundaryLayer && modalMap) {
        modalMap.removeLayer(currentBoundaryLayer);
        currentBoundaryLayer = null;
    }

    document.getElementById('spotFormModal').classList.add('active');
    setTimeout(initModalMap, 200);

    attachFormDirtyListeners();
    window.DraftManager?.startAutoSave(() => saveDraftFromForm(true));
}

function attachFormDirtyListeners() {
    const form = document.getElementById('spotForm');
    if (!form || form.dataset.dirtyBound) return;
    form.dataset.dirtyBound = 'true';

    form.querySelectorAll('input, select, textarea').forEach(el => {
        el.addEventListener('input', () => window.DraftManager?.setDirty(true));
        el.addEventListener('change', () => window.DraftManager?.setDirty(true));
    });
}

window.openCreateForm = async function () {
    if (window.userRole === 'picto') return;
    if (window.DraftManager) window.DraftManager.ensureModals();

    const draft = await window.DraftManager?.fetchDraft();
    if (draft) {
        const modal = document.getElementById('draftFoundModal');
        if (modal) {
            window.DraftManager.setPendingDraft(draft);
            modal.classList.add('active');

            document.getElementById('btnContinueDraft').onclick = () => {
                modal.classList.remove('active');
                restoreDraftData(draft);
            };
            document.getElementById('btnStartNewDraft').onclick = () => {
                modal.classList.remove('active');
                initBlankCreateForm();
            };
            document.getElementById('btnDeleteDraft').onclick = async () => {
                modal.classList.remove('active');
                await window.DraftManager.deleteDraft(draft.id);
                if (typeof showToast === 'function') showToast('Draft deleted successfully', 'info');
                initBlankCreateForm();
            };
            return;
        }
    }

    initBlankCreateForm();
};

window.editSpot = async function (spotId) {
    try {
        // First try to find spot in local data
        let spot = window.touristSpotsAll?.find(s => s.id == spotId);
        if (!spot) {
            // If not found, fetch from API
            spot = await window.getSpot(spotId);
        }
        uploadedImages = spot.images && spot.images.length > 0 ? spot.images : (spot.photo_url ? [{ photo_url: spot.photo_url }] : []);

        document.getElementById('formModalTitle').textContent = 'Edit Spot';
        document.getElementById('spotId').value = spot.id;
        document.getElementById('spotName').value = spot.name;
        document.getElementById('nameCharCount').textContent = spot.name.length;
        document.getElementById('spotPoints').value = spot.points !== undefined ? spot.points : '0';

        setSelectedCategories(spot.category || '');

        // Convert DB status to form display status
        const formStatus = statusDisplayMap[spot.classification_status] || spot.classification_status;
        const classificationSelect = document.getElementById('spotClassification');
        const isPictoEdit = window.userRole === 'picto';
        classificationSelect.innerHTML = `
            <option value="">— Select Status —</option>
            <option value="EXISTING">Existing</option>
            ${isPictoEdit ? `
            <option value="EMERGING">Emerging</option>
            <option value="POTENTIAL">Potential</option>
            ` : ''}
        `;
        classificationSelect.value = formStatus;

        document.getElementById('spotFee').value = spot.entrance_fee || 0;
        document.getElementById('environmentalFee').value = spot.environmental_fee || 0;
        setFeeTypesFromData(spot.fee_types || []);
        document.getElementById('spotLatitude').value = spot.latitude || '';
        document.getElementById('spotLongitude').value = spot.longitude || '';
        document.getElementById('spotDescription').value = spot.description || '';
        document.getElementById('descCharCount').textContent = (spot.description || '').length;

        document.getElementById('spotMunicipality').value = spot.municipality_id;
        onMunicipalityChange(spot.municipality_id);
        if (spot.barangay) {
            setTimeout(() => {
                document.getElementById('spotBarangay').value = spot.barangay;
            }, 50);
        }

        document.getElementById('spotOpeningTime').value = spot.opening_time || '';
        document.getElementById('spotClosingTime').value = spot.closing_time || '';
        document.getElementById('spotIsMaintenance').checked = spot.is_maintenance ? true : false;

        // Show under maintenance field in edit mode
        document.getElementById('maintenance-field').style.display = 'block';

        renderImagePreviews();
        document.getElementById('spotFormModal').classList.add('active');
        setTimeout(initModalMap, 200);


    } catch (err) {
        console.error(err);
        showToast('Failed to load spot for editing', 'danger');
    }
};

// ── Initialize Modal Map
function initModalMap() {
    if (!document.getElementById('modalMap')) return;

    if (modalMap) {
        modalMap.remove();
        modalMap = null;
        modalMarker = null;
    }

    modalMap = L.map('modalMap', { minZoom: 10, maxZoom: 18 });

    // Dedicated layer instances for the modal map to avoid sharing singletons with main map
    const modalStreet = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
        maxZoom: 18
    });
    const modalSatellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: '© Esri',
        maxZoom: 18
    });

    modalStreet.addTo(modalMap);

    const modalBaseMaps = {
        "Street Map": modalStreet,
        "Satellite Map": modalSatellite
    };
    L.control.layers(modalBaseMaps, null, { position: 'topright' }).addTo(modalMap);

    modalMap.setView([16.5, 120.3], 10);

    [100, 250, 500].forEach(delay => {
        setTimeout(() => {
            if (modalMap) modalMap.invalidateSize();
        }, delay);
    });

    modalMap.on('click', function (e) {
        placeOrMoveDraggableMarker(e.latlng.lat, e.latlng.lng, false, true, true);
    });

    // Req 2: re-show boundary if a municipality was already selected (e.g. edit mode)
    const selectedMuni = getSelectedMuniName();
    if (selectedMuni) {
        displayMunicipalityBoundary(selectedMuni);
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
            // Edit mode: restore saved pin
            startLat = existingLat;
            startLng = existingLng;
            isExisting = true;
        } else if (selectedMuni) {
            // Municipality already chosen: use its centroid
            const boundary = window.laUnionBoundaries && window.laUnionBoundaries[selectedMuni];
            const centroid = getBoundaryCentroid(boundary);
            const muniIdVal = parseInt(document.getElementById('spotMunicipality')?.value);
            const fallback = (typeof municipalityCoordinates !== 'undefined') && municipalityCoordinates[muniIdVal];
            const target = centroid || fallback;
            startLat = target ? target.lat : 16.5;
            startLng = target ? target.lng : 120.3;
        } else {
            // No municipality yet: center of La Union
            startLat = 16.5;
            startLng = 120.3;
        }

        // Only place marker if none exists yet (edit mode may already have one)
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
            modalMap.setView([startLat, startLng], isExisting ? 15 : (selectedMuni ? 13 : 10));
        }
    }, 180);
}

window.placeOrMoveDraggableMarker = function placeOrMoveDraggableMarker(lat, lng, skipBoundaryCheck = false, updateInputs = true, isUserAction = false) {
    if (!modalMap) return;
    const isValid = window.validateAndMovePin(lat, lng, skipBoundaryCheck, updateInputs, isUserAction);
    if (isValid) {
        modalMap.setView([lat, lng], 16);
        if (updateInputs) {
            autoDetectBarangayFromCoords(lat, lng);
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

window.updateMapMarker = window.updateMapMarkerFromInput;

window.useCurrentLocation = function () {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                document.getElementById('spotLatitude').value = position.coords.latitude;
                document.getElementById('spotLongitude').value = position.coords.longitude;
                updateMapMarker();
                showToast('Location set successfully', 'success');
            },
            () => showToast('Failed to get current location', 'danger')
        );
    } else {
        showToast('Geolocation not supported', 'danger');
    }
};

window.attemptCloseFormModal = function () {
    const isAddingNewSpot = !document.getElementById('spotId')?.value;
    if (isAddingNewSpot && window.DraftManager?.isDirty()) {
        const confirmModal = document.getElementById('saveAsDraftConfirmModal');
        if (confirmModal) {
            confirmModal.classList.add('active');

            document.getElementById('btnConfirmSaveDraft').onclick = async () => {
                confirmModal.classList.remove('active');
                await saveDraftFromForm(false);
                forceCloseFormModal();
            };
            document.getElementById('btnConfirmDiscardDraft').onclick = () => {
                confirmModal.classList.remove('active');
                window.DraftManager.setDirty(false);
                forceCloseFormModal();
            };
            document.getElementById('btnConfirmContinueEditing').onclick = () => {
                confirmModal.classList.remove('active');
            };
            return;
        }
    }

    forceCloseFormModal();
};

function forceCloseFormModal() {
    uploadedImages = [];
    pendingSaveData = null;
    window.DraftManager?.stopAutoSave();
    window.DraftManager?.setActiveDraftId(null);
    window.DraftManager?.setDirty(false);

    document.getElementById('spotFormModal')?.classList.remove('active');
    document.getElementById('duplicateSpotNameModal')?.classList.remove('active');
    document.getElementById('saveAsDraftConfirmModal')?.classList.remove('active');
    document.getElementById('draftFoundModal')?.classList.remove('active');

    const spotIdEl = document.getElementById('spotId');
    if (spotIdEl) spotIdEl.value = '';
    const spotNameEl = document.getElementById('spotName');
    if (spotNameEl) spotNameEl.value = '';
    if (modalMap) {
        modalMap.remove();
        modalMap = null;
        modalMarker = null;
    }
}

window.closeFormModal = window.attemptCloseFormModal;

// ════════════════════════════════════════════════════════════════════════════════
// FORM SUBMISSION WITH CONFIRMATION
// ════════════════════════════════════════════════════════════════════════════════

window.submitSpotForm = async function (e) {
    e.preventDefault();

    // ── Loading state on Save Spot button
    const saveBtn = document.getElementById('saveSpotBtn');
    const saveIcon = document.getElementById('saveSpotIcon');
    const saveSpinner = document.getElementById('saveSpotSpinner');
    const saveLabel = document.getElementById('saveSpotLabel');
    if (saveBtn) {
        saveBtn.disabled = true;
        if (saveIcon) saveIcon.style.display = 'none';
        if (saveSpinner) saveSpinner.style.display = 'inline-block';
        if (saveLabel) saveLabel.textContent = 'Validating...';
    }

    const resetSaveBtn = () => {
        if (saveBtn) {
            saveBtn.disabled = false;
            if (saveIcon) saveIcon.style.display = 'inline-block';
            if (saveSpinner) saveSpinner.style.display = 'none';
            if (saveLabel) saveLabel.textContent = 'Save Spot';
        }
    };

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

    const categoryValue = document.getElementById('spotCategory').value;
    if (!categoryValue) {
        showToast('Please select at least one category', 'danger');
        resetSaveBtn();
        return;
    }

    const classificationValue = document.getElementById('spotClassification').value;
    if (!classificationValue) {
        showToast('Please select a classification status', 'danger');
        resetSaveBtn();
        return;
    }

    const pointsInput = document.getElementById('spotPoints');
    const pointsValue = pointsInput ? pointsInput.value.trim() : '';
    if (!pointsValue) {
        showToast('Points field is required.', 'danger');
        resetSaveBtn();
        return;
    }
    const pointsNum = Number(pointsValue);
    if (isNaN(pointsNum) || !Number.isInteger(pointsNum) || pointsNum < 0) {
        showToast('Points must be a positive whole number.', 'danger');
        resetSaveBtn();
        return;
    }

    const stillUploading = uploadedImages.some(img => img.isLoading);
    if (stillUploading) {
        showToast('Please wait for all images to finish uploading before saving', 'danger');
        resetSaveBtn();
        return;
    }

    const latVal = parseFloat(document.getElementById('spotLatitude').value) || null;
    const lngVal = parseFloat(document.getElementById('spotLongitude').value) || null;
    const muniName = getSelectedMuniName();
    const isEditMode = !!document.getElementById('spotId').value;
    if (!isEditMode && latVal !== null && lngVal !== null && muniName) {
        const boundary = window.laUnionBoundaries && window.laUnionBoundaries[muniName];
        if (boundary && !isPointInBoundary(latVal, lngVal, boundary)) {
            showInvalidLocationModal(muniName, "The selected location is outside the boundary of the selected municipality. Please choose a location within the selected municipality before adding the tourist spot.");
            resetSaveBtn();
            return;
        }
    }

    const cleanImages = uploadedImages
        .filter(img => !img.isLoading && img.photo_url && !img.photo_url.startsWith('blob:'))
        .map(img => ({ photo_url: img.photo_url, filename: img.filename || '' }));

    const spotIdValue = document.getElementById('spotId').value;

    // Convert form status to DB status
    const dbStatus = statusReverseMap[classificationValue] || classificationValue;

    pendingSaveData = {
        id: spotIdValue ? parseInt(spotIdValue) : null,
        name: document.getElementById('spotName').value,
        category: categoryValue,
        classification_status: dbStatus,
        entrance_fee: parseFloat(document.getElementById('spotFee').value) || 0,
        environmental_fee: parseFloat(document.getElementById('environmentalFee').value) || 0,
        fee_types: getFeeTypesArray(),
        latitude: parseFloat(document.getElementById('spotLatitude').value) || null,
        longitude: parseFloat(document.getElementById('spotLongitude').value) || null,
        barangay: document.getElementById('spotBarangay').value || null,
        description: document.getElementById('spotDescription').value,
        municipality_id: parseInt(document.getElementById('spotMunicipality').value),
        images: cleanImages,
        opening_time: document.getElementById('spotOpeningTime').value || null,
        closing_time: document.getElementById('spotClosingTime').value || null,
        is_maintenance: document.getElementById('spotIsMaintenance').checked ? 1 : 0,
        points: parseInt(pointsValue)
    };

    void 0;
    void 0;

    resetSaveBtn();
    setConfirmLoading(false);
    document.getElementById('saveConfirmModal').classList.add('active');
};

const setConfirmLoading = (loading, isEdit = false) => {
    const confirmBtn = document.getElementById('saveConfirmBtn');
    const confirmIcon = document.getElementById('confirmBtnIcon');
    const confirmSpinner = document.getElementById('confirmBtnSpinner');
    const confirmLabel = document.getElementById('confirmBtnLabel');
    const cancelBtn = document.querySelector('[data-action="close-save-confirm"]');

    if (!confirmBtn) return;
    confirmBtn.disabled = loading;
    if (cancelBtn) cancelBtn.disabled = loading;
    if (loading) {
        if (confirmIcon) confirmIcon.style.display = 'none';
        if (confirmSpinner) confirmSpinner.style.display = 'inline-block';
        if (confirmLabel) confirmLabel.textContent = isEdit ? 'Updating...' : 'Saving...';
        confirmBtn.style.opacity = '0.85';
        confirmBtn.style.cursor = 'not-allowed';
    } else {
        if (confirmIcon) confirmIcon.style.display = 'inline-block';
        if (confirmSpinner) confirmSpinner.style.display = 'none';
        if (confirmLabel) confirmLabel.textContent = 'Yes, Save';
        confirmBtn.style.opacity = '';
        confirmBtn.style.cursor = '';
    }
};

window.closeSaveConfirmModal = function () {
    document.getElementById('saveConfirmModal').classList.remove('active');
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
    void 0;

    if (!pendingSaveData) {
        showToast('No data to save', 'danger');
        return;
    }

    const isEdit = pendingSaveData.id;
    setConfirmLoading(true, !!isEdit);

    try {
        let res;

        void 0;

        if (isEdit) {
            res = await updateSpot(pendingSaveData.id, pendingSaveData);
        } else {
            res = await createSpot(pendingSaveData);
        }

        void 0;

        if (res && (res.success || res.message)) {
            window.DraftManager?.setDirty(false);
            window.DraftManager?.setActiveDraftId(null);
            window.DraftManager?.stopAutoSave();

            const saveData = pendingSaveData;
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
                    municipality_name: document.getElementById('spotMunicipality')?.options[document.getElementById('spotMunicipality')?.selectedIndex]?.text || '',
                    created_at: new Date().toISOString()
                };
                if (window.touristSpotsData) window.touristSpotsData.unshift(newSpot);
                if (window.touristSpotsAll) window.touristSpotsAll.unshift(newSpot);
            }

            // Sort and render instantly
            if (typeof sortSpotsPendingFirst === 'function') {
                sortSpotsPendingFirst(window.touristSpotsData);
                sortSpotsPendingFirst(window.touristSpotsAll);
            }
            if (typeof renderCardsGrid === 'function' && window.touristSpotsData) {
                renderCardsGrid(window.touristSpotsData);
            }
            if (typeof renderTableRows === 'function' && window.touristSpotsData) {
                renderTableRows(window.touristSpotsData);
            }
            if (typeof updateKpiCards === 'function' && window.touristSpotsData && window.municipalitiesData) {
                updateKpiCards(window.touristSpotsData, window.municipalitiesData);
            }
            if (document.getElementById('touristMap') && typeof initMap === 'function' && window.touristSpotsData && window.municipalitiesData) {
                initMap(window.touristSpotsData, window.municipalitiesData);
            }
            if (document.getElementById('lupto-map') && typeof window.refreshLuptoMap === 'function') {
                window.refreshLuptoMap();
            }

            showToast(isEdit ? '✅ Spot updated successfully!' : '✅ Spot created successfully!', 'success');
            if (typeof window.notifyTouristSpotChanged === 'function') {
                window.notifyTouristSpotChanged();
            }
        } else {
            throw new Error(res.message || 'Unknown error');
        }
    } catch (err) {
        console.error('❌ Save error:', err);
        const errorMsg = err.message || 'Failed to save spot';
        showToast(`❌ Error: ${errorMsg}`, 'danger');
        setConfirmLoading(false);
        closeSaveConfirmModal();
    }
};

// ════════════════════════════════════════════════════════════════════════════════
// INITIALIZE ALL EVENT LISTENERS
// ════════════════════════════════════════════════════════════════════════════════

const sortSpotsPendingFirst = (arr) => {
    if (!arr || !Array.isArray(arr)) return;
    arr.sort((a, b) => {
        const statusA = a.status || '';
        const statusB = b.status || '';
        if (statusA === 'pending' && statusB !== 'pending') return -1;
        if (statusA !== 'pending' && statusB === 'pending') return 1;

        const timeA = new Date(a.created_at || 0).getTime();
        const timeB = new Date(b.created_at || 0).getTime();
        if (timeA !== timeB && !isNaN(timeA) && !isNaN(timeB) && timeA > 0 && timeB > 0) {
            return timeB - timeA;
        }

        const idA = parseInt(a.id) || 0;
        const idB = parseInt(b.id) || 0;
        return idB - idA;
    });
};

export async function initializeAll(spotsData, municipalData) {
    loadCachedKpis();

    // Check window cache first for instantaneous loading
    const cacheKey = '__LUPTO_TOURIST_SPOTS_CACHE__';
    window[cacheKey] = window[cacheKey] || { spots: null, munis: null, timestamp: 0 };
    const cached = window[cacheKey];
    const isFresh = cached.spots && cached.munis && (Date.now() - cached.timestamp < 300000); // 5 minutes fresh TTL

    // Render instantly from cache if available
    if ((!spotsData || !spotsData.length) && cached.spots && cached.munis) {
        spotsData = cached.spots;
        municipalData = cached.munis;

        sortSpotsPendingFirst(spotsData);
        window.touristSpotsData = spotsData;
        window.municipalitiesData = municipalData;
        window.touristSpotsAll = spotsData;
        window.municipalitiesAll = municipalData;

        renderCardsGrid(spotsData);
        renderTableRows(spotsData);
        populateMuniDropdowns(municipalData);
        updateKpiCards(spotsData, municipalData);

        const pendingToast = sessionStorage.getItem('save_success_toast');
        if (pendingToast) {
            showToast(pendingToast, 'success');
            sessionStorage.removeItem('save_success_toast');
        }

        try {
            initMap(spotsData, municipalData);
            setupMapLayerToggle();
        } catch (e) {
            console.error('Leaflet Map initialization failed:', e);
        }
        try { setupViewToggle(); } catch (e) { console.error('setupViewToggle failed:', e); }
        try { setupFilterListeners(); } catch (e) { console.error('setupFilterListeners failed:', e); }
        try { setupDropdownListeners(); } catch (e) { console.error('setupDropdownListeners failed:', e); }
        try { setupModalListeners(); } catch (e) { console.error('setupModalListeners failed:', e); }
        try { initCategoryChips(); } catch (e) { console.error('initCategoryChips failed:', e); }

        // Bind creation actions
        document.querySelectorAll('[data-action="open-create-form"]').forEach(btn => {
            btn.addEventListener('click', () => openCreateForm());
        });
        document.querySelectorAll('[data-action="close-form-modal"]').forEach(el => {
            el.addEventListener('click', closeFormModal);
        });
        document.getElementById('spotFormModal')?.addEventListener('click', e => {
            if (e.target.id === 'spotFormModal') closeFormModal();
        });
        document.getElementById('spotForm')?.addEventListener('submit', submitSpotForm);
        document.getElementById('saveConfirmModal')?.addEventListener('click', e => {
            if (e.target.id === 'saveConfirmModal') closeSaveConfirmModal();
        });
        document.querySelector('[data-action="close-save-confirm"]')?.addEventListener('click', closeSaveConfirmModal);
        document.querySelector('[data-action="confirm-save-spot"]')?.addEventListener('click', confirmSaveSpot);

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
            const [spotsRes, muniRes] = await Promise.all([
                window.API_CONFIG.get(`${baseUrl}/api/tourist-spots`),
                window.API_CONFIG.get(`${baseUrl}/api/municipalities`)
            ]);
            spotsData = spotsRes.data || spotsRes || [];
            municipalData = muniRes.municipalities || muniRes.data || muniRes || [];

            // Save to cache
            cached.spots = spotsData;
            cached.munis = municipalData;
            cached.timestamp = Date.now();
        } catch (err) {
            console.error('Failed to fetch tourist spots:', err);
            spotsData = [];
            municipalData = [];
        }
    } else {
        // If data was passed in, update cache
        cached.spots = spotsData;
        cached.munis = municipalData;
        cached.timestamp = Date.now();
    }

    sortSpotsPendingFirst(spotsData);

    window.touristSpotsData = spotsData;
    window.municipalitiesData = municipalData;
    window.touristSpotsAll = spotsData;
    window.municipalitiesAll = municipalData;

    // Render cards and table from JS data
    renderCardsGrid(spotsData);
    renderTableRows(spotsData);
    populateMuniDropdowns(municipalData);

    // Update KPIs
    updateKpiCards(spotsData, municipalData);

    void 0;
    void 0;
    void 0;

    const pendingToast = sessionStorage.getItem('save_success_toast');
    if (pendingToast) {
        showToast(pendingToast, 'success');
        sessionStorage.removeItem('save_success_toast');
    }

    try {
        initMap(spotsData, municipalData);
        setupMapLayerToggle();
    } catch (e) {
        console.error('Leaflet Map initialization failed:', e);
    }
    try { setupViewToggle(); } catch (e) { console.error('setupViewToggle failed:', e); }
    try { setupFilterListeners(); } catch (e) { console.error('setupFilterListeners failed:', e); }
    try { setupDropdownListeners(); } catch (e) { console.error('setupDropdownListeners failed:', e); }
    try { setupModalListeners(); } catch (e) { console.error('setupModalListeners failed:', e); }
    try { initCategoryChips(); } catch (e) { console.error('initCategoryChips failed:', e); }

    // Add Spot
    document.querySelectorAll('[data-action="open-create-form"]').forEach(btn => {
        btn.addEventListener('click', () => openCreateForm());
    });

    // Close Form Modal
    document.querySelectorAll('[data-action="close-form-modal"]').forEach(el => {
        el.addEventListener('click', closeFormModal);
    });

    // Backdrop close
    document.getElementById('spotFormModal')?.addEventListener('click', e => {
        if (e.target.id === 'spotFormModal') closeFormModal();
    });

    // Form submit
    document.getElementById('spotForm')?.addEventListener('submit', submitSpotForm);

    // Save confirmation
    document.getElementById('saveConfirmModal')?.addEventListener('click', e => {
        if (e.target.id === 'saveConfirmModal') closeSaveConfirmModal();
    });
    document.querySelector('[data-action="close-save-confirm"]')
        ?.addEventListener('click', closeSaveConfirmModal);
    document.querySelector('[data-action="confirm-save-spot"]')
        ?.addEventListener('click', confirmSaveSpot);

    // Current location
    document.querySelector('[data-action="use-current-location"]')
        ?.addEventListener('click', useCurrentLocation);

    // Fee types dropdown close handled via initCategoryChips

    // Classification status change points calculation
    document.getElementById('spotClassification')
        ?.addEventListener('change', function () {
            const val = this.value.toUpperCase();
            let points = '';
            if (val === 'EXISTING') points = '50';
            else if (val === 'EMERGING') points = '100';
            else if (val === 'POTENTIAL') points = '75';
            const ptsInput = document.getElementById('spotPoints');
            if (ptsInput) ptsInput.value = points;
        });

    // Municipality change
    document.getElementById('spotMunicipality')
        ?.addEventListener('change', function () { onMunicipalityChange(this.value); });

    // Barangay change
    document.getElementById('spotBarangay')
        ?.addEventListener('change', function () { onBarangayChange(this.value); });

    // Lat/Lng input
    document.getElementById('spotLatitude')
        ?.addEventListener('input', updateMapMarkerFromInput);
    document.getElementById('spotLongitude')
        ?.addEventListener('input', updateMapMarkerFromInput);

    const triggerBoundaryCheckOnBlur = function () {
        const lat = parseFloat(document.getElementById('spotLatitude').value);
        const lng = parseFloat(document.getElementById('spotLongitude').value);
        if (!isNaN(lat) && !isNaN(lng)) {
            window.validateAndMovePin(lat, lng, false, true);
        }
    };
    document.getElementById('spotLatitude')
        ?.addEventListener('change', triggerBoundaryCheckOnBlur);
    document.getElementById('spotLongitude')
        ?.addEventListener('change', triggerBoundaryCheckOnBlur);

    // Character counters
    document.getElementById('spotName')
        ?.addEventListener('input', function () {
            document.getElementById('nameCharCount').textContent = this.value.length;
        });

    const checkDuplicateName = () => {
        const spotFormModal = document.getElementById('spotFormModal');
        if (!spotFormModal || !spotFormModal.classList.contains('active')) return;

        const spotNameVal = (document.getElementById('spotName')?.value || '').trim();
        if (!spotNameVal) return;

        const currentSpotId = document.getElementById('spotId')?.value;
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

    document.getElementById('spotDescription')
        ?.addEventListener('input', function () {
            document.getElementById('descCharCount').textContent = this.value.length;
        });

    // Image upload
    const uploadArea = document.getElementById('imageUploadArea');
    const fileInput = document.getElementById('spotImages');
    if (uploadArea) {
        uploadArea.addEventListener('dragover', handleDragOver);
        uploadArea.addEventListener('dragleave', handleDragLeave);
        uploadArea.addEventListener('drop', handleImageDrop);
    }
    if (fileInput) {
        fileInput.addEventListener('change', handleImageSelect);
    }

    void 0;

    startKpiAutoRefresh();

    // Initialize decline modal (LUPTO only)
    if (window.userRole === 'lupto') {
        try { initDeclineModal(); } catch (e) { console.error('initDeclineModal failed:', e); }
    }

    // Initialize the interactive map (map-view-api.js skips auto-init on this page)
    if (typeof initMapView === 'function' && document.getElementById('lupto-map')) {
        var existing = document.getElementById('lupto-map')._leaflet_map;
        if (existing) { existing.remove(); delete document.getElementById('lupto-map')._leaflet_map; }
        initMapView();
    }
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

function populateMuniDropdowns(municipalData) {
    const filterSelect = document.getElementById('filterMunicipality');
    const formSelect = document.getElementById('spotMunicipality');

    const savedFormValue = formSelect ? formSelect.value : '';

    if (filterSelect) {
        filterSelect.innerHTML = '<option value="">All Municipalities</option>';
    }
    if (formSelect) {
        formSelect.innerHTML = '<option value="">Select Municipality</option>';
    }

    municipalData.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.name;
        opt.textContent = m.name;
        if (filterSelect) filterSelect.appendChild(opt.cloneNode(true));
        if (formSelect) {
            const fOpt = document.createElement('option');
            fOpt.value = m.id;
            fOpt.textContent = m.name;
            formSelect.appendChild(fOpt);
        }
    });

    if (formSelect && savedFormValue) {
        formSelect.value = savedFormValue;
    }
}

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

    if (elTotal) { window.animateKpiValue(elTotal, total); elTotal.style.color = ''; }
    if (elApproved) { window.animateKpiValue(elApproved, approved); elApproved.style.color = ''; }
    if (elPending) { window.animateKpiValue(elPending, pending); elPending.style.color = ''; }
    if (elDeclined) { window.animateKpiValue(elDeclined, declined); elDeclined.style.color = ''; }
    if (elCategory) { window.animateKpiValue(elCategory, topCategory); elCategory.style.color = ''; }

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

    const spotCount = container.querySelector('#spotCount');
    if (spotCount) spotCount.textContent = total;

    try { sessionStorage.setItem('ts_kpis_lupto', JSON.stringify({ total, approved, pending, declined, topCategory, topCatCount })); } catch (e) { }
}

function loadCachedKpis() {
    try {
        const raw = sessionStorage.getItem('ts_kpis_lupto');
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

        const spotCount = container.querySelector('#spotCount');
        if (spotCount) spotCount.textContent = v.total;
    } catch (e) { }
}

function renderCardsGrid(spotsData) {
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
        const munName = spot.municipality_name || (spot.municipality && spot.municipality.name) || '';
        const cats = (spot.category || 'Other').split(',').map(c => c.trim()).filter(Boolean);
        const catTags = cats.map(c => `<span class="tag" style="background:#DBEAFE;color:#2563EB;">${c}</span>`).join('');
        const photoUrl = spot.photo_url || '';

        const isNew = previousIds.size > 0 && !previousIds.has(String(spot.id));
        const animateClass = isNew ? ' new-card-animate' : '';

        html += `<div class="spot-card${animateClass}" data-spot-id="${spot.id}" data-municipality="${munName}" data-category="${spot.category || ''}" data-status="${statusClass}" data-name="${(spot.name || '').toLowerCase()}" style="cursor: pointer;">`;
        html += `<div class="spot-image">`;
        if (approvalStatus === 'pending') {
            html += `<span class="pending-badge" style="z-index: 2;"><i class="far fa-clock"></i> Pending</span>`;
        }
        if (photoUrl) {
            html += `<img src="${escapeHtml(photoUrl)}" alt="${escapeHtml(spot.name || '')}" loading="lazy" style="width:100%;height:100%;object-fit:cover;display:block;" onerror="var p=this.parentElement;this.style.display='none';var ph=p.querySelector('.spot-image-placeholder');if(ph)ph.style.display='flex';">`;
            html += `<div class="spot-image-placeholder" style="display:none;"><i class="fas fa-image"></i><span>Image unavailable</span></div>`;
        } else {
            html += `<div class="spot-image-placeholder"><i class="fas fa-image"></i><span>No image yet</span></div>`;
        }
        html += `</div>`;
        if (window.userRole !== 'picto' && approvalStatus !== 'pending') {
            html += `<div class="card-actions-dropdown">`;
            html += `<button class="dropdown-toggle" id="card-dropdown-${spot.id}"><i class="fas fa-ellipsis-v"></i></button>`;
            html += `<div class="dropdown-menu" id="card-menu-${spot.id}">`;
            html += `<button class="dropdown-item" data-action="edit-spot" data-spot-id="${spot.id}"><i class="fas fa-pen-to-square" style="color:#F59E0B;"></i> Edit</button>`;
            html += `</div></div>`;
        }
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
        if (approvalStatus && approvalStatus !== 'approved') {
            html += `<span class="tag" style="background:${approvalBg};color:${approvalTextColor};">${approvalLabel}</span>`;
        }
        html += `</div>`;
        html += `<p>${desc}${(spot.description || '').length > 100 ? '...' : ''}</p>`;
        if (approvalStatus === 'pending' && window.userRole === 'lupto') {
            html += `
            <div class="pending-card-actions" style="margin-top: auto; padding-top: 12px; border-top: 1px solid #F1F5F9;">
                <button class="pending-btn-approve" onclick="window.approvePendingSpot(${spot.id})" title="Approve">
                    <i class="fas fa-check"></i> Approve
                </button>
                <button class="pending-btn-reject" onclick="window.openDeclineModal(${spot.id})" title="Decline">
                    <i class="fas fa-times"></i> Reject
                </button>
            </div>`;
        }
        html += `</div></div>`;
    });
    grid.innerHTML = html;
    document.getElementById('spotCount').textContent = spotsData.length;

    // Bind click listener to cards (except actions/buttons)
    grid.querySelectorAll('.spot-card').forEach(card => {
        card.addEventListener('click', (e) => {
            if (e.target.closest('.card-actions-dropdown') || e.target.closest('.pending-card-actions')) {
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

function renderTableRows(spotsData) {
    const tbody = document.querySelector('#tableView tbody');
    if (!tbody) return;
    let html = '';
    spotsData.forEach(spot => {
        const munName = spot.municipality_name || (spot.municipality && spot.municipality.name) || '';
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
        html += `<td>${munName}</td>`;
        html += `<td>${catTags}</td>`;
        html += `<td>${status ? `<span class="tag" style="background:${statusBg};color:${statusColor};">${statusClass}</span>` : ''}</td>`;
        html += `<td style="font-weight: 600; color: #D97706;">${spot.points !== undefined ? spot.points : 0} pts</td>`;
        html += `<td>${(approvalStatus && approvalStatus !== 'approved') ? `<span class="tag" style="background:${approvalBg};color:${approvalTextColor};">${approvalLabel}</span>` : ''}</td>`;
        html += `<td>${feeDisplay}</td>`;
        html += `<td>${date}</td>`;
        html += `<td style="text-align:right;">`;
        if (window.userRole !== 'picto' && approvalStatus !== 'pending') {
            html += `<div class="table-actions-dropdown">`;
            html += `<button class="dropdown-toggle" id="tbl-dropdown-${spot.id}"><i class="fas fa-ellipsis-v"></i></button>`;
            html += `<div class="dropdown-menu" id="tbl-menu-${spot.id}">`;
            html += `<button class="dropdown-item" data-action="edit-spot" data-spot-id="${spot.id}"><i class="fas fa-pen-to-square" style="color:#F59E0B;"></i> Edit</button>`;
            html += `</div></div>`;
        }
        html += `</td></tr>`;
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
    loadCachedKpis();
    try {
        let freshSpots = spotsData;
        let freshMunis = muniData;

        if (!freshSpots || !freshMunis) {
            const baseUrl = window.API_CONFIG?.BASE_URL || (`http://${window.location.hostname || '127.0.0.1'}:8000`);
            const [spotsRes, muniRes] = await Promise.all([
                window.API_CONFIG.get(`${baseUrl}/api/tourist-spots`),
                window.API_CONFIG.get(`${baseUrl}/api/municipalities`)
            ]);
            freshSpots = spotsRes.data || spotsRes || [];
            freshMunis = muniRes.municipalities || muniRes.data || muniRes || [];
        }

        sortSpotsPendingFirst(freshSpots);

        window.touristSpotsData = freshSpots;
        window.municipalitiesData = freshMunis;
        window.touristSpotsAll = freshSpots;
        window.municipalitiesAll = freshMunis;

        // Update the cache object so that isFresh becomes true again
        const cacheObj = window['__LUPTO_TOURIST_SPOTS_CACHE__'];
        if (cacheObj) {
            cacheObj.spots = freshSpots;
            cacheObj.munis = freshMunis;
            cacheObj.timestamp = Date.now();
        }

        // Save current filter values before rendering
        const searchInputEl = document.getElementById('searchInput');
        const activeSearch = searchInputEl ? searchInputEl.value : '';
        const filterMuniEl = document.getElementById('filterMunicipality');
        const activeMuni = filterMuniEl ? filterMuniEl.value : '';
        const filterStatusEl = document.getElementById('filterStatus');
        const activeStatus = filterStatusEl ? filterStatusEl.value : '';

        renderCardsGrid(freshSpots);
        renderTableRows(freshSpots);

        populateMuniDropdowns(freshMunis);

        // Restore dropdown selected values after refreshing
        if (filterMuniEl) filterMuniEl.value = activeMuni;
        if (filterStatusEl) filterStatusEl.value = activeStatus;
        if (searchInputEl) searchInputEl.value = activeSearch;

        updateKpiCards(freshSpots, freshMunis);
        setupDropdownListeners();

        if (document.getElementById('touristMap')) {
            initMap(freshSpots, freshMunis);
        }

        if (document.getElementById('lupto-map') && typeof window.refreshLuptoMap === 'function') {
            window.refreshLuptoMap();
        }

        const selectedCats = Array.from(document.querySelectorAll('.cat-filter-chk:checked')).map(c => c.value);
        filterSpots(activeSearch, activeMuni, selectedCats, activeStatus);

        void 0;

    } catch (err) {
        if (err.name !== 'AbortError') {
            console.error('? Soft refresh failed:', err);
        }
    }
}

window.softRefreshTouristSpots = softRefreshSpots;

// ════════════════════════════════════════════════════════════════════════════════
// PENDING APPROVAL FUNCTIONS
// ════════════════════════════════════════════════════════════════════════════════

window.approvePendingSpot = function (id) {
    window.openApproveModal(id);
};

window.openApproveModal = function (id) {
    document.getElementById('approveSpotId').value = id;
    document.getElementById('approveConfirmModal').classList.add('active');
};

window.closeApproveModal = function () {
    document.getElementById('approveConfirmModal').classList.remove('active');
};

window.confirmApprove = async function () {
    const id = document.getElementById('approveSpotId').value;
    const btn = document.getElementById('confirmApproveBtn');
    const icon = document.getElementById('approveBtnIcon');
    const spinner = document.getElementById('approveBtnSpinner');
    const label = document.getElementById('approveBtnLabel');
    const cancelBtn = document.getElementById('cancelApproveBtn');

    if (btn) { btn.disabled = true; if (cancelBtn) cancelBtn.disabled = true; if (icon) icon.style.display = 'none'; if (spinner) spinner.style.display = 'inline-block'; if (label) label.textContent = 'Approving...'; }

    try {
        const baseUrl = window.API_CONFIG?.BASE_URL || (`http://${window.location.hostname || '127.0.0.1'}:8000`);
        const apiRolePrefix = window.userRole === 'picto' ? 'pitco' : 'lupto';
        const res = await window.API_CONFIG.post(`${baseUrl}/api/${apiRolePrefix}/dashboard/approve-spot`, { id: parseInt(id) });
        if (res && res.success) {
            // Update local memory data instantly
            const spotId = parseInt(id);
            [window.touristSpotsData, window.touristSpotsAll].forEach(arr => {
                if (arr) {
                    const spot = arr.find(s => s.id === spotId);
                    if (spot) {
                        spot.status = 'approved';
                        spot.approved_by = res.approved_by ?? null;
                        spot.approved_at = res.approved_at ?? null;
                        // Store approver name if user info available
                        if (!spot.approver) spot.approver = {};
                        if (window.currentUserName) spot.approver.name = window.currentUserName;
                        if (window.municipalitiesData) {
                            const muni = window.municipalitiesData.find(m => m.id === spot.municipality_id);
                            if (muni) muni.attraction_count = (muni.attraction_count || 0) + 1;
                        }
                    }
                }
            });

            sortSpotsPendingFirst(window.touristSpotsData);
            sortSpotsPendingFirst(window.touristSpotsAll);

            // Re-render local views instantly
            if (typeof renderCardsGrid === 'function' && window.touristSpotsData) {
                renderCardsGrid(window.touristSpotsData);
            }
            if (typeof renderTableRows === 'function' && window.touristSpotsData) {
                renderTableRows(window.touristSpotsData);
            }
            if (typeof updateKpiCards === 'function' && window.touristSpotsData && window.municipalitiesData) {
                updateKpiCards(window.touristSpotsData, window.municipalitiesData);
            }
            if (document.getElementById('touristMap') && typeof initMap === 'function' && window.touristSpotsData && window.municipalitiesData) {
                initMap(window.touristSpotsData, window.municipalitiesData);
            }
            if (document.getElementById('lupto-map') && typeof window.refreshLuptoMap === 'function') {
                window.refreshLuptoMap();
            }

            closeApproveModal();

            if (typeof window.notifyTouristSpotChanged === 'function') {
                await window.notifyTouristSpotChanged();
            }
            showToast('Tourist spot approved successfully!', 'success');
        } else {
            showToast(res?.error || 'Failed to approve', 'danger');
        }
    } catch (err) {
        showToast(`Error: ${err.message || 'Failed to approve'}`, 'danger');
    } finally {
        if (btn) { btn.disabled = false; if (cancelBtn) cancelBtn.disabled = false; if (icon) icon.style.display = 'inline-block'; if (spinner) spinner.style.display = 'none'; if (label) label.textContent = 'Yes, Approve'; }
    }
};

window.openDeclineModal = function (id) {
    document.getElementById('declineSpotId').value = id;
    document.getElementById('declineReason').value = '';
    document.getElementById('declineReasonCount').textContent = '0';
    document.getElementById('declineModal').classList.add('active');
};

window.closeDeclineModal = function () {
    document.getElementById('declineModal').classList.remove('active');
};

window.confirmDecline = async function () {
    const id = document.getElementById('declineSpotId').value;
    const reason = document.getElementById('declineReason').value.trim();
    if (!reason) {
        showToast('Please provide a reason for rejection.', 'danger');
        return;
    }
    const btn = document.getElementById('confirmDeclineBtn');
    const icon = document.getElementById('declineBtnIcon');
    const spinner = document.getElementById('declineBtnSpinner');
    const label = document.getElementById('declineBtnLabel');
    if (btn) { btn.disabled = true; if (icon) icon.style.display = 'none'; if (spinner) spinner.style.display = 'inline-block'; if (label) label.textContent = 'Submitting...'; }

    try {
        const baseUrl = window.API_CONFIG?.BASE_URL || (`http://${window.location.hostname || '127.0.0.1'}:8000`);
        const apiRolePrefix = window.userRole === 'picto' ? 'pitco' : 'lupto';
        const res = await window.API_CONFIG.post(`${baseUrl}/api/${apiRolePrefix}/dashboard/reject-spot`, { id: parseInt(id), rejection_reason: reason });
        if (res && res.success) {
            // Update local memory data instantly
            const spotId = parseInt(id);
            [window.touristSpotsData, window.touristSpotsAll].forEach(arr => {
                if (arr) {
                    const spot = arr.find(s => s.id === spotId);
                    if (spot) {
                        spot.status = 'rejected';
                        spot.rejection_reason = reason;
                    }
                }
            });

            sortSpotsPendingFirst(window.touristSpotsData);
            sortSpotsPendingFirst(window.touristSpotsAll);

            // Re-render local views instantly
            if (typeof renderCardsGrid === 'function' && window.touristSpotsData) {
                renderCardsGrid(window.touristSpotsData);
            }
            if (typeof renderTableRows === 'function' && window.touristSpotsData) {
                renderTableRows(window.touristSpotsData);
            }
            if (typeof updateKpiCards === 'function' && window.touristSpotsData && window.municipalitiesData) {
                updateKpiCards(window.touristSpotsData, window.municipalitiesData);
            }
            if (document.getElementById('touristMap') && typeof initMap === 'function' && window.touristSpotsData && window.municipalitiesData) {
                initMap(window.touristSpotsData, window.municipalitiesData);
            }
            if (document.getElementById('lupto-map') && typeof window.refreshLuptoMap === 'function') {
                window.refreshLuptoMap();
            }

            closeDeclineModal();

            if (typeof window.notifyTouristSpotChanged === 'function') {
                await window.notifyTouristSpotChanged();
            }
            showToast('Tourist spot declined.', 'success');
        } else {
            showToast(res?.error || 'Failed to decline', 'danger');
        }
    } catch (err) {
        showToast(`Error: ${err.message || 'Failed to decline'}`, 'danger');
    } finally {
        if (btn) { btn.disabled = false; if (icon) icon.style.display = 'inline-block'; if (spinner) spinner.style.display = 'none'; if (label) label.textContent = 'Submit'; }
    }
};

function initDeclineModal() {
    document.getElementById('cancelDeclineBtn')?.addEventListener('click', closeDeclineModal);
    document.getElementById('confirmDeclineBtn')?.addEventListener('click', confirmDecline);
    document.getElementById('declineModal')?.addEventListener('click', e => { if (e.target.id === 'declineModal') closeDeclineModal(); });
    document.getElementById('declineReason')?.addEventListener('input', function () {
        document.getElementById('declineReasonCount').textContent = this.value.length;
    });

    // Approve modal listeners
    document.getElementById('cancelApproveBtn')?.addEventListener('click', closeApproveModal);
    document.getElementById('confirmApproveBtn')?.addEventListener('click', confirmApprove);
    document.getElementById('approveConfirmModal')?.addEventListener('click', e => { if (e.target.id === 'approveConfirmModal') closeApproveModal(); });
}

window.initDeclineModal = initDeclineModal;