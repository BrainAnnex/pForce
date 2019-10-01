<?php

use pforce\siteAuth;
use pforce\dbasePDO;
use pforce\logging;


ini_set('display_errors', 1);								// This ensures error-reporting display regardless of PHP configuration, which might have disabled it
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);	// Most verbose option


require_once("../../pForce/siteAuth/siteAuth.php" );		// ADJUST PATH AS NEEDED


$db = new dbasePDO();
$db->dbaseServer = "PROVIDE_YOUR_OWN";					// Database server (e.g. "localhost" or "mysql.mysite.com")
$db->dbase = "PROVIDE_YOUR_OWN";						// Name of default database schema
$db->dbUser = "PROVIDE_YOUR_OWN";						// Database username
$db->pass = "PROVIDE_YOUR_OWN";							// Password for the given database user


$messageLogger = new logging("myLogFile.txt");		// Instantiate a "logging" object

$siteAuthObj = new siteAuth($db, $messageLogger);

$userAccessID = $siteAuthObj->userLoginStatus();


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

<h1>Test of siteAuth</h1>


<?php


echo "Name of database table used by this module: `<b>" . $siteAuthObj->loginTable . "</b>`<br><br>";

if ($userAccessID)
	echo "You ARE logged in: userAccessID = $userAccessID and username: " . $siteAuthObj->userLoginStatusUnverified();
else
	echo "You are NOT logged in";
	
?>


</body>

</html>