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

//todo: remove example.com domain from href and add real url

function sendWelcomeEmail(string $email, string $username, string $password, string $role): void
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

            <a href="https://example.com/" class="btn">Log in to your account</a>

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

    } catch (Exception $e) {
        error_log("Email sending failed: {$e->getMessage()}");
    }
}

function sendApprovedEmail(string $name,string $email,string $agentName,string $studentName, string $programme, string $date): void
{
    try {
        $mail = getMailer();
        $mail->addAddress($email, $name);
        $mail->Subject = 'Document approved';

        // ── HTML body ──────────────────────────────────────────────────
        $mail->Body = <<<HTML
            <!-- 3. DOCUMENT APPROVED (for staff) -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Approved</title>
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
            background: #1e6b3a;
            padding: 32px;
            text-align: center;
        }
        .card-top h1 {
            font-size: 22px;
            font-weight: 500;
            color: #ffffff;
            margin-bottom: 6px;
        }
        .card-top p { font-size: 13px; color: #a8d5b8; }
        .card-body { padding: 32px; }
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
        .status-pill {
            display: inline-block;
            font-size: 11px;
            font-weight: 500;
            padding: 3px 12px;
            border-radius: 100px;
            background: #d4edda;
            color: #1e6b3a;
            margin-bottom: 20px;
        }
        .info-box {
            background: #f5f5f3;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }
        .info-box-title {
            font-size: 11px;
            font-weight: 500;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 12px;
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
            background: #1e6b3a;
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
        .note { font-size: 12px; color: #aaa; line-height: 1.6; }
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
            <h1>Document approved</h1>
            <p>The submitted document has been verified</p>
        </div>
        <div class="card-body">
            <div class="greeting">Hi $name,</div>
            <p class="text">
                Great news — the document submitted for 
                <strong>$studentName</strong> has been reviewed and 
                approved by a GlobalNxt University agent.
            </p>

            <div class="status-pill">Approved</div>

            <div class="info-box">
                <div class="info-box-title">Document details</div>
                <div class="info-row">
                    <span class="info-label">Student name :&nbsp;</span>
                    <span class="info-value">$studentName</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Programme :&nbsp;</span>
                    <span class="info-value">$programme</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Reviewed by :&nbsp;</span>
                    <span class="info-value">$agentName</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Reviewed on :&nbsp;</span>
                    <span class="info-value">$date</span>
                </div>
            </div>

            <a href="https://example.com/" class="btn">View on dashboard</a>

            <div class="divider"></div>

            <p class="note">
                You can log in to the platform to download the approved document.
                This is an automated notification — do not reply to this email.
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
        $mail->send();

    } catch (Exception $e) {
        error_log("Email sending failed: {$e->getMessage()}");
    }
}


function sendRejectedEmail(string $name, string $email, string $agentName, string $studentName, string $programme, string $date, string $reason): void
{
    try {
        $mail = getMailer();
        $mail->addAddress($email, $name);
        $mail->Subject = 'Document rejected';

        // ── HTML body ──────────────────────────────────────────────────
        $mail->Body = <<<HTML
<!-- 4. DOCUMENT REJECTED (for staff) -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Rejected</title>
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
            background: #911f2a;
            padding: 32px;
            text-align: center;
        }
        .card-top h1 {
            font-size: 22px;
            font-weight: 500;
            color: #ffffff;
            margin-bottom: 6px;
        }
        .card-top p { font-size: 13px; color: #f5c6c8; }
        .card-body { padding: 32px; }
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
        .status-pill {
            display: inline-block;
            font-size: 11px;
            font-weight: 500;
            padding: 3px 12px;
            border-radius: 100px;
            background: #f8d7da;
            color: #911f2a;
            margin-bottom: 20px;
        }
        .info-box {
            background: #f5f5f3;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 16px;
        }
        .info-box-title {
            font-size: 11px;
            font-weight: 500;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 12px;
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
        .reason-box {
            background: #fdf0f0;
            border: 0.5px solid #f5c6c8;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }
        .reason-box-title {
            font-size: 11px;
            font-weight: 500;
            color: #911f2a;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 8px;
        }
        .reason-text {
            font-size: 13px;
            color: #911f2a;
            line-height: 1.6;
        }
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
        .note { font-size: 12px; color: #aaa; line-height: 1.6; }
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
            <h1>Document rejected</h1>
            <p>Action is required for this submission</p>
        </div>
        <div class="card-body">
            <div class="greeting">Hi {$name},</div>
            <p class="text">
                Unfortunately, the document submitted for 
                <strong>{$studentName}</strong> has been reviewed and 
                rejected by a GlobalNxt University agent. Please review 
                the reason below and re-upload a corrected document.
            </p>

            <div class="status-pill">Rejected</div>

            <div class="info-box">
                <div class="info-box-title">Document details</div>
                <div class="info-row">
                    <span class="info-label">Student name :&nbsp;</span>
                    <span class="info-value">{$studentName}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Programme :&nbsp;</span>
                    <span class="info-value">{$programme}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Reviewed by :&nbsp;</span>
                    <span class="info-value">{$agentName}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Reviewed on :&nbsp;</span>
                    <span class="info-value">{$date}</span>
                </div>
            </div>

            <div class="reason-box">
                <div class="reason-box-title">Reason for rejection</div>
                <div class="reason-text">{$reason}</div>
            </div>

            <a href="https://example.com/" class="btn">Re-upload document</a>

            <div class="divider"></div>

            <p class="note">
                Log in to the platform to re-upload a corrected document directly 
                from your dashboard. This is an automated notification — do not 
                reply to this email.
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
        $mail->send();
    }
    catch (Exception $e) {
        error_log("Email sending failed: {$e->getMessage()}");
    }

}


function sendNewSubmissionEmail(string $name, string $email, string $staffName, string $studentName, string $programme, string $date): void
{
    try {
        $mail = getMailer();
        $mail->addAddress($email, $name);
        $mail->Subject = 'New document submitted';

        // ── HTML body ──────────────────────────────────────────────────
        $mail->Body = <<<HTML
            <!-- 2. NEW DOCUMENT SUBMISSION (for agent) -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Document Submission</title>
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
        .card-top p { font-size: 13px; color: #aaa; }
        .card-body { padding: 32px; }
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
        .status-pill {
            display: inline-block;
            font-size: 11px;
            font-weight: 500;
            padding: 3px 12px;
            border-radius: 100px;
            background: #fef3cd;
            color: #a07000;
            margin-bottom: 20px;
        }
        .info-box {
            background: #f5f5f3;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }
        .info-box-title {
            font-size: 11px;
            font-weight: 500;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 12px;
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
        .note { font-size: 12px; color: #aaa; line-height: 1.6; }
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
            <h1>New document submitted</h1>
            <p>A document is awaiting your review</p>
        </div>
        <div class="card-body">
            <div class="greeting">Hi {$name},</div>
            <p class="text">
                A new student document has been submitted by 
                <strong>$staffName</strong> from Metropolitan College 
                and is pending your review.
            </p>

            <div class="status-pill">Pending review</div>

            <div class="info-box">
                <div class="info-box-title">Student information</div>
                <div class="info-row">
                    <span class="info-label">Student name :&nbsp;</span>
                    <span class="info-value">$studentName</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Programme :&nbsp;</span>
                    <span class="info-value">$programme</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Submitted by :&nbsp;</span>
                    <span class="info-value">$staffName</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Submitted on :&nbsp;</span>
                    <span class="info-value">$date</span>
                </div>
            </div>

            <a href="https://example.com/" class="btn">Review document</a>

            <div class="divider"></div>

            <p class="note">
                Please log in to the platform to preview and review the document. 
                This is an automated notification — do not reply to this email.
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
        $mail->send();

    } catch (Exception $e) {
        error_log("Email sending failed: {$e->getMessage()}");
    }

}