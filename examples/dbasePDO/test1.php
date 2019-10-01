<?php

use pforce\dbasePDO;


ini_set('display_errors', 1);								// This ensures error-reporting display regardless of PHP configuration, which might have disabled it
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);	// Most verbose option


require_once("../../pForce/dbasePDO/dbasePDO.php" );		// ADJUST PATH AS NEEDED


$db = new dbasePDO();
$db->dbaseServer = "PROVIDE_YOUR_OWN";					// Database server (e.g. "localhost" or "mysql.mysite.com")
$db->dbase = "PROVIDE_YOUR_OWN";						// Name of default database schema
$db->dbUser = "PROVIDE_YOUR_OWN";						// Database username
$db->pass = "PROVIDE_YOUR_OWN";							// Password for the given database user


$db->dbLogFile = "myDbaseLog.htm";




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

<h1>Test of dbasePDO</h1>


<?php

$sql = "SELECT IP FROM `entry` WHERE `userAccessID` = ? LIMIT 1";	// Change as needed based on tables/fields/data you have!

$value = $db->selectSQLOneValue($sql, 1);

if ($value === false)
	echo "Unable to perform database lookup.  Reason: " . $db->errorSummary;
elseif ($value === null)
	echo "No record found";
else
	echo "Value lookup: <b>$value</b>";
	

	
echo "<hr>";

$sql = "SELECT IP FROM `Non_Existing_table` WHERE `userAccessID` = ? LIMIT 1";	// This will generate an error

$value = $db->selectSQLOneValue($sql, 1);

if ($value === false)
	echo "Unable to perform database lookup.  Reason: " . $db->errorSummary;
elseif ($value === null)
	echo "No record found";
else
	echo "Value lookup: <b>$value</b>";
	
	

echo "<hr>";

$sql = "SELECT IP FROM `entry` WHERE `userAccessID` = ? LIMIT 1";	// Change as needed based on tables/fields/data you have!

$value = $db->selectSQLOneValue($sql, 99999999);					// Here we're attempting to find a non-existing record

if ($value === false)
	echo "Unable to perform database lookup.  Reason: " . $db->errorSummary;
elseif ($value === null)
	echo "No record found";
else
	echo "Value lookup: <b>$value</b>";
	

?>

<h2>View the <a href="myDbaseLog.htm" target="_new">database log file</a></h2>

</body>

</html>