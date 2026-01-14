<?php 
	
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
	
	$query = "SELECT * FROM emails WHERE ID='2'"; 
	//echo "$ID $action $time $vkey";
	if ($result = $mysqli->query($query)) { 
	//print_r($result);
		while ($row = $result->fetch_assoc()) {
			print_r($row);
		}
		$result->free();
	}
	
	mysqli_close ($mysqli);
?>