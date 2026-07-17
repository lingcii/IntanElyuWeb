<?php
require_once __DIR__ . '/../../session-bridge.php';
// Check role
if ($_SESSION['user_role'] !== 'municipal' && !str_ends_with($_SESSION['user_role'], '_mto')) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'Municipal Map View';

// ── Fetch data from Laravel API ───────────────────────────────────────────
$laravelBase = 'http://127.0.0.1:8000/api';

$cookieStr = '';
foreach ($_COOKIE as $name => $value) {
    $cookieStr .= $name . '=' . urlencode($value) . '; ';
}

function muniMapGet(string $url, string $cookieStr): array {
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

// Fetch municipality info and spots from Laravel
$muniId = $_SESSION['user_municipality_id'] ?? null;
$municipalityResponse = muniMapGet("{$laravelBase}/municipalities/{$muniId}", $cookieStr);
$municipality = $municipalityResponse['municipality'] ?? $municipalityResponse['data'] ?? $municipalityResponse ?? [];

$spotsResponse = muniMapGet("{$laravelBase}/municipal/tourist-spots", $cookieStr);
$spots = $spotsResponse['data'] ?? $spotsResponse ?? [];


// Extra head content for CSS that needs to load in <head>
ob_start();
?>
    
    <!-- Base CSS -->
    <link rel="stylesheet" href="../../css/LUPTO/base.css">
    <!-- LUPTO Dashboard CSS (contains map styles) -->
    <link rel="stylesheet" href="../../css/LUPTO/dashboard.css">
    <link rel="stylesheet" href="../../css/LUPTO/map-view.css">
    <link rel="stylesheet" href="../../css/MUNICIPAL/map.css">
    <!-- Leaflet Map CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <!-- Inline styles for fullscreen map -->
 
<?php
$extraHeadContent = ob_get_clean();

ob_start();
?>
    <div class="lupto-fullscreen-map-wrapper">
        <div class="lupto-map-controls-panel">
            <h3 class="card-title" style="margin:0;">
                <i class="fas fa-map"></i> <?php echo htmlspecialchars($municipality['name']); ?> Tourist Spots
            </h3>
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
        
        <div class="map-wrapper">
            <div id="lupto-map" class="lupto-dedicated-map"></div>
            
            <!-- Overlay -->
            <div class="sidebar-overlay" id="sidebarOverlay"></div>
            
            <!-- Sidebar -->
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
                    <!-- Content will be dynamically populated -->
                </div>
            </div>
        </div>
    </div>

    <!-- Leaflet Map -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- Data passed from PHP to the external JS file -->
    <script>
        window.municipalityData = <?php echo json_encode($municipality); ?>;
        window.touristSpotsData = <?php echo json_encode($spots); ?>;
    </script>

    <!-- Map logic, separated into its own file -->
    <script src="../../scripts/functions/MUNICIPAL/map-api.js?v=<?= time() ?>"></script>
<?php
$pageContent = ob_get_clean();
require_once '../../components/sections.php';
?>