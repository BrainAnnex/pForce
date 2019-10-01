<?php

use pforce\formBuilder;


ini_set('display_errors', 1);								// This ensures error-reporting display regardless of PHP configuration, which might have disabled it
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);	// Most verbose option


require_once("../../pForce/formBuilder/formBuilder.php" );		// ADJUST PATH AS NEEDED


?>
<!DOCTYPE html>
<html>
<head>
<style type="text/css">
	h1  {
		margin-bottom:30px; 
		color:brown; 
		border-bottom:1px solid gray;
		padding-bottom:3px;
	}
</style>
</head>

<body>

<h1>Test of formBuilder</h1>


<?php


$form = new formBuilder("Add New User to Current Account", "yourHandlerScript.php", "POST");

	$form->addControl_Text("Username", "username");
	$form->addControl_Text("Email", "email");
	$form->addControl_Password("Password", "pass");
	$form->addControl_Password("Re-enter", "pass2");
	
	$form->addFooter("Note: clioking on the button won't work because no handler script is defined");
		
echo $form->generateHTML("ADD NEW USER");
	
?>


</body>

</html>