<?php

use pforce\directedGraph;
use pforce\dbasePDO;


ini_set('display_errors', 1);								// This ensures error-reporting display regardless of PHP configuration, which might have disabled it
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);	// Most verbose option


require_once("../../pForce/directedGraphs/directedGraphs.php" );		// ADJUST PATH AS NEEDED


$db = new dbasePDO();
$db->dbaseServer = "PROVIDE_YOUR_OWN";					// Database server (e.g. "localhost" or "mysql.mysite.com")
$db->dbase = "PROVIDE_YOUR_OWN";						// Name of default database schema
$db->dbUser = "PROVIDE_YOUR_OWN";						// Database username
$db->pass = "PROVIDE_YOUR_OWN";							// Password for the given database user

$db->dbLogFile = "myDbaseLog.txt";


$graph = new directedGraph($db, "testNodes", "testEdges", "testCategoryListing");


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

<h1>Test of directedGraphs</h1>


<?php

// Create tables
$semanticsDefs = array("name" => "varchar(60) CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT NULL", 
					  "remarks" => "varchar(100) CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT NULL");
					  
$status = $graph->createNodesTable($semanticsDefs);
echo "Creating Nodes Table...  STATUS: $status (0 = success; -1 = error)<br>";



$semanticsDefs = array("childSequenceNo" => "smallint(5) unsigned DEFAULT '0' COMMENT 'used to list a node s children in a particular order'");
$status = $graph->createEdgesTable($semanticsDefs);
echo "Creating Edges Table...  STATUS: $status (0 = success; -1 = error)<br>";


$status = $graph->createTraversalTable();
echo "Creating Traversal Table...  STATUS: $status (0 = success; -1 = error)<br>";



$errorStatus = $graph->addNode("my very latest node", "my latest comment");

if ($errorStatus != "")  {
	echo "<b>Failed node addition</b>: $errorStatus<br>";
	echo $dbase->errorSummary, "<br>";
	echo $dbase->errorDetails, "<br>";
}
else
	echo "Node added successfully, and assigned ID: $graph->methodDetails<br>";



$errorStatus = $graph->setNodeAsRoot(125);

if ($errorStatus != "")  {
	echo "<b>Failed setting node as root</b>: $errorStatus<br>";
	echo $dbase->errorSummary, "<br>";
	echo $dbase->errorDetails, "<br>";
}
else
	echo "Node successfully set as root<br>";
	


$errorStatus = $graph->deleteNode(124);

if ($errorStatus != "")  {
	echo "<b>Failed node deletion</b>: $errorStatus<br>";
	echo $dbase->errorSummary, "<br>";
	echo $dbase->errorDetails, "<br>";
}
else
	echo "Node successfully deleted<br>";
	


$errorStatus = $graph->addChild("new child", "and comments", 4);

if ($errorStatus != "")  {
	echo "<b>Failed child addition</b>: $errorStatus<br>";
	echo $dbase->errorSummary, "<br>";
	echo $dbase->errorDetails, "<br>";
}
else
	echo "Child successfully added, and assigned ID: $graph->methodDetails<br>";
	
	
	
$errorStatus = $graph->renameNode(4, "new name for 4", "new comments for 4");

if ($errorStatus != "")  {
	echo "<b>Failed node renaming</b>: $errorStatus<br>";
	echo $dbase->errorSummary, "<br>";
	echo $dbase->errorDetails, "<br>";
}
else
	echo "Node successfully renamed<br>";
	


$errorStatus = $graph->addEdge(69, 96);

if ($errorStatus != "")  {
	echo "<b>Failed edge addition</b>: $errorStatus<br>";
	echo $dbase->errorSummary, "<br>";
	echo $dbase->errorDetails, "<br>";
}
else
	echo "Edge successfully added<br>";

	

$errorStatus = $graph->removeEdge(69, 96);

if ($errorStatus != "")  {
	echo "<b>Failed edge removal</b>: $errorStatus<br>";
	echo $dbase->errorSummary, "<br>";
	echo $dbase->errorDetails, "<br>";
}
else
	echo "Edge successfully removed<br>";
	


$errorStatus = $graph->switchParentNode(100, 5, 6969);

if ($errorStatus != "")  {
	echo "<b>Failed parent switching</b>: $errorStatus<br>";
	echo $dbase->errorSummary, "<br>";
	echo $dbase->errorDetails, "<br>";
}
else
	echo "Parent successfully switched<br>";	
	
?>


</body>

</html>