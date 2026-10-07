<?php

require_once __DIR__ . '/../vendor/PHPMailer/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function getBulkMailer() {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host          = SMTP_HOST;
    $mail->SMTPAuth      = true;
    $mail->Username      = SMTP_USERNAME;
    $mail->Password      = SMTP_PASSWORD;
    $mail->SMTPSecure    = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port          = SMTP_PORT;
    $mail->CharSet       = 'UTF-8';
    $mail->SMTPDebug     = 0;
    $mail->SMTPKeepAlive = true;
    $mail->Timeout       = 30;
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->isHTML(true);
    return $mail;
}

function sendEmailWithMailer(PHPMailer $mail, $to_email, $to_name, $subject, $body_html, $qr_path = null) {
    try {
        $mail->clearAddresses();
        $mail->clearAttachments();
        $mail->clearAllRecipients();
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $to_name);
        if ($qr_file = qrFilePath($qr_path)) {
            $mail->addEmbeddedImage($qr_file, 'qr_code', 'qrcode.png');
        }
        $mail->Subject = $subject;
        $mail->Body    = $body_html;
        $mail->AltBody = strip_tags($body_html);
        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Email error: ' . $mail->ErrorInfo];
    }
}

function sendEmail($to_email, $to_name, $subject, $body_html, $qr_path = null) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->SMTPDebug  = 0;
        $mail->Timeout    = 30;
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $to_name);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        if ($qr_file = qrFilePath($qr_path)) {
            $mail->addEmbeddedImage($qr_file, 'qr_code', 'qrcode.png');
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body_html;
        $mail->AltBody = strip_tags($body_html);
        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Email error: ' . $mail->ErrorInfo];
    }
}

