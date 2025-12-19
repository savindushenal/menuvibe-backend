<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Account Credentials</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h1 {
            color: #10b981;
            font-size: 28px;
            margin: 0;
        }
        h2 {
            color: #1a1a1a;
            font-size: 24px;
            margin-bottom: 20px;
        }
        .credentials-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        .credentials-box p {
            margin: 8px 0;
        }
        .credentials-box strong {
            display: inline-block;
            width: 100px;
            color: #64748b;
        }
        .credentials-box .value {
            color: #1e293b;
            font-family: monospace;
            font-size: 14px;
        }
        .warning {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
            color: #92400e;
        }
        .button {
            display: inline-block;
            background: #10b981;
            color: white !important;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 8px;
            font-weight: 600;
            margin: 20px 0;
        }
        .button:hover {
            background: #059669;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #9ca3af;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>🍽️ MenuVire</h1>
        </div>
        
        <h2>Welcome, {{ $userName }}!</h2>
        
        <p>Your account has been created for <strong>{{ $franchiseName }}</strong>. You can now access the platform using the credentials below.</p>
        
        <div class="credentials-box">
            <p><strong>Email:</strong> <span class="value">{{ $userEmail }}</span></p>
            <p><strong>Password:</strong> <span class="value">{{ $password }}</span></p>
        </div>
        
        <div class="warning">
            ⚠️ <strong>Important:</strong> Please change your password immediately after your first login for security purposes.
        </div>
        
        <p style="text-align: center;">
            <a href="{{ $loginUrl }}" class="button">Login to Your Account</a>
        </p>
        
        <p>If the button above doesn't work, copy and paste this URL into your browser:</p>
        <p style="color: #64748b; word-break: break-all; font-size: 14px;">{{ $loginUrl }}</p>
        
        <div class="footer">
            <p>This email was sent by {{ config('app.name') }}.</p>
            <p>If you didn't expect this email, please contact support.</p>
        </div>
    </div>
</body>
</html>
