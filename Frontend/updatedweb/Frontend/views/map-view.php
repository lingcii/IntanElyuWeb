<?php
// Dedicated Interactive Map View page — LUPTO, PICTO, and Municipal roles.
// Fetches tourist spot locations and municipality coordinates via Laravel API to render interactive Leaflet maps.

require_once __DIR__ . '/../session-bridge.php';
$allowedRoles = ['lupto', 'picto', 'municipal'];
require_once __DIR__ . '/_role_guard.php';
$pageTitle = strtoupper($userRole) . ' Map View';

// Backend Laravel API base URL
$laravelBase = 'http://127.0.0.1:8000/api';

// Build the Laravel session cookie header string
$cookieStr = '';
foreach ($_COOKIE as $name => $value) {
    $cookieStr .= $name . '=' . urlencode($value) . '; ';
}

// Helper function to execute cURL GET requests to backend API with user cookies
function laravelGet(string $url, string $cookieStr): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Cookie: ' . $cookieStr,
        ],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    if (!$body) return [];
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : [];
}

// Fetch tourist spots data from backend
$spotsResponse = laravelGet("{$laravelBase}/lupto/tourist-spots", $cookieStr);
$spots = $spotsResponse['data'] ?? $spotsResponse ?? [];

// Fetch municipalities list from backend
$muniResponse = laravelGet("{$laravelBase}/municipalities", $cookieStr);
$municipalities = $muniResponse['municipalities'] ?? $muniResponse['data'] ?? $muniResponse ?? [];

// Extra head content for CSS stylesheets that need to load in <head>
ob_start();
?>
    <!-- Extra Head Content: Dashboard & Dedicated Map View Stylesheets -->
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/map-view.css">
<?php
$extraHeadContent = ob_get_clean();

ob_start();
?>
    <!-- Top Control Panel: Title, Selected Municipality Badge, Map Type Switcher & Back Navigation -->
    <div class="lupto-fullscreen-map-wrapper">
        <div class="lupto-map-controls-panel">
            <div class="lupto-controls-title-row">
                <h3 class="card-title" style="margin:0;">
                    <i class="fas fa-map"></i> La Union Interactive Map
                </h3>
                <div class="selected-muni-badge" id="selectedMuniBadge" style="display:none;">
                    <i class="fas fa-map-pin"></i>
                    <span id="selectedMuniName"></span>
                    <button class="muni-deselect-btn" id="muniDeselectBtn" aria-label="Deselect municipality">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="map-view-toolbar">
                <div class="map-tabs" aria-label="Map layer switcher">
                    <button class="map-tab active" data-view="street" type="button">
                        <i class="fas fa-map"></i> Street Map
                    </button>
                    <button class="map-tab" data-view="satellite" type="button">
                        <i class="fas fa-satellite"></i> Satellite
                    </button>
                </div>
                <a href="tourist-spots.php" class="btn-gov btn-gov-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Tourist Spot Management
                </a>
            </div>
        </div>
        
        <!-- Interactive Leaflet Map Canvas Container & Dynamic Tourist Spot Details Sidebar -->
        <div class="map-wrapper">
            <div id="lupto-map" class="lupto-dedicated-map"></div>
            
            <!-- Map Drawer Sidebar Overlay -->
            <div class="sidebar-overlay" id="sidebarOverlay"></div>
            
            <!-- Spot Details Slide-out Sidebar -->
            <div class="sidebar-container" id="sidebarContainer" role="dialog" aria-labelledby="sidebarTitle">
                <div class="sidebar-header">
                    <div class="sidebar-header-left">
                        <button class="sidebar-back-btn hidden" id="sidebarBackBtn" aria-label="Go back">
                            <i class="fas fa-arrow-left"></i>
                        </button>
                        <h3 id="sidebarTitle">Tourist Spots</h3>
                    </div>
                    <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close sidebar">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="sidebar-content" id="sidebarContent">
                    <!-- Dynamic spot info and reviews populated via JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- Leaflet CDN Dependencies & Server-Side Data Injection to Window Context -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script>
        // Pass server-fetched data from PHP to JavaScript window object before map-view-api.js executes
        window.touristSpotsData = <?= json_encode($spots) ?>;
        window.municipalitiesData = <?= json_encode($municipalities) ?>;
    </script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="../scripts/functions/map-view-api.js?v=<?= time() ?>"></script>
<?php
// Render content layout depending on AJAX SPA or direct page request
$pageContent = ob_get_clean();
if (is_ajax_request()) {
    if (isset($extraHeadContent)) {
        echo $extraHeadContent;
    }
    echo $pageContent;
    exit;
}
include '../components/sections.php';
