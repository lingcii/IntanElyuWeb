// LUPTO Provincial Tourism Operations - Dashboard Application React Component
// Compiled in-browser via Babel Standalone

const { useState, useEffect, useRef, useCallback } = React;

// JSON mock data fallback (mirrors gawat.sql seed)
const LUPTO_MOCK_DATA = {
    kpis: { totalTouristSpots: 847, activeUsers: 9, monthlyVisits: 45200, systemUptime: '99.95%' },
    municipalities: [
        { id: 1, name: 'San Juan', latitude: 16.6644, longitude: 120.3208, attraction_count: 130, categories: [{ category: 'Beach', count: 45 }, { category: 'Adventure', count: 30 }] },
        { id: 2, name: 'San Fernando City', latitude: 16.6156, longitude: 120.3167, attraction_count: 110, categories: [{ category: 'Religious', count: 20 }] },
        { id: 3, name: 'Bauang', latitude: 16.5297, longitude: 120.3308, attraction_count: 90, categories: [{ category: 'Beach', count: 35 }] },
        { id: 4, name: 'Agoo', latitude: 16.3217, longitude: 120.3683, attraction_count: 75, categories: [{ category: 'Religious', count: 15 }] },
        { id: 5, name: 'Luna', latitude: 16.8525, longitude: 120.3797, attraction_count: 68, categories: [{ category: 'Beach', count: 25 }] },
        { id: 6, name: 'San Gabriel', latitude: 16.6667, longitude: 120.4167, attraction_count: 58, categories: [{ category: 'Waterfalls', count: 18 }] },
        { id: 7, name: 'Balaoan', latitude: 16.8244, longitude: 120.4003, attraction_count: 45, categories: [] },
        { id: 8, name: 'Aringay', latitude: 16.3956, longitude: 120.3547, attraction_count: 38, categories: [] },
        { id: 9, name: 'Rosario', latitude: 16.2300, longitude: 120.4864, attraction_count: 35, categories: [] },
        { id: 10, name: 'Bacnotan', latitude: 16.7264, longitude: 120.3519, attraction_count: 32, categories: [] },
        { id: 11, name: 'Naguilian', latitude: 16.5367, longitude: 120.3953, attraction_count: 30, categories: [] },
        { id: 12, name: 'Tubao', latitude: 16.3475, longitude: 120.4342, attraction_count: 27, categories: [] },
        { id: 13, name: 'Pugo', latitude: 16.3267, longitude: 120.4828, attraction_count: 24, categories: [] },
        { id: 14, name: 'Caba', latitude: 16.4294, longitude: 120.3503, attraction_count: 22, categories: [] },
        { id: 15, name: 'Santo Tomas', latitude: 16.2731, longitude: 120.3847, attraction_count: 20, categories: [] },
        { id: 16, name: 'Bangar', latitude: 16.8967, longitude: 120.4181, attraction_count: 18, categories: [] },
        { id: 17, name: 'Burgos', latitude: 16.5208, longitude: 120.4850, attraction_count: 15, categories: [] },
        { id: 18, name: 'Bagulin', latitude: 16.6067, longitude: 120.4503, attraction_count: 12, categories: [] },
        { id: 19, name: 'Santol', latitude: 16.7667, longitude: 120.4667, attraction_count: 11, categories: [] },
        { id: 20, name: 'Sudipen', latitude: 16.9022, longitude: 120.4447, attraction_count: 10, categories: [] }
    ],
    systemStatuses: [
        { id: 1, service_name: 'Database Service (MySQL)', status: 'online', uptime: '99.98%', last_checked: new Date().toISOString() },
        { id: 2, service_name: 'User Management Control', status: 'online', uptime: '99.95%', last_checked: new Date().toISOString() },
        { id: 3, service_name: 'Leaflet.js Mapping Integration', status: 'online', uptime: '100%', last_checked: new Date().toISOString() },
        { id: 4, service_name: 'Analytics Engine (YoY Reporting)', status: 'online', uptime: '99.90%', last_checked: new Date().toISOString() }
    ],
    users: [
        { id: 1, name: 'PITCO Super Admin', email: 'pitco@gawat.com', role: 'pitco', status: 'active', last_activity: new Date().toISOString() },
        { id: 2, name: 'LUPTO Provincial Admin', email: 'lupto@gawat.com', role: 'lupto', status: 'active', last_activity: new Date(Date.now() - 300000).toISOString() },
        { id: 3, name: 'San Juan MTO Officer', email: 'municipal@gawat.com', role: 'municipal', status: 'active', last_activity: new Date(Date.now() - 900000).toISOString() },
        { id: 4, name: 'Luna MTO Officer', email: 'luna@gawat.com', role: 'municipal', status: 'active', last_activity: new Date(Date.now() - 7200000).toISOString() },
        { id: 5, name: 'Juan dela Cruz (Tourist)', email: 'tourist@gawat.com', role: 'tourist', status: 'active', last_activity: new Date(Date.now() - 86400000).toISOString() },
        { id: 6, name: 'Inactive MTO User', email: 'inactive@gawat.com', role: 'municipal', status: 'inactive', last_activity: new Date(Date.now() - 2592000000).toISOString() },
        { id: 7, name: 'Maria Santos', email: 'maria@gawat.com', role: 'tourist', status: 'active', last_activity: new Date().toISOString() },
        { id: 8, name: 'John Doe', email: 'johndoe@gawat.com', role: 'tourist', status: 'active', last_activity: new Date(Date.now() - 14400000).toISOString() }
    ],
    pendingSpots: [
        { id: 1, name: 'Urbiztondo Surf Spot', municipality_name: 'San Juan', category: 'Beach', entrance_fee: 50, status: 'pending', description: 'The surfing capital of Northern Luzon.' },
        { id: 2, name: 'Tangadan Falls Adventure', municipality_name: 'San Gabriel', category: 'Waterfalls', entrance_fee: 30, status: 'pending', description: 'Cold spring waterfall popular for cliff jumping.' },
        { id: 3, name: 'Luna Baluarte Watchtower', municipality_name: 'Luna', category: 'Historical', entrance_fee: 0, status: 'pending', description: 'Spanish-era watchtower on pebble shores.' },
        { id: 4, name: 'Ma-Cho Temple Sanctuary', municipality_name: 'San Fernando City', category: 'Religious', entrance_fee: 0, status: 'pending', description: 'Grand Taoist temple overlooking the bay.' },
        { id: 5, name: 'Lomboy Grape Farm Tour', municipality_name: 'Bauang', category: 'Farm', entrance_fee: 100, status: 'pending', description: 'Pioneer grape farm with picking experience.' },
        { id: 6, name: 'Pugo Adventure (Pugad)', municipality_name: 'Pugo', category: 'Adventure', entrance_fee: 250, status: 'pending', description: 'Ziplines, pool slides, and ATV tours.' },
        { id: 7, name: 'Bakas ng Higante Rock', municipality_name: 'Bagulin', category: 'Mountain', entrance_fee: 20, status: 'pending', description: 'Mythical giant footprint on rock formation.' },
        { id: 8, name: 'Agoo Eco-Park Reserve', municipality_name: 'Agoo', category: 'Beach', entrance_fee: 20, status: 'pending', description: 'Pine-lined coastal eco-tourism park.' },
        { id: 9, name: 'Immuki Island Lagoon', municipality_name: 'Balaoan', category: 'Beach', entrance_fee: 50, status: 'pending', description: 'Crystal-clear lagoons for snorkeling.' },
        { id: 10, name: 'Bangar Loom Weaving Center', municipality_name: 'Bangar', category: 'Historical', entrance_fee: 0, status: 'pending', description: 'Traditional Abel Iloco weaving demonstrations.' },
        { id: 11, name: 'Aringay Centenary Bridge', municipality_name: 'Aringay', category: 'Historical', entrance_fee: 0, status: 'pending', description: 'Abandoned Spanish-era rail bridge.' },
        { id: 12, name: 'Mt. Bulik Ridge Peak', municipality_name: 'Burgos', category: 'Mountain', entrance_fee: 50, status: 'pending', description: 'Challenging hike with mountain range views.' },
        { id: 13, name: 'Occalong Waterfalls', municipality_name: 'Santol', category: 'Waterfalls', entrance_fee: 20, status: 'pending', description: 'Serene cascade with deep swimming pools.' },
        { id: 14, name: 'Damortis Protected Landscape', municipality_name: 'Aringay', category: 'Mountain', entrance_fee: 15, status: 'pending', description: 'Coastal mangrove and bird sanctuary.' },
        { id: 15, name: 'Caba Beach Cove', municipality_name: 'Caba', category: 'Beach', entrance_fee: 25, status: 'pending', description: 'Hidden cove with calm family-friendly waters.' }
    ],
    analytics: {
        monthlyTrends: [
            { year: 2025, month: 1, total_visits: 28000 }, { year: 2025, month: 6, total_visits: 22000 },
            { year: 2026, month: 1, total_visits: 31000 }, { year: 2026, month: 6, total_visits: 45200 }
        ],
        topSpots: [
            { id: 16, name: 'Pebble Beach of Luna', municipality_name: 'Luna', category: 'Beach', rating: 4.7, visits: 14300 },
            { id: 17, name: 'Bauang Beach Resorts', municipality_name: 'Bauang', category: 'Beach', rating: 4.4, visits: 12500 },
            { id: 19, name: 'Basilica of Our Lady of Charity', municipality_name: 'Agoo', category: 'Religious', rating: 4.7, visits: 11200 },
            { id: 20, name: 'Red Clay Pagdamilian', municipality_name: 'San Juan', category: 'Farm', rating: 4.5, visits: 9800 },
            { id: 18, name: 'Tapuakan River', municipality_name: 'Pugo', category: 'Adventure', rating: 4.6, visits: 8800 }
        ],
        transportData: { car: 23504, bus: 5876, van: 12204, other: 3616 },
        rankings: [
            { name: 'San Juan', total_visits: 128000, avg_spend: 1350 },
            { name: 'San Fernando City', total_visits: 98000, avg_spend: 1100 },
            { name: 'Bauang', total_visits: 72000, avg_spend: 893 },
            { name: 'Agoo', total_visits: 58000, avg_spend: 819 },
            { name: 'Luna', total_visits: 54000, avg_spend: 966 },
            { name: 'San Gabriel', total_visits: 42000, avg_spend: 788 }
        ],
        costBreakdown: [
            { name: 'San Juan', avg_spend: 1350, total_visits: 128000, estimated_revenue: 172800000 },
            { name: 'San Fernando City', avg_spend: 1100, total_visits: 98000, estimated_revenue: 107800000 },
            { name: 'Bauang', avg_spend: 893, total_visits: 72000, estimated_revenue: 64296000 }
        ],
        municipalityVisits: [
            { name: 'San Juan', total_visits: 12800 }, { name: 'San Fernando City', total_visits: 4200 },
            { name: 'Bauang', total_visits: 3500 }, { name: 'Agoo', total_visits: 2900 },
            { name: 'Luna', total_visits: 3300 }, { name: 'San Gabriel', total_visits: 2800 }
        ]
    }
};

