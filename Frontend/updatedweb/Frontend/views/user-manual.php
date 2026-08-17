<?php

//  user-manual.php — Intan Elyu Tourism Monitoring System
//  Role-based User Manual / Help Center
//  Accessible to all three role families: picto, lupto, municipal

require_once __DIR__ . '/../session-bridge.php';

$allowedRoles  = ['lupto', 'picto', 'municipal'];
$loginRedirect = '../login.php';
require_once __DIR__ . '/_role_guard.php';

// $userRole is now normalized: 'picto' | 'lupto' | 'municipal'
$pageTitle  = 'User Manual';
$roleLabel  = match($userRole) {
    'picto'     => 'PICTO',
    'lupto'     => 'LUPTO',
    'municipal' => 'MTO / Municipal',
    default     => strtoupper($userRole),
};
$roleBannerClass = 'banner-' . $userRole;
$roleBadgeClass  = 'role-' . $userRole;
$userName        = $_SESSION['user_name'] ?? 'User';
$municipalityName = $_SESSION['user_municipality_name'] ?? null;

$extraHeadContent = '
    <link rel="stylesheet" href="../css/user-manual.css">
';

ob_start();
?>

<div class="um-wrapper">

    <!-- ── PAGE HEADER ─────────────────────────────────────────── -->
    <div class="um-page-header">
        <div class="um-page-title-group">
            <h2 class="um-page-title">
                <i class="fas fa-book-open"></i>
                User Manual
            </h2>
            <p class="um-page-subtitle">
                Intan Elyu Tourism Monitoring System &nbsp;·&nbsp;
                <span class="um-role-badge <?= $roleBadgeClass ?>">
                    <i class="fas fa-user-shield"></i>
                    <?= htmlspecialchars($roleLabel) ?> Manual
                </span>
            </p>
        </div>
        <div class="um-search-wrap">
            <i class="fas fa-search um-search-icon"></i>
            <input type="text" id="umSearchInput" placeholder="Search User Manual…" autocomplete="off">
            <div id="umSearchResults"></div>
        </div>
    </div>

    <!-- ── TWO-COLUMN LAYOUT ────────────────────────────────────── -->
    <div class="um-layout">

        <!-- LEFT: Table of Contents -->
        <aside class="um-toc" id="umToc">
            <div class="um-toc-header">
                <i class="fas fa-list-ul"></i> Table of Contents
            </div>
            <div class="um-toc-body">

                <!-- Getting Started -->
                <div class="um-toc-group open" data-group="getting-started">
                    <div class="um-toc-group-label">
                        Getting Started <i class="fas fa-chevron-right um-toc-arrow"></i>
                    </div>
                    <div class="um-toc-links">
                        <a class="um-toc-link" data-target="sec-login"><i class="fas fa-sign-in-alt"></i> Login</a>
                        <a class="um-toc-link" data-target="sec-dashboard"><i class="fas fa-gauge-high"></i> Dashboard</a>
                        <a class="um-toc-link" data-target="sec-navigation"><i class="fas fa-compass"></i> Navigation</a>
                        <a class="um-toc-link" data-target="sec-account-activation"><i class="fas fa-user-check"></i> Account Activation</a>
                    </div>
                </div>

                <!-- Tourist Sites -->
                <div class="um-toc-group open" data-group="tourist-sites">
                    <div class="um-toc-group-label">
                        Tourist Sites <i class="fas fa-chevron-right um-toc-arrow"></i>
                    </div>
                    <div class="um-toc-links">
                        <a class="um-toc-link" data-target="sec-tourist-sites"><i class="fas fa-location-dot"></i> View Tourist Sites</a>
                        <?php if ($userRole !== 'picto'): // MTO & LUPTO can add ?>
                        <a class="um-toc-link" data-target="sec-add-site"><i class="fas fa-plus-circle"></i> Add Tourist Site</a>
                        <a class="um-toc-link" data-target="sec-edit-site"><i class="fas fa-pencil"></i> Edit Tourist Site</a>
                        <?php endif; ?>
                        <?php if ($userRole === 'municipal'): ?>
                        <a class="um-toc-link" data-target="sec-submit-approval"><i class="fas fa-paper-plane"></i> Submit for Approval</a>
                        <a class="um-toc-link" data-target="sec-approval-status"><i class="fas fa-clock"></i> Check Approval Status</a>
                        <?php endif; ?>
                        <?php if ($userRole === 'lupto'): ?>
                        <a class="um-toc-link" data-target="sec-approval"><i class="fas fa-clipboard-check"></i> Approval Workflow</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Map & Analytics -->
                <div class="um-toc-group open" data-group="map-analytics">
                    <div class="um-toc-group-label">
                        Map & Analytics <i class="fas fa-chevron-right um-toc-arrow"></i>
                    </div>
                    <div class="um-toc-links">
                        <a class="um-toc-link" data-target="sec-map"><i class="fas fa-map"></i> Interactive Map</a>
                        <a class="um-toc-link" data-target="sec-analytics"><i class="fas fa-chart-simple"></i> Analytics & Reports</a>
                    </div>
                </div>

                <!-- Modules -->
                <div class="um-toc-group open" data-group="modules">
                    <div class="um-toc-group-label">
                        Modules <i class="fas fa-chevron-right um-toc-arrow"></i>
                    </div>
                    <div class="um-toc-links">
                        <a class="um-toc-link" data-target="sec-fare"><i class="fas fa-money-bill-trend-up"></i> Transportation Fare</a>
                        <a class="um-toc-link" data-target="sec-vouchers"><i class="fas fa-ticket-simple"></i> Voucher & Rewards</a>
                        <a class="um-toc-link" data-target="sec-leaderboard"><i class="fas fa-trophy"></i> Leaderboard</a>
                        <a class="um-toc-link" data-target="sec-proof"><i class="fas fa-images"></i> Proof Validation</a>
                        <a class="um-toc-link" data-target="sec-feedback"><i class="fas fa-comments"></i> Feedback</a>
                        <?php if ($userRole !== 'municipal'): ?>
                        <a class="um-toc-link" data-target="sec-usermgmt"><i class="fas fa-user"></i> User Management</a>
                        <?php endif; ?>
                        <a class="um-toc-link" data-target="sec-activitylogs"><i class="fas fa-history"></i> Activity Logs</a>
                        <?php if ($userRole === 'picto'): ?>
                        <a class="um-toc-link" data-target="sec-archive"><i class="fas fa-box-archive"></i> Archive Management</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Settings & Reference -->
                <div class="um-toc-group open" data-group="settings-ref">
                    <div class="um-toc-group-label">
                        Settings & Reference <i class="fas fa-chevron-right um-toc-arrow"></i>
                    </div>
                    <div class="um-toc-links">
                        <a class="um-toc-link" data-target="sec-settings"><i class="fas fa-cog"></i> System Settings</a>
                        <a class="um-toc-link" data-target="sec-points"><i class="fas fa-star"></i> Points & Rewards</a>
                        <a class="um-toc-link" data-target="sec-permissions"><i class="fas fa-shield-alt"></i> Role Permissions</a>
                        <a class="um-toc-link" data-target="sec-faq"><i class="fas fa-circle-question"></i> FAQ</a>
                        <a class="um-toc-link" data-target="sec-troubleshooting"><i class="fas fa-wrench"></i> Troubleshooting</a>
                        <a class="um-toc-link" data-target="sec-support"><i class="fas fa-headset"></i> Contact / Support</a>
                    </div>
                </div>

            </div><!-- /um-toc-body -->
        </aside>

        <!-- RIGHT: Content -->
        <div class="um-content" id="umContent">

            <!-- Welcome Banner -->
            <div class="um-welcome-banner <?= $roleBannerClass ?>">
                <div class="um-welcome-icon">
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="um-welcome-text">
                    <h3>Welcome, <?= htmlspecialchars($roleLabel) ?><?= $municipalityName ? ' — ' . htmlspecialchars($municipalityName) : '' ?></h3>
                    <p>
                        Hello, <strong><?= htmlspecialchars($userName) ?></strong>. Select a topic from the Table of Contents
                        or search above to find step-by-step instructions for using the Intan Elyu Tourism Monitoring System.
                        This manual shows only the features and modules available to your role.
                    </p>
                </div>
            </div>

            <!-- Active Topic Navigation & Breadcrumb Bar -->
            <div class="um-active-topic-bar" id="umActiveTopicBar">
                <div class="um-topic-meta">
                    <span class="um-topic-category" id="umActiveCategory"><i class="fas fa-folder-open"></i> Getting Started</span>
                    <span class="um-topic-divider">/</span>
                    <span class="um-topic-current" id="umActiveTitle">Login</span>
                </div>
                <div class="um-topic-controls">
                    <button type="button" class="um-topic-btn" id="umPrevTopicBtn" title="Previous Topic"><i class="fas fa-chevron-left"></i> Prev</button>
                    <button type="button" class="um-topic-btn" id="umNextTopicBtn" title="Next Topic">Next <i class="fas fa-chevron-right"></i></button>
                    <button type="button" class="um-topic-btn" id="umToggleViewAllBtn" title="Show all sections simultaneously"><i class="fas fa-layer-group"></i> <span id="umViewAllText">View All</span></button>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: LOGIN                                     -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div class="um-section active-manual open" id="sec-login" data-section="login"
                 data-keywords="login sign in log in password credentials access enter">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-sign-in-alt"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>Login</h3>
                        <p>How to access the system</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <h4><i class="fas fa-info-circle"></i> Overview</h4>
                    <p>
                        The Intan Elyu Tourism Monitoring System requires a valid account to access. Your account is
                        created by an authorized administrator. You will receive login credentials (username/email and
                        a temporary password) before your first login.
                    </p>

                    <h4><i class="fas fa-list-ol"></i> How to Log In</h4>
                    <div class="um-steps">
                        <div class="um-step">
                            <div class="um-step-number">1</div>
                            <div class="um-step-content">
                                <strong>Open the Login Page</strong>
                                <span>Open your browser and navigate to the system's URL provided by your administrator.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">2</div>
                            <div class="um-step-content">
                                <strong>Enter Your Credentials</strong>
                                <span>Type your email address (or username) and your password in the respective fields.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">3</div>
                            <div class="um-step-content">
                                <strong>Click "Login"</strong>
                                <span>Press the Login button. If your credentials are correct and your account is active, you will be redirected to the Dashboard.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">4</div>
                            <div class="um-step-content">
                                <strong>First-Time Login: Change Your Password</strong>
                                <span>If this is your first login, the system will require you to change your password before accessing any other module. Follow the on-screen instructions in System Settings.</span>
                            </div>
                        </div>
                    </div>

                    <div class="um-screenshot">
                        <i class="fas fa-image"></i>
                        <div class="um-sc-label">Figure 1. Login Page</div>
                        <div>[SCREENSHOT NEEDED]</div>
                    </div>

                    <div class="um-callout um-callout-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>If your account status is <strong>Pending</strong>, you will not be able to log in until an administrator activates your account.</span>
                    </div>

                    <h4><i class="fas fa-key"></i> Forgot Password</h4>
                    <p>
                        If you have forgotten your password, use the <strong>"Forgot Password"</strong> link on the
                        login page. Enter your registered email address. A password reset link will be sent to your
                        email. Follow the link to create a new password.
                    </p>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: ACCOUNT ACTIVATION                        -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div class="um-section" id="sec-account-activation" data-section="account-activation"
                 data-keywords="account activation pending active status activate">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-user-check"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>Account Activation</h3>
                        <p>Understanding account status and activation</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <h4><i class="fas fa-info-circle"></i> Overview</h4>
                    <p>
                        Every user account in the system goes through an activation process before it can be used
                        to access the system. Accounts start as <strong>Pending</strong> after creation.
                    </p>

                    <h4><i class="fas fa-diagram-project"></i> Account Activation Workflow</h4>
                    <div class="um-workflow-v">
                        <div class="um-wf-node">
                            <div class="um-wf-box wf-blue">Administrator Creates User Account</div>
                        </div>
                        <div class="um-wf-arrow-v"><i class="fas fa-arrow-down"></i></div>
                        <div class="um-wf-node">
                            <div class="um-wf-box wf-yellow">Account Status = <strong>Pending</strong></div>
                        </div>
                        <div class="um-wf-arrow-v"><i class="fas fa-arrow-down"></i></div>
                        <div class="um-wf-node">
                            <div class="um-wf-box wf-blue">Account Review by Administrator</div>
                        </div>
                        <div class="um-wf-arrow-v"><i class="fas fa-arrow-down"></i></div>
                        <div class="um-wf-node">
                            <div class="um-wf-box wf-green">Account Activated → Status = <strong>Active</strong></div>
                        </div>
                        <div class="um-wf-arrow-v"><i class="fas fa-arrow-down"></i></div>
                        <div class="um-wf-node">
                            <div class="um-wf-box wf-green">User Can Now Log In</div>
                        </div>
                    </div>

                    <div class="um-callout um-callout-info">
                        <i class="fas fa-circle-info"></i>
                        <span><strong>Pending accounts</strong> cannot access any protected system functions until they are activated by an authorized administrator.</span>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: DASHBOARD                                  -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div class="um-section" id="sec-dashboard" data-section="dashboard"
                 data-keywords="dashboard overview kpi cards statistics visitors map analytics recent activities">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-gauge-high"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>Dashboard</h3>
                        <p>Understanding your dashboard components</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <h4><i class="fas fa-info-circle"></i> Overview</h4>
                    <p>
                        The Dashboard is the first page you see after logging in. It provides a real-time summary
                        of key tourism data for <?= htmlspecialchars($roleLabel) ?>.
                    </p>

                    <?php if ($userRole === 'picto' || $userRole === 'lupto'): ?>
                    <h4><i class="fas fa-grid-2"></i> Dashboard Components</h4>
                    <ul>
                        <li><strong>Total Tourist Sites</strong> — Total number of tourist sites recorded in the system.</li>
                        <li><strong>Total Fare Matrix</strong> — Number of transportation fare entries.</li>
                        <li><strong>Total Tourist Users</strong> — Registered tourist app users.</li>
                        <li><strong>Total Points Earned</strong> — Cumulative points earned by tourists.</li>
                        <li><strong>Total Monthly Visitors</strong> — Visitor count for the current month.</li>
                        <li><strong>Interactive LGU Profile Map</strong> — A map of La Union showing tourist sites per municipality, colored by classification.</li>
                        <li><strong>Classification Filter</strong> — Filter map markers by site classification (Existing, Emerging, Potential).</li>
                        <li><strong>Category Filter</strong> — Filter by tourism category.</li>
                        <li><strong>Municipality Filter</strong> — Zoom in on a specific municipality.</li>
                        <li><strong>Map Legend</strong> — Color key for map markers.</li>
                        <li><strong>Recent Activities</strong> — Latest system activity feed.</li>
                        <li><strong>Visitor Trends</strong> — Chart showing visitor counts over time.</li>
                        <li><strong>Top Municipalities</strong> — Ranking of municipalities by tourist site count or visitors.</li>
                    </ul>
                    <?php else: ?>
                    <h4><i class="fas fa-grid-2"></i> Dashboard Components</h4>
                    <ul>
                        <li><strong>Total Tourist Sites</strong> — Number of tourist sites in your municipality.</li>
                        <li><strong>Municipality Profile Map</strong> — Map showing your municipality's tourist sites.</li>
                        <li><strong>Classification Filter</strong> — Filter by site classification.</li>
                        <li><strong>Recent Activities</strong> — Latest actions in your municipality's data.</li>
                        <li><strong>Visitor Trends</strong> — Visitor data specific to your municipality.</li>
                    </ul>
                    <?php endif; ?>

                    <div class="um-screenshot">
                        <i class="fas fa-image"></i>
                        <div class="um-sc-label">Figure 2. Dashboard Overview</div>
                        <div>[SCREENSHOT NEEDED]</div>
                    </div>

                    <div class="um-callout um-callout-info">
                        <i class="fas fa-circle-info"></i>
                        <span>Dashboard numbers update automatically. The values shown reflect the current live data from the system — they are not fixed.</span>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: NAVIGATION                                 -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div class="um-section" id="sec-navigation" data-section="navigation"
                 data-keywords="navigation sidebar menu navigate between pages modules">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-compass"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>Navigation</h3>
                        <p>How to navigate between modules</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <p>The system uses a left sidebar for navigation. Click any item in the sidebar to open that module.</p>
                    <ul>
                        <li><strong>Sidebar Toggle</strong> — Click the hamburger icon (☰) in the top-left to collapse or expand the sidebar.</li>
                        <li><strong>Active Page</strong> — The currently open page is highlighted in the sidebar.</li>
                        <li><strong>SPA Navigation</strong> — Pages load without a full browser refresh, making navigation fast.</li>
                        <li><strong>User Menu</strong> — Click your name in the top-right header to access Profile, Settings, or Logout.</li>
                        <li><strong>Notifications</strong> — Click the bell icon (🔔) in the header to view system notifications.</li>
                    </ul>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: MANAGE TOURIST SITES                       -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div class="um-section" id="sec-tourist-sites" data-section="tourist-sites"
                 data-keywords="tourist sites manage view search filter classification category municipality status">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-location-dot"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>Manage Tourist Sites</h3>
                        <p>View, search, and filter tourist sites</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <h4><i class="fas fa-info-circle"></i> Overview</h4>
                    <p>
                        The <strong>Manage Tourist Sites</strong> module lets you view all tourist sites
                        <?php if ($userRole === 'municipal'): ?>
                        in your assigned municipality.
                        <?php elseif ($userRole === 'lupto'): ?>
                        across all municipalities in La Union.
                        <?php else: ?>
                        across the province.
                        <?php endif; ?>
                        You can search, filter, and view details for each site.
                    </p>

                    <h4><i class="fas fa-search"></i> How to Search and Filter</h4>
                    <div class="um-steps">
                        <div class="um-step">
                            <div class="um-step-number">1</div>
                            <div class="um-step-content">
                                <strong>Open "Manage Tourist Sites"</strong>
                                <span>Click <em>Manage Tourist Sites</em> from the sidebar.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">2</div>
                            <div class="um-step-content">
                                <strong>Use the Search Bar</strong>
                                <span>Type the name of a tourist site in the search bar to filter the list.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">3</div>
                            <div class="um-step-content">
                                <strong>Apply Filters</strong>
                                <span>Use the Classification, Category, Municipality, and Status dropdown filters to narrow results.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">4</div>
                            <div class="um-step-content">
                                <strong>Click a Site to View Details</strong>
                                <span>Click the view icon or the site row to open its full details.</span>
                            </div>
                        </div>
                    </div>

                    <div class="um-screenshot">
                        <i class="fas fa-image"></i>
                        <div class="um-sc-label">Figure 3. Manage Tourist Sites</div>
                        <div>[SCREENSHOT NEEDED]</div>
                    </div>
                    <div class="um-annotations">
                        <div class="um-annotation"><div class="um-annotation-num">①</div> Add Tourist Site Button</div>
                        <div class="um-annotation"><div class="um-annotation-num">②</div> Search Bar</div>
                        <div class="um-annotation"><div class="um-annotation-num">③</div> Classification Filter</div>
                        <div class="um-annotation"><div class="um-annotation-num">④</div> Edit Button</div>
                        <div class="um-annotation"><div class="um-annotation-num">⑤</div> View Details Button</div>
                        <div class="um-annotation"><div class="um-annotation-num">⑥</div> Status Badge</div>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: ADD TOURIST SITE (MTO & LUPTO)            -->
            <!-- ═══════════════════════════════════════════════════ -->
            <?php if ($userRole !== 'picto'): ?>
            <div class="um-section" id="sec-add-site" data-section="add-site"
                 data-keywords="add tourist site create new site submit save upload images location map">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-plus-circle"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>Add a Tourist Site</h3>
                        <p>Step-by-step guide to creating a new tourist site</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <h4><i class="fas fa-list-ol"></i> How to Add a Tourist Site</h4>
                    <div class="um-steps">
                        <div class="um-step">
                            <div class="um-step-number">1</div>
                            <div class="um-step-content">
                                <strong>Open "Manage Tourist Sites"</strong>
                                <span>Click <em>Manage Tourist Sites</em> from the sidebar.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">2</div>
                            <div class="um-step-content">
                                <strong>Click "Add Tourist Site"</strong>
                                <span>Locate and click the <em>Add Tourist Site</em> button, usually at the top-right of the page.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">3</div>
                            <div class="um-step-content">
                                <strong>Enter Tourist Site Information</strong>
                                <span>Fill in all required fields: Site Name, Description, Address, and other relevant details.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">4</div>
                            <div class="um-step-content">
                                <strong>Select Classification</strong>
                                <span>Choose the appropriate classification: <strong>Existing</strong>, <strong>Emerging</strong>, or <strong>Potential</strong>. This also determines the points value for the site.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">5</div>
                            <div class="um-step-content">
                                <strong>Select Municipality and Barangay</strong>
                                <span>Choose the correct municipality and barangay where the site is located.
                                <?php if ($userRole === 'municipal'): ?>
                                Note: Your account is restricted to your assigned municipality only.
                                <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">6</div>
                            <div class="um-step-content">
                                <strong>Set Location on the Map</strong>
                                <span>Use the interactive map to place a marker at the exact location of the tourist site. Make sure the marker is inside the correct municipality boundary.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">7</div>
                            <div class="um-step-content">
                                <strong>Upload Required Images</strong>
                                <span>Upload photos of the tourist site. Accepted formats are JPG, JPEG, and PNG.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">8</div>
                            <div class="um-step-content">
                                <strong>Review All Information</strong>
                                <span>Double-check the site name, location, classification, and uploaded images before saving.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">9</div>
                            <div class="um-step-content">
                                <strong>Click "Save" or "Submit"</strong>
                                <span>
                                <?php if ($userRole === 'municipal'): ?>
                                Click <strong>Submit</strong>. The site will be sent to LUPTO for review and approval before it becomes publicly visible.
                                <?php else: ?>
                                Click <strong>Save</strong> to create the tourist site.
                                <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <?php if ($userRole === 'municipal'): ?>
                        <div class="um-step">
                            <div class="um-step-number">10</div>
                            <div class="um-step-content">
                                <strong>Wait for LUPTO Approval</strong>
                                <span>After submission, the site status will show as <strong>Pending</strong>. Monitor the status in the Manage Tourist Sites list.</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="um-callout um-callout-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Make sure the map marker is placed inside the correct municipality boundary. Locations outside the boundary will be flagged as <strong>invalid</strong>.</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: EDIT TOURIST SITE (MTO & LUPTO)           -->
            <!-- ═══════════════════════════════════════════════════ -->
            <?php if ($userRole !== 'picto'): ?>
            <div class="um-section" id="sec-edit-site" data-section="edit-site"
                 data-keywords="edit update modify tourist site change details images location">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-pencil"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>Edit a Tourist Site</h3>
                        <p>How to update existing tourist site information</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <div class="um-steps">
                        <div class="um-step">
                            <div class="um-step-number">1</div>
                            <div class="um-step-content">
                                <strong>Open "Manage Tourist Sites"</strong>
                                <span>Click <em>Manage Tourist Sites</em> from the sidebar.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">2</div>
                            <div class="um-step-content">
                                <strong>Find the Tourist Site</strong>
                                <span>Use the search bar or filters to locate the site you want to edit.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">3</div>
                            <div class="um-step-content">
                                <strong>Click the Edit Button</strong>
                                <span>Click the pencil/edit icon (✏️) on the site's row to open the edit form.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">4</div>
                            <div class="um-step-content">
                                <strong>Update the Information</strong>
                                <span>Make the necessary changes to the site details, location, images, or classification.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">5</div>
                            <div class="um-step-content">
                                <strong>Save Changes</strong>
                                <span>Click <strong>Save</strong> to confirm the changes.</span>
                            </div>
                        </div>
                    </div>
                    <div class="um-callout um-callout-info">
                        <i class="fas fa-circle-info"></i>
                        <span>You can only edit tourist sites that belong to <?php if ($userRole === 'municipal'): ?>your assigned municipality<?php else: ?>the system<?php endif; ?>.</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: SUBMIT FOR APPROVAL (MTO)                 -->
            <!-- ═══════════════════════════════════════════════════ -->
            <?php if ($userRole === 'municipal'): ?>
            <div class="um-section" id="sec-submit-approval" data-section="submit-approval"
                 data-keywords="submit approval pending lupto review tourist site submission">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-paper-plane"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>Submit Tourist Site for Approval</h3>
                        <p>How to submit a site for LUPTO review</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <p>After creating a tourist site, it must be reviewed and approved by LUPTO before it is available in the system.</p>
                    <h4><i class="fas fa-diagram-project"></i> Submission Workflow</h4>
                    <div class="um-workflow-v">
                        <div class="um-wf-node"><div class="um-wf-box wf-blue">MTO Creates Tourist Site</div></div>
                        <div class="um-wf-arrow-v"><i class="fas fa-arrow-down"></i></div>
                        <div class="um-wf-node"><div class="um-wf-box wf-blue">Click Submit</div></div>
                        <div class="um-wf-arrow-v"><i class="fas fa-arrow-down"></i></div>
                        <div class="um-wf-node"><div class="um-wf-box wf-yellow">Status = Pending</div></div>
                        <div class="um-wf-arrow-v"><i class="fas fa-arrow-down"></i></div>
                        <div class="um-wf-node"><div class="um-wf-box wf-blue">LUPTO Reviews Submission</div></div>
                        <div class="um-wf-arrow-v"><i class="fas fa-arrow-down"></i></div>
                        <div class="um-wf-node"><div class="um-wf-box wf-green">Approved → Site is Available</div></div>
                    </div>
                    <div class="um-callout um-callout-info">
                        <i class="fas fa-circle-info"></i>
                        <span>If rejected, LUPTO will provide a reason. You can correct the information and resubmit the site.</span>
                    </div>
                </div>
            </div>

            <!-- CHECK APPROVAL STATUS (MTO) -->
            <div class="um-section" id="sec-approval-status" data-section="approval-status"
                 data-keywords="check approval status pending approved rejected submission status">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-clock"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>Check Approval Status</h3>
                        <p>Monitor the status of your submitted tourist sites</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <div class="um-steps">
                        <div class="um-step">
                            <div class="um-step-number">1</div>
                            <div class="um-step-content">
                                <strong>Go to "Manage Tourist Sites"</strong>
                                <span>Open the Manage Tourist Sites module from the sidebar.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">2</div>
                            <div class="um-step-content">
                                <strong>Check the Status Column</strong>
                                <span>Each site has a <em>Status</em> indicator: <strong>Pending</strong>, <strong>Approved</strong>, or <strong>Rejected</strong>.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">3</div>
                            <div class="um-step-content">
                                <strong>View Rejection Reason</strong>
                                <span>If a site is <strong>Rejected</strong>, click the site to view the reason provided by LUPTO, then correct and resubmit.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: APPROVAL WORKFLOW (LUPTO)                  -->
            <!-- ═══════════════════════════════════════════════════ -->
            <?php if ($userRole === 'lupto'): ?>
            <div class="um-section" id="sec-approval" data-section="approval"
                 data-keywords="approve reject tourist site approval workflow review pending submission">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-clipboard-check"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>Tourist Site Approval Workflow</h3>
                        <p>How to review and approve or reject MTO submissions</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <p>LUPTO is responsible for reviewing all tourist site submissions from Municipal Tourism Offices (MTO). You can approve or reject submissions with a reason.</p>
                    <h4><i class="fas fa-diagram-project"></i> Approval Workflow</h4>
                    <div class="um-workflow-v">
                        <div class="um-wf-node"><div class="um-wf-box wf-blue">MTO Submits Tourist Site</div></div>
                        <div class="um-wf-arrow-v"><i class="fas fa-arrow-down"></i></div>
                        <div class="um-wf-node"><div class="um-wf-box wf-yellow">Status = Pending</div></div>
                        <div class="um-wf-arrow-v"><i class="fas fa-arrow-down"></i></div>
                        <div class="um-wf-node"><div class="um-wf-box wf-blue">LUPTO Reviews Submission</div></div>
                        <div class="um-wf-arrow-v"><i class="fas fa-arrow-down"></i></div>
                        <div class="um-wf-node"><div class="um-wf-box wf-green">Approve → Site Available</div></div>
                    </div>
                    <p style="margin-top:8px;"><strong>If Rejected:</strong></p>
                    <div class="um-workflow-v">
                        <div class="um-wf-node"><div class="um-wf-box wf-red">Reject → Provide Reason</div></div>
                        <div class="um-wf-arrow-v"><i class="fas fa-arrow-down"></i></div>
                        <div class="um-wf-node"><div class="um-wf-box wf-yellow">MTO is Notified</div></div>
                        <div class="um-wf-arrow-v"><i class="fas fa-arrow-down"></i></div>
                        <div class="um-wf-node"><div class="um-wf-box wf-blue">MTO Corrects & Resubmits</div></div>
                    </div>
                    <h4><i class="fas fa-list-ol"></i> How to Approve or Reject</h4>
                    <div class="um-steps">
                        <div class="um-step">
                            <div class="um-step-number">1</div>
                            <div class="um-step-content">
                                <strong>Open "Manage Tourist Sites"</strong>
                                <span>Filter the list by Status = <em>Pending</em> to see all submissions awaiting review.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">2</div>
                            <div class="um-step-content">
                                <strong>Click the Site to View Details</strong>
                                <span>Review the site name, description, location, classification, and uploaded images.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">3</div>
                            <div class="um-step-content">
                                <strong>Approve or Reject</strong>
                                <span>Click <strong>Approve</strong> to accept the site. Click <strong>Reject</strong> and provide a clear reason for rejection.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: INTERACTIVE MAP                            -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div class="um-section" id="sec-map" data-section="map"
                 data-keywords="map interactive zoom pan marker legend classification municipality boundary location coordinates leaflet">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-map"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>Interactive Map</h3>
                        <p>Using the La Union tourism map</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <h4><i class="fas fa-info-circle"></i> Overview</h4>
                    <p>
                        The Interactive Map displays all tourist sites in La Union as map markers. You can zoom, pan,
                        filter by classification, category, or municipality, and click any marker to view the site's
                        details.
                    </p>
                    <h4><i class="fas fa-hand-pointer"></i> Map Controls</h4>
                    <ul>
                        <li><strong>Zoom</strong> — Use the +/− buttons or scroll the mouse wheel to zoom in/out.</li>
                        <li><strong>Pan</strong> — Click and drag the map to move around.</li>
                        <li><strong>Markers</strong> — Each marker represents one tourist site. Colors correspond to classification.</li>
                        <li><strong>Legend</strong> — The map legend explains marker colors (Existing, Emerging, Potential).</li>
                        <li><strong>Classification Filter</strong> — Filter markers by classification type.</li>
                        <li><strong>Category Filter</strong> — Filter markers by tourism category.</li>
                        <li><strong>Municipality Filter</strong> — Filter markers to a specific municipality.</li>
                        <li><strong>Click a Marker</strong> — Click any marker to view that site's name, classification, and basic information.</li>
                        <li><strong>Municipality Boundaries</strong> — Colored polygon overlays show each municipality's geographic boundary.</li>
                    </ul>
                    <h4><i class="fas fa-map-pin"></i> How to Locate a Tourist Site on the Map</h4>
                    <div class="um-steps">
                        <div class="um-step">
                            <div class="um-step-number">1</div>
                            <div class="um-step-content">
                                <strong>Open the Interactive Map</strong>
                                <span>Navigate to the Map module from the sidebar.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">2</div>
                            <div class="um-step-content">
                                <strong>Select Municipality (Optional)</strong>
                                <span>Use the Municipality filter to zoom in on a specific area.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">3</div>
                            <div class="um-step-content">
                                <strong>Apply Filters</strong>
                                <span>Use Classification or Category filters to narrow the visible markers.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">4</div>
                            <div class="um-step-content">
                                <strong>Click a Marker</strong>
                                <span>Click any map marker to view the tourist site's information popup.</span>
                            </div>
                        </div>
                    </div>
                    <?php if ($userRole !== 'picto'): ?>
                    <h4><i class="fas fa-crosshairs"></i> Setting a Tourist Site Location (When Adding/Editing)</h4>
                    <div class="um-steps">
                        <div class="um-step">
                            <div class="um-step-number">1</div>
                            <div class="um-step-content">
                                <strong>Open Add/Edit Tourist Site Form</strong>
                                <span>The map will appear in the form for location selection.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">2</div>
                            <div class="um-step-content">
                                <strong>Click on the Map to Place a Marker</strong>
                                <span>Click the exact location of the tourist site on the map. A marker will be placed automatically.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">3</div>
                            <div class="um-step-content">
                                <strong>Verify the Marker is Inside the Correct Boundary</strong>
                                <span>The system validates that the marker is within the selected municipality's boundary. If it is outside, the location will be flagged as invalid.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">4</div>
                            <div class="um-step-content">
                                <strong>Drag to Adjust (if needed)</strong>
                                <span>You can drag the marker to fine-tune the location. Coordinates are updated automatically.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">5</div>
                            <div class="um-step-content">
                                <strong>Save the Location</strong>
                                <span>The coordinates are saved when you save the tourist site form.</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: ANALYTICS & REPORTS                        -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div class="um-section" id="sec-analytics" data-section="analytics"
                 data-keywords="analytics reports charts graphs visitor trends export generate report data statistics">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-chart-simple"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>Analytics & Reports</h3>
                        <p>Viewing data insights and generating reports</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <h4><i class="fas fa-info-circle"></i> Overview</h4>
                    <p>
                        The Analytics & Reports module provides visual dashboards of tourism data including visitor counts,
                        top tourist sites, classification breakdowns, and trends.
                        <?php if ($userRole === 'municipal'): ?>
                        Data shown is filtered to your assigned municipality.
                        <?php else: ?>
                        Data is shown for the entire province of La Union.
                        <?php endif; ?>
                    </p>
                    <h4><i class="fas fa-list-ol"></i> How to View Analytics</h4>
                    <div class="um-steps">
                        <div class="um-step">
                            <div class="um-step-number">1</div>
                            <div class="um-step-content">
                                <strong>Open "Analytics & Reports"</strong>
                                <span>Click <em>Analytics & Reports</em> from the sidebar.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">2</div>
                            <div class="um-step-content">
                                <strong>Select Date Range</strong>
                                <span>Use the date filter to set the reporting period.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">3</div>
                            <div class="um-step-content">
                                <strong>Review Charts and Data</strong>
                                <span>View visitor trends, tourist site breakdowns, and other key metrics displayed as charts and tables.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">4</div>
                            <div class="um-step-content">
                                <strong>Export Data</strong>
                                <span>Use the Export or Print button to download the report in the available format.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: TRANSPORTATION FARE                        -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div class="um-section" id="sec-fare" data-section="fare"
                 data-keywords="transportation fare matrix jeepney bus route rates manage add edit">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-money-bill-trend-up"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>Transportation Fare</h3>
                        <p>Managing and viewing fare matrix data</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <h4><i class="fas fa-info-circle"></i> Overview</h4>
                    <p>
                        The Transportation Fare module displays fare matrix information for transportation routes
                        across La Union. It helps tourists plan their travel by showing transportation options and rates.
                    </p>
                    <ul>
                        <li>View existing fare entries for routes and transport types.</li>
                        <?php if ($userRole !== 'municipal'): ?>
                        <li>Add new fare entries for new routes or transport modes.</li>
                        <li>Edit or update fare rates as they change.</li>
                        <?php endif; ?>
                        <li>Search and filter fare data by route, transport type, or municipality.</li>
                    </ul>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: VOUCHER & REWARDS                          -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div class="um-section" id="sec-vouchers" data-section="vouchers"
                 data-keywords="voucher rewards redeem points manage vouchers prizes">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-ticket-simple"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>Voucher & Rewards</h3>
                        <p>Managing vouchers and tourist reward redemptions</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <h4><i class="fas fa-info-circle"></i> Overview</h4>
                    <p>
                        The Voucher & Rewards module manages the rewards program for tourist app users. Tourists earn
                        points by visiting tourist sites. These points can be redeemed for vouchers (prizes or discounts)
                        set up in this module.
                    </p>
                    <?php if ($userRole !== 'municipal'): ?>
                    <ul>
                        <li>Create and manage reward vouchers.</li>
                        <li>Set point thresholds for redemption.</li>
                        <li>View redemption history.</li>
                        <li>Activate or deactivate vouchers.</li>
                    </ul>
                    <?php else: ?>
                    <ul>
                        <li>View available vouchers for your municipality's tourists.</li>
                        <li>View redemption history for your municipality.</li>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: LEADERBOARD                                -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div class="um-section" id="sec-leaderboard" data-section="leaderboard"
                 data-keywords="leaderboard rankings top tourists points scores">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-trophy"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>Leaderboard</h3>
                        <p>Tourist ranking based on points earned</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <p>
                        The Leaderboard displays tourist app users ranked by the total points they have earned
                        through visiting tourist sites in La Union. Higher-classified sites award more points.
                    </p>
                    <ul>
                        <li>View top-ranking tourists by points.</li>
                        <li>Filter by time period or municipality.</li>
                        <li>See points breakdown per tourist.</li>
                    </ul>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: PROOF VALIDATION                           -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div class="um-section" id="sec-proof" data-section="proof-validation"
                 data-keywords="proof validation validate visit image photo approve reject tourist visit submission">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-images"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>Proof Validation</h3>
                        <p>Reviewing tourist visit proof submissions</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <p>
                        Tourists earn points by submitting photo proof of their visits to tourist sites. The Proof
                        Validation module allows you to review and validate these submissions.
                    </p>
                    <div class="um-steps">
                        <div class="um-step">
                            <div class="um-step-number">1</div>
                            <div class="um-step-content">
                                <strong>Open "Proof Validation"</strong>
                                <span>Click <em>Proof Validation</em> from the sidebar.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">2</div>
                            <div class="um-step-content">
                                <strong>Review Submissions</strong>
                                <span>Browse pending proof submissions. Each submission shows the tourist's name, site visited, date, and uploaded photo(s).</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">3</div>
                            <div class="um-step-content">
                                <strong>Approve or Reject</strong>
                                <span>Click <strong>Approve</strong> if the proof is valid. Click <strong>Reject</strong> if it does not meet requirements.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: FEEDBACK                                   -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div class="um-section" id="sec-feedback" data-section="feedback"
                 data-keywords="feedback review tourist comments ratings review respond">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-comments"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>Feedback</h3>
                        <p>Viewing and managing tourist feedback</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <p>
                        The Feedback module collects ratings and comments from tourist app users about tourist sites.
                        <?php if ($userRole === 'municipal'): ?>
                        You can view feedback submitted for tourist sites in your municipality.
                        <?php else: ?>
                        You can view feedback submitted province-wide.
                        <?php endif; ?>
                    </p>
                    <ul>
                        <li>View tourist comments and star ratings for each site.</li>
                        <li>Filter feedback by site, rating, date, or municipality.</li>
                        <li>Monitor feedback trends to identify areas for improvement.</li>
                    </ul>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: USER MANAGEMENT (PICTO & LUPTO only)       -->
            <!-- ═══════════════════════════════════════════════════ -->
            <?php if ($userRole !== 'municipal'): ?>
            <div class="um-section" id="sec-usermgmt" data-section="user-management"
                 data-keywords="user management create edit activate deactivate users account roles permissions">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-user"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>User Management</h3>
                        <p>Managing system user accounts</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <h4><i class="fas fa-info-circle"></i> Overview</h4>
                    <p>
                        The User Management module allows authorized users to create, edit, activate, or deactivate
                        system accounts for LUPTO, PICTO, and Municipal Tourism Office staff.
                    </p>
                    <h4><i class="fas fa-list-ol"></i> How to Create a New User</h4>
                    <div class="um-steps">
                        <div class="um-step">
                            <div class="um-step-number">1</div>
                            <div class="um-step-content">
                                <strong>Open "User Management"</strong>
                                <span>Click <em>User Management</em> from the sidebar.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">2</div>
                            <div class="um-step-content">
                                <strong>Click "Add User"</strong>
                                <span>Click the <em>Add User</em> button to open the user creation form.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">3</div>
                            <div class="um-step-content">
                                <strong>Fill in User Information</strong>
                                <span>Enter the user's name, email address, role, and assigned municipality (for MTO accounts).</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">4</div>
                            <div class="um-step-content">
                                <strong>Save the Account</strong>
                                <span>Click <strong>Save</strong>. The account is created with a default temporary password. The new user must change the password on first login.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">5</div>
                            <div class="um-step-content">
                                <strong>Activate the Account</strong>
                                <span>The account status starts as <strong>Pending</strong>. Activate it by changing the status to <strong>Active</strong>.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: ACTIVITY LOGS                              -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div class="um-section" id="sec-activitylogs" data-section="activity-logs"
                 data-keywords="activity logs audit trail history actions recorded user activity">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-history"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>Activity Logs</h3>
                        <p>Viewing the system activity audit trail</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <p>
                        The Activity Logs module records all significant actions performed in the system, such as
                        creating, updating, approving, or deleting records. This provides an audit trail for
                        accountability and monitoring.
                    </p>
                    <ul>
                        <li>View a chronological log of all system actions.</li>
                        <li>See who performed each action and when.</li>
                        <li>Filter logs by date range, user, or action type.</li>
                        <li>Logs cannot be edited or deleted to maintain integrity.</li>
                    </ul>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: ARCHIVE MANAGEMENT (PICTO only)            -->
            <!-- ═══════════════════════════════════════════════════ -->
            <?php if ($userRole === 'picto'): ?>
            <div class="um-section" id="sec-archive" data-section="archive-management"
                 data-keywords="archive archived records restore delete archive management">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-box-archive"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>Archive Management</h3>
                        <p>Managing archived system records</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <p>
                        The Archive Management module allows PICTO to view and manage archived records from the system.
                        Archived records are removed from the active lists but retained for record-keeping purposes.
                    </p>
                    <ul>
                        <li>View all archived tourist sites, users, or other records.</li>
                        <li>Restore an archived record to active status.</li>
                        <li>Permanently delete archived records when appropriate.</li>
                    </ul>
                    <div class="um-callout um-callout-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Permanent deletion cannot be undone. Make sure you have confirmed the record is no longer needed before permanently deleting it.</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: SYSTEM SETTINGS                            -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div class="um-section" id="sec-settings" data-section="settings"
                 data-keywords="system settings password change profile security backup restore">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-cog"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>System Settings</h3>
                        <p>Managing your account and system preferences</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <h4><i class="fas fa-lock"></i> How to Change Your Password</h4>
                    <div class="um-steps">
                        <div class="um-step">
                            <div class="um-step-number">1</div>
                            <div class="um-step-content">
                                <strong>Open "System Settings"</strong>
                                <span>Click <em>System Settings</em> from the sidebar or from the user dropdown menu in the header.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">2</div>
                            <div class="um-step-content">
                                <strong>Locate "Security Settings"</strong>
                                <span>Find the Security Settings card on the settings page.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">3</div>
                            <div class="um-step-content">
                                <strong>Enter Current and New Password</strong>
                                <span>Enter your current password, then your new password. Confirm the new password.</span>
                            </div>
                        </div>
                        <div class="um-step">
                            <div class="um-step-number">4</div>
                            <div class="um-step-content">
                                <strong>Click "Save Changes"</strong>
                                <span>Confirm and save. You will be asked to log in again with your new password.</span>
                            </div>
                        </div>
                    </div>
                    <?php if ($userRole !== 'municipal'): ?>
                    <hr class="um-divider">
                    <h4><i class="fas fa-database"></i> Backup & Restore</h4>
                    <p>The Backup Settings card allows you to create a full database backup and download it, or restore the database from a previously saved backup file.</p>
                    <div class="um-callout um-callout-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Restoring a backup will replace all current data in the system. This action cannot be undone. Ensure you have confirmed the correct backup file before restoring.</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: POINTS & REWARDS GUIDE                     -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div class="um-section" id="sec-points" data-section="points-rewards"
                 data-keywords="points rewards classification existing emerging potential leaderboard voucher redeem earn points calculation">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-star"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>Points & Rewards Guide</h3>
                        <p>How the tourism points system works</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <h4><i class="fas fa-info-circle"></i> How Points Are Assigned</h4>
                    <p>
                        Tourist app users earn points every time they visit a tourist site and submit valid photo proof.
                        The number of points earned depends on the <strong>classification</strong> of the tourist site.
                    </p>
                    <div class="um-points-grid">
                        <div class="um-points-card">
                            <div class="um-points-card-header existing">Existing</div>
                            <div class="um-points-card-body">
                                <div class="um-points-value">50</div>
                                <div class="um-points-label">points per visit</div>
                            </div>
                        </div>
                        <div class="um-points-card">
                            <div class="um-points-card-header emerging">Emerging</div>
                            <div class="um-points-card-body">
                                <div class="um-points-value">100</div>
                                <div class="um-points-label">points per visit</div>
                            </div>
                        </div>
                        <div class="um-points-card">
                            <div class="um-points-card-header potential">Potential</div>
                            <div class="um-points-card-body">
                                <div class="um-points-value">75</div>
                                <div class="um-points-label">points per visit</div>
                            </div>
                        </div>
                    </div>
                    <h4><i class="fas fa-list"></i> Points Workflow</h4>
                    <ul>
                        <li>Tourist visits a site and uploads proof via the tourist mobile app.</li>
                        <li>An authorized user validates the proof in the <strong>Proof Validation</strong> module.</li>
                        <li>Upon approval, points are credited to the tourist's account.</li>
                        <li>Tourists can view their points total in the tourist app.</li>
                        <li>Points rankings are shown in the <strong>Leaderboard</strong>.</li>
                        <li>Accumulated points can be redeemed for vouchers through the <strong>Voucher & Rewards</strong> module.</li>
                    </ul>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: ROLE PERMISSIONS                           -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div class="um-section" id="sec-permissions" data-section="permissions"
                 data-keywords="role permissions what can i do access control features picto lupto mto municipal capabilities">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-shield-alt"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>Role Permissions — What Can I Do?</h3>
                        <p>Overview of feature access by role</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <p>The table below summarizes what each role can do in the system. Your current role is highlighted.</p>
                    <div class="um-perm-table-wrap">
                        <table class="um-perm-table">
                            <thead>
                                <tr>
                                    <th>Feature / Module</th>
                                    <th>PICTO</th>
                                    <th>LUPTO</th>
                                    <th>MTO / Municipal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="<?= $userRole === 'picto' ? 'um-current-role' : '' ?>">
                                    <td>View Dashboard</td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                </tr>
                                <tr>
                                    <td>View Tourist Sites</td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-check um-perm-check"></i> <span class="um-perm-note">(own muni)</span></td>
                                </tr>
                                <tr>
                                    <td>Add Tourist Site</td>
                                    <td><span class="um-perm-note">Per permission</span></td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-check um-perm-check"></i> <span class="um-perm-note">(own muni)</span></td>
                                </tr>
                                <tr>
                                    <td>Edit Tourist Site</td>
                                    <td><span class="um-perm-note">Per permission</span></td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-check um-perm-check"></i> <span class="um-perm-note">(own muni)</span></td>
                                </tr>
                                <tr>
                                    <td>Approve Tourist Sites</td>
                                    <td><span class="um-perm-note">Per permission</span></td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-minus um-perm-dash"></i></td>
                                </tr>
                                <tr>
                                    <td>Interactive Map</td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                </tr>
                                <tr>
                                    <td>Analytics & Reports</td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-check um-perm-check"></i> <span class="um-perm-note">(own muni)</span></td>
                                </tr>
                                <tr>
                                    <td>Transportation Fare</td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-check um-perm-check"></i> <span class="um-perm-note">(view)</span></td>
                                </tr>
                                <tr>
                                    <td>Voucher & Rewards</td>
                                    <td><span class="um-perm-note">View</span></td>
                                    <td><i class="fas fa-check um-perm-check"></i> <span class="um-perm-note">(manage)</span></td>
                                    <td><span class="um-perm-note">View</span></td>
                                </tr>
                                <tr>
                                    <td>Leaderboard</td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                </tr>
                                <tr>
                                    <td>Proof Validation</td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                </tr>
                                <tr>
                                    <td>Feedback</td>
                                    <td><i class="fas fa-check um-perm-check"></i> <span class="um-perm-note">(province-wide)</span></td>
                                    <td><i class="fas fa-check um-perm-check"></i> <span class="um-perm-note">(province-wide)</span></td>
                                    <td><i class="fas fa-check um-perm-check"></i> <span class="um-perm-note">(own muni)</span></td>
                                </tr>
                                <tr>
                                    <td>User Management</td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-minus um-perm-dash"></i></td>
                                </tr>
                                <tr>
                                    <td>Activity Logs</td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                </tr>
                                <tr>
                                    <td>Archive Management</td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-minus um-perm-dash"></i></td>
                                    <td><i class="fas fa-minus um-perm-dash"></i></td>
                                </tr>
                                <tr>
                                    <td>System Settings</td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                </tr>
                                <tr>
                                    <td>User Manual</td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                    <td><i class="fas fa-check um-perm-check"></i></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="um-callout um-callout-info">
                        <i class="fas fa-circle-info"></i>
                        <span>Your current role is <strong><?= htmlspecialchars($roleLabel) ?></strong>. Rows with a light blue background indicate your role's access level.</span>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: FAQ                                        -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div class="um-section" id="sec-faq" data-section="faq"
                 data-keywords="faq frequently asked questions help common issues why can't access pending approved rejected points voucher map password">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-circle-question"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>Frequently Asked Questions (FAQ)</h3>
                        <p>Common questions and answers</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <div class="um-faq-list">

                        <div class="um-faq-item">
                            <div class="um-faq-question">
                                <div class="um-faq-q-icon">Q</div>
                                <span>Why is my account Pending?</span>
                                <i class="fas fa-chevron-down um-faq-chevron"></i>
                            </div>
                            <div class="um-faq-answer">
                                Your account was created by an administrator but has not yet been activated. Accounts start with a
                                <strong>Pending</strong> status. Please contact your authorized administrator to activate your account
                                before you can log in.
                            </div>
                        </div>

                        <div class="um-faq-item">
                            <div class="um-faq-question">
                                <div class="um-faq-q-icon">Q</div>
                                <span>Why can't I edit this tourist site?</span>
                                <i class="fas fa-chevron-down um-faq-chevron"></i>
                            </div>
                            <div class="um-faq-answer">
                                <?php if ($userRole === 'municipal'): ?>
                                You can only edit tourist sites that belong to your assigned municipality. If you see a site from another municipality, you cannot edit it. If you believe you should have access, contact your administrator.
                                <?php else: ?>
                                Editing may be restricted based on the site's current status (e.g., an Approved site may require special permission to edit) or your specific account permissions. Contact your system administrator if you need to make changes.
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="um-faq-item">
                            <div class="um-faq-question">
                                <div class="um-faq-q-icon">Q</div>
                                <span>Who approves tourist sites?</span>
                                <i class="fas fa-chevron-down um-faq-chevron"></i>
                            </div>
                            <div class="um-faq-answer">
                                Tourist sites submitted by Municipal Tourism Offices (MTO) are reviewed and approved (or rejected) by
                                <strong>LUPTO (La Union Provincial Tourism Office)</strong>.
                            </div>
                        </div>

                        <div class="um-faq-item">
                            <div class="um-faq-question">
                                <div class="um-faq-q-icon">Q</div>
                                <span>How do I add a tourist site?</span>
                                <i class="fas fa-chevron-down um-faq-chevron"></i>
                            </div>
                            <div class="um-faq-answer">
                                <?php if ($userRole === 'picto'): ?>
                                PICTO access to adding tourist sites depends on your specific account permissions. Please refer to the Manage Tourist Sites section above or contact your administrator.
                                <?php else: ?>
                                Go to <strong>Manage Tourist Sites → Add Tourist Site</strong>. Fill in the required details, set the location on the map, upload images, and click Save/Submit. See the <em>Add Tourist Site</em> section above for step-by-step instructions.
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="um-faq-item">
                            <div class="um-faq-question">
                                <div class="um-faq-q-icon">Q</div>
                                <span>Why is my tourist site location flagged as invalid?</span>
                                <i class="fas fa-chevron-down um-faq-chevron"></i>
                            </div>
                            <div class="um-faq-answer">
                                The system validates that your map marker is inside the correct municipality boundary. If the marker is
                                outside the municipality you selected, the location is flagged as invalid. Re-open the site, adjust the
                                marker so it is within the correct boundary, and save again.
                            </div>
                        </div>

                        <div class="um-faq-item">
                            <div class="um-faq-question">
                                <div class="um-faq-q-icon">Q</div>
                                <span>How do I check my submission status?</span>
                                <i class="fas fa-chevron-down um-faq-chevron"></i>
                            </div>
                            <div class="um-faq-answer">
                                Go to <strong>Manage Tourist Sites</strong> and check the <em>Status</em> column next to your submitted site.
                                Status options are: <strong>Pending</strong> (awaiting review), <strong>Approved</strong>, or <strong>Rejected</strong>.
                                Click the site row to see a rejection reason if applicable.
                            </div>
                        </div>

                        <div class="um-faq-item">
                            <div class="um-faq-question">
                                <div class="um-faq-q-icon">Q</div>
                                <span>How are points calculated for tourists?</span>
                                <i class="fas fa-chevron-down um-faq-chevron"></i>
                            </div>
                            <div class="um-faq-answer">
                                Points are based on the tourist site's classification:
                                <ul style="margin-top:8px;">
                                    <li><strong>Existing</strong> sites = 50 points per visit</li>
                                    <li><strong>Emerging</strong> sites = 100 points per visit</li>
                                    <li><strong>Potential</strong> sites = 75 points per visit</li>
                                </ul>
                                Points are only credited after the visit proof is validated and approved in the Proof Validation module.
                            </div>
                        </div>

                        <div class="um-faq-item">
                            <div class="um-faq-question">
                                <div class="um-faq-q-icon">Q</div>
                                <span>How do tourists redeem a voucher?</span>
                                <i class="fas fa-chevron-down um-faq-chevron"></i>
                            </div>
                            <div class="um-faq-answer">
                                Tourists accumulate points by visiting tourist sites. When they reach the required points threshold
                                for a voucher, they can redeem it through the tourist mobile app. The redemption process and available
                                vouchers are managed in the <strong>Voucher & Rewards</strong> module.
                            </div>
                        </div>

                        <div class="um-faq-item">
                            <div class="um-faq-question">
                                <div class="um-faq-q-icon">Q</div>
                                <span>Why can't I access a menu item?</span>
                                <i class="fas fa-chevron-down um-faq-chevron"></i>
                            </div>
                            <div class="um-faq-answer">
                                Some modules are only accessible to certain roles. If you cannot see a menu item, it is likely not
                                available for your role (<?= htmlspecialchars($roleLabel) ?>). If you believe this is an error, contact
                                your system administrator. Additionally, if you have a <em>must change password</em> flag on your account,
                                you must update your password before accessing other modules.
                            </div>
                        </div>

                        <div class="um-faq-item">
                            <div class="um-faq-question">
                                <div class="um-faq-q-icon">Q</div>
                                <span>How do I generate a report?</span>
                                <i class="fas fa-chevron-down um-faq-chevron"></i>
                            </div>
                            <div class="um-faq-answer">
                                Go to the <strong>Analytics & Reports</strong> module. Select your desired date range and filters.
                                Use the <em>Export</em> or <em>Print</em> button to generate and download your report.
                            </div>
                        </div>

                    </div><!-- /um-faq-list -->
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: TROUBLESHOOTING                            -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div class="um-section" id="sec-troubleshooting" data-section="troubleshooting"
                 data-keywords="troubleshoot problems issues cannot login error page not loading slow map not loading">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-wrench"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>Troubleshooting</h3>
                        <p>Common problems and how to fix them</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <div class="um-trouble-list">

                        <div class="um-trouble-card">
                            <div class="um-trouble-problem">
                                <i class="fas fa-exclamation-circle"></i>
                                I cannot log in to the system.
                            </div>
                            <div class="um-trouble-body">
                                <div class="um-trouble-section">
                                    <h5>Possible Causes</h5>
                                    <ul>
                                        <li>Incorrect email or password</li>
                                        <li>Account status is <strong>Pending</strong></li>
                                        <li>Account status is <strong>Inactive</strong></li>
                                        <li>Network or server issue</li>
                                    </ul>
                                </div>
                                <div class="um-trouble-section um-trouble-solution">
                                    <h5>Solution</h5>
                                    <div class="um-trouble-solution-box">
                                        <i class="fas fa-lightbulb"></i>
                                        <span>Verify your email address and password. Check for caps lock. If the account is Pending or Inactive, contact your authorized system administrator to activate it. If the server is unreachable, check your network connection.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="um-trouble-card">
                            <div class="um-trouble-problem">
                                <i class="fas fa-exclamation-circle"></i>
                                The page is loading slowly or not loading at all.
                            </div>
                            <div class="um-trouble-body">
                                <div class="um-trouble-section">
                                    <h5>Possible Causes</h5>
                                    <ul>
                                        <li>Slow or unstable internet connection</li>
                                        <li>Server-side processing delay</li>
                                        <li>Browser cache issue</li>
                                    </ul>
                                </div>
                                <div class="um-trouble-section um-trouble-solution">
                                    <h5>Solution</h5>
                                    <div class="um-trouble-solution-box">
                                        <i class="fas fa-lightbulb"></i>
                                        <span>Check your internet connection. Try refreshing the page (Ctrl+F5 for a hard refresh). If the issue persists, clear your browser cache or try a different browser.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="um-trouble-card">
                            <div class="um-trouble-problem">
                                <i class="fas fa-exclamation-circle"></i>
                                The map is not loading or shows a blank area.
                            </div>
                            <div class="um-trouble-body">
                                <div class="um-trouble-section">
                                    <h5>Possible Causes</h5>
                                    <ul>
                                        <li>No internet connection (map tiles need internet)</li>
                                        <li>Browser extension blocking map tiles</li>
                                        <li>Browser doesn't support Leaflet.js</li>
                                    </ul>
                                </div>
                                <div class="um-trouble-section um-trouble-solution">
                                    <h5>Solution</h5>
                                    <div class="um-trouble-solution-box">
                                        <i class="fas fa-lightbulb"></i>
                                        <span>Ensure you have an active internet connection. Disable browser extensions that may block content (such as ad-blockers). Try a modern browser (Chrome, Edge, Firefox).</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="um-trouble-card">
                            <div class="um-trouble-problem">
                                <i class="fas fa-exclamation-circle"></i>
                                I cannot save a tourist site — an error appears.
                            </div>
                            <div class="um-trouble-body">
                                <div class="um-trouble-section">
                                    <h5>Possible Causes</h5>
                                    <ul>
                                        <li>Required fields are empty</li>
                                        <li>Map marker is outside the municipality boundary</li>
                                        <li>Image file is too large or wrong format</li>
                                        <li>Session has expired</li>
                                    </ul>
                                </div>
                                <div class="um-trouble-section um-trouble-solution">
                                    <h5>Solution</h5>
                                    <div class="um-trouble-solution-box">
                                        <i class="fas fa-lightbulb"></i>
                                        <span>Fill in all required fields. Ensure the map marker is inside the correct municipality. Use JPG or PNG images under the allowed file size. If the session expired, log in again.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="um-trouble-card">
                            <div class="um-trouble-problem">
                                <i class="fas fa-exclamation-circle"></i>
                                I get an "Access Denied" or "Change Password" modal on every page.
                            </div>
                            <div class="um-trouble-body">
                                <div class="um-trouble-section">
                                    <h5>Possible Causes</h5>
                                    <ul>
                                        <li>First login with a default/temporary password</li>
                                        <li>Administrator has reset your password</li>
                                    </ul>
                                </div>
                                <div class="um-trouble-section um-trouble-solution">
                                    <h5>Solution</h5>
                                    <div class="um-trouble-solution-box">
                                        <i class="fas fa-lightbulb"></i>
                                        <span>You must change your password before accessing other modules. Go to <strong>System Settings → Security Settings</strong> and change your password immediately.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div><!-- /um-trouble-list -->
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════ -->
            <!--  SECTION: CONTACT / SUPPORT                          -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div class="um-section open" id="sec-support" data-section="support"
                 data-keywords="contact support help administrator office need help assistance">
                <div class="um-section-header">
                    <div class="um-section-icon"><i class="fas fa-headset"></i></div>
                    <div class="um-section-title-wrap">
                        <h3>Contact / Support</h3>
                        <p>Getting help when you need it</p>
                    </div>
                    <i class="fas fa-chevron-down um-section-chevron"></i>
                </div>
                <div class="um-section-body">
                    <div class="um-support-card">
                        <i class="fas fa-headset um-support-main-icon"></i>
                        <h4>Need Help?</h4>
                        <p>
                            If you encounter issues not covered in this manual, or if you need assistance
                            with your account or system access, please contact the authorized system administrator
                            or your respective tourism office.
                        </p>
                        <p>
                            <?php if ($userRole === 'municipal'): ?>
                            For site-related concerns, you may also coordinate with the
                            <strong>La Union Provincial Tourism Office (LUPTO)</strong>.
                            <?php else: ?>
                            For technical issues, coordinate with the
                            <strong>Provincial Information and Communications Technology Office (PICTO)</strong>.
                            <?php endif; ?>
                        </p>
                        <p class="um-support-note">
                            This system is managed by the La Union Provincial Government.
                            Do not share your login credentials with anyone.
                        </p>
                    </div>
                </div>
            </div>

        </div><!-- /um-content -->
    </div><!-- /um-layout -->
