<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Reset Your Password – INTAN ELYU</title>
    <style>
        /* ── Reset ───────────────────────────────────────────────────────── */
        * { box-sizing: border-box; }
        body, html { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        a { text-decoration: none; }

        /* ── Body ────────────────────────────────────────────────────────── */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #eef2f7;
            color: #334155;
        }

        /* ── Wrapper ─────────────────────────────────────────────────────── */
        .email-wrapper {
            width: 100%;
            background-color: #eef2f7;
            padding: 40px 16px;
        }

        /* ── Card ────────────────────────────────────────────────────────── */
        .email-card {
            background-color: #ffffff;
            max-width: 600px;
            margin: 0 auto;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.10);
        }

        /* ── HEADER — Dark Navy Blue gradient (matches login page) ────────── */
        .header {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 50%, #1d4ed8 100%);
            padding: 44px 32px 36px;
            text-align: center;
        }

        .header-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 50px;
            padding: 5px 16px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #fbbf24;
            margin-bottom: 14px;
        }

        .header h1 {
            margin: 0 0 6px;
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.3px;
            color: #ffffff;
        }

        .header-sub {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.70);
            margin: 0;
        }

        /* ── CONTENT ─────────────────────────────────────────────────────── */
        .content {
            padding: 36px 36px 28px;
        }

        .greeting {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 14px;
        }

        .content p {
            font-size: 15px;
            line-height: 1.7;
            color: #475569;
            margin: 0 0 16px;
        }

        /* ── INFO CARD ───────────────────────────────────────────────────── */
        .info-card {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 18px 20px;
            margin: 20px 0;
            font-size: 13px;
            color: #1e40af;
            line-height: 1.6;
        }

        .info-card strong {
            display: block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #1d4ed8;
            margin-bottom: 6px;
        }

        /* ── BUTTON — Dark Blue gradient (matches login page button) ──────── */
        .btn-wrap {
            text-align: center;
            padding: 8px 0 20px;
        }

        .btn-reset {
            display: inline-block;
            background: linear-gradient(90deg, #3b82f6 0%, #1d4ed8 50%, #1e3a8a 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 15px 44px;
            border-radius: 50px;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.3px;
            box-shadow: 0 4px 16px rgba(29, 78, 216, 0.40);
        }

        /* ── SECURITY NOTICE ─────────────────────────────────────────────── */
        .security-notice {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            border-radius: 0 8px 8px 0;
            padding: 12px 16px;
            margin: 20px 0 0;
            font-size: 13px;
            line-height: 1.6;
            color: #475569;
        }

        .security-notice strong { color: #0f172a; }

        /* ── DIVIDER ─────────────────────────────────────────────────────── */
        .divider {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 28px 0 0;
        }

        /* ── FOOTER ──────────────────────────────────────────────────────── */
        .footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 24px 36px;
            text-align: center;
        }

        .footer p {
            font-size: 12px;
            line-height: 1.6;
            color: #94a3b8;
            margin: 0 0 6px;
        }

        .footer-link-row {
            font-size: 12px;
            color: #94a3b8;
            word-break: break-all;
            margin: 8px 0 0 !important;
        }

        .footer-link-row a {
            color: #1d4ed8;
        }

        .footer-brand {
            font-size: 13px;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 4px !important;
        }

        /* ── RESPONSIVE ──────────────────────────────────────────────────── */
        @media only screen and (max-width: 480px) {
            .content { padding: 24px 20px 20px; }
            .header  { padding: 32px 20px 28px; }
            .footer  { padding: 20px; }
            .btn-reset { padding: 13px 32px; font-size: 14px; }
        }
    </style>
</head>
<body>
<div class="email-wrapper">
    <table class="email-card" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation">

        {{-- ── HEADER ──────────────────────────────────────────────────── --}}
        <tr>
            <td class="header">
                <div class="header-badge">Security Notice</div>
                <h1>🔐 Password Reset Request</h1>
                <p class="header-sub">INTAN ELYU Tourism Management System</p>
            </td>
        </tr>

        {{-- ── BODY ───────────────────────────────────────────────────── --}}
        <tr>
            <td class="content">

                <p class="greeting">Hi {{ $userName }},</p>

                <p>
                    We received a request to reset the password for your account associated with
                    <strong style="color:#0f172a;">{{ $userEmail }}</strong>.
                </p>

                <p>
                    Click the button below to set a new password. This reset link is valid for
                    <strong style="color:#1d4ed8;">60 minutes</strong> and can only be used once.
                </p>

                {{-- Info card --}}
                <div class="info-card">
                    <strong>⏱ &nbsp;Link expires in 60 minutes</strong>
                    For your security, this link will become invalid after the time limit or after it has been used.
                </div>

                {{-- Reset Button --}}
                <div class="btn-wrap">
                    <a href="{{ $resetUrl }}" class="btn-reset" target="_blank" rel="noopener noreferrer">
                        Reset My Password &rarr;
                    </a>
                </div>

                {{-- Security notice --}}
                <div class="security-notice">
                    <strong>🔒 Didn't request this?</strong>
                    If you did not request a password reset, you can safely ignore this email — your password will remain unchanged. If you think your account is at risk, please contact your system administrator.
                </div>

                <hr class="divider">

            </td>
        </tr>

        {{-- ── FOOTER ──────────────────────────────────────────────────── --}}
        <tr>
            <td class="footer">
                <p class="footer-brand">🏝️ INTAN ELYU Tourism Management System</p>
                <p>La Union Provincial Tourism Office &mdash; Official System</p>
                <p>If the button above doesn't work, copy and paste this link into your browser:</p>
                <p class="footer-link-row">
                    <a href="{{ $resetUrl }}">{{ $resetUrl }}</a>
                </p>
                <p style="margin-top:12px !important;">&copy; {{ date('Y') }} INTAN ELYU Tourism Management. All rights reserved.</p>
            </td>
        </tr>

    </table>
</div>
</body>
</html>
