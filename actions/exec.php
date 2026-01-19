<?php
// actions/exec.php - Unified endpoint for remote actions (Snooze, Verify, Cancel)

require_once '../env_loader.php';
require_once '../src/Database.php';
require_once '../src/User.php';
require_once '../src/Utils.php';
require_once '../src/EmailStatus.php';

$db = Database::getInstance();

$action = $_GET['a'] ?? '';
$vkey = rawurldecode($_GET['vkey'] ?? '');
$ID = intval($_GET['ID'] ?? 0);
$time = strtolower($_GET['t'] ?? '');
$emailParam = $_GET['email'] ?? '';

// Helper for HTML response (legacy-style styling kept for compatibility)
function renderResponse($title, $header, $subject, $message)
{
	echo '<html>
    <head>
    <title>' . htmlspecialchars($title) . ' | SnoozeR</title>
    <style>
    .section { clear: both; padding: 0px; margin: 0px; }
    .col { display: block; float:left; margin: 1% 0 1% 1.6%; }
    .col:first-child { margin-left: 0; }
    .group:before, .group:after { content:""; display:table; }
    .group:after { clear:both;}
    .group { zoom:1; }
    .span_3_of_3 { width: 100%; }
    .span_1_of_3 { width: 40%; }
    .span_pad { width: 10%;}
    @media only screen and (max-width: 480px) {
        .col { margin: 1% 0 1% 0%; }
        .span_3_of_3, .span_1_of_3 { width: 100%; }
    }
    </style>
    </head>
    <body>
    <div>
    <h1 style="text-align: center; margin-bottom:1px;"><span style="text-align: center; color: #7d3c98;"><strong>snoozer.cloud</strong></span></h1>
    <h4 style="text-align: center; margin-top:1px;"><span style="text-align: center; color: #7f7783; line-height: 80%;">reach &amp; maintain a zero inbox status</span></h4>
    </div>
    <div class="section group">
    <div class="col span_pad"></div>
    <div class="col span_1_of_3">
    <h2>' . htmlspecialchars($header) . '</h2>
    <p><strong>Subject: </strong>' . htmlspecialchars($subject) . '</p>
    <p>' . $message . '</p>
    </div>
    <div class="col span_pad"></div>
    </div>
    <hr style="border-top: dotted 1px;" />
    <div class="section group">
    <div class="col span_pad"></div>
    <div class="col span_1_of_3">
    Congrats you are one step closer to a zero inbox ... | <a href="https://blog.snoozer.cloud" target="_blank">Blog</a>
    </div>
    <div class="col span_pad"></div>
    </div>
    </body>
    </html>';
}

if ($action == "s") { // Snooze
	$stmt = $db->query("SELECT * FROM emails WHERE ID = ?", [$ID]);
	$res = $stmt->get_result();
	$email = $res->fetch_assoc();

	if ($email) {
		$sslkey = $email["sslkey"];
		$decrypted_message_id = Utils::dataDecrypt($vkey, $sslkey);

		if ($decrypted_message_id == $email["message_id"]) {
			// Apply User Timezone if possible
			$userObj = new User();
			$owner = $userObj->findByEmail($email['fromaddress']);
			if ($owner && !empty($owner['timezone'])) {
				date_default_timezone_set($owner['timezone']);
			}

			$subject = $email["subject"];
			if (function_exists('mb_decode_mimeheader')) {
				$subject = mb_decode_mimeheader($subject);
			}

			if ($time == "today.midnight") {
				$actiontimestamp = time();
				$msg = "Email is scheduled for release.";
				renderResponse("Done", "Email released", $subject, $msg);
			} else {
				// Use shared time parsing utility
				$actiontimestamp = Utils::parseTimeExpression($time);

				$formattedDate = date('l dS \o\f F Y h:i:s A', $actiontimestamp);
				$msg = "Next reminder scheduled for <strong>$formattedDate</strong>.";
				renderResponse("Done", "Email snoozed, now remove it from your inbox ...", $subject, $msg);
			}

			// Update database
			$status = EmailStatus::PROCESSED;
			$db->query("UPDATE emails SET processed='{$status}', actiontimestamp=? WHERE id=?", [$actiontimestamp, $ID]);
		} else {
			echo "Security verification failed.";
		}
	} else {
		echo "Email not found.";
	}
} else if ($action == "v") { // Email verification
	$userObj = new User();
	$user = $userObj->findByEmail($emailParam);

	if ($user) {
		$sslkey = $user["sslkey"];
		$owneremail = Utils::dataDecrypt($vkey, $sslkey);

		if ($user["emailVerified"] == 1) {
			echo "Your email <strong>" . htmlspecialchars($emailParam) . "</strong> is already verified";
		} else if ($emailParam == $owneremail) {
			$db->query("UPDATE users SET emailVerified='1' WHERE email=?", [$emailParam]);
			echo "Thank you! Your email address <strong>" . htmlspecialchars($emailParam) . "</strong> is now verified";
		} else {
			echo "Verification failed.";
		}
	} else {
		echo "User not found.";
	}
} else if ($action == "c") { // Cancel reminder
	$stmt = $db->query("SELECT * FROM emails WHERE ID = ?", [$ID]);
	$res = $stmt->get_result();
	$email = $res->fetch_assoc();

	if ($email) {
		$sslkey = $email["sslkey"];
		$decrypted_message_id = Utils::dataDecrypt($vkey, $sslkey);

		if ($decrypted_message_id == $email["message_id"]) {
			$status = EmailStatus::REMINDED;
			$db->query("UPDATE emails SET processed='{$status}' WHERE id=?", [$ID]);
			$subject = $email["subject"];
			if (function_exists('mb_decode_mimeheader')) {
				$subject = mb_decode_mimeheader($subject);
			}
			echo "Your reminder for email with subject <strong>\"" . htmlspecialchars($subject) . "\"</strong> was Canceled";
		} else {
			echo "Security verification failed.";
		}
	} else {
		echo "Email not found.";
	}
} else {
	echo "Invalid action.";
}
