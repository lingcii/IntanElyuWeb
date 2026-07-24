document.addEventListener('DOMContentLoaded', () => {
    // ----------------------------------------------------
    // Element Selectors
    // ----------------------------------------------------
    const loginWrapper = document.getElementById('loginWrapper');
    const loginForm = document.getElementById('loginForm');
    const recoveryEmailForm = document.getElementById('recoveryEmailForm');
    const btnResendEmail = document.getElementById('btnResendEmail');
    
    // Alert & Message Selectors
    const errorMessage = document.getElementById('errorMessage');
    const recoveryErrorMessage = document.getElementById('recoveryErrorMessage');
    
    // Modals
    const successModal = document.getElementById('successModal');
    const errorModal = document.getElementById('errorModal');
    const errorModalText = document.getElementById('errorModalText');
    const closeErrorModal = document.getElementById('closeErrorModal');

    // Navigation triggers
    const forgotPasswordBtn = document.getElementById('forgotPasswordBtn');
    const topBackToLoginBtn = document.getElementById('topBackToLoginBtn');
    const backToLoginBtn2 = document.getElementById('backToLoginBtn2');
    const returnToLoginSuccessBtn = document.getElementById('returnToLoginSuccessBtn');

    // State indicators
    const statsContainer = document.getElementById('leftStatsContainer');
    const recoveryContainer = document.getElementById('leftRecoveryContainer');
    const leftPortalBadge = document.getElementById('leftPortalBadge');
    const leftTitleText = document.getElementById('leftTitleText');
    
    const step1 = document.getElementById('stepIndicator1');
    const step2 = document.getElementById('stepIndicator2');
    const step3 = document.getElementById('stepIndicator3');
    const conn1 = document.getElementById('stepConnector1');
    const conn2 = document.getElementById('stepConnector2');
    
    const topBack = document.getElementById('topBackToLogin');
    const brandLogo = document.getElementById('brandLogoSquare');
    const sentEmailPlaceholder = document.getElementById('sentEmailPlaceholder');

    // ----------------------------------------------------
    // State Manager
    // ----------------------------------------------------
    function switchState(stateName) {
        // Clear active alerts
        if (errorMessage) errorMessage.style.display = 'none';
        if (recoveryErrorMessage) recoveryErrorMessage.style.display = 'none';
        
        // Hide all form sections
        document.querySelectorAll('.form-section-container').forEach(el => {
            el.classList.remove('active');
        });
        
        // Reset brand logo styles
        if (brandLogo) brandLogo.style.background = '';
        
        // Reset wrapper classes
        loginWrapper.classList.remove('login-mode', 'recovery-step-1', 'recovery-step-2');
        
        if (stateName === 'login') {
            loginWrapper.classList.add('login-mode');
            
            statsContainer.style.display = 'flex';
            recoveryContainer.style.display = 'none';
            
            leftPortalBadge.textContent = 'OFFICIAL PORTAL';
            leftTitleText.innerHTML = 'Tourist Spots <br><span class="highlight">Management</span> <br>System';
            
            topBack.style.visibility = 'hidden';
            
            if (brandLogo) {
                brandLogo.className = 'brand-logo-square';
                brandLogo.innerHTML = '<i class="fas fa-map-marker-alt"></i>';
            }
            
            document.getElementById('loginSection').classList.add('active');
        } else if (stateName === 'recovery-1') {
            loginWrapper.classList.add('recovery-step-1');
            
            statsContainer.style.display = 'none';
            recoveryContainer.style.display = 'block';
            
            leftPortalBadge.textContent = 'SECURE RESET';
            leftTitleText.innerHTML = 'Account <span class="highlight">Recovery</span>';
            
            // Steps progress tracking
            step1.classList.add('active');
            step2.classList.remove('active');
            step3.classList.remove('active');
            conn1.classList.remove('active');
            conn2.classList.remove('active');
            
            topBack.style.visibility = 'visible';
            
            if (brandLogo) {
                brandLogo.className = 'brand-logo-square recovery';
                brandLogo.innerHTML = '<i class="fas fa-key"></i>';
            }
            
            document.getElementById('recoveryStep1Section').classList.add('active');
        } else if (stateName === 'recovery-2') {
            loginWrapper.classList.add('recovery-step-2');
            
            statsContainer.style.display = 'none';
            recoveryContainer.style.display = 'block';
            
            leftPortalBadge.textContent = 'EMAIL SENT';
            leftTitleText.innerHTML = 'Check Your <span class="highlight">Inbox</span>';
            
            // Steps progress tracking
            step1.classList.add('active');
            step2.classList.add('active');
            step3.classList.remove('active');
            conn1.classList.add('active');
            conn2.classList.remove('active');
            
            topBack.style.visibility = 'hidden';
            
            if (brandLogo) {
                brandLogo.className = 'brand-logo-square';
                brandLogo.style.background = '#10b981'; // Success emerald color
                brandLogo.innerHTML = '<i class="far fa-envelope"></i>';
            }
            
            document.getElementById('recoveryStep2Section').classList.add('active');
        }
    }

    // ----------------------------------------------------
    // Event Listeners for State Toggling
    // ----------------------------------------------------
    if (forgotPasswordBtn) {
        forgotPasswordBtn.addEventListener('click', (e) => {
            e.preventDefault();
            switchState('recovery-1');
        });
    }

    const backToLoginTriggers = [topBackToLoginBtn, backToLoginBtn2, returnToLoginSuccessBtn];
    backToLoginTriggers.forEach(btn => {
        if (btn) {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                switchState('login');
            });
        }
    });

    // Error Modal Close Listener
    if (closeErrorModal && errorModal) {
        closeErrorModal.addEventListener('click', () => {
            errorModal.classList.remove('active');
        });
        errorModal.addEventListener('click', (e) => {
            if (e.target === errorModal) {
                errorModal.classList.remove('active');
            }
        });
    }

    // ----------------------------------------------------
    // LOGIN FORM SUBMISSION (INTEGRATED WITH LARAVEL API)
    // ----------------------------------------------------
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const emailInput = document.getElementById('email');
            const passwordInput = document.querySelector('#loginForm #password');
            const submitBtn = loginForm.querySelector('.btn-login');

            const email = emailInput.value.trim();
            const password = passwordInput.value;

            if (!email || !password) {
                showError('Please fill in all fields.');
                return;
            }

            emailInput.disabled = true;
            passwordInput.disabled = true;
            submitBtn.disabled = true;
            const originalBtnHtml = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Signing In...';

            try {
                const params = new URLSearchParams();
                params.append('email', email);
                params.append('password', password);

                const response = await fetch(window.API_CONFIG.AUTH + '/login', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    credentials: 'include',
                    body: params
                }).then(async res => {
                    if (!res.ok) {
                        const errData = await res.json().catch(() => ({}));
                        throw new Error(errData.error || errData.message || `HTTP ${res.status}`);
                    }
                    return res.json();
                });

                if (response.success && response.user) {
                    if (errorMessage) errorMessage.style.display = 'none';
                    sessionStorage.clear();

                    let redirectUrl = 'views/dashboard.php'; // All roles → flat dashboard

                    if (successModal) {
                        successModal.classList.add('active');
                    }

                    // Sync PHP session — must await so browser doesn't cancel it on redirect
                    await fetch('sync-session.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ user: response.user })
                    });

                    // Brief modal display then redirect
                    setTimeout(() => {
                        window.location.href = redirectUrl;
                    }, 300);
                }
            } catch (err) {
                console.error('Login error:', err);
                showError(err.message || 'Invalid email or password.');
                emailInput.disabled = false;
                passwordInput.disabled = false;
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            }
        });
    }

    // ----------------------------------------------------
    // ACCOUNT RECOVERY FORM SUBMISSION
    // ----------------------------------------------------
    if (recoveryEmailForm) {
        recoveryEmailForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const recoveryEmailInput = document.getElementById('recoveryEmail');
            const submitBtn = recoveryEmailForm.querySelector('.btn-send-link');
            const email = recoveryEmailInput.value.trim();

            if (!email) {
                showRecoveryError('Please enter your registered email address.');
                return;
            }

            recoveryEmailInput.disabled = true;
            submitBtn.disabled = true;
            const originalBtnHtml = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Processing...';

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            try {
                const resp = await fetch('api/forgot-password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ email: email }),
                });
                const data = await resp.json();

                if (data.success) {
                    if (sentEmailPlaceholder) {
                        sentEmailPlaceholder.textContent = email;
                    }
                    switchState('recovery-2');
                } else {
                    showRecoveryError(data.message || 'An error occurred. Please try again.');
                    recoveryEmailInput.disabled = false;
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHtml;
                }
            } catch (err) {
                showRecoveryError('Network error. Please check your connection.');
                recoveryEmailInput.disabled = false;
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            }
        });
    }

    // ----------------------------------------------------
    // RESEND EMAIL HANDLER
    // ----------------------------------------------------
    if (btnResendEmail) {
        btnResendEmail.addEventListener('click', async () => {
            const email = sentEmailPlaceholder ? sentEmailPlaceholder.textContent.trim() : '';
            if (!email) {
                alert('No email address found. Please go back and try again.');
                return;
            }

            const originalBtnHtml = btnResendEmail.innerHTML;
            btnResendEmail.disabled = true;
            btnResendEmail.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Resending...';

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            try {
                const resp = await fetch('api/forgot-password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ email: email }),
                });
                const data = await resp.json();

                btnResendEmail.disabled = false;
                btnResendEmail.innerHTML = '<i class="fas fa-check-circle"></i> Email Resent!';
                setTimeout(() => {
                    btnResendEmail.innerHTML = originalBtnHtml;
                }, 3000);
            } catch (err) {
                btnResendEmail.disabled = false;
                btnResendEmail.innerHTML = originalBtnHtml;
                alert('Unable to resend email. Please try again.');
            }
        });
    }

    // Helper functions
    function showError(msg) {
        if (errorModal && errorModalText) {
            errorModalText.textContent = msg;
            errorModal.classList.add('active');
        } else if (errorMessage) {
            errorMessage.textContent = msg;
            errorMessage.style.display = 'block';
        }
    }

    function showRecoveryError(msg) {
        if (recoveryErrorMessage) {
            recoveryErrorMessage.textContent = msg;
            recoveryErrorMessage.style.display = 'block';
        }
    }
});
