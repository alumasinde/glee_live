<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background: #2563eb;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
            margin: -30px -30px 30px -30px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content {
            background: white;
            padding: 30px;
            border-radius: 8px;
        }
        .button {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: 600;
        }
        .button:hover {
            background: #1d4ed8;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 12px;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .link-text {
            word-break: break-all;
            color: #2563eb;
            font-size: 13px;
        }
        .greeting {
            font-size: 16px;
            margin-bottom: 15px;
        }
        .body-text {
            font-size: 14px;
            line-height: 1.8;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Password Reset</h1>
        </div>
        <div class="content">
            <p class="greeting">Hello,</p>

            <p class="body-text">
                We received a request to reset your password for your <strong><?= htmlspecialchars($appName) ?></strong> account.
            </p>

            <p class="body-text">Click the button below to reset your password:</p>

            <p style="text-align: center;">
                <a href="<?= htmlspecialchars($resetUrl) ?>" class="button">Reset Password</a>
            </p>

            <p class="body-text">Or copy and paste this link into your browser:</p>

            <p class="link-text"><?= htmlspecialchars($resetUrl) ?></p>

            <div class="warning">
                <strong>Important:</strong> This link will expire in 1 hour for your security.
            </div>

            <p class="body-text">
                If you did not request this password reset, please ignore this email. Your password will remain unchanged.
            </p>
        </div>
        <div class="footer">
            <p>This is an automated email from <?= htmlspecialchars($appName) ?>. Please do not reply.</p>
            <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($appName) ?>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