function getQREmailTemplate($attendee, $event) {
    $name           = htmlspecialchars($attendee['full_name']);
    $first_name     = htmlspecialchars(explode(' ', trim($attendee['full_name']))[0]);
    $company        = htmlspecialchars($attendee['company'] ?? '');
    $code           = htmlspecialchars($attendee['attendee_code']);
    $event_name     = htmlspecialchars($event['event_name']);
    $event_date     = formatDate($event['event_date']);
    $event_time     = formatTime($event['event_time']);
    $event_location = htmlspecialchars($event['location'] ?? 'TBA');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Your QR Code — {$event_name}</title>
</head>
<body style="margin:0;padding:0;background:#f5f1f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#2d1b3d;">

<!-- Preheader -->
<div style="display:none;font-size:1px;color:#f5f1f7;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
    You're all set for {$event_name}. Present your QR code at check-in.
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5f1f7;padding:16px 12px;">
<tr><td align="center">

<table role="presentation" width="400" cellpadding="0" cellspacing="0" border="0" style="max-width:400px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 8px 32px rgba(74,44,90,0.12);">

    <!-- HERO -->
    <tr><td style="background:linear-gradient(135deg,#4a2c5a 0%,#6b3d7a 50%,#d946ef 100%);padding:18px 22px 16px;text-align:center;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 10px;">
            <tr><td style="background:rgba(255,255,255,0.18);border:1.5px solid rgba(255,255,255,0.3);border-radius:999px;padding:4px 12px;">
                <span style="color:#ffffff;font-size:9px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;">Full Circle Events Asia</span
            </td></tr>
        </table>
        <h1 style="color:#ffffff;margin:0;font-size:18px;line-height:1.2;font-weight:800;letter-spacing:-0.4px;">
            You're confirmed, {$first_name}
        </h1>
        <p style="color:#f3d9f7;margin:6px 0 0;font-size:11px;line-height:1.5;font-weight:500;">
            Your spot for <strong style="color:#ffffff;">{$event_name}</strong> is reserved.
        </p>
    </td></tr>

    <!-- Accent stripe -->
    <tr><td style="height:4px;background:linear-gradient(90deg,#d946ef 0%,#a374b8 50%,#6b3d7a 100%);padding:0;line-height:0;font-size:0;">&nbsp;</td></tr>

    <!-- EVENT DETAILS -->
    <tr><td style="padding:16px 22px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:linear-gradient(135deg,#faf5fc 0%,#f3e8f7 100%);border-radius:10px;border-left:3px solid #d946ef;">
            <tr><td style="padding:12px 14px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td width="50%" valign="top" style="padding:0 6px 10px 0;">
                            <div style="color:#9b8aa3;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;line-height:1;margin-bottom:3px;">Date</div>
                            <div style="color:#4a2c5a;font-size:12px;font-weight:600;line-height:1.3;">{$event_date}</div>
                        </td>
                        <td width="50%" valign="top" style="padding:0 0 10px 6px;border-left:1px solid #ecd9f0;">
                            <div style="color:#9b8aa3;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;line-height:1;margin-bottom:3px;padding-left:6px;">Time</div>
                            <div style="color:#4a2c5a;font-size:12px;font-weight:600;line-height:1.3;padding-left:6px;">{$event_time}</div>
                        </td>
                    </tr>
                    <tr>
                        <td width="50%" valign="top" style="padding:10px 6px 0 0;border-top:1px solid #ecd9f0;">
                            <div style="color:#9b8aa3;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;line-height:1;margin-bottom:3px;">Venue</div>
                            <div style="color:#4a2c5a;font-size:12px;font-weight:600;line-height:1.3;">{$event_location}</div>
                        </td>
                        <td width="50%" valign="top" style="padding:10px 0 0 6px;border-top:1px solid #ecd9f0;border-left:1px solid #ecd9f0;">
                            <div style="color:#9b8aa3;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;line-height:1;margin-bottom:3px;padding-left:6px;">Company</div>
                            <div style="color:#4a2c5a;font-size:12px;font-weight:600;line-height:1.3;padding-left:6px;">{$company}</div>
                        </td>
                    </tr>
                </table>
            </td></tr>
        </table>
    </td></tr>

    <!-- QR CODE -->
    <tr><td style="padding:12px 22px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff;border:1.5px solid #f3d9f7;border-radius:12px;">
            <tr><td style="padding:14px;text-align:center;">
                <div style="color:#9b8aa3;font-size:9px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;margin-bottom:3px;">
                    Your Personal QR Code
                </div>
                <div style="color:#4a2c5a;font-size:11px;font-weight:500;margin-bottom:10px;">
                    Show this at the registration desk
                </div>

                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;">
                    <tr><td style="background:linear-gradient(135deg,#d946ef 0%,#6b3d7a 100%);padding:3px;border-radius:14px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                            <tr><td style="background:#ffffff;padding:12px;border-radius:11px;">
                                <img src="cid:qr_code" alt="QR Code" width="125" height="125" style="display:block;width:125px;height:125px;">
                            </td></tr>
                        </table>
                    </td></tr>
                </table>

                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:10px auto 0;">
                    <tr><td style="background:#faf5fc;border:1px solid #f3d9f7;border-radius:999px;padding:5px 14px;">
                        <span style="color:#9b8aa3;font-size:8px;font-weight:700;letter-spacing:1px;text-transform:uppercase;">Code:</span>
                        <span style="color:#4a2c5a;font-size:12px;font-weight:700;font-family:'SF Mono',Monaco,Consolas,monospace;margin-left:5px;">{$code}</span>
                    </td></tr>
                </table>
            </td></tr>
        </table>
    </td></tr>

    <!-- INSTRUCTIONS -->
    <tr><td style="padding:14px 22px 0;">
        <h3 style="color:#4a2c5a;margin:0 0 8px;font-size:12px;font-weight:700;">
            <span style="display:inline-block;width:4px;height:12px;background:linear-gradient(180deg,#d946ef,#6b3d7a);border-radius:2px;vertical-align:middle;margin-right:7px;"></span>
            What to do next
        </h3>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr><td style="padding:3px 0;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                    <td width="18" valign="top" style="padding-right:8px;">
                        <div style="width:16px;height:16px;background:linear-gradient(135deg,#d946ef,#6b3d7a);border-radius:50%;color:white;text-align:center;line-height:16px;font-size:9px;font-weight:700;">1</div>
                    </td>
                    <td style="color:#4a3d52;font-size:11px;line-height:1.5;"><strong style="color:#4a2c5a;">Save this email</strong> or screenshot the QR code.</td>
                </tr></table>
            </td></tr>
            <tr><td style="padding:3px 0;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                    <td width="18" valign="top" style="padding-right:8px;">
                        <div style="width:16px;height:16px;background:linear-gradient(135deg,#d946ef,#6b3d7a);border-radius:50%;color:white;text-align:center;line-height:16px;font-size:9px;font-weight:700;">2</div>
                    </td>
                    <td style="color:#4a3d52;font-size:11px;line-height:1.5;"><strong style="color:#4a2c5a;">Arrive on time</strong> and head to the registration desk.</td>
                </tr></table>
            </td></tr>
            <tr><td style="padding:3px 0;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                    <td width="18" valign="top" style="padding-right:8px;">
                        <div style="width:16px;height:16px;background:linear-gradient(135deg,#d946ef,#6b3d7a);border-radius:50%;color:white;text-align:center;line-height:16px;font-size:9px;font-weight:700;">3</div>
                    </td>
                    <td style="color:#4a3d52;font-size:11px;line-height:1.5;"><strong style="color:#4a2c5a;">Show your QR code</strong> for instant check-in.</td>
                </tr></table>
            </td></tr>
            <tr><td style="padding:3px 0;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                    <td width="18" valign="top" style="padding-right:8px;">
                        <div style="width:16px;height:16px;background:linear-gradient(135deg,#d946ef,#6b3d7a);border-radius:50%;color:white;text-align:center;line-height:16px;font-size:9px;font-weight:700;">4</div>
                    </td>
                    <td style="color:#4a3d52;font-size:11px;line-height:1.5;"><strong style="color:#4a2c5a;">Collect your badge</strong> and enjoy!</td>
                </tr></table>
            </td></tr>
        </table>
    </td></tr>

    <!-- TIPS -->
    <tr><td style="padding:12px 22px 18px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fef3c7;border-radius:8px;border-left:3px solid #d97706;">
            <tr><td style="padding:9px 12px;">
                <span style="color:#92400e;font-size:11px;line-height:1.5;"><strong>Tip:</strong> Save this email or take a screenshot so your QR is ready offline when you arrive.</span>
            </td></tr>
        </table>
    </td></tr>

    <!-- FOOTER -->
    <tr><td style="background:linear-gradient(180deg,#4a2c5a 0%,#2d1b3d 100%);padding:16px 22px;text-align:center;">
        <p style="color:#ffffff;font-size:12px;margin:0 0 3px;font-weight:600;">
            See you at the event!
        </p>
        <p style="color:#a374b8;font-size:10px;margin:0 0 10px;line-height:1.5;">
            Questions? Simply reply to this email and we'll help you out.
        </p>
        <div style="height:1px;background:rgba(255,255,255,0.1);margin:10px 0;line-height:0;font-size:0;">&nbsp;</div>
        <p style="color:#a374b8;font-size:9px;margin:0;line-height:1.5;">
            &copy; Full Circle Events Asia, Inc.<br>
            This is a transactional message for your registered event.
        </p>
    </td></tr>

</table>

</td></tr>
</table>

</body>
</html>
HTML;
}

