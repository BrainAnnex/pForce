<?php

ini_set('display_errors', 1);								// This ensures error-reporting display regardless of PHP configuration, which might have disabled it
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);	// Most verbose option


require_once("../../pForce/cookies/cookies.php" );			// ADJUST PATH AS NEEDED

use pforce\cookies;


//cookies::requireHTTPS();

$status1 = cookies::setSessionOnly("test_sess_only", "test_session_only");
	
$status2 = cookies::setDays("test_sess_days", 		"test_3_days", 3);
	
$status3 = cookies::setYears("test_sess_years", 	"test_2_years", 2);

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

<h1>Test of <i>"cookies"</i> class, part 1 (Setting test cookies)</h1>

<h2>Version of PHP: <?=\PHP_VERSION_ID?></h2>

<h2>Status of setting cookie "test_sess_only": <?=$status1?></h2>

<h2>Status of setting cookie "test_sess_days": <?=$status2?></h2>

<h2>Status of setting cookie "test_sess_years": <?=$status3?></h2>

<?
$secureConnection = (cookies::$httpsRequired) ? "YES" : "NO";

echo "HTTPS required? : " . $secureConnection . "<br>";


$HTTP_only = (cookies::$httponly) ? "YES" : "NO";

echo "HTTP only? : " . $HTTP_only . "<br>";

echo "Path : '" . cookies::$path . "'<br>";
?>


<h2>Now check the cookies with <a href='test2.php'>this other script</a> (or check them using your brower)</h2>


</body>

</html>