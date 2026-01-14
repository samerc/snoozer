<?php 
	//https://app.remembr.co/actions/snooze.php?ID=2&a=s&t=2Hours&vkey=eUN0T0FyNWZrWkFndm81VUtMUG1Fdz09Ojrpma5usxAqemprFeC0MBxa
	
	$servername = "localhost";
	$username = "snoozeradmin";
	//$password = "S0meWeirdRemembrDBadminPassw0rd";
	$password = "P@ssw0rd";
	$dbname = "snoozer";
	
	// Create connection
	$mysqli = new mysqli($servername, $username, $password, $dbname);
	// Check connection
	if ($mysqli->connect_error) {
		die("Connection failed: " . $mysqli->connect_error);
	} 


	$action = $_GET['a'];
	$vkey = rawurldecode($_GET['vkey']);
	
	function data_decrypt($data, $key) {
		// Remove the base64 encoding from our key
		//$encryption_key = base64_decode($key);
		$encryption_key = $key;
		// To decrypt, split the encrypted data from our IV - our unique separator used was "::"
		list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
		return openssl_decrypt($encrypted_data, 'aes-256-cbc', $encryption_key, 0, $iv);
	}
	
	if($action == "s"){ //snooze
		$ID = intval($_GET['ID']);
		$time = strtolower($_GET['t']);
		$query = "SELECT * FROM emails WHERE ID='$ID'"; 
		
		if ($result = $mysqli->query($query)) { 
			while ($row = $result->fetch_assoc()) {
				$sslkey = $row["sslkey"];
				$message_id = data_decrypt($vkey, $sslkey);
				//echo "$message_id  ". $row["message_id"];
				if ($message_id == $row["message_id"]){ //key verified (change it to contain action & tf too)
					//echo Verified;
					$subject = imap_utf8($row["subject"]);
					if($time == "today.midnight"){ //release was clicked
						$actiontimestamp = time();
						$outstr = "Email with subject <strong>\"". $subject ."\"</strong> is scheduled for release";
					} else {
						$actiontimestamp = strtotime($time);
						//---
						if($actiontimestamp < time()) { // is a valid date time but time is in the past
							//echo "time in the past " . $time;
							if (preg_match("/(?:sat|sun|mon|tue|wed|thu|fri)/", $time)){ //is weekday
								//echo "day";
								$actiontimestamp = strtotime('+1 week', $actiontimestamp);
							} else if(preg_match("/(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)/", $time)){ //is monthday
								//echo "month";
								$actiontimestamp = strtotime('+1 year', $actiontimestamp);
							} else if(!preg_match("/(?:min|hour|day|week|month)/", $time)){ 
								//echo "not min/hour/day/week/month/";
								$actiontimestamp = strtotime('+1 day', $actiontimestamp);
							}
						}
						//---
						date_default_timezone_set("Asia/Beirut");
						//$outstr = "Email with subject <strong>\"". $subject ."\"</strong> was snoozed until " . date('l dS \o\f F Y h:i:s A', $actiontimestamp);
						$outstr = '<html>
						<head>
						<title>Done | SnoozeR</title>
						<style>
						/*  SECTIONS  */
						.section {
							clear: both;
							padding: 0px;
							margin: 0px;
						}

						/*  COLUMN SETUP  */
						.col {
							display: block;
							float:left;
							margin: 1% 0 1% 1.6%;
						}
						.col:first-child { margin-left: 0; }

						/*  GROUPING  */
						.group:before,
						.group:after { content:""; display:table; }
						.group:after { clear:both;}
						.group { zoom:1; /* For IE 6/7 */ }
						/*  GRID OF THREE  */
						.span_3_of_3 { width: 100%; }
						.span_2_of_3 { width: 66.13%; }
						.span_1_of_3 { width: 40%; /* 32.26%; */}
						.span_pad	 { width: 10%;}

						/*  GO FULL WIDTH BELOW 480 PIXELS */
						@media only screen and (max-width: 480px) {
							.col {  margin: 1% 0 1% 0%; }
							.span_3_of_3, .span_2_of_3, .span_1_of_3 { width: 100%; }
						}
						</style>
						</head>
						<body>
						<div>
						<h1 style="text-align: center; margin-bottom:1px;"><span style="text-align: center; color: #7d3c98;"><strong>snoozer.cloud</strong></span></h1>
						<h4 style="text-align: center; margin-top:1px;"><span style="text-align: center; color: #7f7783; line-height: 80%;">reach &amp; maintain a zero inbox status</span></h4>
						</div>
						<div class="section group">
						<div class="col span_pad">
						</div>
						<div class="col span_1_of_3">
						<h2>Email snoozed, now remove it from your inbox ...</h2>
						<p><strong>Subject:&nbsp;</strong>'. $subject . '</p>
						<p>Next reminder scheduled for <strong>' . date('l dS \o\f F Y h:i:s A', $actiontimestamp) . '</strong>.</p>
						</div>
						<div class="col span_pad">
						</div>
						</div>										
						<hr style="border-top: dotted 1px;" />						
						<div class="section group">
						<div class="col span_pad">
						</div>
						<div class="col span_1_of_3">
						Congrats you are one step closer to a zero inbox ... | <a href="https://blog.remembr.co" target="_blank">Blog</a>
						</div>
						<div class="col span_pad">
						</div>
						</div>
						</body>
						</html>';
					}
				
					$query = "UPDATE emails SET processed='1', actiontimestamp='$actiontimestamp' WHERE id=$ID";
					$mysqli->query($query);
					
					echo $outstr;
					
					//echo " " . date('l dS \o\f F Y h:i:s A', time());
				} 
				//echo base64_encode($sslkey);
			}
		}
		$result->free();
	}
	
 	if($action == "v"){ //email verification
		//"https://app.remembr.co/actions/exec.php?a=$action&email=$email&vkey=$vkey";
		$email = $_GET['email'];
		//echo "email verification for $email";
		$query = "SELECT * FROM users WHERE email='$email'"; 
		if ($result = $mysqli->query($query)) {
			while ($row = $result->fetch_assoc()) {
				$sslkey = $row["sslkey"];
				$owneremail = data_decrypt($vkey, $sslkey);
				//echo $owneremail . " " . $vkey;
				if($row["emailVerified"] == 1){
					echo "Your email <strong>$email</strong> is already verified";
				} else if($email == $owneremail){
					echo "Thank you! Your email address <strong>$email</strong> is how verified";
					$query = "UPDATE users SET emailVerified='1' WHERE email='$email'";
					$mysqli->query($query);
				}
				
			}
		}
		$result->free();
	}
	
	if($action == "c"){ //Cancel reminder
		$ID = intval($_GET['ID']);
		$time = strtolower($_GET['t']);
		$query = "SELECT * FROM emails WHERE ID='$ID'"; 
		
		if ($result = $mysqli->query($query)) { 
			while ($row = $result->fetch_assoc()) {
				$sslkey = $row["sslkey"];
				$message_id = data_decrypt($vkey, $sslkey);
				//echo "$message_id  ". $row["message_id"];
				if ($message_id == $row["message_id"]){ //key verified (change it to contain action & tf too)
					echo Verified;
					$actiontimestamp = strtotime($time);
					
					$query = "UPDATE emails SET processed='2' WHERE id=$ID";
					$mysqli->query($query);
					$subject = imap_utf8($row["subject"]);
					echo "Your reminder for email with subject <strong>\"". $subject ."\"</strong> was Canceled";
					//echo " " . date('l dS \o\f F Y h:i:s A', time());
				} 
				//echo base64_encode($sslkey);
			}
		}
		$result->free();
	
	}
	mysqli_close ($mysqli);
?>