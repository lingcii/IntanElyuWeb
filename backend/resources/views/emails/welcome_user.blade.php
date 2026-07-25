<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Welcome to INTAN ELYU Tourism Management System</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* Reset */
        * { box-sizing: border-box; }
        body, html { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        a { text-decoration: none; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #eef2f7;
            color: #334155;
        }
        .email-wrapper { width: 100%; background-color: #eef2f7; padding: 40px 16px; }
        .email-card { background-color: #ffffff; max-width: 600px; margin: 0 auto; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.10); }

        /* HEADER */
        .header { background: linear-gradient(135deg, #0c4a6e 0%, #0369a1 50%, #0ea5e9 100%); padding: 48px 32px 40px; text-align: center; }
        .header-badge { display: inline-block; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3); border-radius: 50px; padding: 6px 18px; font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #bae6fd; margin-bottom: 16px; }
        .header-title { margin: 0 0 8px; font-size: 30px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px; line-height: 1.2; }
        .header-subtitle { margin: 0; font-size: 15px; color: #bae6fd; font-weight: 400; }

        /* CONTENT */
        .content { padding: 36px 36px 28px; }
        .greeting { font-size: 22px; font-weight: 700; color: #0f172a; margin: 0 0 12px; }
        .intro-text { font-size: 15px; line-height: 1.7; color: #475569; margin: 0 0 28px; }

        /* ACCOUNT DETAILS CARD */
        .details-card { background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border: 1px solid #bae6fd; border-radius: 14px; padding: 24px; margin: 0 0 28px; }
        .details-card-title { font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: #0369a1; margin: 0 0 16px; }
        .details-table { width: 100%; }
        .details-table td { padding: 8px 0; font-size: 14px; border-bottom: 1px solid #e0f2fe; vertical-align: top; }
        .details-table tr:last-child td { border-bottom: none; }
        .detail-label { color: #64748b; font-weight: 500; width: 40%; padding-right: 12px; }
        .detail-value { color: #0f172a; font-weight: 600; text-align: right; }
        .role-badge { display: inline-block; background: linear-gradient(135deg, #0369a1, #0ea5e9); color: #ffffff; border-radius: 6px; padding: 3px 10px; font-size: 12px; font-weight: 700; }

        /* PASSWORD CARD */
        .password-card { background: linear-gradient(135deg, #fefce8 0%, #fef9c3 100%); border: 1px solid #fde047; border-radius: 14px; padding: 20px 24px; margin: 0 0 28px; }
        .password-card-title { font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: #92400e; margin: 0 0 12px; }
        .password-display { font-family: 'Courier New', Courier, monospace; font-size: 22px; font-weight: 700; color: #1e3a5f; letter-spacing: 3px; background: #ffffff; border: 2px dashed #fde047; border-radius: 8px; padding: 12px 16px; text-align: center; margin: 0 0 12px; }
        .password-note { font-size: 12px; color: #78350f; margin: 0; line-height: 1.5; }

        /* LOGIN BUTTON */
        .btn-wrap { text-align: center; margin: 0 0 28px; }
        .btn-login { display: inline-block; background: linear-gradient(135deg, #0369a1 0%, #0ea5e9 100%); color: #ffffff !important; text-decoration: none; padding: 15px 40px; border-radius: 50px; font-size: 16px; font-weight: 700; letter-spacing: 0.3px; box-shadow: 0 4px 15px rgba(14,165,233,0.4); }
        .btn-login-fallback { display: block; font-size: 12px; color: #94a3b8; margin-top: 12px; word-break: break-all; }
        .btn-login-fallback a { color: #0369a1; }

        /* STEPS */
        .steps-title { font-size: 15px; font-weight: 700; color: #0f172a; margin: 0 0 14px; }
        .steps-list { margin: 0 0 28px; padding: 0; list-style: none; }
        .steps-list li { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 10px; font-size: 14px; line-height: 1.6; color: #475569; }
        .step-num { flex-shrink: 0; width: 24px; height: 24px; border-radius: 50%; background: linear-gradient(135deg, #0369a1, #0ea5e9); color: #fff; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; margin-top: 1px; }

        /* SECURITY NOTICE */
        .security-notice { background: #f8fafc; border-left: 4px solid #f59e0b; border-radius: 0 8px 8px 0; padding: 14px 18px; margin: 0 0 28px; font-size: 13px; line-height: 1.6; color: #475569; }
        .security-notice strong { color: #0f172a; }

        /* DIVIDER */
        .divider { border: none; border-top: 1px solid #e2e8f0; margin: 0 0 28px; }

        /* FOOTER */
        .footer { background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 28px 36px; text-align: center; }
        .footer p { font-size: 12px; line-height: 1.6; color: #94a3b8; margin: 0 0 6px; }
        .footer a { color: #0369a1; }
        .footer-brand { font-size: 14px; font-weight: 700; color: #64748b; margin-bottom: 4px !important; }

        @media only screen and (max-width: 480px) {
            .content { padding: 24px 20px 20px; }
            .header { padding: 36px 20px 30px; }
            .header-title { font-size: 24px; }
            .footer { padding: 20px; }
            .details-card, .password-card { padding: 16px; }
            .password-display { font-size: 18px; letter-spacing: 2px; }
        }
    </style>
</head>
<body>
<div class="email-wrapper">
    <table class="email-card" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation">

        {{-- ── HEADER ────────────────────────────────── --}}
        <tr>
            <td class="header">
                <div class="header-badge">Official Notice</div>
                <h1 class="header-title">🏝️ Welcome Aboard!</h1>
                <p class="header-subtitle">INTAN ELYU Tourism Management System</p>
            </td>
        </tr>

        {{-- ── BODY ─────────────────────────────────── --}}
        <tr>
            <td class="content">

                <p class="greeting">Hi {{ $userName }},</p>
                <p class="intro-text">
                    Congratulations! Your account on the <strong>INTAN ELYU Tourism Management System</strong>
                    has been successfully created by an administrator. You now have access to the system based
                    on the role assigned to you.
                </p>

                {{-- Account Details --}}
                <div class="details-card">
                    <div class="details-card-title">📋 &nbsp;Account Details</div>
                    <table class="details-table" cellpadding="0" cellspacing="0">
                        <tr>
                            <td class="detail-label">Full Name</td>
                            <td class="detail-value">{{ $userName }}</td>
                        </tr>
                        <tr>
                            <td class="detail-label">Email Address</td>
                            <td class="detail-value">{{ $userEmail }}</td>
                        </tr>
                        <tr>
                            <td class="detail-label">Assigned Role</td>
                            <td class="detail-value">
                                <span class="role-badge">{{ $userRole }}</span>
                            </td>
                        </tr>
                        @if(!empty($municipalName))
                        <tr>
                            <td class="detail-label">Municipality</td>
                            <td class="detail-value">{{ $municipalName }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="detail-label">Account Created</td>
                            <td class="detail-value">{{ $registeredAt }}</td>
                        </tr>
                    </table>
                </div>

                {{-- Temporary Password --}}
                <div class="password-card">
                    <div class="password-card-title">🔑 &nbsp;Your Login Password</div>
                    <div class="password-display">{{ $plainPassword }}</div>
                    <p class="password-note">
                        ⚠️ This is your assigned password. Please use it to log in for the first time,
                        then immediately change it to a strong personal password for security.
                    </p>
                </div>

                {{-- Login Button --}}
                <div class="btn-wrap">
                    <a href="{{ $loginUrl }}" class="btn-login" target="_blank" rel="noopener noreferrer">
                        Login to Your Account &rarr;
                    </a>
                    <span class="btn-login-fallback">
                        Or copy this link: <a href="{{ $loginUrl }}">{{ $loginUrl }}</a>
                    </span>
                </div>

                {{-- Getting Started Steps --}}
                <p class="steps-title">🚀 Getting Started</p>
                <ul class="steps-list">
                    <li>
                        <span class="step-num">1</span>
                        <span>Click the <strong>Login to Your Account</strong> button above or visit the login page.</span>
                    </li>
                    <li>
                        <span class="step-num">2</span>
                        <span>Enter your email address (<strong>{{ $userEmail }}</strong>) and the password shown above.</span>
                    </li>
                    <li>
                        <span class="step-num">3</span>
                        <span>Navigate to <strong>Profile → Change Password</strong> and set a strong personal password.</span>
                    </li>
                    <li>
                        <span class="step-num">4</span>
                        <span>Explore the system and begin managing tourism data for your area.</span>
                    </li>
                </ul>

                {{-- Security Notice --}}
                <div class="security-notice">
                    <strong>🔒 Security Reminder:</strong> Never share your password with anyone. The INTAN ELYU Tourism Management System will never ask for your password via email or phone. If you did not expect this email, please contact your system administrator immediately.
                </div>

                <hr class="divider">

                <p style="font-size:14px; color:#64748b; margin:0;">
                    If you need assistance or have questions about your account, please reach out to your system administrator.
                </p>

            </td>
        </tr>

        {{-- ── FOOTER ───────────────────────────────── --}}
        <tr>
            <td class="footer">
                <p class="footer-brand">🏝️ INTAN ELYU Tourism Management System</p>
                <p>La Union Provincial Tourism Office &mdash; Official System</p>
                <p>This is an automated email. Please do not reply to this message.</p>
                <p>&copy; {{ date('Y') }} INTAN ELYU Tourism Management. All rights reserved.</p>
            </td>
        </tr>

    </table>
</div>
</body>
</html>
