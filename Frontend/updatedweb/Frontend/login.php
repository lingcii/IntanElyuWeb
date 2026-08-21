<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// If already logged in, redirect to the SHARED dashboard (works for all roles)
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? '';
    // Normalise pitco → picto
    if ($role === 'pitco') {
        $_SESSION['user_role'] = 'picto';
    }
    // All roles now use the single flat dashboard
    header('Location: views/dashboard.php');
    exit;
}

// ── Check if Maintenance Mode is active ──────────────────────────────────────
// When maintenance is active, going to login.php automatically shows the full
// maintenance page (views/maintenance.php), unless ?admin=1 or ?bypass=1 is used for PICTO admin login.
$isAdminBypass = isset($_GET['admin']) || isset($_GET['bypass']);
if (!$isAdminBypass) {
    $_sb_api_base = 'http://127.0.0.1:8000';
    $_sb_ctx = stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]]);
    $_sb_resp = @file_get_contents($_sb_api_base . '/api/system/maintenance-status', false, $_sb_ctx);
    if ($_sb_resp !== false) {
        $_sb_data = json_decode($_sb_resp, true);
        if (!empty($_sb_data['maintenance'])) {
            header('Location: views/maintenance.php');
            exit;
        }
    }
}

// Dynamic scan for login background images in login/ folder
$loginImagesDir = __DIR__ . '/login';
$loginImages = [];
if (is_dir($loginImagesDir)) {
    $files = scandir($loginImagesDir);
    $validFiles = [];
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && preg_match('/\.(jpe?g|png|webp|avif|gif)$/i', $file)) {
            $validFiles[] = $file;
        }
    }
    // Ensure tangadan.jpg / hero image is first
    if (in_array('tangadan.jpg', $validFiles)) {
        $validFiles = array_values(array_diff($validFiles, ['tangadan.jpg']));
        array_unshift($validFiles, 'tangadan.jpg');
    } else {
        natcasesort($validFiles);
    }
    foreach ($validFiles as $file) {
        $loginImages[] = 'login/' . $file;
    }
}
if (empty($loginImages)) {
    $loginImages = ['images/login_bg_lighthouse.jpg'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="INTAN ELYU Tourism Management System — Official login portal for the City Tourism Office, San Fernando City, La Union.">
    <title>INTAN ELYU — Tourism Management System</title>
    <link rel="icon" type="image/png" href="images/LOGO.png">
    <link rel="shortcut icon" type="image/png" href="images/LOGO.png">
    <!-- Google Fonts: Outfit + Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,500&display=swap"
        rel="stylesheet">
    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Custom Style Sheet -->
    <link rel="stylesheet" href="css/components/login.css?v=<?php echo time(); ?>">
</head>

<body class="login-page-body">
    <div class="login-wrapper login-mode" id="loginWrapper">

        <!-- FULL-SCREEN BACKGROUND SLIDESHOW (Left Hero Photo) -->
        <div class="bg-slider-container" id="bgSliderContainer" aria-hidden="true">
            <?php foreach ($loginImages as $index => $imgSrc): ?>
                <div class="bg-slide <?= $index === 0 ? 'active' : '' ?>" data-index="<?= $index ?>"
                    style="background-image: url('<?= htmlspecialchars($imgSrc) ?>');"></div>
            <?php endforeach; ?>
        </div>

        <!-- Recovery Mode Background Slide -->
        <div class="recovery-bg-slide" id="recoveryBgSlide"
            style="background-image: url('images/login_bg_recovery.png');" aria-hidden="true"></div>

        <!-- Atmospheric Dark Navy Gradient Overlay for Left Hero -->
        <div class="info-overlay"></div>

        <!-- FLOWING S-SHAPED CURVED WHITE PANEL (Gold border + Royal Blue accent ribbon + Pure White canvas) -->
        <div class="curved-bg-layer" aria-hidden="true">
            <svg viewBox="0 0 1440 900" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <!-- Rich Gold Gradient for top flare -->
                    <linearGradient id="topGoldGradient" x1="100%" y1="0%" x2="0%" y2="100%">
                        <stop offset="0%" stop-color="#FFB900" />
                        <stop offset="50%" stop-color="#F5B400" />
                        <stop offset="100%" stop-color="#D97706" />
                    </linearGradient>

                    <!-- Royal Blue Gradient for the bottom crescent -->
                    <linearGradient id="bottomBlueGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#1455D9" />
                        <stop offset="100%" stop-color="#0E3EAA" />
                    </linearGradient>
                </defs>

                <!-- 1. Top Outer Gold Ribbon Flare -->
                <path d="M 808,0 
                         C 784,28 748,65 745,105 
                         C 742,140 762,170 770,185 
                         C 758,155 750,125 755,90 
                         C 762,55 790,22 816,0 Z" 
                      fill="url(#topGoldGradient)" opacity="0.9" />

                <!-- 2. Royal Blue accent ribbon in the lower curve (gentle, balanced crescent) -->
                <path d="M 795,450 
                         C 795,550 705,630 710,720 
                         C 715,790 750,855 770,900 
                         L 818,900 
                         C 782,855 745,790 740,720 
                         C 735,630 805,550 795,450 Z" 
                      fill="url(#bottomBlueGradient)" />
                      
                <!-- 3. Pure White flowing panel covering the right side -->
                <path d="M 780,0 
                         C 765,50 735,110 740,180 
                         C 745,270 795,360 795,450 
                         C 805,550 735,630 740,720 
                         C 745,790 782,855 818,900 
                         L 1440,900 
                         L 1440,0 Z" 
                      fill="#FFFFFF" />

                <!-- 4. Outer Gold accent stroke along the entire continuous S-curve -->
                <path d="M 780,0 
                         C 765,50 735,110 740,180 
                         C 745,270 795,360 795,450 
                         C 795,550 705,630 710,720 
                         C 715,790 750,855 770,900" 
                      fill="none" 
                      stroke="#F5B400" 
                      stroke-width="5" 
                      stroke-linecap="round" />

                <!-- 5. Secondary Inner Gold Accent Line (tightly aligned with top curve) -->
                <path d="M 794,0 
                         C 780,45 752,105 756,170 
                         C 760,240 802,330 802,400" 
                      fill="none" 
                      stroke="rgba(245, 180, 0, 0.4)" 
                      stroke-width="2" 
                      stroke-linecap="round" />
            </svg>
        </div>

        <!-- LEFT PANEL: Hero Branding, Stats & Quote -->
        <div class="info-panel" id="infoPanel">
            <div class="info-content">

                <!-- Top Portal Header -->
                <div class="left-header">
                    <div class="header-logo-container">
                        <div class="header-logo-icon">
                            <img src="images/LOGO.png" alt="Official Logo" class="header-seal-img">
                        </div>
                        <div class="header-logo-text">
                            <span class="portal-badge" id="leftPortalBadge">OFFICIAL PORTAL</span>
                            <span class="portal-dept">City Tourism Office</span>
                        </div>
                    </div>
                </div>

                <!-- Center Main Section -->
                <div class="info-main">
                    <!-- 5 Dots + Gold Bar Indicator -->
                    <div class="dots-pill-indicator" aria-hidden="true">
                        <span class="gold-dot"></span>
                        <span class="gold-dot"></span>
                        <span class="gold-dot"></span>
                        <span class="gold-dot"></span>
                        <span class="gold-dot"></span>
                        <span class="gold-pill"></span>
                    </div>

                    <h1 class="left-title" id="leftTitleText">
                        Tourism <br><span class="highlight">Management</span> <br>System
                    </h1>

                    <p class="left-subtitle">
                        <i class="fas fa-map-marker-alt location-icon"></i>
                        <span>San Fernando City, La Union</span>
                    </p>

                    <!-- Slides Dots Indicator (Dynamic background slideshow controls) -->
                    <div class="dots-indicator" id="dotsIndicator" aria-label="Tourism Spot Slides">
                        <?php foreach ($loginImages as $index => $imgSrc): ?>
                            <button type="button" class="dot <?= $index === 0 ? 'active' : '' ?>" data-slide="<?= $index ?>"
                                aria-label="Spot slide <?= $index + 1 ?>"></button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Tourism Quote (Clean elegant text with gold quotation marks) -->
                    <div class="left-quote" id="leftQuoteBox">
                        <span class="quote-mark-start"><i class="fas fa-quote-left"></i></span>
                        <p class="quote-text">
                            Discover the places that inspire,<br>
                            connect, and showcase<br>
                            the beauty of La Union.
                        </p>
                        <span class="quote-mark-end"><i class="fas fa-quote-right"></i></span>
                    </div>

                    <!-- Dynamic Left Info: Recovery Steps -->
                    <div class="dynamic-left-info">
                        <!-- Steps Progress Tracker (Visible in recovery modes) -->
                        <div class="recovery-left-info" id="leftRecoveryContainer" style="display: none;">
                            <div class="recovery-info-card">
                                <div class="recovery-info-icon">
                                    <i class="fas fa-key"></i>
                                </div>
                                <div class="recovery-info-text">
                                    <h3>Secure Password Reset</h3>
                                    <p>A unique reset link will be sent to your registered email. Links expire after 30
                                        minutes.</p>
                                </div>
                            </div>

                            <div class="steps-progress-bar">
                                <div class="step-item active" id="stepIndicator1">
                                    <div class="step-circle">1</div>
                                    <span class="step-label">Enter Email</span>
                                </div>
                                <div class="step-connector" id="stepConnector1"></div>
                                <div class="step-item" id="stepIndicator2">
                                    <div class="step-circle">2</div>
                                    <span class="step-label">Check Inbox</span>
                                </div>
                                <div class="step-connector" id="stepConnector2"></div>
                                <div class="step-item" id="stepIndicator3">
                                    <div class="step-circle">3</div>
                                    <span class="step-label">Reset Password</span>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- Footer at the bottom left -->
                <div class="left-footer">
                    &copy; 2026 City Tourism Office – San Fernando City, La Union
                </div>
            </div>
        </div>

        <!-- RIGHT PANEL: Forms, Submits, Actions -->
        <div class="form-panel" id="formPanel">

            <!-- Right panel decorative watermark line drawings (La Union Tourist Landmarks: Palm Trees, Surfer, Lighthouse, Baluarte, Bangka, Turtle, Waves) -->
            <div class="right-decorative-bg" aria-hidden="true">
                <svg viewBox="0 0 700 900" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <!-- Gentle light beam gradient for Poro Point Lighthouse -->
                        <linearGradient id="lightBeamGrad" x1="0%" y1="0%" x2="100%" y2="50%">
                            <stop offset="0%" stop-color="#F5B400" stop-opacity="0.35" />
                            <stop offset="60%" stop-color="#2563EB" stop-opacity="0.10" />
                            <stop offset="100%" stop-color="#2563EB" stop-opacity="0" />
                        </linearGradient>
                        <linearGradient id="waveGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" stop-color="#1455D9" />
                            <stop offset="50%" stop-color="#2563EB" />
                            <stop offset="100%" stop-color="#60A5FA" />
                        </linearGradient>
                    </defs>

                    <!-- 1. FLOCK OF SEAGULLS / BIRDS (Upper Sky) -->
                    <g stroke="#2563EB" stroke-width="1.3" fill="none" stroke-linecap="round">
                        <path d="M 90,65 Q 102,52 114,65 Q 126,52 138,65" />
                        <path d="M 145,80 Q 154,70 163,80 Q 172,70 181,80" />
                        <path d="M 115,95 Q 122,87 129,95 Q 136,87 143,95" />
                        <path d="M 210,60 Q 217,53 224,60 Q 231,53 238,60" />
                        <path d="M 245,78 Q 252,70 259,78 Q 266,70 273,78" />
                        <path d="M 175,115 Q 181,108 187,115 Q 193,108 199,115" />
                    </g>

                    <!-- 2. PORO POINT LIGHTHOUSE & RADIATING LIGHT BEAMS (Top Left) -->
                    <g>
                        <!-- Radiating Light Beams -->
                        <polygon points="120,70 0,20 0,140" fill="url(#lightBeamGrad)" />
                        <polygon points="120,70 300,10 320,80" fill="url(#lightBeamGrad)" opacity="0.6" />

                        <!-- Lighthouse Body & Architectural Details -->
                        <g stroke="#2563EB" stroke-width="1.2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <!-- Spire & Dome -->
                            <line x1="120" y1="40" x2="120" y2="48" stroke-width="1.5" />
                            <circle cx="120" cy="40" r="1.5" fill="#F5B400" stroke="#F5B400" />
                            <path d="M 110,58 Q 120,46 130,58" />
                            <line x1="110" y1="58" x2="130" y2="58" />
                            <!-- Lantern Glass Room -->
                            <rect x="112" y="58" width="16" height="18" />
                            <line x1="120" y1="58" x2="120" y2="76" stroke="#F5B400" />
                            <circle cx="120" cy="67" r="3" fill="#F5B400" stroke="#F5B400" />
                            <!-- Railing & Viewing Gallery Deck -->
                            <line x1="102" y1="76" x2="138" y2="76" stroke-width="1.8" />
                            <line x1="105" y1="76" x2="105" y2="84" />
                            <line x1="115" y1="76" x2="115" y2="84" />
                            <line x1="125" y1="76" x2="125" y2="84" />
                            <line x1="135" y1="76" x2="135" y2="84" />
                            <line x1="100" y1="84" x2="140" y2="84" stroke-width="1.8" />
                            <!-- Tapered Tower Shaft -->
                            <path d="M 106,84 L 88,430 L 152,430 L 134,84" />
                            <!-- Decorative Horizontal Ring Bands -->
                            <line x1="104" y1="150" x2="136" y2="150" stroke-dasharray="3,2" />
                            <line x1="101" y1="220" x2="139" y2="220" />
                            <line x1="97" y1="290" x2="143" y2="290" stroke-dasharray="3,2" />
                            <line x1="93" y1="360" x2="147" y2="360" />
                            <!-- Arched Windows -->
                            <path d="M 116,125 L 116,115 Q 120,110 124,115 L 124,125 Z" />
                            <path d="M 115,190 L 115,178 Q 120,172 125,178 L 125,190 Z" />
                            <path d="M 114,260 L 114,246 Q 120,240 126,246 L 126,260 Z" />
                            <path d="M 113,330 L 113,314 Q 120,306 127,314 L 127,330 Z" />
                            <!-- Base Stone Foundation -->
                            <rect x="80" y="430" width="80" height="20" rx="2" stroke-width="1.4" />
                            <line x1="70" y1="450" x2="170" y2="450" stroke-width="1.6" />
                        </g>
                    </g>

                    <!-- 3. HISTORIC BALUARTE WATCHTOWER (Luna Heritage - Mid Left) -->
                    <g stroke="#2563EB" stroke-width="1.1" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <!-- Round Fortress Tower Silhouette -->
                        <path d="M 45,560 Q 40,490 55,430 L 95,430 Q 110,490 105,560" />
                        <!-- Battlements / Crenels on Top -->
                        <line x1="50" y1="430" x2="100" y2="430" stroke-width="1.5" />
                        <rect x="54" y="420" width="8" height="10" />
                        <rect x="68" y="420" width="8" height="10" />
                        <rect x="82" y="420" width="8" height="10" />
                        <rect x="94" y="420" width="6" height="10" />
                        <!-- Arched Watch Observation Portal -->
                        <path d="M 70,470 L 70,455 Q 75,450 80,455 L 80,470 Z" />
                        <!-- Brick / Adobe Masonry Lines -->
                        <line x1="50" y1="490" x2="100" y2="490" stroke-dasharray="4,4" />
                        <line x1="48" y1="515" x2="102" y2="515" stroke-dasharray="5,3" />
                        <line x1="46" y1="540" x2="104" y2="540" stroke-dasharray="4,4" />
                    </g>

                    <!-- 4. PHILIPPINE BANGKA / OUTRIGGER SAILBOAT (Mid-Lower Left Horizon) -->
                    <g stroke="#2563EB" stroke-width="1.2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <!-- Hull -->
                        <path d="M 50,620 Q 80,630 115,620 L 105,626 Q 80,632 60,626 Z" />
                        <!-- Mast & Triangle Sail -->
                        <line x1="82" y1="620" x2="82" y2="575" stroke-width="1.4" />
                        <path d="M 82,577 L 108,615 L 82,615 Z" fill="#2563EB" fill-opacity="0.08" />
                        <path d="M 80,582 L 62,616 L 80,616 Z" />
                        <!-- Bamboo Outrigger (Katig) -->
                        <line x1="68" y1="622" x2="55" y2="630" />
                        <line x1="95" y1="622" x2="82" y2="630" />
                        <path d="M 45,630 Q 75,635 105,630" stroke-width="1.4" />
                    </g>

                    <!-- 5. LUSH TROPICAL COCONUT PALM TREES (Right & Top Right) -->
                    <g>
                        <!-- Main Tall Palm Tree Trunk (Curved & Segmented) -->
                        <g stroke="#2563EB" stroke-width="2.2" fill="none" stroke-linecap="round">
                            <path d="M 645,620 Q 630,420 615,280 Q 600,160 560,115" />
                        </g>
                        <!-- Trunk Texture Rings -->
                        <g stroke="#2563EB" stroke-width="1.3" fill="none">
                            <line x1="637" y1="560" x2="648" y2="562" />
                            <line x1="633" y1="500" x2="643" y2="502" />
                            <line x1="628" y1="440" x2="638" y2="442" />
                            <line x1="622" y1="380" x2="631" y2="382" />
                            <line x1="614" y1="320" x2="623" y2="322" />
                            <line x1="604" y1="260" x2="613" y2="262" />
                            <line x1="591" y1="200" x2="600" y2="202" />
                            <line x1="573" y1="150" x2="581" y2="152" />
                        </g>

                        <!-- Secondary Younger Leaning Palm Tree -->
                        <g stroke="#2563EB" stroke-width="1.8" fill="none" stroke-linecap="round">
                            <path d="M 675,620 Q 670,480 660,370 Q 650,290 620,245" />
                        </g>

                        <!-- Hanging Coconuts Cluster (Gold Accent) -->
                        <g fill="#F5B400" stroke="#D97706" stroke-width="1">
                            <circle cx="558" cy="120" r="4.5" />
                            <circle cx="566" cy="122" r="4.5" />
                            <circle cx="562" cy="127" r="4" />
                            <circle cx="618" cy="250" r="3.5" />
                            <circle cx="624" cy="252" r="3.5" />
                        </g>

                        <!-- Lush Palm Fronds & Detailed Leaflets (Main Tree) -->
                        <g stroke="#2563EB" stroke-width="1.4" fill="none" stroke-linecap="round">
                            <!-- Frond 1 (Sweeping Top Left) -->
                            <path d="M 560,115 Q 500,75 435,100" stroke-width="2" />
                            <path d="M 535,93 L 515,70 M 510,90 L 485,68 M 485,93 L 458,72 M 460,98 L 435,82" />
                            <path d="M 545,105 L 530,125 M 520,102 L 500,125 M 495,100 L 472,122 M 470,98 L 448,118" />

                            <!-- Frond 2 (Sweeping Top Center) -->
                            <path d="M 560,115 Q 540,40 480,45" stroke-width="2" />
                            <path d="M 550,75 L 530,52 M 535,65 L 510,42 M 518,58 L 490,38 M 500,52 L 475,36" />
                            <path d="M 555,85 L 542,105 M 540,75 L 522,98 M 522,68 L 500,90 M 505,62 L 480,82" />

                            <!-- Frond 3 (Sweeping Far Right) -->
                            <path d="M 560,115 Q 610,65 675,90" stroke-width="2" />
                            <path d="M 585,90 L 602,68 M 610,85 L 632,65 M 635,85 L 660,70" />
                            <path d="M 585,102 L 605,122 M 610,100 L 632,120 M 635,95 L 660,112" />

                            <!-- Frond 4 (Sweeping Bottom Left) -->
                            <path d="M 560,115 Q 490,140 440,210" stroke-width="2" />
                            <path d="M 530,132 L 505,115 M 505,148 L 478,130 M 480,170 L 452,150 M 458,195 L 432,175" />
                            <path d="M 540,140 L 525,165 M 515,158 L 495,185 M 490,178 L 468,208" />

                            <!-- Frond 5 (Sweeping Bottom Right Droop) -->
                            <path d="M 560,115 Q 630,150 670,225" stroke-width="2" />
                            <path d="M 585,128 L 610,112 M 610,145 L 638,128 M 635,170 L 665,152 M 652,200 L 680,180" />
                            <path d="M 580,138 L 598,162 M 605,158 L 625,185 M 628,185 L 648,212" />

                            <!-- Fronds on Secondary Tree -->
                            <path d="M 620,245 Q 560,220 510,250" stroke-width="1.6" />
                            <path d="M 585,232 L 565,212 M 560,235 L 538,218 M 535,242 L 512,228" />
                            <path d="M 620,245 Q 670,220 700,265" stroke-width="1.6" />
                            <path d="M 645,235 L 668,218 M 670,245 L 695,230" />
                            <path d="M 620,245 Q 580,280 540,335" stroke-width="1.6" />
                            <path d="M 595,270 L 572,255 M 575,290 L 550,275 M 555,315 L 530,300" />
                        </g>
                    </g>

                    <!-- 6. LA UNION SURFING CULTURE (Surfboard & Surfer Silhouette - Right) -->
                    <g stroke="#2563EB" stroke-width="1.3" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <!-- Upright Surfboard in Sand (Mid-Right) -->
                        <g>
                            <!-- Board Shape -->
                            <path d="M 630,620 C 625,560 622,480 628,420 C 631,395 636,380 638,380 C 640,380 645,395 648,420 C 654,480 651,560 646,620 Z" 
                                  fill="#2563EB" fill-opacity="0.06" stroke-width="1.6" />
                            <!-- Surfboard Center Stringer Stripe -->
                            <line x1="638" y1="380" x2="638" y2="620" stroke="#F5B400" stroke-width="1.4" />
                            <!-- Surfboard Tail Fin in Sand -->
                            <path d="M 630,620 L 646,620" stroke-width="2" />
                            <!-- Tropical Hibiscus / Flower Accent on Board -->
                            <circle cx="638" cy="460" r="3" fill="#F5B400" stroke="#F5B400" />
                            <circle cx="638" cy="454" r="2" stroke="#2563EB" />
                            <circle cx="644" cy="458" r="2" stroke="#2563EB" />
                            <circle cx="642" cy="465" r="2" stroke="#2563EB" />
                            <circle cx="634" cy="465" r="2" stroke="#2563EB" />
                            <circle cx="632" cy="458" r="2" stroke="#2563EB" />
                        </g>

                        <!-- Surfer Riding a Barrel Wave (Lower Mid) -->
                        <g>
                            <!-- Surfer Figure -->
                            <circle cx="340" cy="670" r="3" fill="#2563EB" /> <!-- Head -->
                            <path d="M 340,673 L 338,685 L 332,695 M 338,685 L 344,695" /> <!-- Body & Legs bent in surf stance -->
                            <path d="M 330,678 L 338,677 L 348,675" /> <!-- Balanced Arms extended -->
                            <!-- Surfboard on Wave Face -->
                            <path d="M 324,698 Q 338,697 354,692" stroke-width="2.2" stroke="#F5B400" />
                            <!-- Wave Barrel Lip curling over -->
                            <path d="M 280,720 Q 300,660 340,650 Q 370,645 385,665" stroke-width="1.8" />
                            <!-- Spray Droplets -->
                            <circle cx="375" cy="650" r="1.2" fill="#2563EB" />
                            <circle cx="388" cy="655" r="1.5" fill="#2563EB" />
                            <circle cx="395" cy="662" r="1.2" fill="#2563EB" />
                        </g>
                    </g>

                    <!-- 7. PAWIKAN (Sea Turtle Sanctuary - Bottom Center) -->
                    <g stroke="#2563EB" stroke-width="1.2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <!-- Turtle Carapace (Shell) -->
                        <ellipse cx="200" cy="780" rx="14" ry="18" fill="#2563EB" fill-opacity="0.06" transform="rotate(-25 200 780)" />
                        <!-- Shell Scute Patterns -->
                        <path d="M 194,766 L 202,772 L 200,782 L 192,776 Z" />
                        <path d="M 202,772 L 210,778 L 208,788 L 200,782 Z" />
                        <path d="M 200,782 L 208,788 L 204,798 L 196,792 Z" />
                        <!-- Head -->
                        <path d="M 190,760 Q 186,752 182,755 Q 180,760 186,765 Z" fill="#2563EB" fill-opacity="0.1" />
                        <!-- Front Flippers (Swimming stroke) -->
                        <path d="M 186,768 Q 165,760 155,772 Q 170,780 188,778" stroke-width="1.4" />
                        <path d="M 206,764 Q 225,750 238,760 Q 228,772 208,774" stroke-width="1.4" />
                        <!-- Rear Flippers -->
                        <path d="M 192,796 Q 185,808 190,812 Q 198,810 198,798" />
                        <path d="M 206,794 Q 215,805 220,802 Q 220,796 210,792" />
                    </g>

                    <!-- 8. TROPICAL MONSTERA BOTANICAL LEAF (Bottom Left) -->
                    <g stroke="#2563EB" stroke-width="1.2" fill="none" stroke-linecap="round">
                        <!-- Main Stem -->
                        <path d="M 40,860 Q 60,780 95,730" stroke-width="1.8" />
                        <!-- Leaf Blade Outline with Fenestrations (Monstera cuts) -->
                        <path d="M 95,730 Q 115,745 120,765 Q 105,768 95,755 Q 118,780 115,800 Q 100,798 90,780 Q 105,815 95,835 Q 85,820 80,800 Q 85,840 70,855 L 40,860" />
                        <path d="M 95,730 Q 75,740 60,755 Q 75,760 85,752 Q 60,775 52,795 Q 68,792 78,778 Q 50,810 45,830 L 40,860" />
                    </g>

                    <!-- 9. MULTI-LAYERED OCEAN SWELLS & FOAM CONTOURS (Bottom Across) -->
                    <g stroke="url(#waveGrad)" stroke-linecap="round" fill="none">
                        <!-- Wave 1 (Upper gentle swell) -->
                        <path d="M 10,680 Q 180,640 360,690 T 700,670" stroke-width="1.1" opacity="0.6" />
                        <path d="M 80,685 Q 220,655 380,695 T 660,680" stroke-width="0.8" stroke-dasharray="8,6" opacity="0.4" />

                        <!-- Wave 2 (Mid rolling wave with curl) -->
                        <path d="M 0,730 Q 160,680 340,735 T 700,715" stroke-width="1.4" opacity="0.8" />
                        <path d="M 140,732 Q 260,695 400,740" stroke-width="1.1" stroke="#F5B400" opacity="0.5" />

                        <!-- Wave 3 (Bold surf crest) -->
                        <path d="M 10,780 Q 200,730 400,785 T 700,760" stroke-width="1.6" />
                        <path d="M 220,782 Q 320,750 440,790" stroke-width="1" stroke-dasharray="10,5" opacity="0.5" />

                        <!-- Wave 4 (Deep bottom tide) -->
                        <path d="M 0,830 Q 220,780 440,835 T 700,810" stroke-width="1.8" />
                        <path d="M 10,870 Q 240,820 480,875 T 700,850" stroke-width="2.2" />
                    </g>
                </svg>
            </div>

            <!-- Explore La Union Button (Top Right) -->
            <a href="#" class="explore-btn" id="exploreLaUnionBtn" aria-label="Explore La Union tourism">
                <i class="fas fa-sun sun-icon"></i>
                <span>Explore La Union</span>
            </a>

            <!-- Top Navigation Link (Forgot password back button) -->
            <div class="back-to-login-top" id="topBackToLogin" style="visibility: hidden;">
                <a href="#" class="btn-back-link" id="topBackToLoginBtn"><i class="fas fa-arrow-left"></i> Back to
                    Login</a>
            </div>

            <!-- Floating Form Card Wrapper -->
            <div class="form-card-container">

                <!-- Card Brand Header -->
                <div class="card-brand-header">
                    <div class="brand-logo-image-wrapper">
                        <img src="images/LOGO.png" alt="INTAN ELYU Logo" class="brand-logo-img">
                    </div>
                    <h2 class="brand-title">INTAN ELYU</h2>
                    <p class="brand-subtitle">Tourism Management System</p>
                    <p class="brand-location">San Fernando City, La Union</p>
                    <div class="brand-accent-line" aria-hidden="true"></div>
                </div>

                <!-- 1. LOGIN FORM SECTION -->
                <div id="loginSection" class="form-section-container active">
                    <div class="section-header">
                        <h3>Welcome Back!</h3>
                        <p><?= $isAdminBypass ? '<span style="color:#1D4ED8;font-weight:700;"><i class="fas fa-user-shield"></i> PICTO Administrator Sign In</span>' : 'Sign in to your account to continue' ?></p>
                    </div>

                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-error">
                            <?php
                            $errors = [
                                'empty_fields' => 'Please fill in all fields.',
                                'invalid_credentials' => 'Invalid email or password.',
                                'db_error' => 'Database error occurred.',
                                'unauthorized' => 'You are not authorized to access this page.'
                            ];
                            echo $errors[$_GET['error']] ?? 'An error occurred.';
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['reset_success'])): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> Password successfully updated. Please sign in with your new
                            credentials.
                        </div>
                    <?php endif; ?>

                    <form id="loginForm">
                        <div class="form-input-group">
                            <label for="email">Email</label>
                            <div class="input-with-icon">
                                <i class="far fa-user"></i>
                                <input type="text" id="email" name="email" required placeholder="youremail@example.com">
                            </div>
                        </div>

                        <div class="form-input-group">
                            <label for="password">Password</label>
                            <div class="password-field-wrapper">
                                <i class="fas fa-lock pw-left-icon" aria-hidden="true"></i>
                                <input type="password" id="password" name="password" required placeholder="••••••••"
                                    autocomplete="current-password">
                                <button type="button" id="togglePassword" class="pw-toggle-btn"
                                    aria-label="Toggle password visibility" tabindex="-1">
                                    <i class="far fa-eye" id="pwEyeIcon" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-actions-row">
                            <label class="custom-checkbox" for="rememberMe">
                                <input type="checkbox" id="rememberMe" name="remember_me" checked>
                                <span class="checkmark"><i class="fas fa-check"></i></span>
                                <span class="label-text">Remember me</span>
                            </label>
                            <a href="#" class="forgot-password-link" id="forgotPasswordBtn">Forgot Password?</a>
                        </div>

                        <div id="errorMessage" class="alert alert-error" style="display: none;"></div>

                        <button type="submit" class="btn-primary-gradient btn-login">
                            <i class="fas fa-arrow-right-to-bracket"></i>
                            <span>Sign In</span>
                        </button>

                        <div class="bottom-admin-note">
                            Don't have an account? <a href="mailto:support@sanfernando.gov.ph"
                                id="contactAdminLink">Contact Administrator</a>
                        </div>
                    </form>
                </div>

                <!-- 2. FORGOT PASSWORD STEP 1 SECTION -->
                <div id="recoveryStep1Section" class="form-section-container">
                    <div class="section-header">
                        <h3>Forgot Password?</h3>
                        <p>No worries! Enter your registered email and we'll send you a secure link to reset your
                            password.</p>
                    </div>

                    <form id="recoveryEmailForm">
                        <div class="form-input-group">
                            <label for="recoveryEmail">Registered Email Address</label>
                            <div class="input-with-icon">
                                <i class="far fa-envelope"></i>
                                <input type="email" id="recoveryEmail" required placeholder="your@email.com">
                            </div>
                            <span class="input-hint-info"><i class="fas fa-info-circle"></i> We'll send the reset link
                                to this address.</span>
                        </div>

                        <div id="recoveryErrorMessage" class="alert alert-error" style="display: none;"></div>

                        <button type="submit" class="btn-primary-gradient btn-send-link">
                            <i class="far fa-paper-plane"></i>
                            <span>Send Reset Link</span>
                        </button>

                        <div class="divider-or">OR</div>

                        <button type="button" class="btn-outline btn-back-to-login" id="backToLoginBtn2">
                            <i class="fas fa-sign-in-alt"></i>
                            Back to Login
                        </button>
                    </form>
                </div>

                <!-- 3. CHECK YOUR INBOX STEP 2 SECTION -->
                <div id="recoveryStep2Section" class="form-section-container">
                    <div class="email-success-badge-container">
                        <div class="email-success-badge">
                            <i class="far fa-envelope"></i>
                        </div>
                    </div>

                    <div class="section-header text-center">
                        <h3>Check Your Inbox!</h3>
                        <p>We've sent a password reset link to <span id="sentEmailPlaceholder">your@email.com</span>.
                            Please check your inbox (and spam folder) and click the link.</p>
                    </div>

                    <div class="info-blocks-row">
                        <div class="info-tag-block">
                            <div class="info-tag-icon color-blue">
                                <i class="far fa-clock"></i>
                            </div>
                            <div class="info-tag-text">
                                <span class="tag-title">LINK EXPIRES IN</span>
                                <span class="tag-val">30 minutes</span>
                            </div>
                        </div>
                        <div class="info-tag-block">
                            <div class="info-tag-icon color-blue">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="info-tag-text">
                                <span class="tag-title">SINGLE USE</span>
                                <span class="tag-val">One-time link</span>
                            </div>
                        </div>
                    </div>

                    <div class="progress-line-container">
                        <div class="progress-line-fill"></div>
                        <span class="progress-line-label">Link valid for 30 minutes</span>
                    </div>

                    <button type="button" class="btn-primary-gradient btn-back-to-login-success"
                        id="returnToLoginSuccessBtn">
                        <i class="fas fa-sign-in-alt"></i>
                        Return to Login
                    </button>

                    <button type="button" id="btnResendEmail" class="btn-outline btn-resend-email">
                        <i class="fas fa-sync-alt"></i>
                        Resend Email
                    </button>

                    <div class="support-footer">
                        <i class="fas fa-headset"></i> Need help? Contact <a
                            href="mailto:support@sanfernando.gov.ph">support@sanfernando.gov.ph</a>
                    </div>
                </div>

            </div>

            <!-- Footer at the bottom right -->
            <div class="right-footer">
                &copy; 2026 City Tourism Office – San Fernando City, La Union
            </div>

        </div>

    </div>

    <!-- Success Modal -->
    <div id="successModal" class="modal">
        <div class="modal-content">
            <div class="modal-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2>Login Successful!</h2>
            <p>Redirecting you to the dashboard...</p>
        </div>
    </div>

    <!-- Error Modal -->
    <div id="errorModal" class="modal">
        <div class="modal-content">
            <div class="modal-icon error">
                <i class="fas fa-times-circle"></i>
            </div>
            <h2 id="errorModalTitle">Login Failed</h2>
            <p id="errorModalText">Invalid email or password.</p>
            <button type="button" id="closeErrorModal" class="btn-close">Try Again</button>
        </div>
    </div>

    <!-- ── Pending Account Info Modal (shown when Forgot Password is attempted by a pending user) -->
    <div id="pendingAccountModal" style="
        display:none; position:fixed; inset:0;
        background:rgba(15,23,42,0.6); backdrop-filter:blur(6px);
        z-index:99999; align-items:center; justify-content:center;
    ">
        <div style="
            background:#fff; border-radius:24px; padding:36px 32px;
            max-width:460px; width:90%; box-shadow:0 24px 64px rgba(15,23,42,0.25);
            text-align:center; animation: scaleIn 0.3s ease;
        ">
            <div style="
                width:64px; height:64px; background:linear-gradient(135deg,#FEF3C7,#FDE68A);
                border-radius:50%; margin:0 auto 20px; display:flex;
                align-items:center; justify-content:center;
                border:3px solid #F59E0B;
            ">
                <i class="fas fa-user-clock" style="font-size:26px; color:#D97706;"></i>
            </div>
            <h3
                style="margin:0 0 12px; color:#0F172A; font-size:18px; font-weight:800; font-family:'Outfit',sans-serif;">
                Account Pending Activation
            </h3>
            <p style="margin:0 0 20px; color:#475569; font-size:13.5px; line-height:1.6;">
                Your account is still <strong>pending activation</strong>. Please log in using your
                <strong>assigned default password</strong> and change your password first before
                using the Forgot Password feature.
            </p>
            <div
                style="background:#FFFBEB; border:1px solid #FDE68A; border-radius:12px; padding:14px 18px; margin-bottom:24px; text-align:left;">
                <p style="margin:0; font-size:12.5px; color:#92400E; line-height:1.5;">
                    <i class="fas fa-info-circle" style="margin-right:6px; color:#D97706;"></i>
                    <strong>How to activate:</strong> Go back to the login page, sign in with your
                    default password, then change it when prompted. Your account will become
                    <strong>Active</strong> automatically.
                </p>
            </div>
            <button id="closePendingModal" style="
                background:linear-gradient(135deg,#2563EB,#1D4ED8);
                color:#fff; border:none; border-radius:12px;
                padding:12px 32px; font-size:14px; font-weight:700;
                cursor:pointer; width:100%; transition:transform 0.2s, box-shadow 0.2s;
                box-shadow:0 4px 14px rgba(37,99,235,0.3);
            " onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='translateY(0)'">
                <i class="fas fa-arrow-left" style="margin-right:8px;"></i>
                Back to Login
            </button>
        </div>
    </div>

    <!-- JavaScripts -->
    <script src="scripts/api-config.js?v=<?php echo time(); ?>"></script>
    <script src="scripts/login.js?v=<?php echo time(); ?>"></script>
    <script>
        // ── Password Visibility Toggle ──────────────────────────────────────
        (function () {
            const btn = document.getElementById('togglePassword');
            const input = document.getElementById('password');
            const icon = document.getElementById('pwEyeIcon');
            if (!btn || !input || !icon) return;

            btn.addEventListener('click', function () {
                const isHidden = input.type === 'password';
                input.type = isHidden ? 'text' : 'password';
                icon.className = isHidden ? 'far fa-eye-slash' : 'far fa-eye';
                btn.setAttribute('aria-pressed', String(isHidden));
            });
        })();
    </script>
</body>

</html>