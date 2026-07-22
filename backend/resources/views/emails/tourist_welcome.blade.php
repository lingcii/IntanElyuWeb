<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Elyu!</title>
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
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            padding: 40px 20px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
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
        .card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
        }
        .card-title {
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 12px;
        }
        .card-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 15px;
        }
        .card-row:last-child {
            margin-bottom: 0;
        }
        .card-label {
            color: #64748b;
        }
        .card-value {
            font-weight: 600;
            color: #0f172a;
        }
        .btn {
            display: inline-block;
            background-color: #0284c7;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 16px;
            text-align: center;
            margin-top: 15px;
        }
        .footer {
            padding: 30px;
            text-align: center;
            font-size: 13px;
            color: #94a3b8;
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }
        .footer a {
            color: #0284c7;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <center class="wrapper">
        <table class="main-table">
            <tr>
                <td class="header">
                    <h1>🌊 Welcome to Elyu!</h1>
                </td>
            </tr>
            <tr>
                <td class="content">
                    <h2>Hi {{ $userName }},</h2>
                    <p>We are absolutely thrilled to welcome you to the <strong>{{ $appName }}</strong>! Your tourist account is now active and ready.</p>
                    <p>Explore beautiful destinations across La Union, log your visits, track your itineraries, solve fun puzzles, and redeem points for exciting discounts!</p>
                    
                    <div class="card">
                        <div class="card-title">Account Summary</div>
                        <table style="width: 100%;">
                            <tr>
                                <td style="padding: 4px 0; color: #64748b; font-size: 15px;">Email:</td>
                                <td style="padding: 4px 0; text-align: right; font-weight: 600; color: #0f172a; font-size: 15px;">{{ $userEmail }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 4px 0; color: #64748b; font-size: 15px;">Role:</td>
                                <td style="padding: 4px 0; text-align: right; font-weight: 600; color: #0f172a; font-size: 15px;">{{ $userRole }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 4px 0; color: #64748b; font-size: 15px;">Registered on:</td>
                                <td style="padding: 4px 0; text-align: right; font-weight: 600; color: #0f172a; font-size: 15px;">{{ $registeredAt }}</td>
                            </tr>
                        </table>
                    </div>

                    <p>Start your adventure today! Open the app and begin exploring Elyu's best kept secrets.</p>
                </td>
            </tr>
            <tr>
                <td class="footer">
                    <p>This is an automated email from {{ $appName }}.</p>
                    <p>&copy; {{ date('Y') }} La Union Tourism Management. All rights reserved.</p>
                </td>
            </tr>
        </table>
    </center>
</body>
</html>
