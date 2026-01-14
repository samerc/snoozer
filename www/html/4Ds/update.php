
<?php
	require_once('connection.php');
	//$sourceID = $_POST['sourceID'];
	$destID =  $_POST['destID'];
	$itemID =  $_POST['itemID'];
	//print_r($itemID);
	//parse_str($_POST['sourceOrder'],$sourceOrder);
	//parse_str($_POST['destOrder'],$destOrder);
	//print_r($sourceOrder); // Only for print array
	//echo sizeof($sourceOrder);
	//var_dump($sourceOrder);
	switch ($sourceID) {
		case 'Delayed':
			$srcID = '1';
			break;
		case 'Delegated':
			$srcID = '2';
			break;
		case 'Doing':
			$srcID = '3';
			break;
		case 'Dusted':
			$srcID = '4';
			break;
	}
	
	switch ($destID) {
		case 'Delayed':
			$dstID = '1';
			break;
		case 'Delegated':
			$dstID = '2';
			break;
		case 'Doing':
			$dstID = '3';
			break;
		case 'Dusted':
			$dstID = '4';
			break;
	}
	
/* 	foreach ($sourceOrder['mail'] as $mailID) {
		$query = "UPDATE emails SET catID='$srcID' WHERE ID='$mailID'";
		//print_r($query);
		$mysqli->query($query);	
		//print_r($mail);
	}
	
	foreach ($destOrder['mail'] as $mailID) {
		$query = "UPDATE emails SET catID='$dstID' WHERE ID='$mailID'";
		//print_r($query);
		$mysqli->query($query);	
		//print_r($mail);
	} */
	$mailID = str_replace("mail_","",$itemID);
	if($dstID == '4') {
		$query = "UPDATE emails SET catID='$dstID', processed='2' WHERE ID='$mailID'";
	} else {
		$query = "UPDATE emails SET catID='$dstID', processed='1' WHERE ID='$mailID'";
	}
	
	//echo $query; 
	$mysqli->query($query);
	
?>
