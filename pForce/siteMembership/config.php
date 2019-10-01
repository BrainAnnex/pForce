<?
/* 	CONFIGURATION for the "siteMembership" module, last revised 9/27/2019

		MODULE DEPENDENCIES:   
			1) dbasePDO
			2) dbaseRestricted  (BEING PHASED OUT IN FAVOR OF dbasePDO)
			
 */
 
 
// Import underlying libraries.  Change paths as needed
// Use of absolute path allows inclusion of this file into files that might reside anywhere


require_once( dirname(__FILE__) . "/../dbasePDO/dbasePDO.php");					// Module it depends on
require_once( dirname(__FILE__) . "/../dbaseRestricted/dbaseRestricted.php");	// Module it depends on
?>