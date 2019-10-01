<?
/* 	For Brain Annex project, last revised 12/23/2018 


 *	See http://php.net/manual/en/function.password-hash.php


 * This code will benchmark your server to determine how high of a cost you can
 * afford. You want to set the highest cost that you can without slowing down
 * you server too much. 8-10 is a good baseline, and more is good if your servers
 * are fast enough. The code below aims for = 50 milliseconds stretching time,
 * which is a good baseline for systems handling interactive logins.
 
 */


$timeTarget = 50; // In milliseconds 

$cost = 7;

do {
	$cost += 1;
	
    $start = microtime(true);
	$algorithmOptions = array("cost" => $cost);
    password_hash("test", PASSWORD_BCRYPT, $algorithmOptions);
    $end = microtime(true);
	$timeTaken = ($end - $start) * 1000;
	echo "When cost = $cost , the time is $timeTaken msec<br>"; 
} 
while ($timeTaken < $timeTarget);

$bestCost = $cost - 1;

echo "Appropriate Cost Recommended: " . $bestCost;
?>