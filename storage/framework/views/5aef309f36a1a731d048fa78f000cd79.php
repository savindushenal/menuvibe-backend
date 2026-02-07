<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Password Has Been Reset</title>
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
        .password-highlight {
            background: #ecfdf5;
            border: 2px solid #10b981;
            border-radius: 8px;
            padding: 15px 20px;
            margin: 20px 0;
            text-align: center;
        }
        .password-highlight .label {
            color: #059669;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        .password-highlight .password {
            font-family: monospace;
            font-size: 20px;
            font-weight: bold;
            color: #047857;
            letter-spacing: 2px;
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
            background: #eff6ff;
            border: 1px solid #3b82f6;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
            color: #1e40af;
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
            font-size: 13px;
        }
        .center {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>🍽️ MenuVire</h1>
        </div>
        
        <h2>Your Password Has Been Reset</h2>
        
        <p>Hello <?php echo e($userName); ?>,</p>
        
        <p>Your password has been reset by an administrator. You can now log in with your new credentials:</p>
        
        <div class="credentials-box">
            <p><strong>Email:</strong> <span class="value"><?php echo e($userEmail); ?></span></p>
        </div>

        <div class="password-highlight">
            <div class="label">Your New Password</div>
            <div class="password"><?php echo e($password); ?></div>
        </div>
        
        <div class="warning">
            <strong>⚠️ Important:</strong> For security reasons, we recommend changing your password after logging in. Go to Settings → Security to update your password.
        </div>

        <div class="info">
            <strong>ℹ️ Note:</strong> All your previous sessions have been logged out for security. You'll need to log in again on all devices.
        </div>
        
        <div class="center">
            <a href="<?php echo e($loginUrl); ?>" class="button">Log In Now</a>
        </div>
        
        <p>If you did not expect this password reset, please contact our support team immediately.</p>
        
        <div class="footer">
            <p>&copy; <?php echo e(date('Y')); ?> MenuVire. All rights reserved.</p>
            <p>This is an automated message, please do not reply directly to this email.</p>
        </div>
    </div>
</body>
</html>
<?php /**PATH E:\githubNew\MenuVire-full\MenuVire-backend\resources\views/emails/password-reset-by-admin.blade.php ENDPATH**/ ?>