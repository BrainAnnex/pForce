<?php

ini_set('display_errors', 1);								// This ensures error-reporting display regardless of PHP configuration, which might have disabled it
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);	// Most verbose option


require_once("../../pForce/cookies/cookies.php" );			// ADJUST PATH AS NEEDED

use pforce\cookies;


$status1 = cookies::clear("test_sess_only");

$status2 = cookies::clear("test_sess_days");

$status3 = cookies::clear("test_sess_years");

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

<h1>Test of <i>"cookies"</i> class, part 3 (Clearing cookies)</h1>


<h2>Cleared cookie "test_sess_only"
<?
if ($status1)
	echo " successfully";
else
	echo " UN-successfully";
?>
</h2>


<h2 style="margin-top:15px">Cleared cookie "test_sess_days"
<?
if ($status2)
	echo " successfully";
else
	echo " UN-successfully";
?>
</h2>


<h2 style="margin-top:15px">Cleared cookie "test_sess_years"
<?
if ($status3)
	echo " successfully";
else
	echo " UN-successfully";
?>
</h2>


<h2><a href='test1.php'>Set</a> test cookies</h2>


</body>

</html>