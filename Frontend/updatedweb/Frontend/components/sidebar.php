<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

function mtoSections() {
    $sections = [
        'MAIN MENU' => [
            ['href' => 'dashboard.php',       'icon' => 'fa-gauge-high',      'label' => 'Dashboard Overview'],
            ['href' => 'tourist-spots.php',   'icon' => 'fa-location-dot',    'label' => 'Manage Tourist Sites'],
            ['href' => 'feedback.php',        'icon' => 'fa-comments',        'label' => 'Feedback'],
            ['href' => 'report-generator.php','icon' => 'fa-file-lines',      'label' => 'Report Generation'],
        ],
        'SETTINGS & PRIVACY' => [
            ['href' => 'settings.php',        'icon' => 'fa-cog',             'label' => 'System Settings'],
        ],
    ];
    $sections['MAIN MENU'][] = ['href' => 'fare-data.php', 'icon' => 'fa-money-bill-trend-up', 'label' => 'Fare Management'];
    $sections['MAIN MENU'][] = ['href' => 'analytics.php', 'icon' => 'fa-chart-simple', 'label' => 'Analytics and Statistics'];
    return $sections;
}

// ------------------------------
// ROLE-BASED SIDEBAR CONFIGURATION
// ------------------------------

$sidebarConfig = [
    // LUPTO Role
    'lupto' => [
        'logo'      => 'images/LUPTO.png',
        'brand'     => 'LUPTO',
        'brand_sub' => 'San Fernando City, La Union',
        'sections'  => [
            'MAIN MENU' => [
                ['href' => 'dashboard.php',       'icon' => 'fa-gauge-high',             'label' => 'Dashboard'],
                ['href' => 'tourist-spots.php',   'icon' => 'fa-location-dot',           'label' => 'Manage Tourist Sites'],
                ['href' => 'fare-data.php',       'icon' => 'fa-money-bill-trend-up',    'label' => 'Transportation Fare'],
                ['href' => 'analytics.php',       'icon' => 'fa-chart-simple',           'label' => 'Analytics'],
                ['href' => 'leaderboard.php',     'icon' => 'fa-trophy',                 'label' => 'Leaderboard'],
            ],
            'ADMINISTRATION' => [
                ['href' => 'user-management.php', 'icon' => 'fa-user',                   'label' => 'User Management'],
                ['href' => 'feedback.php',        'icon' => 'fa-comments',               'label' => 'Feedback'],
                ['href' => 'report-generator.php','icon' => 'fa-file-lines',              'label' => 'Report Generation'],
                ['href' => 'activity-logs.php',   'icon' => 'fa-history',                'label' => 'Activity Logs'],
            ],
            'SETTINGS & PRIVACY' => [
                ['href' => 'settings.php',        'icon' => 'fa-cog',                    'label' => 'System Settings'],
            ],
        ],
    ],

    // PICTO Role
    'picto' => [
        'logo'      => 'images/PICTO.jpg',
        'brand'     => 'PICTO',
        'brand_sub' => 'San Fernando City, La Union',
        'sections'  => [
            'MAIN MENU' => [
                ['href' => 'dashboard.php',            'icon' => 'fa-gauge-high',             'label' => 'Dashboard'],
                ['href' => 'tourist-spots.php',        'icon' => 'fa-location-dot',           'label' => 'Manage Tourist Sites'],
                ['href' => 'fare-data.php',            'icon' => 'fa-money-bill-trend-up',    'label' => 'Transportation Fare'],
                ['href' => 'analytics.php',            'icon' => 'fa-chart-simple',           'label' => 'Analytics'],
                ['href' => 'leaderboard.php',          'icon' => 'fa-trophy',                 'label' => 'Leaderboard'],
            ],
            'ADMINISTRATION' => [
                ['href' => 'user-management.php',      'icon' => 'fa-user',                   'label' => 'User Management'],
                ['href' => 'feedback.php',             'icon' => 'fa-comments',               'label' => 'Feedback'],
                ['href' => 'report-generator.php',     'icon' => 'fa-file-lines',              'label' => 'Report Generation'],
                ['href' => 'activity-logs.php',        'icon' => 'fa-history',                'label' => 'Activity Logs'],
                ['href' => 'archive-management.php',   'icon' => 'fa-box-archive',            'label' => 'Archive Management'],
            ],
            'SETTINGS & PRIVACY' => [
                ['href' => 'settings.php',             'icon' => 'fa-cog',                    'label' => 'System Settings'],
            ],
        ],
    ],

    // Municipal/LGU Roles (fallback)
    'municipal' => [
        'logo'      => 'images/SAN-FERNANDO.png',
        'brand'     => 'MTO',
        'brand_sub' => 'San Fernando City, La Union',
        'sections'  => mtoSections(),
    ],

    // Specific municipal roles
    'san_juan_mto'      => ['logo' => 'images/SAN-JUAN.png',     'brand' => 'San Juan MTO',     'brand_sub' => 'San Juan, La Union',       'sections' => mtoSections()],
    'san_fernando_mto'  => ['logo' => 'images/SAN-FERNANDO.png', 'brand' => 'San Fernando MTO', 'brand_sub' => 'San Fernando City, La Union', 'sections' => mtoSections()],
    'bauang_mto'        => ['logo' => 'images/BAUANG.png',       'brand' => 'Bauang MTO',       'brand_sub' => 'Bauang, La Union',          'sections' => mtoSections()],
    'agoo_mto'          => ['logo' => 'images/AGOO.png',         'brand' => 'Agoo MTO',         'brand_sub' => 'Agoo, La Union',            'sections' => mtoSections()],
    'luna_mto'          => ['logo' => 'images/LUNA.png',         'brand' => 'Luna MTO',         'brand_sub' => 'Luna, La Union',            'sections' => mtoSections()],
    'san_gabriel_mto'   => ['logo' => 'images/SAN-GABRIEL.png',  'brand' => 'San Gabriel MTO',  'brand_sub' => 'San Gabriel, La Union',     'sections' => mtoSections()],
    'balaoan_mto'       => ['logo' => 'images/BALAOAN.png',      'brand' => 'Balaoan MTO',      'brand_sub' => 'Balaoan, La Union',         'sections' => mtoSections()],
    'aringay_mto'       => ['logo' => 'images/ARINGAY.png',      'brand' => 'Aringay MTO',      'brand_sub' => 'Aringay, La Union',         'sections' => mtoSections()],
    'rosario_mto'       => ['logo' => 'images/ROSARIO.png',      'brand' => 'Rosario MTO',      'brand_sub' => 'Rosario, La Union',         'sections' => mtoSections()],
    'bacnotan_mto'      => ['logo' => 'images/BACNOTAN.png',     'brand' => 'Bacnotan MTO',     'brand_sub' => 'Bacnotan, La Union',        'sections' => mtoSections()],
    'naguilian_mto'     => ['logo' => 'images/NAGUILIAN.png',    'brand' => 'Naguilian MTO',    'brand_sub' => 'Naguilian, La Union',       'sections' => mtoSections()],
    'tubao_mto'         => ['logo' => 'images/TUBAO.png',        'brand' => 'Tubao MTO',        'brand_sub' => 'Tubao, La Union',           'sections' => mtoSections()],
    'pugo_mto'          => ['logo' => 'images/PUGO.png',         'brand' => 'Pugo MTO',         'brand_sub' => 'Pugo, La Union',            'sections' => mtoSections()],
    'caba_mto'          => ['logo' => 'images/CABA.png',         'brand' => 'Caba MTO',         'brand_sub' => 'Caba, La Union',            'sections' => mtoSections()],
    'santo_tomas_mto'   => ['logo' => 'images/SANTO-TOMAS.png',  'brand' => 'Santo Tomas MTO',  'brand_sub' => 'Santo Tomas, La Union',     'sections' => mtoSections()],
    'bangar_mto'        => ['logo' => 'images/BANGAR.png',       'brand' => 'Bangar MTO',       'brand_sub' => 'Bangar, La Union',          'sections' => mtoSections()],
    'burgos_mto'        => ['logo' => 'images/BURGOS.png',       'brand' => 'Burgos MTO',       'brand_sub' => 'Burgos, La Union',          'sections' => mtoSections()],
    'bagulin_mto'       => ['logo' => 'images/BAGULIN.png',      'brand' => 'Bagulin MTO',      'brand_sub' => 'Bagulin, La Union',         'sections' => mtoSections()],
    'santol_mto'        => ['logo' => 'images/SANTOL.png',       'brand' => 'Santol MTO',       'brand_sub' => 'Santol, La Union',          'sections' => mtoSections()],
    'sudipen_mto'       => ['logo' => 'images/SUDIPEN.png',      'brand' => 'Sudipen MTO',      'brand_sub' => 'Sudipen, La Union',         'sections' => mtoSections()],
];

