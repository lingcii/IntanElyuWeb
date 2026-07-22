<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f1f5f9;
            color: #334155;
            margin: 0;
            padding: 0;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table {
            border-spacing: 0;
            width: 100%;
        }
        td {
            padding: 0;
        }
        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #f1f5f9;
            padding-bottom: 40px;
        }
        .main-table {
            background-color: #ffffff;
            margin: 0 auto;
            width: 100%;
            max-width: 600px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -2px rgba(0,0,0,0.05);
            margin-top: 40px;
        }
        .header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            padding: 40px 20px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .content {
            padding: 40px 30px;
        }
        .content h2 {
            margin-top: 0;
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
        }
        .content p {
            font-size: 16px;
            line-height: 1.6;
            color: #475569;
        }
        .btn {
            display: inline-block;
            background-color: #0284c7;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 16px;
            text-align: center;
            margin: 25px 0;
        }
        .footer {
            padding: 30px;
            text-align: center;
            font-size: 13px;
            color: #94a3b8;
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <center class="wrapper">
        <table class="main-table">
            <tr>
                <td class="header">
                    <h1>🔐 Reset Your Password</h1>
                </td>
            </tr>
            <tr>
                <td class="content">
                    <h2>Hi {{ $userName }},</h2>
                    <p>We received a request to reset the password for your account linked with <strong>{{ $userEmail }}</strong>.</p>
                    <p>Click the button below to choose a new password. This reset link is valid for 60 minutes.</p>
                    
                    <center>
                        <a href="{{ $resetUrl }}" class="btn">Reset Password</a>
                    </center>

                    <p>If you did not request a password reset, you can safely ignore this email. Your password will remain unchanged.</p>
                </td>
            </tr>
            <tr>
                <td class="footer">
                    <p>If you're having trouble clicking the button, copy and paste the link below into your web browser:</p>
                    <p style="word-break: break-all;"><a href="{{ $resetUrl }}" style="color: #0284c7;">{{ $resetUrl }}</a></p>
                </td>
            </tr>
        </table>
    </center>
</body>
</html>
