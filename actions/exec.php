<?php
// actions/exec.php - Unified endpoint for remote actions (Snooze, Verify, Cancel)

require_once '../env_loader.php';
require_once '../src/Database.php';
require_once '../src/User.php';
require_once '../src/Utils.php';
require_once '../src/EmailStatus.php';
require_once '../src/AuditLog.php';

$db = Database::getInstance();
$auditLog = new AuditLog();

$action    = $_GET['a'] ?? '';
$vkey      = rawurldecode($_GET['vkey'] ?? '');
$ID        = intval($_GET['ID'] ?? 0);
$time      = strtolower($_GET['t'] ?? '');
$emailParam = $_GET['email'] ?? '';

$domain = htmlspecialchars($_ENV['MAIL_DOMAIN'] ?? 'Snoozer');

/**
 * Render a unified branded response page.
 *
 * @param string $title    Browser tab title
 * @param string $header   Main heading shown on card
 * @param string $subject  Email subject (displayed as context)
 * @param string $message  Body message (HTML allowed for <strong> etc.)
 * @param string $type     'success' | 'cancel' | 'error'
 */
function renderResponse($title, $header, $subject, $message, $type = 'success')
{
    global $domain;

    switch ($type) {
        case 'cancel':
            $iconBg    = '#fff5f5';
            $iconColor = '#e74c3c';
            $iconBorder = '#fcc';
            $icon      = '&#10005;';
            break;
        case 'error':
            $iconBg    = '#fff8f0';
            $iconColor = '#e67e22';
            $iconBorder = '#fddcb0';
            $icon      = '&#9888;';
            break;
        default: // success
            $iconBg    = '#f7f0fd';
            $iconColor = '#7d3c98';
            $iconBorder = '#d7b8f0';
            $icon      = '&#10003;';
    }

    $safeTitle   = htmlspecialchars($title);
    $safeHeader  = htmlspecialchars($header);
    $safeSubject = htmlspecialchars($subject);

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$safeTitle} — {$domain}</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
      background: #f0f0f4;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .card {
      background: #fff;
      border-radius: 20px;
      box-shadow: 0 6px 32px rgba(0,0,0,0.10);
      max-width: 460px;
      width: 100%;
      overflow: hidden;
    }
    .card-head {
      background: linear-gradient(135deg, #7d3c98 0%, #a855c8 100%);
      padding: 26px 32px;
      text-align: center;
    }
    .card-head .brand {
      font-size: 20px;
      font-weight: 800;
      color: #fff;
      letter-spacing: 4px;
    }
    .card-head .tagline {
      font-size: 10px;
      color: rgba(255,255,255,0.65);
      letter-spacing: 1px;
      margin-top: 4px;
    }
    .card-body {
      padding: 36px 32px 28px;
      text-align: center;
    }
    .icon-wrap {
      width: 66px;
      height: 66px;
      border-radius: 50%;
      background: {$iconBg};
      border: 2px solid {$iconBorder};
      margin: 0 auto 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 30px;
      color: {$iconColor};
    }
    .card-body h2 {
      font-size: 19px;
      font-weight: 700;
      color: #1a1a1a;
      margin-bottom: 10px;
    }
    .subject-line {
      font-size: 12px;
      color: #999;
      margin-bottom: 18px;
    }
    .subject-line strong { color: #666; }
    .message {
      font-size: 14px;
      color: #555;
      line-height: 1.7;
    }
    .message strong { color: #222; }
    .card-foot {
      padding: 16px 32px;
      border-top: 1px solid #f0f0f0;
      text-align: center;
    }
    .card-foot a {
      font-size: 12px;
      color: #bbb;
      text-decoration: none;
      transition: color 0.2s;
    }
    .card-foot a:hover { color: #7d3c98; }
  </style>
</head>
<body>
  <div class="card">
    <div class="card-head">
      <div class="brand">{$domain}</div>
      <div class="tagline">reach &amp; maintain a zero inbox</div>
    </div>
    <div class="card-body">
      <div class="icon-wrap">{$icon}</div>
      <h2>{$safeHeader}</h2>
      <p class="subject-line"><strong>Subject:</strong> {$safeSubject}</p>
      <p class="message">{$message}</p>
    </div>
    <div class="card-foot">
      <a href="javascript:window.close()">Close this window</a>
    </div>
  </div>
</body>
</html>
HTML;
}

// ── Sanitise a raw subject from the DB ──────────────────────────────────────
function cleanSubject($raw)
{
    if (function_exists('mb_decode_mimeheader')) {
        $raw = mb_decode_mimeheader($raw);
    }
    $raw = trim(preg_replace('/[\r\n]+\s*/', ' ', $raw));
    return $raw !== '' ? $raw : '(no subject)';
}

// ── Snooze ───────────────────────────────────────────────────────────────────
if ($action === 's') {
    $stmt  = $db->query("SELECT * FROM emails WHERE ID = ?", [$ID]);
    $res   = $stmt->get_result();
    $email = $res->fetch_assoc();

    if (!$email) {
        renderResponse("Not Found", "Reminder not found", '', "We couldn't find that reminder. It may have already been cancelled.", 'error');
        exit;
    }

    $decrypted = Utils::dataDecrypt($vkey, $email['sslkey']);
    if ($decrypted !== $email['message_id']) {
        renderResponse("Error", "Verification failed", '', "The security token is invalid. Please use the link from your original reminder email.", 'error');
        exit;
    }

    // Apply user timezone
    $userObj = new User();
    $owner   = $userObj->findByEmail($email['fromaddress']);
    if ($owner && !empty($owner['timezone'])) {
        date_default_timezone_set($owner['timezone']);
    }

    $subject = cleanSubject($email['subject']);

    if ($time === 'today.midnight') {
        // Release now: fire the existing reminder immediately
        $actiontimestamp = time();
        $db->query(
            "UPDATE emails SET processed = ?, actiontimestamp = ? WHERE id = ?",
            [EmailStatus::PROCESSED, $actiontimestamp, $ID],
            'iii'
        );
        $msg = "Your email has been <strong>released</strong> and will arrive shortly.";
        renderResponse("Released", "Email Released", $subject, $msg);
    } else {
        // Snooze: create a brand-new reminder row; leave the original as REMINDED
        $actiontimestamp = Utils::parseTimeExpression($time);
        $formattedDate   = date('l, F j Y \a\t g:i A', $actiontimestamp);

        $newMessageId = 'snz_' . bin2hex(random_bytes(16)) . '@snoozer';
        $newSslKey    = random_bytes(32);
        $mailDomain   = Utils::getMailDomain();

        // Copy notes if the column exists (added by migration 008)
        $notesVal = $email['notes'] ?? null;

        $db->query(
            "INSERT INTO emails
                (message_id, fromaddress, toaddress, header, subject, timestamp, sslkey, processed, actiontimestamp, notes)
             VALUES (?, ?, ?, '', ?, ?, ?, ?, ?, ?)",
            [$newMessageId, $email['fromaddress'], $time . '@' . $mailDomain,
             $email['subject'], time(), $newSslKey, EmailStatus::PROCESSED, $actiontimestamp, $notesVal],
            'sssssisiss'
        );

        $msg = "A new reminder has been created for <strong>$formattedDate</strong>.";
        renderResponse("Snoozed", "Reminder Snoozed", $subject, $msg);
    }

    $auditLog->log(
        AuditLog::REMINDER_SNOOZED,
        $owner['ID'] ?? null,
        $email['fromaddress'],
        $ID,
        'email',
        ['subject' => $subject, 'snoozed_to' => date('Y-m-d H:i', $actiontimestamp), 'ip' => Utils::getClientIp()]
    );

// ── Cancel ───────────────────────────────────────────────────────────────────
} elseif ($action === 'c') {
    $stmt  = $db->query("SELECT * FROM emails WHERE ID = ?", [$ID]);
    $res   = $stmt->get_result();
    $email = $res->fetch_assoc();

    if (!$email) {
        renderResponse("Not Found", "Reminder not found", '', "We couldn't find that reminder.", 'error');
        exit;
    }

    $decrypted = Utils::dataDecrypt($vkey, $email['sslkey']);
    if ($decrypted !== $email['message_id']) {
        renderResponse("Error", "Verification failed", '', "The security token is invalid.", 'error');
        exit;
    }

    $subject = cleanSubject($email['subject']);
    $db->query("UPDATE emails SET processed = ? WHERE id = ?", [EmailStatus::CANCELLED, $ID], 'ii');

    $userObj = new User();
    $actor   = $userObj->findByEmail($email['fromaddress']);
    $auditLog->log(
        AuditLog::REMINDER_CANCELLED,
        $actor['ID'] ?? null,
        $email['fromaddress'],
        $ID,
        'email',
        ['subject' => $subject, 'ip' => Utils::getClientIp()]
    );

    renderResponse("Cancelled", "Reminder Cancelled", $subject, "This reminder has been <strong>cancelled</strong> and will not fire again.", 'cancel');

// ── Email Verification ────────────────────────────────────────────────────────
} elseif ($action === 'v') {
    $userObj = new User();
    $user    = $userObj->findByEmail($emailParam);

    if (!$user) {
        renderResponse("Error", "User not found", '', "No account found for that email address.", 'error');
        exit;
    }

    $owneremail = Utils::dataDecrypt($vkey, $user['sslkey']);

    if ((int)($user['emailVerified'] ?? 0) === 1) {
        renderResponse("Already Verified", "Already Verified", htmlspecialchars($emailParam), "Your email address is already verified.");
    } elseif ($emailParam === $owneremail) {
        $db->query("UPDATE users SET emailVerified = 1 WHERE email = ?", [$emailParam]);
        renderResponse("Verified", "Email Verified", htmlspecialchars($emailParam), "Your email address has been <strong>verified</strong>. You're all set!");
    } else {
        renderResponse("Error", "Verification Failed", '', "The verification link is invalid or has expired.", 'error');
    }

// ── Unknown action ────────────────────────────────────────────────────────────
} else {
    renderResponse("Invalid", "Invalid Action", '', "This link is not valid.", 'error');
}