function getReminderEmailTemplate($attendee, $event) {
    $first_name     = htmlspecialchars(explode(' ', trim($attendee['full_name']))[0]);
    $event_name     = htmlspecialchars($event['event_name']);
    $event_date     = formatDate($event['event_date']);
    $event_time     = formatTime($event['event_time']);
    $event_location = htmlspecialchars($event['location'] ?? 'TBA');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tomorrow: {$event_name}</title></head>
<body style="margin:0;padding:0;background:#f5f1f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 8px 32px rgba(74,44,90,0.12);">

    <tr><td style="background:linear-gradient(135deg,#d946ef 0%,#6b3d7a 100%);padding:40px 32px;text-align:center;">
        <h1 style="color:#fff;margin:0;font-size:26px;font-weight:800;letter-spacing:-0.5px;">See you tomorrow!</h1>
        <p style="color:#f3d9f7;margin:8px 0 0;font-size:14px;">Quick reminder about your event</p>
    </td></tr>

    <tr><td style="padding:36px 36px 24px;">
        <h2 style="color:#4a2c5a;margin:0 0 14px;font-size:22px;font-weight:700;">Hi {$first_name},</h2>
        <p style="color:#4a3d52;line-height:1.65;font-size:15px;margin:0 0 20px;">
            Just a friendly reminder that <strong style="color:#4a2c5a;">{$event_name}</strong> is happening tomorrow. We're looking forward to seeing you!
        </p>

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#faf5fc;border-radius:12px;border-left:4px solid #d946ef;margin:20px 0;">
            <tr><td style="padding:18px 22px;">
                <p style="margin:6px 0;color:#4a2c5a;font-size:14px;"><strong>Date:</strong> {$event_date}</p>
                <p style="margin:6px 0;color:#4a2c5a;font-size:14px;"><strong>Time:</strong> {$event_time}</p>
                <p style="margin:6px 0;color:#4a2c5a;font-size:14px;"><strong>Venue:</strong> {$event_location}</p>
            </td></tr>
        </table>

        <p style="color:#4a3d52;font-size:14px;line-height:1.6;margin-top:20px;">
            Don't forget to bring your <strong>QR code</strong> (from the previous email) for quick check-in. See you there!
        </p>
    </td></tr>

    <tr><td style="background:linear-gradient(180deg,#4a2c5a 0%,#2d1b3d 100%);padding:24px;text-align:center;">
        <p style="color:#a374b8;font-size:11px;margin:0;">&copy; Full Circle Events Asia, Inc.</p>
    </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

function getFeedbackEmailTemplate($attendee, $event, $link) {
    $first_name = htmlspecialchars(explode(' ', trim($attendee['full_name']))[0]);
    $event_name = htmlspecialchars($event['event_name']);
    $link       = htmlspecialchars($link);

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Feedback: {$event_name}</title></head>
<body style="margin:0;padding:0;background:#f5f1f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 8px 32px rgba(74,44,90,0.12);">

    <tr><td style="background:linear-gradient(135deg,#d946ef 0%,#6b3d7a 100%);padding:40px 32px;text-align:center;">
        <h1 style="color:#fff;margin:0;font-size:26px;font-weight:800;letter-spacing:-0.5px;">Thank you for coming!</h1>
        <p style="color:#f3d9f7;margin:8px 0 0;font-size:14px;">We'd love to hear what you think</p>
    </td></tr>

    <tr><td style="padding:36px 36px 24px;">
        <h2 style="color:#4a2c5a;margin:0 0 14px;font-size:22px;font-weight:700;">Hi {$first_name},</h2>
        <p style="color:#4a3d52;line-height:1.65;font-size:15px;margin:0 0 24px;">
            Thanks for attending <strong style="color:#4a2c5a;">{$event_name}</strong>. Could you take a minute to rate the event and its sessions? Your feedback helps us make the next one better.
        </p>
        <p style="text-align:center;margin:28px 0;">
            <a href="{$link}" style="display:inline-block;background:linear-gradient(135deg,#d946ef 0%,#6b3d7a 100%);color:#fff;text-decoration:none;font-weight:700;font-size:15px;padding:14px 32px;border-radius:12px;">Give Feedback</a>
        </p>
        <p style="color:#8a7d92;font-size:12px;line-height:1.6;margin:0;">Or open this link: {$link}</p>
    </td></tr>

    <tr><td style="background:linear-gradient(180deg,#4a2c5a 0%,#2d1b3d 100%);padding:24px;text-align:center;">
        <p style="color:#a374b8;font-size:11px;margin:0;">&copy; Full Circle Events Asia, Inc.</p>
    </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
}