// Get current user's role from session
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;

// Dynamically resolve municipal sidebar info
$isMunicipalRole = ($userRole === 'municipal' || (is_string($userRole) && str_ends_with($userRole, '_mto')));
if ($isMunicipalRole && isset($_SESSION['user_municipality_name']) && $_SESSION['user_municipality_name']) {
    $muniName = $_SESSION['user_municipality_name'];
    $muniUpper = strtoupper(str_replace(' ', '-', $muniName));
    $logoPath = 'images/' . $muniUpper . '.png';
    if (!file_exists(__DIR__ . '/' . $logoPath)) {
        $logoPath = 'images/SAN-FERNANDO.png';
    }
    $sidebarConfig['municipal'] = [
        'logo'      => $logoPath,
        'brand'     => $muniName . ' MTO',
        'brand_sub' => $muniName . ', La Union',
        'sections'  => mtoSections(),
    ];
}

// Determine sidebar config (with fallback)
if ($userRole && isset($sidebarConfig[$userRole])) {
    $config = $sidebarConfig[$userRole];
} elseif ($isMunicipalRole) {
    $config = $sidebarConfig['municipal'];
} else {
    $config = $sidebarConfig['lupto'];
}

$navSections = $config['sections'];
$brandName   = $config['brand'];
$brandSub    = $config['brand_sub'];
$brandLogo   = $config['logo'] ?? 'images/LOGO.png';
?>

