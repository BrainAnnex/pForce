<?php
/* 	CONFIGURATION for packaging into pForce Framework, last revised 9/24/2019

	DEPENDENCIES:
		
 		- dbasePDO		This in turn, depends on the "logging" class (used to log messages, such as diagnostics, on the server)
		- cookies		Static methods
		- logging		
 */
 
 
// Import underlying modules it depends on.  Change paths as needed
// Use of absolute path allows inclusion of this file into files that might reside anywhere

require_once( dirname(__FILE__) . "/../dbasePDO/dbasePDO.php" );	
require_once( dirname(__FILE__) . "/../cookies/cookies.php" );
?>