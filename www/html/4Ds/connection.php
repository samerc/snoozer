<?php
	$servername = "localhost";
	$username = "remembrDBadmin";
	$password = "S0meWeirdRemembrDBadminPassw0rd";
	$dbname = "remembrco";
	
	// Create connection
	$mysqli = new mysqli($servername, $username, $password, $dbname);
	// Check connection
	if ($mysqli->connect_error) {
		die("Connection failed: " . $mysqli->connect_error);
	} 
?>