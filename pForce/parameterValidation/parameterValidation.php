<?php
/* 
  	Last revised 9/29/2019.  Distributed as part of the "pForce Web Framework".  For more info: https://github.com/BrainAnnex/pForce
 
  	Class with static functions for PARAMETER VALIDATION (site security) 
 
 
 	DEPENDENCIES:  None
 
 
 	USAGE EXAMPLE:
	
		parameterValidation::validateInteger($myValue)


	@author  Julian West <julian@BrainAnnex.org>
	@url     <https://BrainAnnex.org>
	
	
	----------------------------------------------------------------------------------
	Copyright (c) 2017-2019 Julian A. West
	
	GNU General Public License

	This file is part of the "pForce Web Framework",
    originally developed in the context of the "Brain Annex" project,
    but now independent of it.

	The "pForce Web Framework" is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    The "pForce Web Framework" is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with the "pForce Web Framework", in the file "LICENSE.txt".  
	If not, see <http://www.gnu.org/licenses/>.
	----------------------------------------------------------------------------------
 */


namespace pforce;		// Part of the "pForce" Web Framework


class parameterValidation  {
	
	
	/*
		VALIDATON OF VALUES OR PATTERNS
	 */
	
	
	public static function validatePositiveInteger($arg)
	/* Check if the given argument is a positive, non-zero integer, (or a string representing such an integer.)  
	   Return true if the check succeeded, or false if not
	 */
	{
		if (self::validateInteger($arg) && ($arg > 0))
			return true;
		else
			return false;
	}
	
	
	public static function validateInteger($arg)
	/* 	Check if the given argument is a positive integer (or a string representing such an integer.)  
		Return true if the check succeeded, or false if not
	 */
	{
		/* Alternative methods:
			if((string)(int)$arg == $arg))
			if (ctype_digit((string)$arg))
		 */	
		if (is_int($arg) || ctype_digit($arg))		// The first condition is for numeric variables; the second one for text ones
			return true;
		else
			return false;
	}
	
	
	public static function validateNumeric($arg)
	/* 	Check if the given argument is a numeric quantity. 
		Return true if the check succeeded, or false if not
	 */
	{
		return is_numeric($arg);
	}
	
	
	public static function validateAlphanumeric($arg)
	/* Verify that the given argument is alphanumeric
	 */
	{
		$pattern = "/^[0-9a-zA-Z]+$/";		// Allowable character set : alphanumeric
		
		return preg_match($pattern, $arg);
	}
	
	public static function validateAlphanumericOrBlank($arg)
	/* Verify that the given argument is alphanumeric or blank
	 */
	{
		$pattern = "/^[0-9a-zA-Z ]+$/";		// Allowable character set : alphanumeric
		
		return preg_match($pattern, $arg);
	}
	
	public static function validateAlphanumericOrUnderscore($arg)
	/* Verify that the given argument is alphanumeric or underscore
	 */
	{
		$pattern = "/^[0-9a-zA-Z_]+$/";		// Allowable character set : alphanumeric or underscore
		
		return preg_match($pattern, $arg);
	}
	
	public static function validateUsername($user)		// TO-DO: move to the membership class
	/* Verify that the given username (which might also be an email address or an ID) is drawn from the allowed character set 
	 */
	{
		$pattern = "/^[0-9a-zA-Z_@. -]+$/";		// Allowable character set for usernames or email addresses
												// (listing the hyphen at the end prevents interpreting it as a metacharacter)
		
		return preg_match($pattern, $user);
	}
	
	public static function validatePassword($pass)		// TO-DO: move to the membership class
	/* Verify that the given password is drawn from the allowed character set 
	 */
	{
		$pattern = "/^[0-9a-zA-Z_!@.*+$%()&#?-]+$/";	// Allowable character set for passwords (added the question mark, allowed by Argus)
														// (listing the hyphen at the end prevents interpreting it as a metacharacter)
		
		return preg_match($pattern, $pass);
	}
	
	
	
	public static function validatePattern($string, $pattern, $debug = false)
	/* 	Check the given string value against the specified RegEx pattern.
		If it matches (or if it's an empty string), return true; otherwise print out the given error message (details based on the $stage flag) and return false.
		EXAMPLE:	validatePattern($ID, "/^[0-9]+$/", "ID must be an integer.  Value provided: &ldquo;$ID&rdquo;", true);
	 */
	{
		if ($string == "")
			return true;	// Maybe this should be false?
	
		if  ($debug)
			echo "<b>pattern:</b> $pattern &nbsp;&nbsp;&nbsp;&nbsp; <b>string:</b> $string<br>";
		
		$found = preg_match($pattern, $string);
		
		if ($found === 1)  {
			if  ($debug)
				echo "<br>Validation successful<br><br>";
	
			return true;
		}
	
		// If we get thus far, the validation failed
		return false;
	}
	
	
	
	
	/* 	Older functions of dubious use  
	 */
	 
	public static function sanitizeStringDbase(&$str) {
	/* 
		IMPORTANT:  This is a relic from the pre-PDO world.  Not sure what use there is for it now; the mysql part was taken out.
		This version is used for data that affects SQL queries.
		Modifies the input string (which is PASSED BY REFERENCE), to protect against code injection, etc.
		First, strip out any backslashes that might have been inserted 
			if magic_quotes_gpc is enabled, to avoid escaping the data twice.
		Next, escape special characters, taking into account the current character set of the MySQL connection
			(prepends backslashes to various special characters, such as \n, \r, \, ', ", so that it is safe to place it in a mysql_query.)
			"This function must always (with few exceptions) be used to make data safe before sending a query to MySQL."
		As a final step, strip off all HTML tags.
	*/
		$str = strip_tags(stripslashes($str));
	}
	
	
	public static function sanitizeString(&$str) {
	/* 
		IMPORTANT:  This is a relic from the pre-PDO world.  Not sure what use there is for it now; the mysql part was taken out.
		This version is used for data that does NOT affect SQL queries.
		Modifies the input string (which is PASSED BY REFERENCE), to protect against code injection, etc.
		First, strip out any backslashes that might have been inserted 
			if magic_quotes_gpc is enabled, to avoid escaping the data twice.
		Next, escape special characters, taking into account the current character set of the MySQL connection
			(prepends backslashes to various special characters, such as \n, \r, \, ', ", so that it is safe to place it in a mysql_query.)
			"This function must always (with few exceptions) be used to make data safe before sending a query to MySQL."
		As a final step, strip off all HTML tags.
	*/
		$str = strip_tags(stripslashes($str));
	}
	
	public static function sanitizeQueryString()
	// Clean up the query string, to protect against code injection, etc.
	// Return the sanitized string.
	{
		return  sanitizeStringDbase($_SERVER['QUERY_STRING']);
	}
	
	public static function sanitizeGET()
	// Clean up all the GET variables, to protect against code injection, etc.
	// WARNING: it fails if the GET variable contains strings, such as  "t[]=1&t[]=2"
	{
		array_walk($_GET, "self::sanitizeStringDbase");
	}
	
	public static function sanitizePOST()
	// Clean up all the POST variables, to protect against code injection, etc.
	{
		array_walk($_POST, "self::sanitizeStringDbase");
	}
	
	public static function sanitizeREQUEST()
	// Clean up all the REQUEST variables, to protect against code injection, etc.
	{
		array_walk($_REQUEST, "self::sanitizeStringDbase");
	}
	

}  // class "parameterValidation"
?>