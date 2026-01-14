<?php
// http://orangeloops.com/2016/07/how-to-create-a-kanban-board-with-jquery/ 
// http://jsfiddle.net/phpdeveloperrahul/Hu39L/
	require_once('connection.php');
	
	$query = "SELECT * FROM emails WHERE fromaddress = 'ak@ptgroup.eu' AND processed=1 AND actiontimestamp <> -1 AND (CatID = 0 OR CatID = 1) ORDER BY actiontimestamp"; // find Delayed emails
	
	
	if ($result = $mysqli->query($query)) { 
		$DelayedItems = mysqli_fetch_all($result,MYSQLI_ASSOC);
	}
	
	//$query = "SELECT * FROM emails WHERE fromaddress = 'ak@ptgroup.eu' AND processed=2 AND actiontimestamp <> -1 ORDER BY actiontimestamp DESC"; // find dusted emails 
	$query   = "SELECT * FROM emails WHERE fromaddress = 'ak@ptgroup.eu' AND processed=2 AND FROM_UNIXTIME(`actiontimestamp`) > date_sub(now(), interval 14 day)   ORDER BY actiontimestamp DESC";
	if ($result = $mysqli->query($query)) { 
		$DustedItems = mysqli_fetch_all($result,MYSQLI_ASSOC);
	}
	
	$query = "SELECT * FROM emails WHERE fromaddress = 'ak@ptgroup.eu' AND processed=1 AND actiontimestamp <> -1 AND CatID = 2 ORDER BY actiontimestamp"; // find Delegated
	
	if ($result = $mysqli->query($query)) { 
		$DelegatedItems = mysqli_fetch_all($result,MYSQLI_ASSOC);
	}
	
	$query = "SELECT * FROM emails WHERE fromaddress = 'ak@ptgroup.eu' AND processed=1 AND actiontimestamp <> -1 AND CatID = 3 ORDER BY actiontimestamp"; // find Doing
	
	if ($result = $mysqli->query($query)) { 
		$DoingItems = mysqli_fetch_all($result,MYSQLI_ASSOC);
	}
	
	$result->free();
		
?>

<!doctype html>
<html lang="en">
<head>
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
  <script src="https://code.jquery.com/jquery-1.10.2.js"></script>
  <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
  <link rel="stylesheet" href="https://jqueryui.com/resources/demos/style.css">
  <style>
  body {font-family:Arial;}
  h2 {margin:5px;}
  input[type=text] {margin:10px}
  input[type=button] {margin:10px}  

  .container {width: 20%; float:left;clear: right;margin:10px; border-radius: 5px;  max-height:95vh; overflow: auto;}
  .sortable { list-style-type: none; margin:0; padding:2px; min-height:30px; border-radius: 5px;}
  .sortable li { margin: 3px 3px 3px 3px; padding: 0.4em; padding-left: 1.5em; font-size: 1em; } <!-- height: 200px;}-->
  .sortable li span { position: absolute; margin-left: -1.3em; }
  
  .card{background-color:white;border-radius:3px;}
  </style>
  <script>
  $(function() {
	var oldList, newList, item;
	
    $( ".sortable" ).sortable({
      connectWith: ".connectedSortable",
      receive: function( event, ui ) {
      	//$(this).css({"background-color":"blue"});
		//alert("receive!");
        //$(this).addClass( "ui-state-highlight" );
      },
	  
	  start: function(event, ui) {
		item = ui.item;
        newList = oldList = ui.item.parent().parent();
		
	
      },
	  
	  update: function( event, ui ) {
           // do stuff
		   //alert("update!");
/* 		   var order1 = $('#ToDo').sortable('toArray').toString();
           var order2 = $('#Progress').sortable('toArray').toString();
		   var orderDelay 	  = $('#ToDo').sortable('serialize');
		   var orderDelegated = $('#Progress').sortable('serialize'); */
            
			//alert(data);
           //alert("Order 1:" + order1 + "\n Order 2:" + order2); //Just showing update
		   //alert($(this).id);
           //var order = $(this).sortable('serialize');
		   //alert order;
		   
		   //var orderList1, orderList2;
/* 		   var List1ID = '#ul' + oldList.attr('id');
		   alert(List1ID); 
		   var orderList1 = $(List1ID).sortable('serialize'); */

	  },
	  
	  change: function(event, ui) {  
		if(ui.sender) newList = ui.placeholder.parent().parent();
      },
	  
	  stop: function ( event, ui ){
           // do stuff
		   //alert("stop!");
			//alert("Moved " + item.attr('id') + " from " + oldList.attr('id') + " to " + newList.attr('id'));
			var List1ID = '#ul' + oldList.attr('id');
			var List2ID = '#ul' + newList.attr('id');

			var orderList1 = $(List1ID).sortable('serialize');
			var orderList2 = $(List2ID).sortable('serialize');
			//var orderList1, orderList2;
			//var orderList1 	  = $('#Delayed').sortable('serialize');
			// orderList1 = $(List1ID).sortable('serialize');

			/* switch(oldList.attr('id')){
				case 'Delayed':
					orderList1 = $('#Delayed').sortable('serialize');
					break;
				case 'Delegated':
					orderList1 = $('#Delegated').sortable('serialize');
					break;	
				case 'Delegated':
					orderList1 = $('#Doing').sortable('serialize');
					break;				
				case 'Delegated':
					orderList1 = $('#Dusted').sortable('serialize');
					break;									
			}
			
			switch(oldList.attr('id')){
				case 'Delayed':
					orderList2 = $('#Delayed').sortable('serialize');
					break;
				case 'Delegated':
					orderList2 = $('#Delegated').sortable('serialize');
					break;	
				case 'Delegated':
					orderList2 = $('#Doing').sortable('serialize');
					break;				
				case 'Delegated':
					orderList2 = $('#Dusted').sortable('serialize');
					break;									
			} */
		
			//alert( oldList.attr('id') + "&" + orderList1 + "&" + newList.attr('id') + "&"  + orderList2);
			$.ajax(
			{
				method: "POST",
				url: "update.php",
				data:{
					itemID : item.attr('id'),
					//sourceID: oldList.attr('id'),
					//sourceOrder: orderList1,
					destID: newList.attr('id')//,
					//destOrder: orderList2
					
					},
				
				success: function(output)
				{

					//alert(output);

				}
			});
			// $.post("update.php",{name: "Donald Duck"},function(output) { alert(output);});

	  }
    }).disableSelection(); 
	
	/*
    $('.add-button').click(function() {
    	var txtNewItem = $('#new_text').val();
    	$(this).closest('div.container').find('ul').append('<li class="card">'+txtNewItem+'</li>');
    });  */ 

	/*$("html").on("drop", function(event) {
		event.preventDefault();  
		event.stopPropagation();
		alert("Dropped!");
	});*/	
  });

  </script>      
