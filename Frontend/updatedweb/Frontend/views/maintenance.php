<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If PICTO visits maintenance.php, redirect them directly to dashboard
$role = $_SESSION['user_role'] ?? '';
if (in_array($role, ['picto', 'pitco'], true)) {
    header('Location: dashboard.php');
    exit;
}

$apiBase = 'http://127.0.0.1:8000';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Under Maintenance — INTAN ELYU</title>
    <link rel="icon" type="image/png" href="../images/LOGO.png">
    <link rel="shortcut icon" type="image/png" href="../images/LOGO.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800;900&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Maintenance External CSS -->
    <link rel="stylesheet" href="../css/maintenance.css?v=<?= time() ?>">
</head>
<body>

    <div class="maintenance-layout">

        <!-- ── LEFT SECTION (Blue side with Floating Card) ── -->
        <div class="left-panel">
            
            <!-- Nautical background watermark on the blue side -->
            <svg class="left-watermark" viewBox="0 0 800 900" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- Lighthouse on top left -->
                <g stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M 90,40 L 110,40 L 120,130 L 80,130 Z" />
                    <path d="M 95,25 L 105,25 L 108,40 L 92,40 Z" />
                    <path d="M 95,25 Q 100,16 105,25" />
                    <line x1="100" y1="16" x2="100" y2="10" />
                    <line x1="86" y1="40" x2="114" y2="40" />
                    <line x1="80" y1="28" x2="45" y2="20" stroke-dasharray="4 4" />
                    <line x1="80" y1="32" x2="40" y2="38" stroke-dasharray="4 4" />
                    <line x1="120" y1="28" x2="155" y2="20" stroke-dasharray="4 4" />
                    <rect x="96" y="55" width="8" height="12" rx="2" />
                    <rect x="95" y="85" width="10" height="14" rx="2" />
                    <path d="M 65,130 Q 100,122 135,130" />
                    <path d="M 160,50 Q 168,44 176,50 Q 184,44 192,50" />
                    <path d="M 130,75 Q 136,70 142,75 Q 148,70 154,75" />
                </g>

                <!-- Sailboat on bottom left -->
                <g stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M 70,810 L 130,810 L 120,830 L 80,830 Z" />
                    <line x1="100" y1="810" x2="100" y2="745" />
                    <path d="M 100,750 L 124,800 L 100,800 Z" />
                    <path d="M 96,760 L 78,800 L 96,800 Z" />
                </g>

                <!-- Ocean Waves Contours -->
                <g stroke="#FFFFFF" stroke-width="1.5" opacity="0.6">
                    <path d="M 0,840 Q 150,820 300,850 T 600,835 T 900,860" />
                    <path d="M 0,865 Q 180,850 360,875 T 720,860" />
                    <path d="M 0,890 Q 200,875 400,895 T 800,885" />
                </g>
            </svg>

            <!-- Floating White Maintenance Card -->
            <div class="maintenance-card">
                
                <!-- 3D-Style Maintenance Graphic (Gear + Wrench + Cones + Sparkles) -->
                <div class="card-graphic">
                    <svg viewBox="0 0 260 140" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <!-- Main Blue Gear Gradient -->
                            <linearGradient id="gearGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#3B82F6" />
                                <stop offset="100%" stop-color="#1D4ED8" />
                            </linearGradient>

                            <!-- Glossy Wrench Gradient -->
                            <linearGradient id="wrenchGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#94A3B8" />
                                <stop offset="50%" stop-color="#CBD5E1" />
                                <stop offset="100%" stop-color="#64748B" />
                            </linearGradient>

                            <!-- Traffic Cone Orange Gradient -->
                            <linearGradient id="coneGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" stop-color="#EA580C" />
                                <stop offset="50%" stop-color="#FB923C" />
                                <stop offset="100%" stop-color="#C2410C" />
                            </linearGradient>

                            <!-- Drop Shadows -->
                            <filter id="gearShadow" x="-20%" y="-20%" width="140%" height="140%">
                                <feDropShadow dx="0" dy="8" stdDeviation="10" flood-color="#1D4ED8" flood-opacity="0.28" />
                            </filter>
                            <filter id="coneShadow" x="-30%" y="-20%" width="160%" height="150%">
                                <feDropShadow dx="0" dy="6" stdDeviation="6" flood-color="#000000" flood-opacity="0.16" />
                            </filter>
                        </defs>

                        <!-- Subtle background glow behind gear -->
                        <circle cx="130" cy="65" r="54" fill="#EFF6FF" opacity="0.9" />

                        <!-- Small secondary decorative gear on upper left -->
                        <g transform="translate(48, 48) scale(0.42)" opacity="0.6">
                            <path d="M 0,-24 L 6,-24 L 8,-18 L 16,-16 L 22,-20 L 26,-16 L 24,-10 L 28,-4 L 34,-2 L 34,4 L 28,6 L 24,12 L 26,18 L 22,22 L 16,20 L 10,24 L 8,30 L -8,30 L -10,24 L -16,20 L -22,22 L -26,18 L -24,12 L -28,6 L -34,4 L -34,-2 L -28,-4 L -24,-10 L -26,-16 L -22,-20 L -16,-16 L -8,-18 Z" 
                                  fill="#93C5FD" />
                            <circle cx="0" cy="0" r="10" fill="#EFF6FF" />
                        </g>

                        <!-- Sparkles and floating plus signs -->
                        <g fill="#60A5FA">
                            <path d="M 68,26 H 74 M 71,23 V 29" stroke="#60A5FA" stroke-width="2" stroke-linecap="round" />
                            <path d="M 194,36 H 200 M 197,33 V 39" stroke="#60A5FA" stroke-width="2" stroke-linecap="round" />
                            <circle cx="58" cy="38" r="1.8" />
                            <circle cx="212" cy="50" r="1.8" />
                            <line x1="56" y1="28" x2="62" y2="28" stroke="#93C5FD" stroke-width="2" stroke-linecap="round" />
                            <line x1="202" y1="62" x2="208" y2="62" stroke="#93C5FD" stroke-width="2" stroke-linecap="round" />
                        </g>

                        <!-- ── Main Center Gear ── -->
                        <g filter="url(#gearShadow)" class="anim-float">
                            <!-- Gear Outer Shape -->
                            <path d="M 130,22 
                                     L 136,22 L 138,28 C 142,29 146,31 150,33 L 155,29 L 161,35 L 157,40 C 159,44 161,48 162,52 L 168,54 L 168,62 L 162,64 C 161,68 159,72 157,76 L 161,81 L 155,87 L 150,83 C 146,85 142,87 138,88 L 136,94 L 124,94 L 122,88 C 118,87 114,85 110,83 L 105,87 L 99,81 L 103,76 C 101,72 99,68 98,64 L 92,62 L 92,54 L 98,52 C 99,48 101,44 103,40 L 99,35 L 105,29 L 110,33 C 114,31 118,29 122,28 L 124,22 Z"
                                  fill="url(#gearGradient)" />

                            <!-- Gear Inner Ring & Core Hole -->
                            <circle cx="130" cy="58" r="22" fill="#FFFFFF" opacity="0.95" />
                            <circle cx="130" cy="58" r="13" fill="#1E40AF" />
                            <circle cx="130" cy="58" r="8" fill="#FFFFFF" />
                        </g>

                        <!-- ── Left Traffic Cone ── -->
                        <g filter="url(#coneShadow)">
                            <rect x="58" y="108" width="30" height="7" rx="3" fill="#C2410C" />
                            <path d="M 62,109 L 71,68 L 75,68 L 84,109 Z" fill="url(#coneGradient)" />
                            <path d="M 71,68 Q 73,65 75,68 L 74.5,74 L 71.5,74 Z" fill="#EA580C" />
                            <path d="M 65,96 L 68,82 L 78,82 L 81,96 Z" fill="#FFFFFF" opacity="0.96" />
                            <path d="M 66.5,88 L 69.5,76 L 76.5,76 L 79.5,88 Z" fill="#EA580C" />
                            <path d="M 69.5,77 L 71,70 L 75,70 L 76.5,77 Z" fill="#FFFFFF" opacity="0.96" />
                        </g>

                        <!-- ── Right Traffic Cone ── -->
                        <g filter="url(#coneShadow)">
                            <rect x="172" y="108" width="30" height="7" rx="3" fill="#C2410C" />
                            <path d="M 176,109 L 185,68 L 189,68 L 198,109 Z" fill="url(#coneGradient)" />
                            <path d="M 185,68 Q 187,65 189,68 L 188.5,74 L 185.5,74 Z" fill="#EA580C" />
                            <path d="M 179,96 L 182,82 L 192,82 L 195,96 Z" fill="#FFFFFF" opacity="0.96" />
                            <path d="M 180.5,88 L 183.5,76 L 190.5,76 L 193.5,88 Z" fill="#EA580C" />
                            <path d="M 183.5,77 L 185,70 L 189,70 L 190.5,77 Z" fill="#FFFFFF" opacity="0.96" />
                        </g>

                        <!-- ── Glossy Diagonal Wrench ── -->
                        <g transform="translate(130, 68) rotate(-38) translate(-130, -68)" filter="url(#gearShadow)">
                            <rect x="124" y="28" width="12" height="74" rx="6" fill="url(#wrenchGradient)" stroke="#475569" stroke-width="1.5" />
                            <rect x="127" y="44" width="6" height="42" rx="3" fill="#475569" opacity="0.35" />
                            <path d="M 116,28 C 116,16 124,10 130,10 C 136,10 144,16 144,28 L 137,28 L 137,22 L 123,22 L 123,28 Z" fill="url(#wrenchGradient)" stroke="#475569" stroke-width="1.5" />
                            <circle cx="130" cy="100" r="10" fill="url(#wrenchGradient)" stroke="#475569" stroke-width="1.5" />
                            <circle cx="130" cy="100" r="5" fill="#FFFFFF" />
                        </g>
                    </svg>
                </div>

                <!-- Status Pill Badge -->
                <div class="status-pill">
                    <span class="pill-dot"></span>
                    <span>System Under Maintenance</span>
                </div>

                <!-- Main Card Heading -->
                <h1 class="card-title">System Under Maintenance</h1>

                <!-- Subtitle Description -->
                <p class="card-desc">
                    We are currently performing scheduled maintenance to improve the system. Please try again later.
                </p>

                <!-- Polling Status -->
                <div class="card-poll">
                    <div class="poll-status-row">
                        <span class="poll-spinner" id="pollSpinner"></span>
                        <span id="pollStatus">Checking system status...</span>
                    </div>
                    <div class="countdown-text">
                        Next check in <strong id="countdownNum">28</strong>s
                    </div>
                </div>

            </div>
        </div>

        <!-- ── FLOWING S-CURVE DIVIDER (Gold + Royal Blue ribbon + White fill) ── -->
        <div class="curved-divider" aria-hidden="true">
            <svg viewBox="0 0 1440 900" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="topGoldGrad" x1="100%" y1="0%" x2="0%" y2="100%">
                        <stop offset="0%" stop-color="#FFB900" />
                        <stop offset="50%" stop-color="#F5B400" />
                        <stop offset="100%" stop-color="#D97706" />
                    </linearGradient>

                    <linearGradient id="bottomBlueGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#1455D9" />
                        <stop offset="100%" stop-color="#0E3EAA" />
                    </linearGradient>
                </defs>

                <!-- Royal Blue Accent Ribbon in Lower Curve -->
                <path d="M 760,450 
                         C 760,550 670,630 675,720 
                         C 680,790 715,855 735,900 
                         L 783,900 
                         C 747,855 710,790 705,720 
                         C 700,630 770,550 760,450 Z" 
                      fill="url(#bottomBlueGrad)" />

                <!-- Pure White Flowing Panel Covering Right Half -->
                <path d="M 745,0 
                         C 730,50 700,110 705,180 
                         C 710,270 760,360 760,450 
                         C 770,550 700,630 705,720 
                         C 710,790 747,855 783,900 
                         L 1440,900 
                         L 1440,0 Z" 
                      fill="#FFFFFF" />

                <!-- Continuous Outer Gold Stroke Ribbon -->
                <path d="M 745,0 
                         C 730,50 700,110 705,180 
                         C 710,270 760,360 760,450 
                         C 760,550 670,630 675,720 
                         C 680,790 715,855 735,900" 
                      fill="none" 
                      stroke="#F5B400" 
                      stroke-width="5" 
                      stroke-linecap="round" />

                <!-- Secondary Fine Gold Accent Line -->
                <path d="M 759,0 
                         C 745,45 717,105 721,170 
                         C 725,240 767,330 767,400" 
                      fill="none" 
                      stroke="rgba(245, 180, 0, 0.45)" 
                      stroke-width="2" 
                      stroke-linecap="round" />
            </svg>
        </div>

        <!-- ── RIGHT SECTION (White Branding & Safe Session Box) ── -->
        <div class="right-panel">
            
            <!-- Tropical Palm Tree & Surfboard Watermark on Right Edge -->
            <div class="right-watermark" aria-hidden="true">
                <svg viewBox="0 0 320 800" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <g stroke="#2563EB" stroke-width="1.8" stroke-linecap="round" opacity="0.65">
                        <path d="M 260,800 Q 240,450 210,180" />
                        <path d="M 275,800 Q 255,450 220,180" />
                        <line x1="258" y1="720" x2="273" y2="720" />
                        <line x1="254" y1="640" x2="269" y2="640" />
                        <line x1="248" y1="560" x2="263" y2="560" />
                        <line x1="240" y1="480" x2="255" y2="480" />
                        <line x1="230" y1="400" x2="244" y2="400" />
                        <line x1="220" y1="320" x2="233" y2="320" />
                        <path d="M 215,180 Q 150,140 80,180" />
                        <path d="M 195,172 L 185,150 M 175,165 L 165,142 M 155,160 L 145,138 M 135,160 L 125,140 M 115,165 L 105,148 M 95,172 L 85,160" />
                        <path d="M 215,180 Q 120,180 50,260" />
                        <path d="M 180,182 L 165,200 M 160,186 L 142,210 M 140,194 L 120,225 M 115,208 L 95,245 M 85,230 L 68,260" />
                        <path d="M 215,180 Q 210,80 150,30" />
                        <path d="M 210,150 L 190,135 M 205,120 L 180,105 M 195,90 L 170,80 M 180,65 L 155,60 M 160,45 L 145,45" />
                        <path d="M 215,180 Q 280,100 340,130" />
                        <circle cx="210" cy="190" r="8" fill="#FFFFFF" stroke="#2563EB" stroke-width="1.8" />
                        <circle cx="222" cy="188" r="8" fill="#FFFFFF" stroke="#2563EB" stroke-width="1.8" />
                        <circle cx="216" cy="199" r="8" fill="#FFFFFF" stroke="#2563EB" stroke-width="1.8" />
                    </g>

                    <g stroke="#2563EB" stroke-width="1.8" opacity="0.6">
                        <path d="M 285,420 C 275,460 270,580 270,760 C 270,775 285,785 292,785 C 299,785 314,775 314,760 C 314,580 309,460 299,420 C 295,405 289,405 285,420 Z" fill="#FFFFFF" />
                        <line x1="292" y1="410" x2="292" y2="780" stroke-dasharray="6 3" />
                        <circle cx="292" cy="520" r="4" />
                        <circle cx="292" cy="512" r="3" />
                        <circle cx="292" cy="528" r="3" />
                        <circle cx="284" cy="520" r="3" />
                        <circle cx="300" cy="520" r="3" />
                    </g>
                </svg>
            </div>

            <!-- Content Area -->
            <div class="right-content">

                <!-- Logo -->
                <img src="../images/LOGO.png" alt="INTAN ELYU Logo" class="brand-logo">

                <!-- System Title & Headings -->
                <h2 class="brand-title">INTAN ELYU</h2>
                <p class="brand-subtitle">Tourism Management System</p>
                <p class="brand-location">San Fernando City, La Union</p>
                <div class="brand-gold-bar"></div>

                <!-- Session Safe Information Card -->
                <div class="session-info-card">
                    <div class="session-info-icon">
                        <i class="fas fa-info"></i>
                    </div>
                    <div class="session-info-text">
                        <h3>Your session is safe.</h3>
                        <p>You do not need to log in again. The system will automatically resume once maintenance is complete.</p>
                    </div>
                </div>

                <!-- Footer Notes -->
                <div class="right-footer">
                    <div class="footer-msg">
                        <i class="fas fa-globe"></i>
                        <span>Thank you for your patience.</span>
                    </div>
                    <div class="footer-org">
                        La Union Tourism Management System
                    </div>

                    <!-- PICTO Administrator Login Pill Button -->
                    <a href="../login.php?admin=1" class="picto-admin-login-btn" id="pictoAdminLoginBtn" title="PICTO Administrator Login">
                        <i class="fas fa-user-shield"></i>
                        <span>PICTO Administrator Login</span>
                    </a>
                </div>

            </div>
        </div>

    </div>

    <!-- Polling script -->
    <script>
        const API_BASE = '<?= htmlspecialchars($apiBase) ?>';
        let countdown = 30;
        let countdownInterval = null;

        const pollStatusEl   = document.getElementById('pollStatus');
        const countdownNumEl = document.getElementById('countdownNum');
        const pollSpinner    = document.getElementById('pollSpinner');

        async function checkStatus() {
            if (pollStatusEl) pollStatusEl.textContent = 'Checking system status...';
            if (pollSpinner) pollSpinner.style.borderTopColor = '#2563EB';

            try {
                const r = await fetch(API_BASE + '/api/system/maintenance-status', {
                    credentials: 'include',
                    cache: 'no-store'
                });
                const data = await r.json();

                if (data.success && !data.maintenance) {
                    if (pollStatusEl) pollStatusEl.textContent = 'System restored! Redirecting...';
                    if (pollSpinner) pollSpinner.style.display = 'none';
                    clearInterval(countdownInterval);
                    setTimeout(() => {
                        const isLoggedIn = <?= json_encode(!empty($_SESSION['user_id'])) ?>;
                        window.location.href = isLoggedIn ? 'dashboard.php' : '../login.php';
                    }, 1000);
                    return;
                }
            } catch (e) {
                if (pollStatusEl) pollStatusEl.textContent = 'Could not reach server — retrying soon.';
                if (pollSpinner) pollSpinner.style.borderTopColor = '#F59E0B';
            }
            countdown = 30;
        }

        function startCountdown() {
            clearInterval(countdownInterval);
            countdown = 30;
            if (countdownNumEl) countdownNumEl.textContent = countdown;

            countdownInterval = setInterval(() => {
                countdown--;
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    checkStatus().then(startCountdown);
                } else {
                    if (countdownNumEl) countdownNumEl.textContent = countdown;
                }
            }, 1000);
        }

        // Start initial status poll
        setTimeout(() => {
            checkStatus().then(startCountdown);
        }, 1500);
    </script>
</body>
</html>
