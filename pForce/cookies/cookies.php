<?php
/* 
  	Last revised 9/29/2019.  Distributed as part of the "pForce Web Framework".  For more info: https://github.com/BrainAnnex/pForce

 	Static class to handle COOKIES

 	It provides convenient static methods to set, clear, read and check cookies	
 
 
 	OPTIONAL CONFIGURATION (inside this script): tweak the default values of the static class properties.  Those values can also be changed by calls to methods.
	

 	DEPENDENCIES:  none
	
	
	EXAMPLES OF USAGE:
	
			use pforce\cookies;
			
			$status = cookies::setSessionOnly("myCookieName", someValue);	


 			-> Full examples in the "test.php" files
	
	
	
	BACKGROUND INFORMATION:
	
		A cookie is often used to identify a user or a login. 
		A cookie is a small amount of data that the server embeds on the user's computer; each time the same computer requests a page with a browser, it will send the cookie too.		
		IMPORTANT:  Cookies are part of the HTTP header, so any setting function (including clearing) must be called before any output is sent to the browser

		
 	SEE ALSO:
	
			http://php.net/manual/en/function.setcookie.php


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


// List of all available methods (all static)
interface cookiesInterface
{	
	public static function set($name, $value, $expire, $domain = false);
	
  	public static function setSessionOnly($name, $value, $domain = false);
	
	public static function setDays($name, $value, $expireDays, $domain = false);
	public static function setYears($name, $value, $expireYears, $domain = false);
	
	public static function clear($name, $domain = false);
	
	public static function get($name);
	
	public static function isCookieSet($name);
	
	public static function requireHTTPS($required = true);
	public static function forceHTTPonly($force = true);
	public static function setPath($path = "/");
}



class cookies implements cookiesInterface
/* 
	Provides convenient static methods to set, clear, read and check cookies
 */
{
	/********************************************************************************************************************
	
                        CONFIGURABLE PART: PUBLIC (STATIC) PROPERTIES.   
								
                        Adjust as desired below, or change dynamically using methods such as requireHTTPS()
		
	 ********************************************************************************************************************/

	public static $httpsRequired = false;		// Flag indicating whether the cookie should be sent over HTTPS only
	
												
	public static $httponly = true;				// Flag indicating that the cookie will be made accessible only 
												// through the HTTP protocol - and not thru JavaScript (for security)
	
	
	public static $path = "/";					// The path on the server in which the cookie will be available from; 
												// if set to '/', the cookie will be available within the entire domain
	
	// *** End of configurable part  ***
	


	

	/************************************************************************************************************
	
                                              PUBLIC (STATIC) METHODS
		
	 ************************************************************************************************************/

	
	public static function set($name, $value, $expire, $domain = false)
	/*	Cookie-setting function.  
		IMPORTART: it must be called  *prior* to outputting any text
		
		ARGUMENTS
			$name  		String with cookie name (cannot be empty)
			$value 		String with cookie value
			$expire  	The time (expressed as a Unix timestamp) when the cookie is set to expire.
							If set to 0, it'll expire at the end of the session (when the browser closes)
							To avoid having to deal with Unix-timestamp values, more specialized methods, such as setDays(), are also provided in this class.						
			$domain 	[OPTIONAL] Site's domain name (such as "example.com"); if missing, the domain is inferred from the server variable "SERVER_NAME"
			
		RETURN VALUE: If output exists prior to calling this function, this function will fail and return FALSE;
			if it successfully runs, it will return TRUE.  (This does NOT indicate whether the user accepted the cookie.)
	 */
	{
		if ($name == "")
			return false;
			

		if (! $domain)
			$domain = $_SERVER['SERVER_NAME']; 
	
	
		/* TODO: provide a same-site option if (\PHP_VERSION_ID >= 70300)
			PHP 7.3 has a new function to allow setting same-site cookies:  setcookie('cookie_name', 'cookie_value', ['samesite' => 'Lax']);
			
		 */
	
		return \setcookie($name, $value , $expire,  self::$path, $domain, self::$httpsRequired, self::$httponly);		
		// Note: The second-to-last argument indicates whether an HTTPS connection is required.
		// 		 The last argument ("httponly" option) is used for security;  
		//		 it indicates that the cookie will be made accessible only through the HTTP protocol - and not thru JavaScript		 
		
	} // set()


	public static function setSessionOnly($name, $value, $domain = false)
	// Same as set(), but the cookie will expire at the end of the session (when the browser closes)
	{
		return self::set($name, $value, 0, $domain, self::$httpsRequired);
	}


	public static function setDays($name, $value, $expireDays, $domain = false)
	// Same as set(), but with the expiration time expressed as in "number of days from now"
	{
		return self::set($name, $value, self::daysFromNow($expireDays), $domain, self::$httpsRequired);
	}
	
	public static function setYears($name, $value, $expireYears, $domain = false)
	// Same as set(), but with the expiration time expressed as in "number of years from now"
	{
		return self::set($name, $value, self::yearsFromNow($expireYears), $domain, self::$httpsRequired);
	}
	

	public static function clear($name, $domain = false)
	/* 	Clear the cookie with the given name, in the specified domain (such as "example.com")
		IMPORTART: it must be called  *prior* to outputting any text.
		
		If the site's domain name (such as "example.com") is missing, it is inferred from the server variable "SERVER_NAME"

		RETURN VALUE: If output exists prior to calling this function, this function will fail and return FALSE;
			if it successfully runs, it will return TRUE.  (This does not indicate whether the user accepted the cookie.)
	 */
	{
		if (! $domain)
			$domain = $_SERVER['SERVER_NAME']; 

		return \setcookie($name, "", 1, "/", "." . $domain, false, true);		// The 1 in the third argument is a time years past
	}
	
	
	public static function get($name)
	// Return the value of the cookie with the specified name (in the domain of the calling script); if none is found, return false
	{
		if (isset($_COOKIE[$name]))
			return $_COOKIE[$name];
		else
			return false;
	}
	
	
	public static function isCookieSet($name)
	// Return true if a cookie by that name is set (in the domain of the calling script)
	{
		return  isset($_COOKIE[$name]);
	}
	
	
	
	/*
		 Methods to alter the configurable class properties
	 */
	
	public static function requireHTTPS($required = true)
	// Use this function to require an HTTPS connection (or to turn off such a requirement if previously set)
	{
		self::$httpsRequired = $required;
	}

	public static function forceHTTPonly($force = true)
	// Use this function to restrict the cookie's access thru the HTTP protocol, rather than thru JavaScript (or to turn off such a requirement if previously set)
	{
		self::$httponly = $force;
	}
	
	public static function setPath($path = "/")
	// 	Use this function to set the path on the server in which the cookie will be available from;
	//	if using '/', the cookie will be available within the entire domain
	{
		self::$path = $path;
	}



	
	/**********************************************************************************************************************
	
                                                       PRIVATE (STATIC) METHODS
		
	 **********************************************************************************************************************/

	
	private static function yearsFromNow($numberOfYears)
	// Return a UNIX-format system time, corresponding to the specified number of YEARS from now
	{
		// Add the year interval, converted in seconds, to the current UNIX-format system time
		return  \time()+ (3600 * 24 * 365 * $numberOfYears);		
	}
	
	private static function daysFromNow($numberOfDays)
	// Return a UNIX-format system time, corresponding to the specified number of DAYS from now
	{
		// Add the year interval, converted in seconds, to the current UNIX-format system time
		return  \time()+ (3600 * 24 * $numberOfDays);		
	}


} // class "cookies"
?>