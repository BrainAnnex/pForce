<?php

ini_set('display_errors', 1);								// This ensures error-reporting display regardless of PHP configuration, which might have disabled it
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);	// Most verbose option


require_once("../../pForce/cookies/cookies.php" );			// ADJUST PATH AS NEEDED

use pforce\cookies;


$isSet1 = cookies::isCookieSet("test_sess_only");
$value1 = cookies::get("test_sess_only");

$isSet2 = cookies::isCookieSet("test_sess_days");
$value2 = cookies::get("test_sess_days");

$isSet3 = cookies::isCookieSet("test_sess_years");
$value3 = cookies::get("test_sess_years");

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

<h1>Test of <i>"cookies"</i> class, part 2 (Reading cookies)</h1>


<br><br>Cookie "test_sess_only" is <?= $isSet1 ? "" : "NOT" ?> set
<?
if ($isSet1)
	echo ". Its value is: '$value1'";
?>

<br><br>Cookie "test_sess_days" is <?= $isSet2 ? "" : "NOT" ?> set
<?
if ($isSet2)
	echo ". Its value is: '$value2'";
?>

<br><br>Cookie "test_sess_years" is <?= $isSet3 ? "" : "NOT" ?> set
<?
if ($isSet3)
	echo ". Its value is: '$value3'";
?>


<h2 style='margin-top:50px'>Now <a href='test3.php'>Clear</a> the cookies</h2>


</body>

</html>