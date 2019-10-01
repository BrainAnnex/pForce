<?php

use pforce\uploader;
use pforce\logging;



ini_set('display_errors', 1);								// This ensures error-reporting display regardless of PHP configuration, which might have disabled it
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);	// Most verbose option


require_once("../../pForce/uploader/uploader.php" );		// ADJUST PATH AS NEEDED



$upload_fileset_info = $_FILES["myFile"];					// The string "myFile" must match the name in the calling form:  <input type='file' name='myFile'>


$messageLogger = new logging("myLogFile.htm");				// Instantiate a "logging" object

$uploaderObj = new uploader();

$uploaderObj->logger = $messageLogger;

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

<h1>Test of uploader</h1>



<?php

$dest_dir = "./";
$nameHandler = false;
$postUploadHandler = false;
$verbose = true;

$numberUploaded = $uploaderObj->uploadFiles($upload_fileset_info, $dest_dir, $nameHandler, $postUploadHandler, $verbose);

echo "<h2>Number of files uploaded: $numberUploaded</h2>";
?>

View the <a href="myLogFile.htm" target="_new">log file</a>

</body>

</html>