<aside class="sidebar" id="sidebar">
    <!-- Logo / Brand -->
    <div class="sidebar-brand">
        <div class="brand-logo">
            <img src="<?= $basePath . $brandLogo ?>" alt="<?= htmlspecialchars($brandName) ?>">
        </div>
        <div class="brand-info">
            <span class="brand-name"><?= htmlspecialchars($brandName) ?></span>
            <span class="brand-city"><?= htmlspecialchars($brandSub) ?></span>
        </div>
    </div>

    <!-- Primary Navigation -->
    <nav class="sidebar-nav" role="navigation" aria-label="Main navigation">
        <?php foreach ($navSections as $sectionLabel => $sectionItems): ?>
            <div class="nav-section">
                <div class="nav-section-label"><?= htmlspecialchars($sectionLabel) ?></div>
                <?php foreach ($sectionItems as $item):
                    $active = ($currentPage === $item['href']) ? ' active' : '';
                ?>
                <a href="<?= htmlspecialchars($item['href']) ?>" class="nav-item<?= $active ?>" title="<?= htmlspecialchars($item['label']) ?>">
                    <span class="nav-icon"><i class="fas <?= $item['icon'] ?>"></i></span>
                    <span class="nav-label"><?= htmlspecialchars($item['label']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>
</aside>

<script>
(function() {
    function initSidebarPrefetch() {
        const prefetched = new Set();

        function prefetchLink(url) {
            if (!url || url.startsWith('#') || url.includes('logout.php') || prefetched.has(url)) return;
            prefetched.add(url);
            const link = document.createElement('link');
            link.rel = 'prefetch';
            link.href = url;
            document.head.appendChild(link);
        }

        const navLinks = document.querySelectorAll('.sidebar-nav .nav-item');

        setTimeout(() => {
            navLinks.forEach(item => {
                const href = item.getAttribute('href');
                if (href) prefetchLink(href);
            });
        }, 500);

        navLinks.forEach(item => {
            const href = item.getAttribute('href');
            if (!href) return;
            item.addEventListener('mouseenter', () => prefetchLink(href));
            item.addEventListener('touchstart', () => prefetchLink(href), { passive: true });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebarPrefetch);
    } else {
        initSidebarPrefetch();
    }
})();
</script>