function DashboardApp() {
    // 1. Core State
    const [activeTab, setActiveTab] = useState(window.INITIAL_TAB || 'dashboard');
    const [kpis, setKpis] = useState({
        totalTouristSpots: 847,
        activeUsers: 8,
        monthlyVisits: 45200,
        systemUptime: '99.95%'
    });
    const [municipalities, setMunicipalities] = useState([]);
    const [touristSpots, setTouristSpots] = useState([]);
    const [systemStatuses, setSystemStatuses] = useState([]);
    const [alerts, setAlerts] = useState([]);
    
    // Sub-modules states
    const [users, setUsers] = useState([]);
    const [roleStats, setRoleStats] = useState([]);
    const [pendingSpots, setPendingSpots] = useState([]);
    const [analytics, setAnalytics] = useState(null);
    
    // UI Filters and Selections
    const [userSearch, setUserSearch] = useState('');
    const [userRoleFilter, setUserRoleFilter] = useState('');
    const [userStatusFilter, setUserStatusFilter] = useState('');
    
    const [pendingSearch, setPendingSearch] = useState('');
    const [pendingMuniFilter, setPendingMuniFilter] = useState('');
    const [pendingCatFilter, setPendingCatFilter] = useState('');
    const [pendingStatusFilter, setPendingStatusFilter] = useState('pending');
    const [selectedPendingIds, setSelectedPendingIds] = useState([]);
    
    const [analyticsYear, setAnalyticsYear] = useState('2026');
    const [selectedMuniDetails, setSelectedMuniDetails] = useState(null);
    
    // Leaflet map layer settings
    const [mapSearch, setMapSearch] = useState('');
    const [mapHeatmapMode, setMapHeatmapMode] = useState(false);
    const [mapTileLayer, setMapTileLayer] = useState('street'); // street, satellite, terrain
    
    // Loading & Modal States
    const [loading, setLoading] = useState(true);
    const [actionLoading, setActionLoading] = useState(false);
    const [error, setError] = useState(null);
    
    const [isEditUserOpen, setIsEditUserOpen] = useState(false);
    const [isResetPasswordOpen, setIsResetPasswordOpen] = useState(false);
    const [isFullscreenMapOpen, setIsFullscreenMapOpen] = useState(false);
    const [selectedUser, setSelectedUser] = useState(null);
    const [resetPasswordVal, setResetPasswordVal] = useState('');
    const [editUserRole, setEditUserRole] = useState('municipal');
    const [editUserStatus, setEditUserStatus] = useState('active');

    // Leaflet Map Refs
    const dashboardMapRef = useRef(null);
    const dedicatedMapRef = useRef(null);
    const modalMapRef = useRef(null);
    
    // Chart Refs
    const yoyChartRef = useRef(null);
    const transportChartRef = useRef(null);
    const muniBarChartRef = useRef(null);

    const applyMockData = useCallback(() => {
        setKpis(LUPTO_MOCK_DATA.kpis);
        setMunicipalities(LUPTO_MOCK_DATA.municipalities);
        setSystemStatuses(LUPTO_MOCK_DATA.systemStatuses);
        setUsers(LUPTO_MOCK_DATA.users);
        setPendingSpots(LUPTO_MOCK_DATA.pendingSpots);
        setAnalytics(LUPTO_MOCK_DATA.analytics);
    }, []);

    const TABS = ['dashboard', 'map-view', 'users', 'tourist-spots', 'analytics'];

    // ── In-memory cache — avoids re-fetching when switching back to a visited tab ──
    const _cache = useRef({});
    const CACHE_TTL_MS = 2 * 60 * 1000; // 2 minutes

    const _isCached = (key) => {
        const entry = _cache.current[key];
        return entry && (Date.now() - entry.ts) < CACHE_TTL_MS;
    };

    // 2. Fetch Initial Dashboard Data
    const fetchDashboardData = async (force = false) => {
        if (!force && _isCached('dashboard')) return; // already fresh
        try {
            setLoading(true);
            const response = await fetch('${window.API_CONFIG?.BASE_URL || 'http://' + window.location.hostname + ':8000'}/api/lupto/dashboard', { credentials: 'include' });
            if (!response.ok) throw new Error('Failed to fetch dashboard data');
            const data = await response.json();

            if (data.kpis) setKpis(data.kpis);
            if (data.municipalities) setMunicipalities(data.municipalities);
            if (data.touristSpots) setTouristSpots(data.touristSpots);
            if (data.systemStatuses) setSystemStatuses(data.systemStatuses);
            if (data.alerts) setAlerts(data.alerts);
            setError(null);
            _cache.current['dashboard'] = { ts: Date.now() };
        } catch (err) {
            console.error(err);
            applyMockData();
            setError('Could not connect to the database. Displaying sample data from gawat.sql mock configuration.');
        } finally {
            setLoading(false);
        }
    };

    // Fetch User Management data
    const fetchUsersData = async (force = false) => {
        if (!force && _isCached('users')) return;
        try {
            const response = await fetch('${window.API_CONFIG?.BASE_URL || 'http://' + window.location.hostname + ':8000'}/api/lupto/users', { credentials: 'include' });
            if (!response.ok) throw new Error('Failed to fetch users');
            const data = await response.json();
            setUsers(data.users || []);
            setRoleStats(data.roleStats || []);
            _cache.current['users'] = { ts: Date.now() };
        } catch (err) {
            console.error('Error fetching users:', err);
            setUsers(LUPTO_MOCK_DATA.users);
        }
    };

    // Fetch tourist spots approval queue (filterable by status)
    const fetchSpots = async (status = pendingStatusFilter, force = false) => {
        const cacheKey = 'spots_' + status;
        if (!force && _isCached(cacheKey)) return;
        try {
            const response = await fetch(`${window.API_CONFIG?.BASE_URL || 'http://' + window.location.hostname + ':8000'}/api/lupto/tourist-spots?status=${status}`, { credentials: 'include' });
            if (!response.ok) throw new Error('Failed to fetch spots');
            const data = await response.json();
            setPendingSpots(data || []);
            setSelectedPendingIds([]);
            _cache.current[cacheKey] = { ts: Date.now() };
        } catch (err) {
            console.error('Error fetching spots:', err);
            const mock = status === 'all'
                ? [...LUPTO_MOCK_DATA.pendingSpots, { id: 16, name: 'Pebble Beach of Luna', municipality_name: 'Luna', category: 'Beach', entrance_fee: 0, status: 'approved', description: 'Approved spot.' }]
                : LUPTO_MOCK_DATA.pendingSpots.filter(s => status === 'all' || s.status === status);
            setPendingSpots(mock);
        }
    };

    // Fetch Analytics
    const fetchAnalyticsData = async (force = false) => {
        if (!force && _isCached('analytics')) return;
        try {
            const response = await fetch('${window.API_CONFIG?.BASE_URL || 'http://' + window.location.hostname + ':8000'}/api/lupto/analytics/full', { credentials: 'include' });
            if (!response.ok) throw new Error('Failed to fetch analytics');
            const data = await response.json();
            setAnalytics(data);
            _cache.current['analytics'] = { ts: Date.now() };
        } catch (err) {
            console.error('Error fetching analytics:', err);
            setAnalytics(LUPTO_MOCK_DATA.analytics);
        }
    };

    // Load data based on active tab.
    // map-view shares the same dashboard data — no separate fetch needed.
    useEffect(() => {
        if (activeTab === 'dashboard') fetchDashboardData();
        // map-view reuses dashboard municipalities — only fetch if not already loaded
        if (activeTab === 'map-view') fetchDashboardData();
        if (activeTab === 'users') fetchUsersData();
        if (activeTab === 'tourist-spots') fetchSpots(pendingStatusFilter);
        if (activeTab === 'analytics') fetchAnalyticsData();
    }, [activeTab]);

    useEffect(() => {
        if (activeTab === 'tourist-spots') fetchSpots(pendingStatusFilter);
    }, [pendingStatusFilter]);

    // Handle tab switching from window events (e.g. sidebar links triggers)
    useEffect(() => {
        const handleTabChange = (e) => {
            if (e.detail) setActiveTab(e.detail);
        };
        window.addEventListener('changeTab', handleTabChange);
        return () => window.removeEventListener('changeTab', handleTabChange);
    }, []);

    // Keyboard navigation for tabs (ArrowLeft / ArrowRight)
    useEffect(() => {
        const handleKeyDown = (e) => {
            if (!['ArrowLeft', 'ArrowRight'].includes(e.key)) return;
            const idx = TABS.indexOf(activeTab);
            if (idx === -1) return;
            const next = e.key === 'ArrowRight'
                ? TABS[(idx + 1) % TABS.length]
                : TABS[(idx - 1 + TABS.length) % TABS.length];
            setActiveTab(next);
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [activeTab]);

    // 3. Initialize Leaflet Map Utility
    const initLeafletMap = (mapElementId, municipalitiesList, heatmap, tileType, refStorage) => {
        if (!window.L || !document.getElementById(mapElementId)) return;
        
        // Remove existing map if any
        if (refStorage.current) {
            refStorage.current.remove();
            refStorage.current = null;
        }

        const laUnionPoints = municipalitiesList
            .map((municipality) => [parseFloat(municipality.latitude), parseFloat(municipality.longitude)])
            .filter(([lat, lng]) => Number.isFinite(lat) && Number.isFinite(lng));
        const laUnionBounds = laUnionPoints.length > 0
            ? L.latLngBounds(laUnionPoints).pad(0.08)
            : L.latLngBounds([[16.23, 120.30], [16.91, 120.49]]);
        const map = L.map(mapElementId, {
            maxBounds: laUnionBounds.pad(0.08),
            maxBoundsViscosity: 1.0,
            minZoom: 10,
            worldCopyJump: false
        });
        refStorage.current = map;

        // Base Tile Layers
        const streetTile = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        });

        const satelliteTile = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
        });

        const terrainTile = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
            attribution: 'Tiles &copy; OpenTopoMap &copy; OpenStreetMap contributors'
        });

        // Set active tile
        if (tileType === 'satellite') {
            satelliteTile.addTo(map);
        } else if (tileType === 'terrain') {
            terrainTile.addTo(map);
        } else {
            streetTile.addTo(map);
        }
        map.fitBounds(laUnionBounds);

        // Add layer control manually for nice UI
        const baseMaps = {
            "Streets": streetTile,
            "Satellite": satelliteTile,
            "Terrain": terrainTile
        };
        L.control.layers(baseMaps).addTo(map);

        // Add Municipality Markers or Heatmap circles
        municipalitiesList.forEach(m => {
            const lat = parseFloat(m.latitude);
            const lng = parseFloat(m.longitude);
            const count = parseInt(m.attraction_count);

            if (heatmap) {
                // Draw heatmap style overlays
                const heatRadius = count * 90; // scale size
                const circle = L.circle([lat, lng], {
                    color: count >= 40 ? '#1e40af' : (count >= 25 ? '#3b82f6' : '#93c5fd'),
                    fillColor: count >= 40 ? '#1d4ed8' : (count >= 25 ? '#60a5fa' : '#bfdbfe'),
                    fillOpacity: 0.5,
                    radius: heatRadius
                }).addTo(map);

                circle.bindPopup(`
                    <div class="lupto-popup-content">
                        <h4 class="lupto-popup-title">${m.name}</h4>
                        <p class="lupto-popup-info"><strong>Heat Intensity:</strong> ${count} Attractions</p>
                    </div>
                `);
            } else {
                // Pin color based on attraction gradient
                let color = 'var(--pin-pale-blue)';
                let textColor = '#1e293b';
                if (count >= 40) {
                    color = 'var(--pin-dark-blue)';
                    textColor = '#ffffff';
                } else if (count >= 25) {
                    color = 'var(--pin-light-blue)';
                    textColor = '#1e3a8a';
                }

                // Sizing of pins based on attraction density
                const size = count >= 40 ? 38 : (count >= 25 ? 32 : 26);

                const icon = L.divIcon({
                    html: `<div class="lupto-map-marker" style="background:${color}; width:${size}px; height:${size}px; line-height:${size-4}px; color:${textColor}; border: 2px solid white;">${count}</div>`,
                    className: 'custom-div-icon',
                    iconSize: [size, size]
                });

                const marker = L.marker([lat, lng], { icon: icon }).addTo(map);

                // Zoom to municipality on marker click
                marker.on('click', () => {
                    map.setView([lat, lng], 12);
                });

                // Detail popup
                marker.bindPopup(`
                    <div class="lupto-popup-content">
                        <h4 class="lupto-popup-title">${m.name} Municipality</h4>
                        <p class="lupto-popup-info">Total attractions: <strong>${count} Spots</strong></p>
                        <button class="lupto-popup-btn" onclick="window.dispatchMuniDetails('${m.name}')">View Details</button>
                    </div>
                `);
            }
        });
    };

    // Dispatcher for popup button clicks in Leaflet
    useEffect(() => {
        window.dispatchMuniDetails = (name) => {
            const muni = municipalities.find(m => m.name === name);
            if (muni) {
                setSelectedMuniDetails(muni);
            }
        };
        return () => {
            delete window.dispatchMuniDetails;
        };
    }, [municipalities]);

    // Handle maps rendering based on state
    useEffect(() => {
        if (!loading && municipalities.length > 0) {
            if (activeTab === 'dashboard') {
                setTimeout(() => {
                    initLeafletMap('dashboard-map', municipalities, false, 'street', dashboardMapRef);
                }, 100);
            } else if (activeTab === 'map-view') {
                // Filter municipalities in map search
                const filteredMunis = municipalities.filter(m => 
                    m.name.toLowerCase().includes(mapSearch.toLowerCase())
                );
                setTimeout(() => {
                    initLeafletMap('dedicated-map', filteredMunis, mapHeatmapMode, mapTileLayer, dedicatedMapRef);
                }, 100);
            }
        }
    }, [activeTab, municipalities, mapSearch, mapHeatmapMode, mapTileLayer, loading]);

    // Fullscreen Map Modal Map Loader
    useEffect(() => {
        if (isFullscreenMapOpen && municipalities.length > 0) {
            setTimeout(() => {
                initLeafletMap('fullscreen-modal-map', municipalities, mapHeatmapMode, mapTileLayer, modalMapRef);
            }, 300);
        }
    }, [isFullscreenMapOpen, mapHeatmapMode, mapTileLayer]);

    // 4. Initialize Analytics Charts using Chart.js
    useEffect(() => {
        if (activeTab === 'analytics' && analytics) {
            // Destroy existing charts to prevent canvas reuse errors
            if (yoyChartRef.current) yoyChartRef.current.destroy();
            if (transportChartRef.current) transportChartRef.current.destroy();

            // Prepare YoY monthly comparison chart
            const yoyCtx = document.getElementById('analytics-yoy-chart')?.getContext('2d');
            if (yoyCtx) {
                const monthsNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                
                // Aggregate visits per month
                const visits2025 = Array(12).fill(0);
                const visits2026 = Array(12).fill(0);

                analytics.monthlyTrends.forEach(item => {
                    const mIdx = item.month - 1;
                    if (item.year === 2025) visits2025[mIdx] += parseInt(item.total_visits);
                    if (item.year === 2026) visits2026[mIdx] += parseInt(item.total_visits);
                });

                yoyChartRef.current = new Chart(yoyCtx, {
                    type: 'line',
                    data: {
                        labels: monthsNames,
                        datasets: [
                            {
                                label: '2025 Visits (YoY Basis)',
                                data: visits2025,
                                borderColor: '#94a3b8',
                                backgroundColor: 'rgba(148, 163, 184, 0.1)',
                                borderDash: [5, 5],
                                tension: 0.3,
                                fill: true
                            },
                            {
                                label: '2026 Visits (Current Growth)',
                                data: visits2026,
                                borderColor: '#185FA5',
                                backgroundColor: 'rgba(24, 95, 165, 0.2)',
                                borderWidth: 3,
                                tension: 0.3,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top' }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: (val) => val.toLocaleString()
                                }
                            }
                        }
                    }
                });
            }

            // Prepare transportation breakdown doughnut chart
            const transCtx = document.getElementById('analytics-transport-chart')?.getContext('2d');
            if (transCtx && analytics.transportData) {
                const tData = analytics.transportData;
                transportChartRef.current = new Chart(transCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Private Cars', 'Tour Buses', 'Vans / Vans-Utility', 'Other / Tricycle / Walk'],
                        datasets: [{
                            data: [parseInt(tData.car), parseInt(tData.bus), parseInt(tData.van), parseInt(tData.other)],
                            backgroundColor: ['#185FA5', '#85B7EB', '#B5D4F4', '#e2e8f0'],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'right' }
                        }
                    }
                });
            }

            // Municipality visits bar chart (June 2026)
            const muniCtx = document.getElementById('analytics-muni-bar-chart')?.getContext('2d');
            if (muniCtx && analytics.municipalityVisits) {
                if (muniBarChartRef.current) muniBarChartRef.current.destroy();
                const muniData = analytics.municipalityVisits.slice(0, 10);
                muniBarChartRef.current = new Chart(muniCtx, {
                    type: 'bar',
                    data: {
                        labels: muniData.map(m => m.name),
                        datasets: [{
                            label: 'Monthly Visits (June)',
                            data: muniData.map(m => parseInt(m.total_visits)),
                            backgroundColor: '#85B7EB',
                            borderColor: '#185FA5',
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { callback: (val) => val.toLocaleString() }
                            }
                        }
                    }
                });
            }
        }
    }, [activeTab, analytics]);

    const getTransportPercentages = () => {
        if (!analytics?.transportData) return { car: 52, bus: 13, van: 27, other: 8 };
        const t = analytics.transportData;
        const total = parseInt(t.car) + parseInt(t.bus) + parseInt(t.van) + parseInt(t.other);
        if (total === 0) return { car: 0, bus: 0, van: 0, other: 0 };
        return {
            car: Math.round((parseInt(t.car) / total) * 100),
            bus: Math.round((parseInt(t.bus) / total) * 100),
            van: Math.round((parseInt(t.van) / total) * 100),
            other: Math.round((parseInt(t.other) / total) * 100)
        };
    };

    // 5. User Management Functions
    const handleOpenEditUser = (user) => {
        setSelectedUser(user);
        setEditUserRole(user.role);
        setEditUserStatus(user.status);
        setIsEditUserOpen(true);
    };

    const handleUpdateUser = async () => {
        if (!selectedUser) return;
        setActionLoading(true);
        try {
            const response = await fetch(`${window.API_CONFIG?.BASE_URL || 'http://' + window.location.hostname + ':8000'}/api/lupto/users/${selectedUser.id}`, {
                method: 'PUT',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    role: editUserRole,
                    status: editUserStatus
                })
            });
            const res = await response.json();
            if (res.success) {
                // update local state
                setUsers(users.map(u => u.id === selectedUser.id ? { ...u, role: editUserRole, status: editUserStatus } : u));
                setIsEditUserOpen(false);
            } else {
                alert(res.error || 'Failed to update user');
            }
        } catch (err) {
            console.error(err);
            alert('Error updating user');
        } finally {
            setActionLoading(false);
        }
    };

    const handleOpenResetPassword = (user) => {
        setSelectedUser(user);
        setResetPasswordVal('');
        setIsResetPasswordOpen(true);
    };

    const handleResetPassword = async () => {
        if (!selectedUser || !resetPasswordVal.trim()) return;
        setActionLoading(true);
        try {
            const response = await fetch(`${window.API_CONFIG?.BASE_URL || 'http://' + window.location.hostname + ':8000'}/api/lupto/users/${selectedUser.id}/password`, {
                method: 'PATCH',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    password: resetPasswordVal
                })
            });
            const res = await response.json();
            if (res.success) {
                alert(`Password for user ${selectedUser.name} successfully reset!`);
                setIsResetPasswordOpen(false);
            } else {
                alert(res.error || 'Failed to reset password');
            }
        } catch (err) {
            console.error(err);
            alert('Error resetting password');
        } finally {
            setActionLoading(false);
        }
    };

    const handleToggleUserStatus = async (user) => {
        const nextStatus = user.status === 'active' ? 'inactive' : 'active';
        setActionLoading(true);
        try {
            const response = await fetch(`${window.API_CONFIG?.BASE_URL || 'http://' + window.location.hostname + ':8000'}/api/lupto/users/${user.id}`, {
                method: 'PUT',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    role: user.role,
                    status: nextStatus
                })
            });
            const res = await response.json();
            if (res.success) {
                setUsers(users.map(u => u.id === user.id ? { ...u, status: nextStatus } : u));
            } else {
                alert(res.error || 'Failed to change status');
            }
        } catch (err) {
            console.error(err);
            alert('Error updating status');
        } finally {
            setActionLoading(false);
        }
    };

    // 6. Approvals Queue Functions
    const handleApproveSpot = async (id) => {
        if (!confirm('Are you sure you want to approve this tourist spot attraction?')) return;
        setActionLoading(true);
        try {
            const response = await fetch('${window.API_CONFIG?.BASE_URL || 'http://' + window.location.hostname + ':8000'}/api/lupto/dashboard/approve-spot', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            const res = await response.json();
            if (res.success) {
                setPendingSpots(pendingSpots.filter(s => s.id !== id));
                // Refresh dashboard KPI
                fetchDashboardData();
            } else {
                alert(res.error || 'Failed to approve');
            }
        } catch (err) {
            console.error(err);
        } finally {
            setActionLoading(false);
        }
    };

    const handleRejectSpot = async (id) => {
        const reason = prompt('Please enter a reason for rejection:');
        if (!reason || !reason.trim()) {
            alert('Rejection reason is required.');
            return;
        }
        setActionLoading(true);
        try {
            const response = await fetch(`${window.API_CONFIG?.BASE_URL || 'http://' + window.location.hostname + ':8000'}/api/lupto/dashboard/reject-spot`, {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, rejection_reason: reason.trim() })
            });
            const res = await response.json();
            if (res.success) {
                setPendingSpots(pendingSpots.filter(s => s.id !== id));
            } else {
                alert(res.error || 'Failed to reject');
            }
        } catch (err) {
            console.error(err);
        } finally {
            setActionLoading(false);
        }
    };

    const handleSelectPending = (id) => {
        if (selectedPendingIds.includes(id)) {
            setSelectedPendingIds(selectedPendingIds.filter(item => item !== id));
        } else {
            setSelectedPendingIds([...selectedPendingIds, id]);
        }
    };

    const handleBatchApprove = async () => {
        if (selectedPendingIds.length === 0) return;
        if (!confirm(`Are you sure you want to approve these ${selectedPendingIds.length} selected tourist spots?`)) return;
        setActionLoading(true);
        try {
            const response = await fetch('${window.API_CONFIG?.BASE_URL || 'http://' + window.location.hostname + ':8000'}/api/lupto/dashboard/batch-approve-spots', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: selectedPendingIds })
            });
            const res = await response.json();
            if (res.success) {
                setPendingSpots(pendingSpots.filter(s => !selectedPendingIds.includes(s.id)));
                setSelectedPendingIds([]);
                fetchDashboardData();
            } else {
                alert(res.error || 'Batch approval failed');
            }
        } catch (err) {
            console.error(err);
        } finally {
            setActionLoading(false);
        }
    };

    const handleExportAnalytics = () => {
        alert('Exporting Report in CSV Government Standards format...');
        // Mock export trigger
        const csvContent = "data:text/csv;charset=utf-8,Municipality,Total Visits,Average Spend (PHP)\n" 
            + analytics.rankings.map(r => `"${r.name}",${r.total_visits},${parseFloat(r.avg_spend).toFixed(2)}`).join("\n");
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `LUPTO_Tourism_Report_${analyticsYear}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    // 7. RENDER
    const transportPct = getTransportPercentages();
    const pendingCount = pendingSpots.filter(s => s.status === 'pending').length;

    return (
        <div className="lupto-dashboard-container">
            {/* Breadcrumb Indicators */}
            <div className="lupto-breadcrumbs" role="navigation" aria-label="Breadcrumb">
                <a href="dashboard.php">Home</a>
                <i className="fas fa-chevron-right"></i>
                <span style={{textTransform:'capitalize'}}>{activeTab.replace('-', ' ')}</span>
            </div>

            {/* Main Title Banner */}
            <div className="flex-between" style={{marginBottom:'16px'}}>
                <h2 className="section-title" style={{fontSize:'22px', color:'var(--lupto-primary)', margin:0}}>
                    LUPTO Dashboard — La Union Provincial Tourism Operations
                </h2>
                <span className="badge badge-success" style={{fontSize:'12px', padding:'6px 12px'}}>
                    <i className="fas fa-circle-nodes"></i> Real-time Monitoring Connected
                </span>
            </div>

            {/* Error notifications */}
            {error && (
                <div style={{background:'#fef2f2', border:'1px solid #fecaca', color:'#991b1b', padding:'12px', borderRadius:'8px', marginBottom:'16px', fontSize:'13px'}}>
                    <i className="fas fa-exclamation-triangle"></i> {error}
                </div>
            )}

            {/* Navigation Tabs */}
            <nav className="lupto-tabs-nav" aria-label="Sub pages navigation" role="tablist">
                <button 
                    role="tab"
                    aria-selected={activeTab === 'dashboard'}
                    className={`lupto-tab-btn ${activeTab === 'dashboard' ? 'active' : ''}`}
                    onClick={() => setActiveTab('dashboard')}
                    aria-label="View Dashboard Overview"
                >
                    <i className="fas fa-gauge-high"></i> Dashboard
                </button>
                <button 
                    role="tab"
                    aria-selected={activeTab === 'map-view'}
                    className={`lupto-tab-btn ${activeTab === 'map-view' ? 'active' : ''}`}
                    onClick={() => setActiveTab('map-view')}
                    aria-label="View Full interactive Map"
                >
                    <i className="fas fa-map-location-dot"></i> Map View
                </button>
                <button 
                    role="tab"
                    aria-selected={activeTab === 'users'}
                    className={`lupto-tab-btn ${activeTab === 'users' ? 'active' : ''}`}
                    onClick={() => setActiveTab('users')}
                    aria-label="Manage User Accounts"
                >
                    <i className="fas fa-users-gear"></i> Users
                </button>
                <button 
                    role="tab"
                    aria-selected={activeTab === 'tourist-spots'}
                    className={`lupto-tab-btn ${activeTab === 'tourist-spots' ? 'active' : ''}`}
                    onClick={() => { setActiveTab('tourist-spots'); fetchSpots(pendingStatusFilter); }}
                    aria-label="View Tourist Spot Approvals"
                >
                    <i className="fas fa-location-dot"></i> Tourist Spots 
                    {pendingCount > 0 && <span className="badge badge-danger" style={{marginLeft:'6px'}} aria-label={`${pendingCount} pending`}>{pendingCount}</span>}
                </button>
                <button 
                    role="tab"
                    aria-selected={activeTab === 'analytics'}
                    className={`lupto-tab-btn ${activeTab === 'analytics' ? 'active' : ''}`}
                    onClick={() => setActiveTab('analytics')}
                    aria-label="View Tourism Analytics"
                >
                    <i className="fas fa-chart-line"></i> Analytics
                </button>
            </nav>

            {/* Tab Contents */}
            <main className="lupto-tab-panel">
                {/* 1. DASHBOARD OVERVIEW TAB */}
                {activeTab === 'dashboard' && (
                    <div className="lupto-tab-content" role="tabpanel" aria-label="Dashboard Overview">
                        {/* KPI Cards */}
                        <div className="lupto-kpi-grid">
                            <div className="lupto-kpi-card">
                                <div className="lupto-kpi-info">
                                    <h4>Total Tourist Spots</h4>
                                    <span className="lupto-kpi-value">{kpis.totalTouristSpots.toLocaleString()}</span>
                                </div>
                                <div className="lupto-kpi-icon"><i className="fas fa-compass"></i></div>
                            </div>
                            <div className="lupto-kpi-card">
                                <div className="lupto-kpi-info">
                                    <h4>Active Users</h4>
                                    <span className="lupto-kpi-value">{kpis.activeUsers}</span>
                                </div>
                                <div className="lupto-kpi-icon"><i className="fas fa-users"></i></div>
                            </div>
                            <div className="lupto-kpi-card">
                                <div className="lupto-kpi-info">
                                    <h4>Monthly Visits</h4>
                                    <span className="lupto-kpi-value">{kpis.monthlyVisits.toLocaleString()}</span>
                                </div>
                                <div className="lupto-kpi-icon"><i className="fas fa-eye"></i></div>
                            </div>
                            <div className="lupto-kpi-card">
                                <div className="lupto-kpi-info">
                                    <h4>System Uptime</h4>
                                    <span className="lupto-kpi-value">{kpis.systemUptime}</span>
                                </div>
                                <div className="lupto-kpi-icon"><i className="fas fa-server"></i></div>
                            </div>
                        </div>

                        {/* Map Preview & Details Panel */}
                        <div className="lupto-dashboard-map-grid">
                            {/* Map Canvas */}
                            <div className="card" style={{padding:'14px'}}>
                                <div className="lupto-map-header-action">
                                    <h3 className="card-title" style={{fontSize:'14px', margin:0}}>
                                        <i className="fas fa-map"></i> La Union Interactive LGU Profile Map
                                    </h3>
                                    <button 
                                        className="btn-gov" 
                                        style={{padding:'4px 10px', fontSize:'11px'}}
                                        onClick={() => setIsFullscreenMapOpen(true)}
                                    >
                                        <i className="fas fa-expand"></i> View Full Map
                                    </button>
                                </div>
                                <div id="dashboard-map" className="lupto-embedded-map"></div>
                            </div>

                            {/* Live Notifications Feed / Profile View */}
                            <div className="card" style={{display:'flex', flexDirection:'column'}}>
                                <div className="card-header">
                                    <h3 className="card-title">
                                        <i className="fas fa-circle-info"></i> LGU Detailed profile
                                    </h3>
                                </div>
                                <div className="card-body" style={{flex:1, overflowY:'auto'}}>
                                    {selectedMuniDetails ? (
                                        <div>
                                            <h4 style={{fontSize:'18px', color:'var(--lupto-primary)', margin:'0 0 10px'}}>{selectedMuniDetails.name}</h4>
                                            <p style={{fontSize:'13px', margin:'4px 0'}}><strong>Coordinates:</strong> {selectedMuniDetails.latitude}° N, {selectedMuniDetails.longitude}° E</p>
                                            <p style={{fontSize:'13px', margin:'4px 0'}}><strong>Total Registered Attractions:</strong> {selectedMuniDetails.attraction_count}</p>
                                            
                                            <h5 style={{fontSize:'12px', textTransform:'uppercase', borderBottom:'1px solid var(--border)', paddingBottom:'4px', marginTop:'16px'}}>Attraction Categories Breakdown</h5>
                                            {selectedMuniDetails.categories && selectedMuniDetails.categories.length > 0 ? (
                                                <ul style={{paddingLeft:'20px', margin:'8px 0', fontSize:'13px'}}>
                                                    {selectedMuniDetails.categories.map((c, i) => (
                                                        <li key={i} style={{marginBottom:'4px'}}>{c.category}: <strong>{c.count}</strong></li>
                                                    ))}
                                                </ul>
                                            ) : (
                                                <p style={{fontStyle:'italic', color:'var(--text-muted)', fontSize:'12px'}}>No specific active category registrations in database.</p>
                                            )}
                                            
                                            <button 
                                                className="btn-gov btn-gov-secondary" 
                                                style={{width:'100%', marginTop:'16px', padding:'6px'}}
                                                onClick={() => setSelectedMuniDetails(null)}
                                            >
                                                Close Profile
                                            </button>
                                        </div>
                                    ) : (
                                        <div style={{textAlign:'center', padding:'40px 10px', color:'var(--text-secondary)'}}>
                                            <i className="fas fa-map-pin" style={{fontSize:'36px', color:'var(--lupto-secondary)', marginBottom:'12px'}}></i>
                                            <p style={{margin:0, fontSize:'13px'}}>Click a municipality marker pin on the map to display its registered tourism counts and profiles.</p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* System Status Table */}
                        <div className="card">
                            <div className="card-header">
                                <h3 className="card-title"><i className="fas fa-network-wired"></i> PITCO/LUPTO Operations System Health Status</h3>
                            </div>
                            <div className="card-body" style={{padding:0}}>
                                <table className="data-table">
                                    <thead>
                                        <tr>
                                            <th>Monitoring Service Component</th>
                                            <th>Status</th>
                                            <th>Uptime Rate</th>
                                            <th>Last Checked</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {systemStatuses.map((sys) => (
                                            <tr key={sys.id}>
                                                <td><strong>{sys.service_name}</strong></td>
                                                <td>
                                                    <span className="flex-gap-8" style={{fontSize:'13px'}}>
                                                        <span className={`status-indicator-dot ${sys.status}`}></span>
                                                        <span style={{textTransform:'uppercase', fontWeight:600}} className={`text-${sys.status === 'online' ? 'success' : (sys.status === 'warning' ? 'warning' : 'danger')}`}>{sys.status}</span>
                                                    </span>
                                                </td>
                                                <td><code>{sys.uptime}</code></td>
                                                <td>{new Date(sys.last_checked).toLocaleString()}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                )}

                {/* 2. DEDICATED FULL-SCREEN MAP VIEW */}
                {activeTab === 'map-view' && (
                    <div className="lupto-tab-content lupto-fullscreen-map-wrapper" role="tabpanel" aria-label="Map View">
                        {/* Map Filters Panel */}
                        <div className="lupto-map-controls-panel">
                            <div className="search-input-wrap" style={{maxWidth:'250px'}}>
                                <i className="fas fa-search search-icon"></i>
                                <input 
                                    type="text" 
                                    placeholder="Search by Municipality..." 
                                    value={mapSearch}
                                    onChange={(e) => setMapSearch(e.target.value)}
                                />
                            </div>

                            <select 
                                className="filter-select"
                                value={mapTileLayer}
                                onChange={(e) => setMapTileLayer(e.target.value)}
                            >
                                <option value="street">Layers: Street View</option>
                                <option value="satellite">Layers: Satellite View</option>
                                <option value="terrain">Layers: Terrain View</option>
                            </select>

                            <label className="flex-gap-8" style={{cursor:'pointer', userSelect:'none', fontSize:'13px', fontWeight:600}}>
                                <input 
                                    type="checkbox" 
                                    checked={mapHeatmapMode} 
                                    onChange={() => setMapHeatmapMode(!mapHeatmapMode)}
                                />
                                <i className="fas fa-fire" style={{color:'orange'}}></i> Toggle Heatmap Density Overlay
                            </label>

                            {/* Inline Legend */}
                            <div className="lupto-map-legend" style={{marginLeft:'auto', display:'flex', padding:'4px 10px', gap:'12px', background:'#f8fafc', boxShadow:'none', border:'1px solid #cbd5e1'}}>
                                <div style={{fontWeight:600}}>Gradients:</div>
                                <div className="lupto-legend-item">
                                    <div className="lupto-legend-color" style={{background:'var(--pin-dark-blue)'}}></div>
                                    <span>High Density (40+)</span>
                                </div>
                                <div className="lupto-legend-item">
                                    <div className="lupto-legend-color" style={{background:'var(--pin-light-blue)'}}></div>
                                    <span>Mid Density (25-40)</span>
                                </div>
                                <div className="lupto-legend-item">
                                    <div className="lupto-legend-color" style={{background:'var(--pin-pale-blue)'}}></div>
                                    <span>Low Density (&lt;25)</span>
                                </div>
                            </div>
                        </div>

                        {/* Full Map Canvas */}
                        <div id="dedicated-map" className="lupto-dedicated-map"></div>
                    </div>
                )}

                {/* 3. USER MANAGEMENT TAB */}
                {activeTab === 'users' && (
                    <div className="lupto-tab-content" role="tabpanel" aria-label="User Management">
                        {/* User Stats bar */}
                        <div className="lupto-user-stats-bar">
                            <div className="lupto-user-stat-box">
                                <div className="lupto-user-stat-label">Total Users</div>
                                <div className="lupto-user-stat-number">{users.length}</div>
                            </div>
                            <div className="lupto-user-stat-box">
                                <div className="lupto-user-stat-label">PITCO Admins</div>
                                <div className="lupto-user-stat-number">{users.filter(u => u.role === 'pitco').length}</div>
                            </div>
                            <div className="lupto-user-stat-box">
                                <div className="lupto-user-stat-label">LUPTO Officers</div>
                                <div className="lupto-user-stat-number">{users.filter(u => u.role === 'lupto').length}</div>
                            </div>
                            <div className="lupto-user-stat-box">
                                <div className="lupto-user-stat-label">MTO Officers</div>
                                <div className="lupto-user-stat-number">{users.filter(u => u.role === 'municipal').length}</div>
                            </div>
                            <div className="lupto-user-stat-box">
                                <div className="lupto-user-stat-label">Registered Tourists</div>
                                <div className="lupto-user-stat-number">{users.filter(u => u.role === 'tourist').length}</div>
                            </div>
                        </div>

                        {/* Search & Filter Controls */}
                        <div className="lupto-analytics-filter-row">
                            <div className="search-input-wrap" style={{maxWidth:'300px'}}>
                                <i className="fas fa-search search-icon"></i>
                                <input 
                                    type="text" 
                                    placeholder="Search by Name or Email..." 
                                    value={userSearch}
                                    onChange={(e) => setUserSearch(e.target.value)}
                                />
                            </div>

                            <select 
                                className="filter-select"
                                value={userRoleFilter}
                                onChange={(e) => setUserRoleFilter(e.target.value)}
                            >
                                <option value="">All Roles</option>
                                <option value="pitco">PITCO (Super Admin)</option>
                                <option value="lupto">LUPTO (Provincial)</option>
                                <option value="municipal">MTO (Municipal)</option>
                                <option value="tourist">Tourist Users</option>
                            </select>

                            <select 
                                className="filter-select"
                                value={userStatusFilter}
                                onChange={(e) => setUserStatusFilter(e.target.value)}
                            >
                                <option value="">All Statuses</option>
                                <option value="active">Active Accounts</option>
                                <option value="inactive">Inactive Accounts</option>
                            </select>
                        </div>

                        {/* User Grid Cards */}
                        <div className="lupto-user-grid">
                            {users
                                .filter(u => {
                                    const matchSearch = u.name.toLowerCase().includes(userSearch.toLowerCase()) || u.email.toLowerCase().includes(userSearch.toLowerCase());
                                    const matchRole = userRoleFilter ? u.role === userRoleFilter : true;
                                    const matchStatus = userStatusFilter ? u.status === userStatusFilter : true;
                                    return matchSearch && matchRole && matchStatus;
                                })
                                .map(user => (
                                    <div key={user.id} className="lupto-user-card">
                                        <div>
                                            <div className="lupto-user-info-header">
                                                <div>
                                                    <h4 className="lupto-user-name">{user.name}</h4>
                                                    <span className="lupto-user-email">{user.email}</span>
                                                </div>
                                                <span className={`lupto-role-badge ${user.role}`}>
                                                    {user.role === 'pitco' ? 'PITCO' : (user.role === 'lupto' ? 'LUPTO' : (user.role === 'municipal' ? 'MTO' : 'Tourist'))}
                                                </span>
                                            </div>

                                            <div className="lupto-user-meta-row">
                                                <span>Status badge:</span>
                                                <span className={`badge badge-${user.status === 'active' ? 'success' : 'danger'}`}>
                                                    {user.status === 'active' ? 'Active' : 'Deactivated'}
                                                </span>
                                            </div>
                                            <div className="lupto-user-meta-row">
                                                <span>Last Activity:</span>
                                                <span style={{fontWeight:500}}>{user.last_activity ? new Date(user.last_activity).toLocaleString() : 'N/A'}</span>
                                            </div>
                                        </div>

                                        <div className="lupto-user-actions">
                                            <button 
                                                className="btn-gov btn-gov-secondary" 
                                                style={{padding:'4px 8px', fontSize:'11px', flex:1}}
                                                onClick={() => handleOpenEditUser(user)}
                                            >
                                                <i className="fas fa-edit"></i> Edit Role
                                            </button>
                                            <button 
                                                className={`btn-gov ${user.status === 'active' ? 'btn-gov-danger' : 'btn-gov-success'}`}
                                                style={{padding:'4px 8px', fontSize:'11px', flex:1}}
                                                onClick={() => handleToggleUserStatus(user)}
                                                disabled={actionLoading}
                                            >
                                                <i className={`fas ${user.status === 'active' ? 'fa-ban' : 'fa-check'}`}></i> {user.status === 'active' ? 'Deactivate' : 'Activate'}
                                            </button>
                                            <button 
                                                className="btn-gov btn-gov-secondary"
                                                style={{padding:'4px 8px', fontSize:'11px', background:'#f8fafc', color:'#1e293b'}}
                                                onClick={() => handleOpenResetPassword(user)}
                                                title="Reset User Password"
                                            >
                                                <i className="fas fa-key"></i>
                                            </button>
                                        </div>
                                    </div>
                                ))
                            }
                        </div>
                    </div>
                )}

                {/* 4. TOURIST SPOTS QUEUE TAB */}
                {activeTab === 'tourist-spots' && (
                    <div className="lupto-tab-content" role="tabpanel" aria-label="Tourist Spots Approval">
                        {/* Batch Approval Actions Bar — pending only */}
                        {pendingStatusFilter === 'pending' && (
                        <div className="lupto-batch-actions-bar">
                            <div className="flex-gap-8">
                                <input 
                                    type="checkbox" 
                                    id="select-all-pending"
                                    checked={pendingSpots.length > 0 && selectedPendingIds.length === pendingSpots.length}
                                    onChange={() => {
                                        if (selectedPendingIds.length === pendingSpots.length) {
                                            setSelectedPendingIds([]);
                                        } else {
                                            setSelectedPendingIds(pendingSpots.map(s => s.id));
                                        }
                                    }}
                                />
                                <label htmlFor="select-all-pending" style={{cursor:'pointer', fontWeight:600, fontSize:'13px'}}>
                                    Select All Pending ({selectedPendingIds.length} of {pendingSpots.length} Selected)
                                </label>
                            </div>
                            <button 
                                className="btn-gov btn-gov-success" 
                                onClick={handleBatchApprove}
                                disabled={selectedPendingIds.length === 0 || actionLoading}
                            >
                                <i className="fas fa-thumbs-up"></i> Approve Selected Batch
                            </button>
                        </div>
                        )}

                        {/* Search & Filters */}
                        <div className="lupto-analytics-filter-row">
                            <select 
                                className="filter-select"
                                value={pendingStatusFilter}
                                onChange={(e) => setPendingStatusFilter(e.target.value)}
                                aria-label="Filter by approval status"
                            >
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                                <option value="all">All Statuses</option>
                            </select>
                            <div className="search-input-wrap" style={{maxWidth:'280px'}}>
                                <i className="fas fa-search search-icon"></i>
                                <input 
                                    type="text" 
                                    placeholder="Search by Spot Name..." 
                                    value={pendingSearch}
                                    onChange={(e) => setPendingSearch(e.target.value)}
                                />
                            </div>

                            <select 
                                className="filter-select"
                                value={pendingMuniFilter}
                                onChange={(e) => setPendingMuniFilter(e.target.value)}
                            >
                                <option value="">All Municipalities</option>
                                {municipalities.map(m => (
                                    <option key={m.id} value={m.name}>{m.name}</option>
                                ))}
                            </select>

                            <select 
                                className="filter-select"
                                value={pendingCatFilter}
                                onChange={(e) => setPendingCatFilter(e.target.value)}
                            >
                                <option value="">All Categories</option>
                                <option value="Beach">Beach</option>
                                <option value="Mountain">Mountain</option>
                                <option value="Historical">Historical</option>
                                <option value="Waterfalls">Waterfalls</option>
                                <option value="Adventure">Adventure</option>
                                <option value="Farm">Farm</option>
                                <option value="Religious">Religious</option>
                            </select>
                        </div>

                        {/* Pending Approvals Grid */}
                        {pendingSpots.length === 0 ? (
                            <div className="card" style={{padding:'40px', textAlign:'center', color:'var(--text-secondary)'}}>
                                <i className="fas fa-clipboard-check" style={{fontSize:'48px', color:'var(--status-active)', marginBottom:'12px'}}></i>
                                <p style={{fontSize:'15px', margin:0, fontWeight:600}}>Approvals Queue Empty</p>
                                <p style={{fontSize:'13px', margin:'6px 0 0'}}>All municipal tourist spot submissions have been reviewed.</p>
                            </div>
                        ) : (
                            <div className="lupto-approval-grid">
                                {pendingSpots
                                    .filter(s => {
                                        const matchSearch = s.name.toLowerCase().includes(pendingSearch.toLowerCase());
                                        const matchMuni = pendingMuniFilter ? s.municipality_name === pendingMuniFilter : true;
                                        const matchCat = pendingCatFilter ? s.category === pendingCatFilter : true;
                                        return matchSearch && matchMuni && matchCat;
                                    })
                                    .map(spot => (
                                        <div key={spot.id} className="lupto-spot-card">
                                            {/* Select Checkbox & Photo */}
                                            <div className="lupto-spot-card-photo">
                                                <input 
                                                    type="checkbox" 
                                                    style={{position:'absolute', top:'10px', left:'10px', width:'20px', height:'20px', zIndex:100}}
                                                    checked={selectedPendingIds.includes(spot.id)}
                                                    onChange={() => handleSelectPending(spot.id)}
                                                />
                                                <div className="lupto-spot-photo-placeholder">
                                                    <i className="fas fa-camera"></i>
                                                    <span>{spot.name} Image Placeholder</span>
                                                </div>
                                            </div>

                                            {/* Card Body */}
                                            <div className="lupto-spot-card-body">
                                                <div>
                                                    <div className="lupto-spot-title-row">
                                                        <h4 className="lupto-spot-title">{spot.name}</h4>
                                                        <span className={`badge badge-${spot.status === 'approved' ? 'success' : (spot.status === 'rejected' ? 'danger' : 'warning')}`} style={{fontSize:'10px'}}>
                                                            {spot.status.toUpperCase()}
                                                        </span>
                                                    </div>
                                                    <div className="lupto-spot-muni">
                                                        <i className="fas fa-map-pin"></i> {spot.municipality_name}
                                                    </div>
                                                    
                                                    <div className="lupto-spot-tags">
                                                        <span className="lupto-tag-category">{spot.category}</span>
                                                        <span className="lupto-tag-fee">Fee: PHP {parseFloat(spot.entrance_fee).toFixed(2)}</span>
                                                    </div>
                                                    
                                                    <p style={{fontSize:'12px', color:'var(--text-secondary)', margin:'8px 0', lineHeight:'1.4'}}>
                                                        {spot.description || 'No description provided.'}
                                                    </p>
                                                </div>

                                                {spot.status === 'pending' && (
                                                <div style={{display:'flex', gap:'8px', marginTop:'16px'}}>
                                                    <button 
                                                        className="btn-gov btn-gov-success" 
                                                        style={{flex:1, padding:'6px'}}
                                                        onClick={() => handleApproveSpot(spot.id)}
                                                        disabled={actionLoading}
                                                    >
                                                        <i className="fas fa-check"></i> Approve
                                                    </button>
                                                    <button 
                                                        className="btn-gov btn-gov-danger" 
                                                        style={{flex:1, padding:'6px'}}
                                                        onClick={() => handleRejectSpot(spot.id)}
                                                        disabled={actionLoading}
                                                    >
                                                        <i className="fas fa-times"></i> Reject
                                                    </button>
                                                </div>
                                                )}
                                            </div>
                                        </div>
                                    ))
                                }
                            </div>
                        )}
                    </div>
                )}

                {/* 5. ANALYTICS & REPORTS TAB */}
                {activeTab === 'analytics' && (
                    <div className="lupto-tab-content" role="tabpanel" aria-label="Analytics and Reporting">
                        {/* Filters Row */}
                        <div className="lupto-analytics-filter-row">
                            <label style={{fontWeight:600, fontSize:'13px', display:'flex', alignItems:'center', gap:'6px'}}>
                                <i className="fas fa-calendar"></i> Reporting Year:
                                <select 
                                    className="filter-select"
                                    value={analyticsYear}
                                    onChange={(e) => setAnalyticsYear(e.target.value)}
                                >
                                    <option value="2026">2026 (Comparative View)</option>
                                    <option value="2025">2025 (Historical View)</option>
                                </select>
                            </label>

                            <button className="btn-gov" style={{marginLeft:'auto'}} onClick={handleExportAnalytics}>
                                <i className="fas fa-file-export"></i> Export Report (CSV)
                            </button>
                        </div>

                        {/* Main YoY Chart */}
                        <div className="lupto-chart-container">
                            <h3 style={{fontSize:'14px', margin:'0 0 10px'}}><i className="fas fa-chart-area"></i> Month-on-Month Comparative Visitor Arrivals (YoY trends)</h3>
                            <div style={{height:'260px'}}>
                                <canvas id="analytics-yoy-chart"></canvas>
                            </div>
                        </div>

                        {/* Municipality visits bar chart */}
                        <div className="lupto-chart-container">
                            <h3 style={{fontSize:'14px', margin:'0 0 10px'}}><i className="fas fa-chart-bar"></i> Monthly Visits by Municipality (June {analyticsYear})</h3>
                            <div style={{height:'240px'}}>
                                <canvas id="analytics-muni-bar-chart"></canvas>
                            </div>
                        </div>

                        {/* Split Analytics Section */}
                        <div className="lupto-analytics-grid">
                            {/* Transportation breakdown */}
                            <div className="card" style={{padding:'16px'}}>
                                <h3 className="card-title"><i className="fas fa-bus"></i> Transportation analytics (2026 Shares)</h3>
                                <div style={{height:'180px', marginTop:'10px'}}>
                                    <canvas id="analytics-transport-chart"></canvas>
                                </div>
                                <div className="lupto-transport-grid">
                                    <div className="lupto-transport-box">
                                        <span className="lupto-transport-value">{transportPct.car}%</span>
                                        <span className="lupto-transport-label">Cars</span>
                                    </div>
                                    <div className="lupto-transport-box">
                                        <span className="lupto-transport-value">{transportPct.bus}%</span>
                                        <span className="lupto-transport-label">Buses</span>
                                    </div>
                                    <div className="lupto-transport-box">
                                        <span className="lupto-transport-value">{transportPct.van}%</span>
                                        <span className="lupto-transport-label">Vans</span>
                                    </div>
                                    <div className="lupto-transport-box">
                                        <span className="lupto-transport-value">{transportPct.other}%</span>
                                        <span className="lupto-transport-label">Others</span>
                                    </div>
                                </div>
                            </div>

                            {/* Municipality rankings Table */}
                            <div className="card" style={{padding:'16px', display:'flex', flexDirection:'column'}}>
                                <h3 className="card-title"><i className="fas fa-trophy"></i> Top-Performing Municipalities Ranking (2026)</h3>
                                <div style={{flex:1, overflowY:'auto', marginTop:'10px'}}>
                                    {analytics && analytics.rankings ? (
                                        <table className="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Rank</th>
                                                    <th>Municipality</th>
                                                    <th>Annual Visits</th>
                                                    <th>Avg Spending (PHP)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {analytics.rankings.slice(0, 6).map((muni, index) => (
                                                    <tr key={index}>
                                                        <td><strong>#{index + 1}</strong></td>
                                                        <td><strong>{muni.name}</strong></td>
                                                        <td>{parseInt(muni.total_visits).toLocaleString()}</td>
                                                        <td>₱{parseFloat(muni.avg_spend).toFixed(2)}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    ) : (
                                        <p>Loading ranking metrics...</p>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Cost Estimation Breakdown */}
                        <div className="card" style={{marginBottom:'20px'}}>
                            <div className="card-header">
                                <h3 className="card-title"><i className="fas fa-peso-sign"></i> Tourism Cost Estimation Breakdown (2026)</h3>
                            </div>
                            <div className="card-body" style={{padding:0}}>
                                {analytics && analytics.costBreakdown ? (
                                    <table className="data-table">
                                        <thead>
                                            <tr>
                                                <th>Municipality</th>
                                                <th>Avg Tourist Spend (PHP)</th>
                                                <th>Total Visits</th>
                                                <th>Est. Revenue (PHP)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {analytics.costBreakdown.map((row, i) => (
                                                <tr key={i}>
                                                    <td><strong>{row.name}</strong></td>
                                                    <td>₱{parseFloat(row.avg_spend).toFixed(2)}</td>
                                                    <td>{parseInt(row.total_visits).toLocaleString()}</td>
                                                    <td><strong>₱{parseFloat(row.estimated_revenue).toLocaleString(undefined, {maximumFractionDigits:0})}</strong></td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                ) : (
                                    <p style={{padding:'16px'}}>Loading cost estimation data...</p>
                                )}
                            </div>
                        </div>

                        {/* Top-performing attractions list */}
                        <div className="card">
                            <div className="card-header">
                                <h3 className="card-title"><i className="fas fa-award"></i> Top Performing Tourist Attractions (Visits & Ratings)</h3>
                            </div>
                            <div className="card-body" style={{padding:0}}>
                                <table className="data-table">
                                    <thead>
                                        <tr>
                                            <th>Spot Name</th>
                                            <th>Municipality</th>
                                            <th>Category</th>
                                            <th>Rating</th>
                                            <th>Attained Visitor Count</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {analytics && analytics.topSpots ? (
                                            analytics.topSpots.map((spot, i) => (
                                                <tr key={spot.id}>
                                                    <td><strong>{spot.name}</strong></td>
                                                    <td>{spot.municipality_name}</td>
                                                    <td><span className="lupto-tag-category">{spot.category}</span></td>
                                                    <td>
                                                        <span style={{color:'orange'}}><i className="fas fa-star"></i> {spot.rating} / 5.0</span>
                                                    </td>
                                                    <td><strong>{parseInt(spot.visits).toLocaleString()}</strong></td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr><td colSpan="5">Loading ranking records...</td></tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                )}
            </main>

            {/* ==========================================
                MODAL DIALOGS
                ========================================== */}
            
            {/* 1. Edit User Modal */}
            {isEditUserOpen && selectedUser && (
                <div className="lupto-modal-overlay">
                    <div className="lupto-modal-content">
                        <div className="lupto-modal-header">
                            <h3 className="lupto-modal-title">Edit Account Credentials: {selectedUser.name}</h3>
                            <button className="lupto-modal-close-btn" onClick={() => setIsEditUserOpen(false)}>&times;</button>
                        </div>
                        <div className="lupto-modal-body">
                            <div className="lupto-form-group">
                                <label>User Role Profile</label>
                                <select 
                                    className="lupto-form-control"
                                    value={editUserRole}
                                    onChange={(e) => setEditUserRole(e.target.value)}
                                >
                                    <option value="pitco">PITCO (Super Admin)</option>
                                    <option value="lupto">LUPTO (Provincial Admin)</option>
                                    <option value="municipal">MTO (Municipal User)</option>
                                    <option value="tourist">Tourist User</option>
                                </select>
                            </div>
                            <div className="lupto-form-group">
                                <label>Account Status</label>
                                <select 
                                    className="lupto-form-control"
                                    value={editUserStatus}
                                    onChange={(e) => setEditUserStatus(e.target.value)}
                                >
                                    <option value="active">Active (Permitted)</option>
                                    <option value="inactive">Inactive (Deactivated)</option>
                                </select>
                            </div>
                        </div>
                        <div className="lupto-modal-footer">
                            <button className="btn-gov btn-gov-secondary" onClick={() => setIsEditUserOpen(false)}>Cancel</button>
                            <button className="btn-gov btn-gov-success" onClick={handleUpdateUser} disabled={actionLoading}>Save Changes</button>
                        </div>
                    </div>
                </div>
            )}

            {/* 2. Reset Password Modal */}
            {isResetPasswordOpen && selectedUser && (
                <div className="lupto-modal-overlay">
                    <div className="lupto-modal-content">
                        <div className="lupto-modal-header">
                            <h3 className="lupto-modal-title">Reset Security Password</h3>
                            <button className="lupto-modal-close-btn" onClick={() => setIsResetPasswordOpen(false)}>&times;</button>
                        </div>
                        <div className="lupto-modal-body">
                            <p style={{fontSize:'13px', marginBottom:'14px'}}>Reset security credentials for: <strong>{selectedUser.name} ({selectedUser.email})</strong></p>
                            <div className="lupto-form-group">
                                <label>New Password</label>
                                <input 
                                    type="password" 
                                    className="lupto-form-control"
                                    placeholder="Enter new strong password"
                                    value={resetPasswordVal}
                                    onChange={(e) => setResetPasswordVal(e.target.value)}
                                />
                            </div>
                        </div>
                        <div className="lupto-modal-footer">
                            <button className="btn-gov btn-gov-secondary" onClick={() => setIsResetPasswordOpen(false)}>Cancel</button>
                            <button className="btn-gov btn-gov-danger" onClick={handleResetPassword} disabled={actionLoading}>Update Password</button>
                        </div>
                    </div>
                </div>
            )}

            {/* 3. Fullscreen Map Modal */}
            {isFullscreenMapOpen && (
                <div className="lupto-modal-overlay">
                    <div className="lupto-modal-content lupto-fullscreen-map-modal">
                        <div className="lupto-modal-header">
                            <h3 className="lupto-modal-title"><i className="fas fa-map"></i> Fullscreen Command Map - La Union</h3>
                            <button className="lupto-modal-close-btn" onClick={() => setIsFullscreenMapOpen(false)}>&times;</button>
                        </div>
                        <div className="lupto-modal-body" style={{position:'relative'}}>
                            {/* Embedded Map controls */}
                            <div style={{position:'absolute', top:'10px', left:'60px', zIndex:1000, display:'flex', gap:'8px'}}>
                                <button 
                                    className="btn-gov" 
                                    style={{padding:'6px 12px', fontSize:'12px', background:'rgba(255,255,255,0.95)', color:'#1e293b', border:'1px solid #cbd5e1'}}
                                    onClick={() => setMapHeatmapMode(!mapHeatmapMode)}
                                >
                                    <i className="fas fa-fire" style={{color: mapHeatmapMode ? 'red' : 'gray'}}></i> {mapHeatmapMode ? 'Disable' : 'Enable'} Heatmap Overlay
                                </button>
                                <select 
                                    className="filter-select"
                                    style={{background:'rgba(255,255,255,0.95)', border:'1px solid #cbd5e1', padding:'4px 8px', fontSize:'12px'}}
                                    value={mapTileLayer}
                                    onChange={(e) => setMapTileLayer(e.target.value)}
                                >
                                    <option value="street">Streets</option>
                                    <option value="satellite">Satellite</option>
                                    <option value="terrain">Terrain</option>
                                </select>
                            </div>
                            <div id="fullscreen-modal-map" style={{width:'100%', height:'100%'}}></div>
                        </div>
                        <div className="lupto-modal-footer" style={{padding:'8px 16px'}}>
                            <button className="btn-gov btn-gov-secondary" onClick={() => setIsFullscreenMapOpen(false)}>Close View</button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

// Render React entrypoint in DOM
const rootEl = document.getElementById('dashboard-root');
if (rootEl) {
    const root = ReactDOM.createRoot(rootEl);
    root.render(<DashboardApp />);
}
