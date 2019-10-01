<?php
/* 
  	Last revised 9/29/2019.  Distributed as part of the "pForce Web Framework".  For more info: https://github.com/BrainAnnex/pForce
 
  	Class for SITE USER AUTHENTICATION, incl. login/logout management.
	
	Multiple independent websites are supported.
	
	Authenticated user access is implemented by using randomly-generated session ID's, stored in a cookie on the client side, 
	and in the database on the server side.
	
	The central entity for site authentication is the "User Access ID", which can be thought of as a particular "subscription" (account) of a user to a site.
	A particular user might have multiple subscriptions (accounts) on different sites, and even multiple ones on a single site.
	The "User Access ID" resolves the particular subscription (account) on a particular site.

	On the server side, it makes use of 1 database table:
	
		- A login table (specific to this module), to store the sessionID's 


 	DEPENDENCIES:  
 
 		- dbasePDO		This in turn, depends on the "logging" class
		- cookies		Static methods to set, clear, read and check cookies
		- logging		Use to log messages (diagnostics, etc) on the server
		
 
 
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

use pforce\cookies;


// Import the dependencies.  Use of absolute path allows inclusion of this file into files that might reside anywhere  
require_once( dirname(__FILE__) . "/config.php" );		// The configuration file spells out the location of the dependencies




class siteAuth {
	
	/* PUBLIC PROPERTIES */
	
	public $loginTable = "entry";				// Name of table used to store the session info 
	public $db;									// An object of the "dbasePDO" class, to interact with the database

	public $cookieSession = "pforce";			// Name of cookie with the sessionID
	public $cookieUsername = "username";		// Name of cookie used to store the user name
	public $cookieOld = "old";					// Name of cookie used to store the first few digits of the old session ID

	public $userAccessID = false;				// ID that identifies an instance of user access; typically, either a User ID or a Subscription ID.  If not logged it, then false

	public $errorMessage = "";					// It gets set with an explanatory string in case of errors
	
	public $messageLogger;						// An object of the "logging" class
	
	public $allowMultipleLogins = true;			// Flag indicating whether the same user account is allowed to be accessed through multiple simultaneous logins
												// TO DO: implement a max # of simultaneous logins (?)
												
	public $maxLoginHours = 9;					// Login expiration time, in hours.  Only applicable if allowMultipleLogins is false
	
	public $loginURL;							// URL to re-direct to if login is required but user isn't logged in
		
	
	
	/* PRIVATE PROPERTIES */
	
	private $sessionID;							// Randomly-generated session ID
	

	
	function __construct($db, $messageLogger)		// CONSTRUCTOR
	{
		$this->db = $db;
		$this->messageLogger = $messageLogger;
	}
		
		
		
	/* 
		PUBLIC METHODS 
	 */
	
	
	public function userLoginStatusUnverified()
	/* 	Perform a *minimal* check of whether a user is logged in; only to be used in situations where restricted access isn't important.
	  
	  	RETURN VALUE:  if logged in, return the username; otherwise, set the property "errorMessage" with an explanation of the failed login, and return FALSE.
		
	  	The session ID found in the user's cookie is NOT verified against the database.
	  	This function is a "light" version of userLoginStatus(), suitable for low-protection pages. 
		
	  	Since no cookies are set, this function can be invoked anywhere;  
	  	this is handy in situations where the page header has already been sent. 
	 */
	{
		/* 	Check whether a session cookie is still present from the last login
		 	An absent cookie indicates lack of login or an old login that has been terminated or timed out 
		 */ 
		
		if (! cookies::isCookieSet($this->cookieSession))  {
			$this->errorMessage = "No login credentials found";
			return false;	// Session ID cookie not found
		}

		// Check the cookie where the username is stored
		if (cookies::isCookieSet($this->cookieUsername))
			return cookies::get($this->cookieUsername);
		else  {
			$this->errorMessage = "Invalid login credentials";
			return false;	// username cookie not found
		}
			
	} // userLoginStatusUnverified()
	
	
	
	public function userLoginStatus()
	/* 	Perform a THOROUGH CHECK of the user's login status, based on the available session cookie value, checked against the database.  
		IMPORTANT: this function MUST be invoked PRIOR to outputting any text, since it might set cookies.
		
		RETURN VALUE:  
			If logged in, set the property "userAccessID", and also return that value. 
			If not logged in, then completely log out user (clear his session variables, cookies, database info), 
				set the property "errorMessage" with an explanation of the failed login, and return FALSE.
	 */
	{
		/* 	Check whether a cookie is still present from the last login
		 	An absent cookie indicates lack of login or an old login that has timed out or was terminated (by a logout or a closing of the browser)
		 */
		if (! cookies::isCookieSet($this->cookieSession))  {
			// Session ID cookie NOT found
			$this->logout();	// Completely log user out
			$this->errorMessage = "No login credentials found";

			return false;
		}
		else  {
			$sessionID = cookies::get($this->cookieSession);
		}
	
		//$this->messageLogger->logMessage(1, "userLoginStatus().  Extracted cookie $this->cookieSession . Found value: $sessionID");	
		
		
		// Ensure that the sessionID is of the right form
		if (! $this->validateSessionID($sessionID))  {
			// Bad value of Session ID in cookie
			$this->logout();
			$this->errorMessage = "Invalid login credentials";
			return false;	
		}
		
		
		// Extract user credentials from the session ID
		$sql = "SELECT `userAccessID`, `timeStamp` 
					FROM $this->loginTable
					WHERE sessionID = ?";
			
		$result = $this->db->selectSQLFirstRecord($sql, $sessionID);
	  
		if ($result === false)  {
			// Check for the presence of database errors
			if ($this->db->errorSummary)  {
				// In case of database error, just log it, but DON'T LOG OUT THE USER!!  (for example, the failure to look up user information may be the result of a database timeout)
				$this->messageLogger->logErrorMessage(
						"userLoginStatus(): Unable to locate login credentials from the sessionID passed by the browser. Error message returned by selectSQLFirstRecord(): " 
						. $this->db->errorSummary);
			}
			else {		
				// If unable to find an active login session, log it and and completely log out the user
				$this->messageLogger->logErrorMessage("userLoginStatus(): Unable to locate login credentials from the sessionID passed by the browser. Logging out the user completely");
				$this->logout();
				$this->errorMessage = "Unable to locate login credentials from the sessionID in your browser: $sessionID";
			}
			
			return false;	// Extraction of user credentials failed
		}
	
		list($userAccessID, $timeStamp) = $result;
	  
	  
		/* 	Forcefully expire long lasting session: if the last login is too "stale", then log out user
			Only for sites that don't allow multiple logins 
		*/
		if(! $this->allowMultipleLogins)  {			// Force logouts on old sessions on sites that don't allow multiple logins
		  
			$age = (@strtotime("now") - @strtotime($timeStamp)) / 3600;		// Age of login in hours
		  	if  ($age > $this->maxLoginHours)  {			// If current login is too stale
				$this->logout();
				$this->errorMessage = "Login session expired";
				return false;		// Expired login
		  	}
		}
	
			
		
		$this->userAccessID = $userAccessID;		// TODO: maybe get rid of this....
		
		return $userAccessID;
		
	} // userLoginStatus()


	
	public function  validateLoginAndLookupUser()
	/* If user's login is validated, return the userAccessID (aka subscriptionID); otherwise, return false
	 */	 
	{		
		$loginData = $this->userLoginStatus();
		
	
		if ($loginData === false)  {
			// User is NOT logged in
			return false;
		}
		else  {	
			// User is logged in
			//echo "<br>SUCCESSFUL LOGIN.  subscriptionID = $SITE_ENV->subscriptionID (or $siteAuth->userAccessID) , username = $SITE_ENV->username<br>";
		
			return $this->userAccessID;
		}	
	}
	
	
	
	public function loginUser($userAccessID, $username, $stayLogged, $visitorID = false)
	/*  IMPORTANT: This function, because it may set cookies, must be called before any HTML output is started!
	
		Log in the user with the given ID
			
		ARGUMENTS
			$userAccessID	Typically, a user ID or a subscription ID
			$username		Redundant: passed for avoiding an extra database call
			$stayLogged		Flag indicates whether the login is meant to be persistent upon closing the browser
			$visitorID		Optional integer, for example to associate a login account with data collected from user prior to login
	
		A database connection is assumed.
		For security purposes, a new sessionID is always generated.
		The session info is stored in the database table  $this->loginTable
		
		COOKIES
			$this->cookieSession:	the session ID (also stored in the database)
			$this->cookieUsername: for convenient access by low-value pages.
			$this->cookieOld:		the first few digits of the last sessionID, to clear old session values that may still linger around.
									This is useful for garbage-collecting of one-time logins in cases 
									where the user closes the browser w/o logging out.
			
		RETURN VALUES
			If successful, return true; otherwise, false.  If errors arose, they are noted in the property "errorMessage", and also logged
			on server
	 */
	{
		$sessionID = $this->generateSessionID();		// Create a new random sessionID
		//$this->messageLogger->logMessage(1, "loginUser().  userAccessID = $userAccessID | username: $username .  Created a random session ID: $sessionID");
		
		// Verify that $sessionID isn't already in database (a very unlikely event resulting from the collision of two large random numbers)
		$sql = "SELECT count(*)
					FROM  $this->loginTable
					WHERE sessionID = ?";
		$result = $this->db->selectSQLOneValue($sql, $sessionID);
		
		if ($result)  {
			// The random session ID collided with an existing one!  A rare event!  Abort the login...
			$this->messageLogger->logErrorMessage("loginUser(): The random session ID collided with an existing one!");
			$this->errorMessage = "Temporary login error; please try again";
			return false;	
		}
	
		
		
		/* 	In sites that don't allow multiple concurrent logins, do some "garbage collection" 
			of any old sessionID's left behind for this user 
			(from previous logins that didn't end with a logout, or from other logins, possibly illegit, into the account)
		 */
		if (! $this->allowMultipleLogins)  {
			$sql = "UPDATE  $this->loginTable
							SET sessionID = ''
							WHERE userAccessID = ?";
			$this->db->modificationSQL($sql, $userAccessID);
		}
		
		
		
		/* Update the database table whose name is specified in the "loginTable" property
		 */
		 
		 // First, do some garbage-collecting of old login sessions on the same browser
		 if (cookies::isCookieSet($this->cookieOld))  {		// Check the cookie with first few digits of the old session ID
			$oldSessionID = cookies::get($this->cookieOld);	
			 
			// Eliminate the old sessionID from the database, if present
			$sql = "UPDATE $this->loginTable
						SET sessionID = ''
						WHERE sessionID like '$oldSessionID" . "%'";
			$this->db->modificationSQL($sql);
		}
	
	
		// Extract the IP address from which the user is logging in
		$ip = $_SERVER['REMOTE_ADDR'];
			
		// Add a record for this login, including the sessionID and the IP address
		$sql = "INSERT INTO $this->loginTable  
						    (userAccessID, sessionID, IP, visitorID)
					VALUES  ('$userAccessID', '$sessionID', '$ip', '$visitorID')";
	
		$numberAffected = $this->db->modificationSQL($sql);
	
		if ($numberAffected < 1)  {		// Normal expected value is 1
			$this->messageLogger->logErrorMessage("loginUser(): Unable to add login record to database!");
			$this->errorMessage = "Unable to add login record to database";
			return false;	// Failure to add the login info to the dbase
		}
	
		 
		/* 
			Set login cookies
		 */
		
		$status = true;
		
		//$this->messageLogger->logMessage(1, "loginUser().  About to set cookie $this->cookieSession to value: $sessionID");
		
		$status = $status && cookies::setYears($this->cookieOld, substr($sessionID, 0, 10), 15);	// Convenient cookie to remember the first 10 digits
																									// 		of the login session state.  Used by logout() function 		

		if ($stayLogged)  {
			$expireYears = 3;			// Very long time down the line
			$status = $status && cookies::setYears($this->cookieSession, $sessionID , $expireYears);	// Critical cookie to keep the login session state
			$status = $status && cookies::setYears($this->cookieUsername, $username, $expireYears);		// Convenient cookie to greet user without database calls
		}
		else  {
			$status = $status && cookies::setSessionOnly($this->cookieSession, $sessionID );			// Critical cookie to keep the login session state
			$status = $status && cookies::setSessionOnly($this->cookieUsername, $username);				// Convenient cookie to greet user without database calls
		}
		
		if (! $status)  {	// If any of the cookie operations failed
			$this->messageLogger->logErrorMessage("loginUser(): Unable to set cookies!");
			$this->errorMessage = "Unable to set cookies and complete login process (likely, a browser issue)";
			return false;
		}
		
		
		/* 
			Set property values
		 */
		 
		$this->userAccessID = $userAccessID;
		
		
		return true;		// Successful login
		
	} // loginUser()
	
	
	
	
	public function logout($all = false)
	/* IMPORTANT: This function, because it may set cookies, must be called before any HTML output is started
	   
	   If the $all flag is set, then ALL login sessions by the current user (e.g. on different devices or browsers) are terminated.
	   
	   The username is returned (handy to greet goodbye to the user.)
	*/ 
	{
		if (cookies::isCookieSet($this->cookieSession))  {		// Check the cookie with the login session ID
			$sessionID = cookies::get($this->cookieSession);	// Extract the sessionID from that cookie
			
			if ($all)  {
				// Look up the current user access ID
				$sql = "SELECT userAccessID FROM $this->loginTable
							WHERE sessionID = ?";
				$this->userAccessID = $this->db->selectSQLOneValue($sql, $sessionID);
				
				// Terminate all sessions attached to this user access ID
				$sql = "UPDATE $this->loginTable
							SET sessionID = NULL
							WHERE userAccessID = ?";					
				$this->db->modificationSQL($sql, $this->userAccessID);
			}
			else  {
				// Eliminate the current sessionID from the database
				$sql = "UPDATE $this->loginTable
							SET sessionID = NULL
							WHERE sessionID = ?";
				$this->db->modificationSQL($sql, $sessionID);
			}
			
			// Clear the session cookie
			cookies::clear($this->cookieSession);	
		}
		
		
		if (cookies::isCookieSet($this->cookieOld))  {		// Check the cookie with first few digits of the old session ID
			$oldSessionID = cookies::get($this->cookieOld);	
			 
			// Eliminate the old sessionID from the database, if present
			$sql = "UPDATE $this->loginTable
						SET sessionID = NULL
						WHERE sessionID like '$oldSessionID" . "%'";
			$this->db->modificationSQL($sql);
			
			// Clear the old-session cookie
			cookies::clear($this->cookieOld);
		}

		
		if (cookies::isCookieSet($this->cookieUsername))    {	// Check the cookie with the username
			$username = cookies::get($this->cookieUsername);
			
			// Clear the username cookie
			cookies::clear($this->cookieUsername);
			
			return $username;
		}
		else
			return "";		// If, somehow, the username can't be extracted from a cookie; probably not worth attempting a database lookup

	} // logout()
	

	
	public function enforceLogin($loginPage = false)
	/* 	Re-direct user to login page if not logged in.  Perform a THOROUGH CHECK for the login status.
		If user is logged in, return the userAccessID.
		The database connection gets started if needed.
		[Note: if using the "pageBuilder" module, this function is only used for pages that don't employ the startPage() function]
	 */
	{
		// Make sure the user is logged in.  Perform a THOROUGH CHECK
		$loginData = $this->userLoginStatus();
		
		if ($loginData === false)  {	// If user is NOT logged in
			if (! $loginPage)
				$loginPage = $this->loginURL;
				
			$this->redirectToLoginPage($loginPage);		// Redirect to login page, and exit
		}
	
		// If we get thus far, user is logged in.
		//return $this->userAccessID;
		return $loginData;
	}
	

	
	public function redirectToLoginPage($loginPage)
	/* 	Redirect user to the specified login page, and exit.
		A full URL is recommended over a relative path, to cover situations where the request comes from any pages in the site.
		The loginPage may not include a # fragment
	*/
	{
		// First, save the user's intented destination at the time when the user got intercepted
		// For example:  "/myHomeFolder/someFolder/myFile.php?id=123"
		$dest = $_SERVER['PHP_SELF'];
		if ($_SERVER['QUERY_STRING'])
			$dest .= "?" . $_SERVER['QUERY_STRING'];		

		$loginPage = $this->querystringAppend($loginPage , "dest=" . rawurlencode($dest));	// Pass the original destination to the login page
																							// rawurlencode() and rawurldecode(), as opposed to urlencode and urldecode,
																							// give correct results if the destination contains "+"
		// Redirect user to the specified login page, and exit
		header("Location: " . $loginPage);
		exit;
	}
	
	

	public function loginCount($userAccessID)
	/* 	Return the number of logins that the user has done.
		Handy to optionally pass a login count to the login splash page
	 */
	{
		$sql = "SELECT count(*) FROM $this->loginTable WHERE userAccessID = ?";	
		$numberLogins = $this->db->selectSQLOneValue($sql, $userAccessID);
		return $numberLogins;
	}
	
	

	public function setupDatabase()
	/* 	Create the table used for managing the logins.  To be used for installing a new site.
		In case of failure, an exception is thrown
	 */
	{
		$columnDefinitions = "
			`userAccessID` 	int(11) DEFAULT NULL,
			`timeStamp` 	timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`sessionID` 	varchar(36) DEFAULT NULL,
			`IP` 			varchar(40) DEFAULT NULL,
			`visitorID` 	mediumint(8) unsigned DEFAULT NULL
			 ";
		
		$status = $this->db->createTable($this->loginTable, $columnDefinitions, "" , "KEY `sessionID` (`sessionID`), KEY `userAccessID` (`userAccessID`)");
		
		if (! $status)
			throw new Exception("Failed to create the table $this->loginsTable . " . $this->db->errorSummary . " " . $this->db->errorDetails);
	}
	
	
	
	
	/***************************
		  PRIVATE METHODS 
	 ***************************/
	
	
	private function generateSessionID()
	/* 	Generate a 144-bit random number (returned as a 36-digit hex), to be used as a Session ID.
		32 digits are generated by the pseudo-random number generator mt_rand (seeded automatically), 
		and 4 are the lower digits of the current system micro time.
		The combined 36 hex digits are then shuffled around randomly.
		
		TODO: consider using random_bytes()
	 */
	{
		list($usec, $sec) = explode(" ", microtime());		// Obtain the current system micro time (a string in the form 2 space-separated integers: "microseconds seconds")
		$fiveHexDigits = sprintf('%05x', $usec * 1000000);	// Multiply microseconds by 1 million, to convert the decimal part into a hex integer
		$fourHexDigits = substr($fiveHexDigits, -4, 4);		// Extract the last 4 digits
	  
		$randomString = $fourHexDigits . 
			sprintf('%04x%04x%04x%04x%04x%04x%04x%04x',
					mt_rand(0, 65535),	// 16 bits (i.e. 4 hex)
					mt_rand(0, 65535),
					mt_rand(0, 65535),
					mt_rand(0, 65535),
					mt_rand(0, 65535),
					mt_rand(0, 65535),
					mt_rand(0, 65535),
					mt_rand(0, 65535)
					);
	  
		return str_shuffle($randomString);		// "For extra measure", add a random shuffle at the end!
	}
	
	
	private function validateSessionID($sessionID)
	/*  Ensure that sessionID is of right form and contains no unexpected characters; 
		return true if that's the case, or false otherwise
	 */
	{
	  $requiredLength = 36;		// This MUST match the total length used by generateSessionID()
	  
	  if (strlen($sessionID) != $requiredLength)
		return false;
		
	  for ($i = 0; $i < $requiredLength;  ++$i)  {		// Investigate each character in turn
		$char = $sessionID[$i];	
		if  ( !(($char >= '0'  &&  $char <= '9')  ||  ($char >= 'a'  &&  $char <= 'f')) )
			return false;			// If any character is outside the ranges 0-9 and a-f, then the validation fails
	  }
		
	  return true;
	  
	} // validateSessionID()
	
	
	
	private function querystringAppend($url, $queryString)
	/* 	Add the the specified URL the given query string.  The URL may be in a variety of forms, but assumed NOT to contain # fragments
		See https://stackoverflow.com/questions/5215684/append-query-string-to-any-form-of-url
	 */
	{
		$parsedUrl = parse_url($url);
	
		if ($parsedUrl['path'] == null)
			$url .= '/';		// This would be an issue, for example, if url were "http://example.com"
		
		$separator = ($parsedUrl['query'] == NULL) ? '?' : '&';
		
		//if(!substr_count($url,$query)) 	// if one wished not to re-add already-existing query strings
		$url .= $separator . $queryString;
		
		return $url;

	} // querystringAppend()
	
} // class "siteAuth"
?>