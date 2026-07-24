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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INTAN ELYU - Tourist Spots Management System</title>
    <!-- Google Fonts: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom Style Sheet -->
    <link rel="stylesheet" href="css/components/login.css?v=<?php echo time(); ?>">
</head>
<body class="login-page-body">
    <div class="login-wrapper login-mode" id="loginWrapper">
        
        <!-- LEFT PANEL: Background, Quotes, Stats/Progress -->
        <div class="info-panel" id="infoPanel">
            <div class="info-overlay"></div>
            <div class="info-content">
                
                <!-- Top Portal Header -->
                <div class="left-header">
                    <div class="header-logo-container">
                        <div class="header-logo-icon">
                            <i class="fas fa-shield-alt" id="leftHeaderIcon"></i>
                        </div>
                        <div class="header-logo-text">
                            <span class="portal-badge" id="leftPortalBadge">OFFICIAL PORTAL</span>
                            <span class="portal-dept">City Tourism Office</span>
                        </div>
                    </div>
                </div>
                
                <!-- Center Main Section -->
                <div class="info-main">
                    <div class="dots-indicator">
                        <span class="dot"></span>
                        <span class="dot"></span>
                        <span class="dot active"></span>
                        <span class="dot"></span>
                        <span class="dot"></span>
                    </div>
                    
                    <h1 class="left-title" id="leftTitleText">
                        Tourist Spots <br><span class="highlight">Management</span> <br>System
                    </h1>
                    
                    <p class="left-subtitle">San Fernando City, La Union</p>
                    
                    <div class="left-quote">
                        <span class="quote-bar"></span>
                        <p class="quote-text">"Discover, Preserve, and Promote the Beauty of La Union."</p>
                    </div>
                </div>

                <!-- Bottom dynamic info section: Stats or Recovery Steps -->
                <div class="dynamic-left-info">
                    
                    <!-- Stats Cards (Visible in login mode) -->
                    <div class="stats-container" id="leftStatsContainer">
                        <div class="stat-card">
                            <div class="stat-icon-wrapper">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="stat-details">
                                <div class="stat-number">42+</div>
                                <div class="stat-label">Tourist Spots</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon-wrapper">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="stat-details">
                                <div class="stat-number">4.8</div>
                                <div class="stat-label">Avg. Rating</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon-wrapper">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="stat-details">
                                <div class="stat-number">Secure</div>
                                <div class="stat-label">Gov't Portal</div>
                            </div>
                        </div>
                    </div>

                    <!-- Steps Progress Tracker (Visible in recovery modes) -->
                    <div class="recovery-left-info" id="leftRecoveryContainer" style="display: none;">
                        <div class="recovery-info-card">
                            <div class="recovery-info-icon">
                                <i class="fas fa-key"></i>
                            </div>
                            <div class="recovery-info-text">
                                <h3>Secure Password Reset</h3>
                                <p>A unique reset link will be sent to your registered email. Links expire after 30 minutes.</p>
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

                <!-- Footer at the bottom left -->
                <div class="left-footer">
                    © 2026 City Tourism Office – San Fernando City, La Union
                </div>
            </div>
        </div>

        <!-- RIGHT PANEL: Forms, Submits, Actions -->
        <div class="form-panel" id="formPanel">
            
            <!-- Top Navigation Link (Forgot password back button) -->
            <div class="back-to-login-top" id="topBackToLogin" style="visibility: hidden;">
                <a href="#" class="btn-back-link" id="topBackToLoginBtn"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>
            
            <!-- Form Card Wrapper -->
            <div class="form-card-container">
                
                <!-- Card Brand Header (Now INSIDE the card) -->
                <div class="card-brand-header">
                    <div class="brand-logo-image-wrapper">
                        <img src="images/LOGO.png" alt="INTAN ELYU Logo" class="brand-logo-img">
                    </div>
                    <h2 class="brand-title">INTAN ELYU</h2>
                    <p class="brand-subtitle">Tourist Spots Management System</p>
                    <p class="brand-location">San Fernando City, La Union</p>
                </div>
                
                <!-- 1. LOGIN FORM SECTION -->
                <div id="loginSection" class="form-section-container active">
                    <div class="section-header">
                        <h3>Welcome Back</h3>
                        <p>Sign in to your account to continue</p>
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
                            <i class="fas fa-check-circle"></i> Password successfully updated. Please sign in with your new credentials.
                        </div>
                    <?php endif; ?>

                    <form id="loginForm">
                        <div class="form-input-group">
                            <label for="email">Username</label>
                            <div class="input-with-icon">
                                <i class="far fa-user"></i>
                                <input type="text" id="email" name="email" required placeholder="Enter your username">
                            </div>
                        </div>

                        <div class="form-input-group">
                            <label for="password">Password</label>
                            <div class="password-field-wrapper">
                                <i class="fas fa-lock pw-left-icon" aria-hidden="true"></i>
                                <input type="password" id="password" name="password" required placeholder="Enter your password" autocomplete="current-password">
                                <button type="button" id="togglePassword" class="pw-toggle-btn" aria-label="Toggle password visibility" tabindex="-1">
                                    <i class="far fa-eye" id="pwEyeIcon" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-actions-row">
                            <label class="custom-checkbox">
                                <input type="checkbox" id="rememberMe" name="remember">
                                <span class="checkmark"></span>
                                <span class="label-text">Remember me</span>
                            </label>
                            <a href="#" class="forgot-password-link" id="forgotPasswordBtn">Forgot Password?</a>
                        </div>

                        <div id="errorMessage" class="alert alert-error" style="display: none;"></div>

                        <button type="submit" class="btn-primary-gradient btn-login">
                            <i class="fas fa-sign-in-alt"></i>
                            Sign In
                        </button>
                    </form>
                </div>

                <!-- 2. FORGOT PASSWORD STEP 1 SECTION -->
                <div id="recoveryStep1Section" class="form-section-container">
                    <div class="section-header">
                        <h3>Forgot Password?</h3>
                        <p>No worries! Enter your registered email and we'll send you a secure link to reset your password.</p>
                    </div>

                    <form id="recoveryEmailForm">
                        <div class="form-input-group">
                            <label for="recoveryEmail">Registered Email Address</label>
                            <div class="input-with-icon">
                                <i class="far fa-envelope"></i>
                                <input type="email" id="recoveryEmail" required placeholder="your@email.com">
                            </div>
                            <span class="input-hint-info"><i class="fas fa-info-circle"></i> We'll send the reset link to this address.</span>
                        </div>

                        <div id="recoveryErrorMessage" class="alert alert-error" style="display: none;"></div>

                        <button type="submit" class="btn-primary-gradient btn-send-link">
                            <i class="far fa-paper-plane"></i>
                            Send Reset Link
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
                        <p>We've sent a password reset link to <span id="sentEmailPlaceholder">your@email.com</span>. Please check your inbox (and spam folder) and click the link.</p>
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

                    <button type="button" class="btn-primary-gradient btn-back-to-login-success" id="returnToLoginSuccessBtn">
                        <i class="fas fa-sign-in-alt"></i>
                        Return to Login
                    </button>

                    <button type="button" id="btnResendEmail" class="btn-outline btn-resend-email">
                        <i class="fas fa-sync-alt"></i>
                        Resend Email
                    </button>

                    <div class="support-footer">
                        <i class="fas fa-headset"></i> Need help? Contact <a href="mailto:support@sanfernando.gov.ph">support@sanfernando.gov.ph</a>
                    </div>
                </div>

            </div>

            <!-- Footer at the bottom right -->
            <div class="right-footer">
                © 2026 City Tourism Office – San Fernando City, La Union
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

    <!-- JavaScripts -->
    <script src="scripts/api-config.js?v=<?php echo time(); ?>"></script>
    <script src="scripts/login.js?v=<?php echo time(); ?>"></script>
    <script>
        // ── Password Visibility Toggle ──────────────────────────────────────
        (function () {
            const btn   = document.getElementById('togglePassword');
            const input = document.getElementById('password');
            const icon  = document.getElementById('pwEyeIcon');
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
