<?php

use pforce\logging;


ini_set('display_errors', 1);								// This ensures error-reporting display regardless of PHP configuration, which might have disabled it
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);	// Most verbose option


require_once("../../pForce/logging/logging.php" );			// ADJUST PATH AS NEEDED



$messageLogger = new logging("myLogFile.htm");		// Instantiate a "logging" object


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

<h1>Test of logging</h1>


<?php

$messageLogger->logErrorMessage("This is a test error message");

$logFile = $messageLogger->logFile;
	
echo "Check the log file: <a href='$logFile' target='_new'>" . $logFile . "</a> for the presence of a test error message";

?>


</body>

</html>