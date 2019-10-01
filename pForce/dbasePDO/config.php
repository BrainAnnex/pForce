<?
/* 	CONFIGURATION for packaging into pForce Framework, last revised 9/24/2019

	DEPENDENCIES:
		
	 	Optional Logging module ("logging.php")
 */


define("DATABASE_LOGGING", true);		// If not logging is desired, set the 2nd argument to false
 
 
// Import underlying libraries.  Change paths as needed
// Use of absolute path allows inclusion of this file into files that might reside anywhere

if (DATABASE_LOGGING)
	require_once( dirname(__FILE__) . "/../logging/logging.php" );		// Module it depends on: if using this optional module, adjust the path as needed
?>