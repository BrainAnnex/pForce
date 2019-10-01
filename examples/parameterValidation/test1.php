<?php

use pforce\parameterValidation;


ini_set('display_errors', 1);								// This ensures error-reporting display regardless of PHP configuration, which might have disabled it
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);	// Most verbose option


require_once("../../pForce/parameterValidation/parameterValidation.php" );		// ADJUST PATH AS NEEDED



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

<h1>Test of parameterValidation</h1>


<?php

$x = "293a";
echo "&ldquo;" . $x . "&rdquo;";

if (parameterValidation::validateAlphanumeric($x))
	echo " is alphanumeric";
else
	echo " is NOT alphanumeric";



$x = "293a!";
echo "<br><br>&ldquo;" . $x . "&rdquo;";

if (parameterValidation::validateAlphanumeric($x))
	echo " is alphanumeric";
else
	echo " is NOT alphanumeric";
	
	

?>


</body>

</html>