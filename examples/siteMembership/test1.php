<?php

use pforce\siteMembership;
use pforce\dbasePDO;


ini_set('display_errors', 1);								// This ensures error-reporting display regardless of PHP configuration, which might have disabled it
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);	// Most verbose option


require_once("../../pForce/siteMembership/siteMembership.php" );		// ADJUST PATH AS NEEDED


$db = new dbasePDO();
$db->dbaseServer = "PROVIDE_YOUR_OWN";					// Database server (e.g. "localhost" or "mysql.mysite.com")
$db->dbase = "PROVIDE_YOUR_OWN";						// Name of default database schema
$db->dbUser = "PROVIDE_YOUR_OWN";						// Database username
$db->pass = "PROVIDE_YOUR_OWN";							// Password for the given database user


$db->dbLogFile = "myDbaseLog.txt";


$siteMembershipObj = new siteMembership($db);



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

<h1>Test of siteMembership</h1>


<?php

$allUsers = $siteMembershipObj->listActiveUsers(1);

echo "<b>List of all users in site 1</b>:<br><br>";

foreach ($allUsers as $user)  {
	print_r($user);
	echo "<br><br>";
}
	
?>


</body>

</html>