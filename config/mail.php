<?php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

define('MAIL_HOST', $_ENV['MAIL_HOST']);
define('MAIL_PORT', $_ENV['MAIL_PORT']);
define('MAIL_USERNAME', $_ENV['MAIL_USERNAME']);
define('MAIL_PASSWORD', $_ENV['MAIL_PASSWORD']);

function getMailer(): PHPMailer
{
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = MAIL_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = MAIL_USERNAME;
    $mail->Password = MAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = MAIL_PORT;

    $mail->setFrom('no-reply@metropolitancollege.lk', 'Metropolitan College');
    $mail->isHTML(true);
    $mail->CharSet = PHPMailer::CHARSET_UTF8;

    return $mail;
}

function sendWelcomeEmail(string $email, string $username, string $password,string $role): void
{
    try {
        $mail = getMailer();
        $mail->addAddress($email, $username);
        $mail->Subject = 'Welcome to Document Verification Platform';

        // ── HTML body ──────────────────────────────────────────────────
        $mail->Body = <<<HTML
            <!-- 1. WELCOME EMAIL -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Document Verification Platform</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            background: #f5f5f3;
            color: #1a1a1a;
            -webkit-font-smoothing: antialiased;
        }
        .wrapper {
            max-width: 580px;
            margin: 40px auto;
            padding: 0 16px 40px;
        }
        .header {
            text-align: center;
            padding: 32px 0 24px;
        }
        .header .brand {
            font-size: 13px;
            font-weight: 500;
            color: #888;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .card {
            background: #ffffff;
            border: 0.5px solid #e8e8e8;
            border-radius: 12px;
            overflow: hidden;
        }
        .card-top {
            background: #1a1a1a;
            padding: 32px;
            text-align: center;
        }
        .card-top h1 {
            font-size: 22px;
            font-weight: 500;
            color: #ffffff;
            margin-bottom: 6px;
        }
        .card-top p {
            font-size: 13px;
            color: #aaa;
        }
        .card-body {
            padding: 32px;
        }
        .greeting {
            font-size: 15px;
            font-weight: 500;
            color: #1a1a1a;
            margin-bottom: 12px;
        }
        .text {
            font-size: 14px;
            color: #555;
            line-height: 1.7;
            margin-bottom: 20px;
        }
        .info-box {
            background: #f5f5f3;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            border-bottom: 0.5px solid #efefef;
            font-size: 13px;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #aaa; }
        .info-value { font-weight: 500; color: #1a1a1a; }
        .btn {
            display: block;
            text-align: center;
            background: #1a1a1a;
            color: #ffffff;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            padding: 13px 24px;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        .divider {
            height: 0.5px;
            background: #f0f0f0;
            margin: 24px 0;
        }
        .note {
            font-size: 12px;
            color: #aaa;
            line-height: 1.6;
        }
        .footer {
            text-align: center;
            padding: 24px 0 0;
            font-size: 12px;
            color: #bbb;
            line-height: 1.7;
        }

        @media (max-width: 480px) {
            .wrapper { margin: 16px auto; }
            .card-top { padding: 24px 20px; }
            .card-body { padding: 24px 20px; }
            .card-top h1 { font-size: 18px; }
            .info-row { flex-direction: column; align-items: flex-start; gap: 2px; }
        }
    </style>
</head>
<body>
<div class="wrapper">

    <div class="header">
        <div class="brand">Document Verification Platform</div>
    </div>

    <div class="card">
        <div class="card-top">
            <h1>Welcome aboard</h1>
            <p>Your account has been created</p>
        </div>
        <div class="card-body">
            <div class="greeting">Hi $username,</div>
            <p class="text">
                Your account has been set up on the Document Verification Platform. 
                You can now log in and get started using the credentials below.
            </p>

            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">Username :&nbsp;</span>
                    <span class="info-value">$username</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email :&nbsp;</span>
                    <span class="info-value">$email</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Role :&nbsp;</span>
                    <span class="info-value">$role</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Password :&nbsp;</span>
                    <span class="info-value">$password</span>
                </div>
            </div>

            <a href="{{login_url}}" class="btn">Log in to your account</a>

            <div class="divider"></div>

            <p class="note">
                For security, please change your password after logging in for the first time. 
                If you did not expect this email, please contact your administrator.
            </p>
        </div>
    </div>

    <div class="footer">
        <p>Document Verification Platform</p>
        <p>Metropolitan College × GlobalNxt University</p>
    </div>

</div>
</body>
</html>
HTML;

        // ── Plain-text fallback ────────────────────────────────────────
        $mail->AltBody = <<<TEXT
            Welcome to the Document Verification Platform, {$username}!

            Your login credentials:
              Username : {$username}
              Password : {$password}

            Please change your password after your first login.

        TEXT;

        $mail->send();
        echo "Welcome email sent to {$email}";

    } catch (Exception $e) {
        error_log("Email sending failed: {$e->getMessage()}");
    }
}

function sendApprovedEmail(): void
{
}


function sendRejectedEmail(): void
{
}


function sendNewSubmissionEmail(): void
{
}


sendWelcomeEmail("eamalindu@gmail.com","DevMalindu","123456","Admin");