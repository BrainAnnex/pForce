<?php

use pforce\templateEvaluator;


ini_set('display_errors', 1);								// This ensures error-reporting display regardless of PHP configuration, which might have disabled it
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);	// Most verbose option


require_once("../../pForce/templateEvaluation/templateEvaluation.php" );		// ADJUST PATH AS NEEDED



$templ_eval = new templateEvaluator();

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

<h1>Test of templateEvaluator</h1>


<?php

// Using the default tags
$template = "Dear {{name}}, see you on <b>{{day}}</b>!";

echo "Template:<br><b>$template</b><br><br>";


$replaceArray = array("name" => "Valerie" , "day" => "Sat");

echo "Array of Replacements:<br>";

print_r($replaceArray);


$evaluatedTemplate = $templ_eval->evaluateTemplate($template, $replaceArray);

echo "<br><br>Evaluated Template:<br><b>$evaluatedTemplate</b>";
	
?>


</body>

</html>