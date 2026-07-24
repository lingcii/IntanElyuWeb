// reset-password.js
// Handles reset password form validation, real-time strength checking, password matching, eye toggles, and async submission.

(function () {
    'use strict';

    // Get reset password form element
    const form = document.getElementById('resetPasswordForm');
    if (!form) return;

    // Form inputs and UI elements
    const newPw = document.getElementById('newPassword');
    const confirmPw = document.getElementById('confirmPassword');
    const strengthBar = document.getElementById('passwordStrengthBar');
    const strengthText = document.getElementById('passwordStrengthText');
    const matchIndicator = document.getElementById('passwordMatchIndicator');
    const errorEl = document.getElementById('resetErrorMessage');
    const successEl = document.getElementById('resetSuccessMessage');
    const submitBtn = document.getElementById('resetSubmitBtn');
    const submitLabel = document.getElementById('resetSubmitLabel');
    const submitSpinner = document.getElementById('resetSubmitSpinner');

    // Retrieve CSRF token from HTML meta tag
    function getCsrf() {
        return document.querySelector('meta[name="csrf-token"]').content;
    }

    // Toggle submit button loading state during form submission
    function setLoading(loading) {
        submitBtn.disabled = loading;
        submitLabel.style.display = loading ? 'none' : '';
        submitSpinner.style.display = loading ? 'inline-block' : '';
        submitBtn.innerHTML = loading
            ? '<i class="fas fa-circle-notch fa-spin"></i> Resetting...'
            : '<i class="fas fa-key"></i><span id="resetSubmitLabel">Reset Password</span>';
    }

    // Calculate password strength and update requirement indicators and strength bar
    function checkStrength(val) {
        const reqs = document.querySelectorAll('.reset-req');
        const checks = {
            length: val.length >= 8,
            uppercase: /[A-Z]/.test(val),
            lowercase: /[a-z]/.test(val),
            number: /[0-9]/.test(val),
        };

        reqs.forEach(req => {
            const key = req.dataset.req;
            const icon = req.querySelector('i');
            if (checks[key]) {
                icon.className = 'fas fa-check-circle';
                req.classList.add('met');
            } else {
                icon.className = 'far fa-circle';
                req.classList.remove('met');
            }
        });

        let score = Object.values(checks).filter(Boolean).length;
        let width = (score / 4) * 100;
        let color, label;

        switch (score) {
            case 0: color = '#E5E7EB'; label = ''; break;
            case 1: color = '#DC2626'; label = 'Weak'; break;
            case 2: color = '#F59E0B'; label = 'Medium'; break;
            case 3: color = '#2563EB'; label = 'Strong'; break;
            case 4: color = '#10B981'; label = 'Very Strong'; break;
        }

        strengthBar.style.width = width + '%';
        strengthBar.style.background = color;
        if (label) {
            strengthText.innerHTML = '<i class="fas fa-circle"></i> ' + label;
            strengthText.style.color = color;
        } else {
            strengthText.innerHTML = '<i class="fas fa-circle"></i> Enter a strong password';
            strengthText.style.color = '#9CA3AF';
        }

        return checks;
    }

    // Real-time password strength check on input
    newPw.addEventListener('input', function () {
        checkStrength(this.value);
        if (confirmPw.value) checkMatch();
    });

    // Real-time password confirmation match check on input
    confirmPw.addEventListener('input', function () {
        checkMatch();
    });

    // Check if new password and confirm password match
    function checkMatch() {
        if (!confirmPw.value) {
            matchIndicator.style.display = 'none';
            return;
        }
        matchIndicator.style.display = 'flex';
        if (newPw.value === confirmPw.value) {
            matchIndicator.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match';
            matchIndicator.style.color = '#10B981';
        } else {
            matchIndicator.innerHTML = '<i class="fas fa-times-circle"></i> Passwords do not match';
            matchIndicator.style.color = '#DC2626';
        }
    }

    // Toggle password visibility (show/hide password text)
    document.querySelectorAll('.reset-eye-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const input = document.getElementById(this.dataset.target);
            const icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'far fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'far fa-eye';
            }
        });
    });

    // Handle form submit with client-side validation and async POST request
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        errorEl.style.display = 'none';
        successEl.style.display = 'none';

        const password = newPw.value;
        const passwordConfirm = confirmPw.value;
        const token = form.querySelector('[name="token"]').value;

        if (!password || !passwordConfirm) {
            errorEl.textContent = 'Please fill in all fields.';
            errorEl.style.display = 'block';
            return;
        }

        const checks = checkStrength(password);
        if (!checks.length) {
            errorEl.textContent = 'Password must be at least 8 characters.';
            errorEl.style.display = 'block';
            return;
        }
        if (!checks.uppercase) {
            errorEl.textContent = 'Password must contain at least one uppercase letter.';
            errorEl.style.display = 'block';
            return;
        }
        if (!checks.lowercase) {
            errorEl.textContent = 'Password must contain at least one lowercase letter.';
            errorEl.style.display = 'block';
            return;
        }
        if (!checks.number) {
            errorEl.textContent = 'Password must contain at least one number.';
            errorEl.style.display = 'block';
            return;
        }
        if (password !== passwordConfirm) {
            errorEl.textContent = 'Passwords do not match.';
            errorEl.style.display = 'block';
            return;
        }

        setLoading(true);

        try {
            const resp = await fetch('api/reset-password-action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                },
                body: JSON.stringify({
                    token: token,
                    password: password,
                    password_confirmation: passwordConfirm,
                }),
            });
            const data = await resp.json();

            if (data.success) {
                successEl.textContent = data.message + ' Redirecting...';
                successEl.style.display = 'block';
                setTimeout(() => {
                    window.location.href = 'login.php?reset_success=1';
                }, 1500);
            } else {
                errorEl.textContent = data.message || 'An error occurred.';
                errorEl.style.display = 'block';
                setLoading(false);
            }
        } catch (err) {
            errorEl.textContent = 'Network error. Please check your connection and try again.';
            errorEl.style.display = 'block';
            setLoading(false);
        }
    });
})();
