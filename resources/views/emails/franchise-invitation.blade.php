<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You're Invited</title>
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
        .invite-card {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
            color: white;
        }
        .invite-card h3 {
            margin: 0 0 10px 0;
            font-size: 20px;
        }
        .invite-card p {
            margin: 5px 0;
            opacity: 0.9;
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
        .info {
            background: #e0f2fe;
            border: 1px solid #0ea5e9;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
            color: #0c4a6e;
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
        .button-secondary {
            background: #6366f1;
        }
        .button-secondary:hover {
            background: #4f46e5;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #9ca3af;
            font-size: 14px;
        }
        .role-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>🍽️ MenuVibe</h1>
        </div>
        
        <h2>You're Invited, {{ $inviteeName }}!</h2>
        
        <p><strong>{{ $invitedByName }}</strong> has invited you to join <strong>{{ $franchiseName }}</strong> on MenuVibe.</p>
        
        <div class="invite-card">
            <h3>{{ $franchiseName }}</h3>
            <p>Role: <span class="role-badge">{{ $role }}</span></p>
        </div>
        
        @if($tempPassword)
        <div class="credentials-box">
            <h4 style="margin-top: 0; color: #1e293b;">Your Login Credentials</h4>
            <p><strong>Email:</strong> <span class="value">{{ $email }}</span></p>
            <p><strong>Password:</strong> <span class="value">{{ $tempPassword }}</span></p>
        </div>
        
        <div class="warning">
            ⚠️ <strong>Important:</strong> Please change your password immediately after your first login for security purposes.
        </div>
        
        <p style="text-align: center;">
            <a href="{{ $loginUrl }}" class="button">Login to Your Account</a>
        </p>
        @else
        <div class="info">
            ℹ️ An account already exists with this email. Click below to accept the invitation and join the franchise.
        </div>
        
        <p style="text-align: center;">
            <a href="{{ $acceptUrl }}" class="button">Accept Invitation</a>
        </p>
        @endif
        
        @if($expiresAt)
        <p style="text-align: center; color: #64748b; font-size: 14px;">
            This invitation expires on {{ $expiresAt }}
        </p>
        @endif
        
        <p>If the button above doesn't work, copy and paste this URL into your browser:</p>
        <p style="color: #64748b; word-break: break-all; font-size: 14px;">
            {{ $tempPassword ? $loginUrl : $acceptUrl }}
        </p>
        
        <div class="footer">
            <p>This email was sent by {{ config('app.name') }}.</p>
            <p>If you didn't expect this email, you can safely ignore it.</p>
        </div>
    </div>
</body>
</html>