</div><!-- /um-wrapper -->

<script>
(function () {
    'use strict';

    /* ── Elements ────────────────────────────────────────────── */
    const searchInput      = document.getElementById('umSearchInput');
    const searchResults    = document.getElementById('umSearchResults');
    const sections         = Array.from(document.querySelectorAll('.um-section'));
    const umContent        = document.getElementById('umContent');
    const activeCategoryEl = document.getElementById('umActiveCategory');
    const activeTitleEl    = document.getElementById('umActiveTitle');
    const prevBtn          = document.getElementById('umPrevTopicBtn');
    const nextBtn          = document.getElementById('umNextTopicBtn');
    const toggleViewAllBtn = document.getElementById('umToggleViewAllBtn');
    const viewAllText      = document.getElementById('umViewAllText');
    const tocLinks         = Array.from(document.querySelectorAll('.um-toc-link[data-target]'));

    let isViewAll = false;
    let currentTopicIndex = 0;

    function escHTML(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    /* ── Build Search Index ──────────────────────────────────── */
    function buildSearchIndex() {
        return sections.map(sec => {
            const title   = sec.querySelector('.um-section-title-wrap h3')?.textContent || '';
            const desc    = sec.querySelector('.um-section-title-wrap p')?.textContent || '';
            const kw      = sec.dataset.keywords || '';
            const body    = sec.querySelector('.um-section-body')?.textContent || '';
            return { el: sec, title, keywords: (kw + ' ' + desc + ' ' + body).toLowerCase() };
        });
    }

    const searchIndex = buildSearchIndex();

    /* ── Switch Topic Focus ──────────────────────────────────── */
    function switchTopic(targetId, updateHistory = true) {
        if (!targetId) return;
        const targetEl = document.getElementById(targetId);
        if (!targetEl) return;

        // If currently in View All mode and user specifically chose a topic, return to single mode
        if (isViewAll) {
            setViewAll(false);
        }

        // 1. Hide all sections, show only target
        sections.forEach(s => {
            s.classList.remove('active-manual');
        });
        targetEl.classList.add('active-manual');
        targetEl.classList.add('open');

        // 2. Highlight matching TOC link and ensure its parent group is open
        let activeLink = null;
        tocLinks.forEach((link, idx) => {
            const isMatch = (link.dataset.target === targetId);
            link.classList.toggle('active', isMatch);
            if (isMatch) {
                activeLink = link;
                currentTopicIndex = idx;
                const parentGroup = link.closest('.um-toc-group');
                if (parentGroup) parentGroup.classList.add('open');
            }
        });

        // 3. Update breadcrumb bar
        if (activeCategoryEl && activeTitleEl) {
            const parentGroupLabel = activeLink?.closest('.um-toc-group')?.querySelector('.um-toc-group-label')?.textContent?.trim() || 'Manual';
            const cleanCategory = parentGroupLabel.replace(/\s+/g, ' ');
            const topicTitle = targetEl.querySelector('.um-section-title-wrap h3')?.textContent || activeLink?.textContent?.trim() || 'Topic';
            
            activeCategoryEl.innerHTML = `<i class="fas fa-folder-open"></i> ${escHTML(cleanCategory)}`;
            activeTitleEl.textContent = topicTitle;
        }

        // 4. Update Prev / Next buttons
        if (prevBtn) prevBtn.disabled = (currentTopicIndex <= 0);
        if (nextBtn) nextBtn.disabled = (currentTopicIndex >= tocLinks.length - 1);

        // 5. Scroll to top of active topic bar smoothly
        const topicBar = document.getElementById('umActiveTopicBar');
        if (topicBar) {
            topicBar.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        // 6. Update URL hash
        if (updateHistory && history.replaceState) {
            history.replaceState(null, '', '#' + targetId);
        }
    }

    /* ── View All Toggle ─────────────────────────────────────── */
    function setViewAll(enable) {
        isViewAll = enable;
        if (umContent) {
            umContent.classList.toggle('view-all', isViewAll);
        }
        if (toggleViewAllBtn && viewAllText) {
            toggleViewAllBtn.classList.toggle('view-all-active', isViewAll);
            viewAllText.textContent = isViewAll ? 'Focus Mode' : 'View All';
        }
        if (prevBtn) prevBtn.style.display = isViewAll ? 'none' : '';
        if (nextBtn) nextBtn.style.display = isViewAll ? 'none' : '';
    }

    if (toggleViewAllBtn) {
        toggleViewAllBtn.addEventListener('click', () => {
            setViewAll(!isViewAll);
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentTopicIndex > 0) {
                const prevTarget = tocLinks[currentTopicIndex - 1]?.dataset.target;
                if (prevTarget) switchTopic(prevTarget);
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            if (currentTopicIndex < tocLinks.length - 1) {
                const nextTarget = tocLinks[currentTopicIndex + 1]?.dataset.target;
                if (nextTarget) switchTopic(nextTarget);
            }
        });
    }

    /* ── Search Handling ─────────────────────────────────────── */
    function renderSearchResults(query) {
        if (!query || query.length < 2) {
            searchResults.innerHTML = '';
            searchResults.classList.remove('active');
            return;
        }
        const q = query.toLowerCase();
        const matches = searchIndex.filter(item =>
            item.title.toLowerCase().includes(q) || item.keywords.includes(q)
        );
        if (!matches.length) {
            searchResults.innerHTML = '<div class="um-search-no-results"><i class="fas fa-search"></i> No results found for "' + escHTML(query) + '"</div>';
        } else {
            searchResults.innerHTML = matches.slice(0, 8).map(item =>
                '<div class="um-search-result-item" data-target="' + item.el.id + '">' +
                '<i class="fas fa-book-open"></i>' +
                '<div><div>' + escHTML(item.title) + '</div>' +
                '<div class="um-sr-section">' + escHTML(item.el.querySelector('.um-section-title-wrap p')?.textContent || '') + '</div>' +
                '</div></div>'
            ).join('');
        }
        searchResults.classList.add('active');
    }

    if (searchInput) {
        searchInput.addEventListener('input', () => renderSearchResults(searchInput.value.trim()));
    }

    if (searchResults) {
        searchResults.addEventListener('click', (e) => {
            const item = e.target.closest('.um-search-result-item');
            if (!item) return;
            const targetId = item.dataset.target;
            if (!targetId) return;
            switchTopic(targetId);
            if (searchInput) searchInput.value = '';
            searchResults.classList.remove('active');
            
            const targetEl = document.getElementById(targetId);
            if (targetEl) {
                targetEl.classList.add('highlighted');
                setTimeout(() => targetEl.classList.remove('highlighted'), 2500);
            }
        });
    }

    document.addEventListener('click', (e) => {
        if (searchResults && !searchResults.contains(e.target) && e.target !== searchInput) {
            searchResults.classList.remove('active');
        }
    });

    /* ── Section Accordion (Click Header to expand/collapse) ─── */
    sections.forEach(sec => {
        const header = sec.querySelector('.um-section-header');
        if (!header) return;
        header.addEventListener('click', () => {
            sec.classList.toggle('open');
        });
    });

    /* ── Table of Contents Group Accordion ───────────────────── */
    document.querySelectorAll('.um-toc-group-label').forEach(label => {
        label.addEventListener('click', () => {
            const group = label.closest('.um-toc-group');
            if (group) group.classList.toggle('open');
        });
    });

    /* ── TOC Link Click Event ────────────────────────────────── */
    tocLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = link.dataset.target;
            if (targetId) {
                switchTopic(targetId);
            }
        });
    });

    /* ── FAQ Accordion ───────────────────────────────────────── */
    document.querySelectorAll('.um-faq-question').forEach(q => {
        q.addEventListener('click', () => {
            const item = q.closest('.um-faq-item');
            if (!item) return;
            const wasOpen = item.classList.contains('open');
            document.querySelectorAll('.um-faq-item').forEach(i => i.classList.remove('open'));
            if (!wasOpen) item.classList.add('open');
        });
    });

    /* ── Initial Load: Determine Starting Topic ──────────────── */
    const initialHash = window.location.hash.replace('#', '');
    if (initialHash && document.getElementById(initialHash)) {
        switchTopic(initialHash, false);
    } else if (tocLinks.length > 0) {
        switchTopic(tocLinks[0].dataset.target, false);
    }

})();
</script>

<?php
$pageContent = ob_get_clean();
if (is_ajax_request()) {
    if (isset($extraHeadContent)) {
        echo $extraHeadContent;
    }
    echo $pageContent;
    exit;
}
include '../components/sections.php';
