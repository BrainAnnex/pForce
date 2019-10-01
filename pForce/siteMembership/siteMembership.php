<?php
/* 	
	Last revised 9/29/2019.   Distributed as part of the "pForce Web Framework".  For more info: https://github.com/BrainAnnex/pForce

	Class for the management of generic user accounts on a site: the underlying database table could have more fields, used by other more site-specific modules
	
	Based on the entity "User-access ID" : identifying a particular membership for some user
	
	
	DEPENDENCIES:   
	
			- dbasePDO


	TODO: Further expand the process of regarding "accounts" as "sites"
	

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


// Import this module's configuration file.  The use of absolute path allows inclusion of the calling script into files that might reside anywhere	
require_once( dirname(__FILE__) . "/config.php");		// It takes care of the inclusion of dependent modules



class siteMembership {
	
	// PUBLIC PROPERTIES
	public $errorSummary = "";			// Summary of the last error, if applicable
		
	
	// PRIVATE PROPERTIES
	private $membershipTable = "siteSubscriptions";	// Table used for user account information
	private $dbPDO;						// Object for database operations

	
	
	/****************************
			CONSTRUCTOR
	 ****************************/
	 
	public function __construct($dbPDO)
	{
		$this->dbPDO = $dbPDO;
	}

	

	
	/*********************************************
	
					PUBLIC METHODS
			
	 *********************************************/

	
	public function validateUser($dbField, $usernameOrEmail, $attemptedPassword, $marketingSlogan = "")
	/* 	Attempt to retrieve the membership record for the user with the provided credentials.
		Verify that the credentials are valid and that the account is active.
	
		ARGUMENTS:
			$dbField 			Should be the string "email" or "username"
			$usernameOrEmail
			$attemptedPassword
			$marketingSlogan	An optional string to be returned to the user in case the memberhips has expirerd
			
		RETURN:
			If successful, return the array [ID, username] ; otherwise, return false and set the property "errorSummary" to the reason for failure
	 */
	{
		/* Locate the most recent membership (preferentially current, but possibly expired) for this user
		 */
		$sql = "SELECT ID, username, active, hashedPassword
				FROM $this->membershipTable 
				WHERE `$dbField` = ?
					ORDER BY active desc, ID desc";
		//echo $sql;
		
		$result = $this->dbPDO->selectSQLFirstRecord(	$sql, 
														$usernameOrEmail
													);
		
		if (! $result) {
			// If no membership found, or in case of database error, set the property "errorSummary" and return false
			if ($this->dbPDO->errorSummary)
				$this->errorSummary = "database lookup error. If the problem persists, contact your system administrator";	// If the database query generated an error...
			else
				$this->errorSummary = "no such user/password. Please try again";	// ...otherwise, it's just a case of user not found
				
			return false;
		}
		
		
		// Extract the fields from the 0-th (and only row) of the SQL result 
		$savedHashedPassword = $result["hashedPassword"];
		$ID = $result["ID"];
		$username = $result["username"];
		$active = $result["active"];
		
		
		// Verify the provided password
		if (! password_verify($attemptedPassword , $savedHashedPassword))  {
			$this->errorSummary = "no such user/password. Please try again";	// ...otherwise, it's just a case of user not found
			return false;
		}
		
		
		
		/* If we get thus far, the supplied credentials were good, and a membership was found, but it might be expired
		 */
		
		if  (! $active)  {
			// If subscription is expired, compose a message with an optional marketing slogan
			$this->errorSummary = "sorry, &lsquo;$username&rsquo;, your membership has EXPIRED. $marketingSlogan";
			return false;
		}

		return array($ID, $username);

	} // validateUser()
	
	
	
	public function reValidatePassword($userID, $attemptedPassword)
	/* 	For safety, wheneven a user attempts to make important account changes.
		Return true if re-validation suceeeds, or false otherwise.
	 */
	{
		$sql = "SELECT hashedPassword
				FROM $this->membershipTable 
				WHERE `ID` = ?";
		//echo $sql;
		
		$savedHashedPassword = $this->dbPDO->selectSQLOneValue( $sql, $userID );
													
														
		if (! $savedHashedPassword)
			return false;		// Record not found
			
		if (! password_verify($attemptedPassword , $savedHashedPassword))
			return false;		// Bad password provided


		return true;			// The record was located, and the provided password was good
			
	} // reValidatePassword()
	


	public function changePassword($userID, $newPass)
	// Update the given user's password with a hashed version of the given new one
	{
		$hashedPassword = $this->encryptPassword($newPass);
		
		$sql = "UPDATE $this->membershipTable SET `hashedPassword` = :hashedPassword WHERE ID = $userID";
		
		return $this->dbPDO->modificationSQL($sql, array(":hashedPassword" => $hashedPassword));
	}
	


	public function listActiveUsers($siteID)
	/* 	Return a traversable dataset of ACTIVE users on the specified site, with all the available fields in the database table.
		Example of a way to traverse it:
		
			$allUsers = $siteMembership_object->listActiveUsers($siteID);
			foreach ($allUsers as $user)  {
				print_r($user);
				echo "<br><br>";
			}

		TODO: generalize, to also optionally include inactive users, and to optionally hide admins
	 */
	{
		$sql = "SELECT * FROM $this->membershipTable 
						 WHERE `active` AND `siteID` = ? 
						 ORDER BY `ID`";
		
		return $this->dbPDO->selectSQL($sql, $siteID);
	}
	
	
	
	public function addUserToAccount($siteID, $username, $email, $pass, $permissions = 0)
	/* 	Add a new user to the current site.
		Return the new userID if all operations were successful, or false othewise
	 */
	{
		//echo "siteID, username, email, pass, permissions: $siteID, $username, $email, $pass, $permissions<br>";
		
		// Locate the ID of the last user added to this site
		$sql = "SELECT max(`userNumber`) FROM $this->membershipTable WHERE `siteID` = ?";
		$lastUserNumber = $this->dbPDO->selectSQLOneValue($sql, $siteID);
				
		if ($lastUserNumber === null)  {		// Note: $lastUserNumber might be zero (user 0); so, need to test for being identically NULL (no users found)
			$this->errorSummary = "Unable to locate any existing users in this site ($siteID)";
			return false;
		}
		
		$newUserNumber = $lastUserNumber + 1;
		
		
		// Add a new user to the current account
		$userID = $this->addNewUser($siteID, $username, $email, $pass, $newUserNumber);

		if (! $userID)
			return false;		// Adding a new user/account failed
						
		
		return 	$userID;
			
	} // addUserToAccount()
	

	
	public function addNewAccount($name, $email, $pass, $newSiteID = null)
	/* 	Add a new account, including a user who is an admin for that account.
		Optionally, accept a value for the new siteID (typically used for sites identified by text codes, such as "e", "s"); 
		if a value isn't provided, siteID's are assumed to be consecutive integers, and the next available number is used.
		Return the new account ID if all operations were successful, or -1 othewise; in case of errors, also set the property "errorSummary"
	 */
	{
		/* Add the user to a new account
		 */
		 
		if (!$newSiteID)  {
			// Locate the ID of the last account added (if any)
			$sql = "SELECT max(`siteID`) FROM $this->membershipTable";
			$lastAccountID = $this->dbPDO->selectSQLOneValue($sql);
					
			if (! $lastAccountID)
				$newSiteID = 1;						// We're adding a first account (i.e. a new installation)
			else
				$newSiteID = $lastAccountID + 1;		// Adding a new account
		}
		
		
		// Create a first user for this new account
		$userID = $this->addNewUser($newSiteID, $name, $email, $pass, 0);
		
		if (! $userID)  {
			$this->errorSummary = "Unable to add user $name to the new account";
			return -1;		// Adding a new user/account failed
		}
			
		// Make this first user an admin - and, if this is the first account being created, also make that user a Site Manager
		if ($newSiteID == 1)
			$result = $this->makeAdmin($userID, true);
		else
			$result = $this->makeAdmin($userID, false);
		
		if (! $result)  {
			$this->errorSummary = "Unable to set the newly-added user to be an administrator";
			return -1;
		}


		return $newSiteID;	// Successful termination
			
	} // addNewAccount()
	
	
	
	public function makeAdmin($userID, $siteManager = false)
	/* 	Mark the specified user as an admin for their account - and, optionally, also as a Site Manager
		Return true iff successful
	 */
	{
		if ($siteManager)
			$sql = "UPDATE $this->membershipTable SET admin = 1, siteManager = 1 WHERE ID = ?";
			//$numberAffected = $this->dbaseRestricted->updateSQL($this->membershipTable, "admin = 1, siteManager = 1", "ID = ?", $userID);
		else
			$sql = "UPDATE $this->membershipTable SET admin = 1 WHERE ID = ?";
			//$numberAffected = $this->dbaseRestricted->updateSQL($this->membershipTable, "admin = 1", "ID = ?", $userID);
			
		$numberAffected = $this->dbPDO->modificationSQL($sql, $userID);
		
		return ($numberAffected == 1) ? true : false;		// the number of records added ought to be 1

	} // makeAdmin()
	

	
	public function setupDatabase()
	/* 	Create the table used for the membership information.  To be used for installing a new site.
		In case of failure, an exception is thrown
	 */
	{	
		$columnDefinitions = "
			`ID`			mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
			`active` 		tinyint(1) DEFAULT '1',
			`username` 		varchar(32) COLLATE latin1_general_ci DEFAULT NULL,
			`siteID` 		varchar(8) COLLATE latin1_general_ci NOT NULL,
			`userNumber` 	smallint(6) DEFAULT NULL COMMENT '0 indicates the account owner',  
			`admin` 		tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Account admin',
			`siteManager` 	tinyint(1) DEFAULT '0' COMMENT 'The highest permissions level', 
			`notes` 		varchar(160) COLLATE latin1_general_ci DEFAULT NULL,
			`email` 		varchar(60) COLLATE latin1_general_ci DEFAULT NULL,
			`hashedPassword` varchar(60) COLLATE latin1_general_ci NOT NULL,
			`timeStamp` 	timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'TimeStamp of record creation'
			 ";


		$status = $this->dbPDO->createTable($this->membershipTable, $columnDefinitions, "`ID`" , "KEY `Email` (`email`), KEY `Username` (`username`)");
		
		if (! $status)
			throw new Exception("Failed to create the table $this->membershipTable. " . $this->dbPDO->errorSummary . " " . $this->dbPDO->errorDetails);

	} // setupDatabase()
	

	
	public function alreadyInstalled()
	// Return true iff this module table is already installed
	{
		return $this->dbPDO->tableExists($this->membershipTable);
	}
	
	
	public function lookupUserByEmail($email)
	// Return the full record of the user with the given email address.  If that email occurs in more than one place, the user with the largest ID is picked
	{
		$sql = "SELECT * 
					FROM  $this->membershipTable 
					WHERE email = ?
					ORDER BY ID DESC";
		
		return $this->dbPDO->selectSQLFirstRecord($sql, $email);
	}


	public function verifyUserCredentialsAndFetchUserInfo($userAccessID)	
	/* 	Extract and return all user-access field values (such as username, email, active flag, etc),
		while also enforcing the account to be active.
		ARGUMENT:	The user's access ID
		RETURN VALUE: an associative array, containing the user's data.  In case of error or record not found (possibly because account was inactive), return false.
	 */
	{
		if (! $userAccessID)
			return false;

		$sql = "SELECT * 
					FROM  $this->membershipTable 
					WHERE ID = ? 
					AND `active` = 1";
		
		$result = $this->dbPDO->selectSQLFirstRecord($sql, $userAccessID);
		
		return $result;
	}
	
	
	public function isUserActive($userAccessID)
	/* Verify the validity of the user access ID.  Return true iff an ACTIVE user membership with the given ID is found
	 */
	{
		if (! $userAccessID)
			return false;
			
		$sql = "SELECT count(*) FROM  $this->membershipTable 
					WHERE `ID` = ? 
					AND `active` = 1";
		
		$result = $this->dbPDO->selectSQLOneValue($sql, $userAccessID);
		
		return ($result == 1);
	}
	
	
	
	public function passwordRequirements()
	// Return a string explaining the password requirements.  The message must match the policy set in the method passwordValidateRequirements()
	{
		return "at least 6 characters";
	}

	public function passwordValidateRequirements($pass)
	// Enforce password requirements.  Any change needs to be also reflected in the method passwordRequirements()
	{
		if (strlen($pass) >= 6)
			return true;
		else
			return false;
	}



	/*********************************************
	
					PRIVATE METHODS
			
	 *********************************************/
	
	
	private function encryptPassword($password)
	/* 	Password encryption.
		Use the CRYPT_BLOWFISH algorithm to create the hash. 
		This will produce a standard crypt() compatible hash using the "$2y$" identifier. 
		The result will always be a 60 character string, or FALSE on failure
	 */
	{ 
		$cost = 9;		// Use evaluateCostFunction.php to estimate a good value for this parameter
		
		$algorithmOptions = array("cost" => $cost);
		
		return password_hash($password, PASSWORD_BCRYPT, $algorithmOptions);
	}



	private function addNewUser($siteID, $username, $email, $password, $usernumber = 0)
	// Add a new user to the specified account.  If successful, return the new record ID; in case of failure, return false
	{
		if (! $siteID)  {
			$this->errorSummary = "Missing Site ID";
			return false;
		}
		
		$sql = "SELECT count(*) FROM $this->membershipTable WHERE siteID = '$siteID'  AND  username = ?";
		$numberFound = $this->dbPDO->selectSQLOneValue($sql, $username);
				
		if ($numberFound > 0)  {
			$this->errorSummary = "This username (&ldquo;$username&rdquo;) ALREADY exists in the site";		// TO-DO: it might be ok to have non-unique usernames, as long as they don't have same pass!
			return false;
		}
		
		$hashedPassword = $this->encryptPassword($password);
		
		$sql = "INSERT INTO $this->membershipTable (`username`, `siteID`, `userNumber`, `email`, `hashedPassword`) 
											VALUES (:username, :siteID, :usernumber, :email, :hashedPassword)";
		
		$numberAffected = $this->dbPDO->modificationSQL($sql, 
														array(":username" => $username , ":siteID" => $siteID , ":usernumber" => $usernumber , ":email" => $email , ":hashedPassword" => $hashedPassword)
								);
								
		if ($numberAffected != 1)  {
			$this->errorSummary = "Addition of new user to database failed";
			return false;
		}
		else
			return $this->dbPDO->retrieveInsertID();
			
	} // addNewUser()
	
} // class "siteMembership"
?>