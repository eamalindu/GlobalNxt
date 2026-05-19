<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

define('MAIL_HOST',     $_ENV['MAIL_HOST']);
define('MAIL_PORT',     $_ENV['MAIL_PORT']);
define('MAIL_USERNAME', $_ENV['MAIL_USERNAME']);
define('MAIL_PASSWORD', $_ENV['MAIL_PASSWORD']);

function getMailer(): PHPMailer
{
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = MAIL_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_USERNAME;
    $mail->Password   = MAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = MAIL_PORT;

    $mail->setFrom('no-reply@metropolitancollege.lk', 'Metropolitan College');
    $mail->isHTML(true);
    $mail->CharSet = PHPMailer::CHARSET_UTF8;

    return $mail;
}

function sendWelcomeEmail(string $email, string $username, string $password): void
{
    try {
        $mail = getMailer();
        $mail->addAddress($email, $username);
        $mail->Subject = 'Welcome to Document Verification Platform';

        // ── HTML body ──────────────────────────────────────────────────
        $mail->Body = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
            </head>
            <body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,sans-serif;">
                <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4;padding:40px 0;">
                    <tr>
                        <td align="center">
                            <table width="600" cellpadding="0" cellspacing="0"
                                style="background-color:#ffffff;border-radius:8px;
                                       overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

                                <!-- Header -->
                                <tr>
                                    <td style="background-color:#1a3c6e;padding:32px 40px;text-align:center;">
                                        <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;
                                                   letter-spacing:0.5px;">
                                            Metropolitan College
                                        </h1>
                                        <p style="margin:6px 0 0;color:#a8c4e0;font-size:13px;">
                                            Document Verification Platform
                                        </p>
                                    </td>
                                </tr>

                                <!-- Body -->
                                <tr>
                                    <td style="padding:40px;">
                                        <p style="margin:0 0 16px;color:#333333;font-size:16px;">
                                            Dear <strong>{$username}</strong>,
                                        </p>
                                        <p style="margin:0 0 16px;color:#555555;font-size:15px;line-height:1.6;">
                                            Your account has been created successfully on the
                                            <strong>Document Verification Platform</strong>.
                                            Below are your login credentials:
                                        </p>

                                        <!-- Credentials box -->
                                        <table width="100%" cellpadding="0" cellspacing="0"
                                            style="background-color:#f0f4fa;border-left:4px solid #1a3c6e;
                                                   border-radius:4px;margin:24px 0;">
                                            <tr>
                                                <td style="padding:20px 24px;">
                                                    <p style="margin:0 0 8px;color:#555555;font-size:14px;">
                                                        <strong>Username:</strong>&nbsp;{$username}
                                                    </p>
                                                    <p style="margin:0;color:#555555;font-size:14px;">
                                                        <strong>Password:</strong>&nbsp;{$password}
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>

                                        <p style="margin:0 0 24px;color:#e53935;font-size:13px;">
                                            ⚠️ Please change your password immediately after your first login.
                                        </p>

                                        <p style="margin:0;color:#555555;font-size:15px;line-height:1.6;">
                                            If you have any questions, contact the IT department
                                        </p>
                                    </td>
                                </tr>

                                <!-- Footer -->
                                <tr>
                                    <td style="background-color:#f9f9f9;padding:20px 40px;
                                               border-top:1px solid #eeeeee;text-align:center;">
                                        <p style="margin:0;color:#999999;font-size:12px;">
                                            © <?= date('Y') ?> Metropolitan College &mdash;
                                            This is an automated message, please do not reply directly.
                                        </p>
                                    </td>
                                </tr>

                            </table>
                        </td>
                    </tr>
                </table>
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

function sendApprovedEmail():void{}


function sendRejectedEmail():void{}


function sendNewSubmissionEmail():void{}

