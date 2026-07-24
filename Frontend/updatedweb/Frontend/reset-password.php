<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/api/db.php';

$token = trim($_GET['token'] ?? '');
$tokenError = null;
$tokenValid = false;

if (!empty($token) && preg_match('/^[a-f0-9]{64}$/', $token)) {
    try {
        $db = getDb();
        $tokenHash = hash('sha256', $token);

        $stmt = $db->prepare(
            'SELECT id FROM frontend_password_resets
             WHERE token_hash = :hash AND expires_at > NOW() AND used = 0
             LIMIT 1'
        );
        $stmt->execute([':hash' => $tokenHash]);
        $resetRow = $stmt->fetch();

        if ($resetRow) {
            $tokenValid = true;
        }
    } catch (Exception $e) {
        $tokenError = 'Unable to verify reset token. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">
    <title>Reset Password - INTAN ELYU</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/components/reset-password.css">
</head>
<body class="reset-page-body">
    <div class="reset-wrapper">

        <!-- Background Decorative Panel -->
        <div class="reset-bg-panel">
            <div class="reset-bg-overlay"></div>
            <div class="reset-bg-content">
                <div class="reset-bg-logo">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1 class="reset-bg-title">Secure Password <br><span class="highlight">Reset</span></h1>
                <p class="reset-bg-subtitle">San Fernando City, La Union</p>
                <div class="reset-bg-quote">
                    <span class="reset-quote-bar"></span>
                    <p>Create a strong, unique password to keep your account secure.</p>
                </div>
                <div class="reset-bg-footer">
                    &copy; 2026 City Tourism Office &bull; San Fernando City, La Union
                </div>
            </div>
        </div>

        <!-- Form Panel -->
        <div class="reset-form-panel">
            <div class="reset-form-card">

                <!-- Brand Header -->
                <div class="reset-brand">
                    <div class="reset-brand-img-wrapper">
                        <img src="images/LOGO.png" alt="INTAN ELYU Logo" class="reset-brand-img">
                    </div>
                    <h2>INTAN ELYU</h2>
                    <p>Tourist Spots Management System</p>
                </div>

                <?php if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)): ?>
                <!-- Invalid Token State -->
                <div class="reset-state">
                    <div class="reset-state-icon error">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3>Invalid Reset Link</h3>
                    <p>This password reset link is missing or malformed. Please request a new reset link from the login page.</p>
                    <a href="login.php" class="reset-btn reset-btn-primary">
                        <i class="fas fa-arrow-left"></i> Return to Login
                    </a>
                </div>

                <?php elseif (!$tokenValid): ?>
                <!-- Expired / Used Token State -->
                <div class="reset-state">
                    <div class="reset-state-icon error">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>Link Expired or Already Used</h3>
                    <p>This password reset link is no longer valid. For your security, reset links expire after 30 minutes and can only be used once.</p>
                    <a href="login.php" class="reset-btn reset-btn-primary">
                        <i class="fas fa-arrow-left"></i> Return to Login
                    </a>
                </div>

                <?php else: ?>
                <!-- Reset Form -->
                <div class="reset-form-section">
                    <div class="reset-section-header">
                        <h3>Set New Password</h3>
                        <p>Choose a strong password for your account</p>
                    </div>

                    <div id="resetErrorMessage" class="reset-alert reset-alert-error" style="display:none;"></div>
                    <div id="resetSuccessMessage" class="reset-alert reset-alert-success" style="display:none;"></div>

                    <form id="resetPasswordForm">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                        <div class="reset-field">
                            <label for="newPassword">New Password</label>
                            <div class="reset-password-wrapper">
                                <i class="fas fa-lock reset-field-icon"></i>
                                <input type="password" id="newPassword" required
                                       placeholder="Enter new password"
                                       autocomplete="new-password">
                                <button type="button" class="reset-eye-btn" data-target="newPassword" tabindex="-1">
                                    <i class="far fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Password Strength Meter -->
                        <div class="reset-strength-meter" id="passwordStrengthMeter">
                            <div class="reset-strength-bar" id="passwordStrengthBar"></div>
                        </div>
                        <div class="reset-strength-text" id="passwordStrengthText">
                            <i class="fas fa-circle"></i> Enter a strong password
                        </div>

                        <!-- Password Requirements -->
                        <div class="reset-requirements" id="passwordRequirements">
                            <div class="reset-req" data-req="length">
                                <i class="far fa-circle"></i> At least 8 characters
                            </div>
                            <div class="reset-req" data-req="uppercase">
                                <i class="far fa-circle"></i> One uppercase letter
                            </div>
                            <div class="reset-req" data-req="lowercase">
                                <i class="far fa-circle"></i> One lowercase letter
                            </div>
                            <div class="reset-req" data-req="number">
                                <i class="far fa-circle"></i> One number
                            </div>
                        </div>

                        <div class="reset-field">
                            <label for="confirmPassword">Confirm Password</label>
                            <div class="reset-password-wrapper">
                                <i class="fas fa-lock reset-field-icon"></i>
                                <input type="password" id="confirmPassword" required
                                       placeholder="Re-enter new password"
                                       autocomplete="new-password">
                                <button type="button" class="reset-eye-btn" data-target="confirmPassword" tabindex="-1">
                                    <i class="far fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="reset-match-indicator" id="passwordMatchIndicator" style="display:none;">
                            <i class="fas fa-check-circle"></i> Passwords match
                        </div>

                        <button type="submit" class="reset-btn reset-btn-primary" id="resetSubmitBtn">
                            <i class="fas fa-key"></i>
                            <span id="resetSubmitLabel">Reset Password</span>
                            <i class="fas fa-circle-notch fa-spin" id="resetSubmitSpinner" style="display:none;"></i>
                        </button>
                    </form>

                    <div class="reset-back-link">
                        <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>

    </div>

 <script src="../scripts/reset-password.js"></script>
</body>
</html>