</head>
<body>
</body>
<div>
<div id="Delayed" class="container" style="background-color:pink;">
<h2>Delayed</h2>
<ul id="ulDelayed" class="sortable connectedSortable"> 
	<?php
		//print_r($incomleteItems);
		foreach($DelayedItems as $item){
			$id = "mail_" . $item['ID'];
			$actiontimestamp = $item['actiontimestamp'];
			$subject = imap_utf8($item['subject']);?>
			<li class="card" id="<?php echo $id; ?>"> 
				<p><strong><?php echo $subject;?></strong> <hr /> <small><?php echo date('Y-m-d \@ H:i', $actiontimestamp) ?></small></p>
			</li>
			
	<?php	} ?>
<!--	
  <li class="card" id="Todo_1">CAS-04688-K0V4N9 - Vitas - Admin accounts are not displayed in Office 365 <hr /> <a href="https://blog.remembr.co" target="_blank">release</a></li>
  <li class="card" id="Todo_2">Activity A2</li>
  <li class="card" id="Todo_3">Activity A3</li>-->
</ul>
<!--
<div class="link-div">
	<input type="text" id="new_text" value=""/>
	<input type="button" name="btnAddNew" value="Add" class="add-button"/>
</div>
-->
</div>
<div id="Delegated" class="container" style="background-color:orange;">
<h2>Delegated</h2>
<ul id="ulDelegated" class="sortable connectedSortable" >
	<?php
		//print_r($incomleteItems);
		foreach($DelegatedItems as $item){
			$id = "mail_" . $item['ID'];
			$actiontimestamp = $item['actiontimestamp'];
			$subject = imap_utf8($item['subject']);?>
			<li class="card" id="<?php echo $id; ?>"> 
				<p><strong><?php echo $subject; ?></strong> <hr /> <small><?php echo date('Y-m-d \@ H:i', $actiontimestamp) ?></small></p>
			</li>
			
	<?php	} ?>
</ul>
</div>
<div id="Doing" class="container" style="background-color:yellow;">
<h2>Doing</h2>
<ul id="ulDoing" class="sortable connectedSortable" >
	<?php
		//print_r($incomleteItems);
		foreach($DoingItems as $item){
			$id = "mail_" . $item['ID'];
			$actiontimestamp = $item['actiontimestamp'];
			$subject = imap_utf8($item['subject']);?>
			<li class="card" id="<?php echo $id; ?>"> 
				<p><strong><?php echo $subject; ?></strong> <hr /> <small><?php echo date('Y-m-d \@ H:i', $actiontimestamp) ?></small></p>
			</li>
	<?php	} ?>
</ul>
</div>
<div id="Dusted" class="container" style="background-color:green;">
<h2>Dusted (last 14 days)</h2>
<ul id="ulDusted" class="sortable connectedSortable" >
	<?php
		//print_r($incomleteItems);
		foreach($DustedItems as $item){
			$id = "mail_" . $item['ID'];
			$subject = imap_utf8($item['subject']);
			$actiontimestamp = $item['actiontimestamp'];?>
			<li class="card" id="<?php echo $id; ?>"> 
				<p><strong><?php echo $subject; ?></strong> <hr /> <small><?php echo date('Y-m-d \@ H:i', $actiontimestamp) ?></small></p>
			</li>
			
	<?php	} ?>
</ul>
</div>
</div>
</html>