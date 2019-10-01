<?php
/*
 	Last revised 10/1/2019.  Distributed as part of the "pForce Web Framework".  For more info: https://github.com/BrainAnnex/pForce
 
  	Class for DATABASE-INTERFACE using the PDO functions ( see https://BrainAnnex.org/viewer.php?ac=2&cat=5 )
	
	Only tested on MySQL, but expected to work on other databases as well.
 
	The dababase object needs to be instantiated and have the following properties set for database access:
					- dbaseServer  
					- dbase  
					- dbUser  
					- pass   
					- dbLogFile (optional but recommended)
		
	DEPENDENCIES:
		
	 	- Logging module ("logging.php").  Its location needs to be specified in the "config.php" file
		
	
	ERROR HANDLING:
	
		In case of errors (including successful re-tries), a log of the problem in made into the text file whose name 
		is specified in the property "dbLogFile" (if the property is set);
		also, the properties "errorSummary" and "errorDetails" are set to, respectively, a short and long explanation of the error


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

use pforce\logging;


// Import this module's configuration file.  The use of absolute path allows inclusion of the calling script into files that might reside anywhere	
require_once( dirname(__FILE__) . "/config.php");		// It takes care of the inclusion of dependent modules




class dbasePDO  {
	// Public properties
	
	public $dbHandler;						// Object created by call to new PDO()
	
	public $dbaseServer;					// Database server (e.g. "localhost" or "mysql.mysite.com")
	public $dbase;							// Name of default database schema
	public $dbUser;							// Database username
	public $pass;							// Password for the given database user

	public $errorSummary = "";				// Short version of the last database error, if applicable
	public $errorDetails = "";				// Longer version of the last database error, if applicable
	
	public $dbLogFile = "";					// Optional [VERY RECOMMENDED] file in which to keep log of database errors (re-tries and outright failures)
	


	// Private properties
	
	private $logObject = false;				// File-logging object (instantiated at the first call, and re-used if available)
	private $retry = true;					// If a SQL query fails (typically because of "server gone away"), do we run it again?
											// Very handy in case of long-running queries during whose execution the dbase connection is lost



	/*
		CLASS METHODS
	 */


	public function setConnectionParameters($dbaseServer, $dbase, $dbUser, $pass)
	// Permits the setting of all 4 connection parameters at once
	{
		$this->dbaseServer = $dbaseServer;	// Database server
		$this->dbase = $dbase;				// Name of default database
		$this->dbUser = $dbUser;			// Database username
		$this->pass = $pass; 				// For the above user
	}
	
	
	
	public function dbaseStart($retry = false)
	/*  Create a new database connection.  Any existing old connection gets cleared (forced restart.)

		If successful, it saves the newly-created handle and returns true;
		in case of failure, it tries a second time, then it sets some error-reporting properties (plus optional file log) and returns false.
		
		ARGUMENTS
			retry		flag indicating whether this is the second (and last) attempt
	 */
	{
		unset($this->dbHandler); 
		/* Not sure if this is really needed.  Some say it isn't; others say that all the references
				to the old link (incl. statement objects) must be cleared to terminate the old connection.
				But maybe it's not necessary to terminate an old connection in order to start a new one.
				At any rate, the #1 reason to force a new connection is when the old one "goes away"!
		 */
		
				 
		try {
			/* Validate the parameters to connect to the database
			 */
			if (! $this->dbaseServer)  {			
				$this->errorSummary = "Cannot connect to the database";
				$this->errorDetails = "Reason: missing database server name in dbaseStart()";
				$this->logErrorMessage($this->errorSummary . ". " . $this->errorDetails);
				return false;
			}
			if (! $this->dbase)  {			
				$this->errorSummary = "Cannot connect to the database";
				$this->errorDetails = "Reason: missing database name in dbaseStart()";
				$this->logErrorMessage($this->errorSummary . ". " . $this->errorDetails);
				return false;
			}
			if (! $this->dbUser)  {			
				$this->errorSummary = "Cannot connect to the database";
				$this->errorDetails = "Reason: missing name of database user in dbaseStart()";
				$this->logErrorMessage($this->errorSummary . ". " . $this->errorDetails);
				return false;
			}
			if (! $this->pass)  {			
				$this->errorSummary = "Cannot connect to the database";
				$this->errorDetails = "Reason: missing database password in dbaseStart()";
				$this->logErrorMessage($this->errorSummary . ". " . $this->errorDetails);
				return false;
			}
			
			$dataSourceName = "mysql:host=$this->dbaseServer;dbname=$this->dbase;charset=latin1";
			// "Adding the charset is very important for security reasons" 
			// (http://wiki.hashphp.org/PDO_Tutorial_for_MySQL_Developers)
			
			$dbh = @new \PDO($dataSourceName, $this->dbUser, $this->pass);
			// The @ is to prevent extra screen printouts in case of a bad host argument
			
			// $this->logMessage(1, "Connected to database");
			

			/* Set the error reporting attribute (for all operations using this database handler) */
			$dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);					// The \ indicates the global namespace
			/*
				Possible values for the last argument: 
						PDO::ERRMODE_EXCEPTION
						PDO::ERRMODE_WARNING
						PDO::ERRMODE_SILENT  (default)
				Note: PDO::__construct() will always throw a PDOException if the connection fails,
						regardless of which PDO::ATTR_ERRMODE is currently set.
						
				"ERRMODE_EXCEPTION" is safer because errors from you database queries won't show sensitive data like your directories
			*/
			
			/* Not sure if the following would help - see: http://brady.lucidgene.com/articles/pdo-lost-mysql-connection-error
					$dbh->setAttribute(PDO::ATTR_TIMEOUT, 1000);
					
				Alternative:
					array(
						PDO::ATTR_TIMEOUT => 1000,
						PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
					)
				as the 4th argument of new PDO()
			*/ 
		}
		catch(\PDOException  $e)  		// The \ indicates the global namespace
		{
			if ($retry)
				$this->logErrorMessage("dbaseStart(): Creation of new database PDO connection failed again on 2nd (and last) attempt! Error msg: '" . $e->getMessage() . "'");
			else
				$this->logErrorMessage("dbaseStart(): Creation of new database PDO connection failed (on first attempt)! Error msg: '" . $e->getMessage() . "'");
				
			$this->errorSummary = "Cannot connect to the database";
			$this->errorDetails = "Reason: " . $e->getMessage();
			
			if (! $retry)  {
				$this->logMessage(1, "dbaseStart(): Trying a second time to create a new database PDO connection...");
				usleep(300000);		// 3/10 sec
				return $this->dbaseStart(true);		// Recursive call
			}
					
			return false;			// Giving up
		} 
		
		$this->dbHandler = $dbh;	// Save the newly-created connection
		
		return true;	// Normal termination

	} // dbaseStart()
	

	
	public function selectSQL($sql, $bindings = false) 
	/*	Run the specified SELECT query, with or without bindings.
	
		ARGUMENTS
			If the optional argument $bindings is specified, the SQL must contain one or more "?" (without quotes), 
				and $bindings must contain an array of values, or a single value.
				Example1: $sql = "SELECT * FROM mytable WHERE x = ? AND y = ?"
									and
							$bindings = array($value1, $value2)
							
							A single binding value can also be passed without an array. [i.e. a scalar $s gets converted to array($s)]
			
				Example2: $sql = "SELECT * FROM mytable WHERE x = :p1 AND y = :p2"
									and
							$bindings = array(':p1' => $value1, ':p2' => $value2))
		
							
		RETURN a (possibly emtpy) traversable associative/num dataset (aka "PDO statement".)	
			In case of ERROR, false is returned, and the "errorSummary" and "errorDetails" properties are set; also, the error is logged.
			The retry flag in the database object determines if a 2nd attempt is made in case of error.
		
		NOTES
		=====
		If an ASSOCIATIVE dataset is desired instead of an associative/numeric one, just do:
			$result = selectSQL($sql);
			$result->setFetchMode(PDO::FETCH_ASSOC);
			[note: with an associative array, one cannot use the list() construct]
			
		Likewise, if desiring a purely NUMERIC dataset, do:
			$result = selectSQL($sql);
			$result->setFetchMode(PDO::FETCH_NUM);
			
		
		If one wishes to determine how many records were retrieved, use pdo_num_rows() IF using a MySQL database
		
		
		TYPICAL CALLING CODE:
		====================
		$sql = "SELECT a, b FROM table";
		$result = $dbaseHandler->selectSQL($sql);
			
		
		EXAMPLES OF HOW TO USE THE RESULT:
		=================================	
		(1)
			foreach ($result as $row)  {
				$myFieldValue = $row["myFieldName"];	// An associative/numeric dataset is returned by default (see notes above.)  Note that $row[0], etc., will also work
		
				echo "myFieldValue: $myFieldValue<br>";
			}
		
		(2)
			while($row = $result->fetch())
				echo $row['field1']; 				// $row[0], etc., will also work
			
		(3)
			while($row = $result->fetch(PDO::FETCH_ASSOC))	// The PDO::FETCH_ASSOC part is not strictly necessary, because the default is PDO::FETCH_BOTH
				echo $row['field1'];  
				
		(4)
			while($row = $result->fetch(PDO::FETCH_NUM))	// The PDO::FETCH_NUM part is not strictly necessary, because the default is PDO::FETCH_BOTH
				echo $row[0]; 
			
		(5)
			$resultArray = $result->fetchall();		// Transform the result into an array (containing all of the result-set rows);
													//		for the individual entry (i.e. each dbase record) if one wishes a purely associative array, use fetchall(PDO::FETCH_ASSOC); 
													//		for a purely numeric arrays, use fetchall(PDO::FETCH_NUM)
													//	An empty array is returned if there are zero results to fetch, or FALSE on failure
													//  For convenience, the methods selectSQLarrayNum() and selectSQLarrayAssoc() are also available
			foreach($resultArray as $row)  {
				... 	// like before
			}
			
		(6)
			$sql = "SELECT a, b FROM table";
			$result = $dbObject->selectSQL($sql);
			foreach ($result as $row)  {
				list($a, $b) = $row;
				echo "a: $a | b: $b<br>";
			}
			
		(7)
			$sql = "SELECT a FROM table";
			$result = $dbObject->selectSQL($sql);
			$resultArray = $result->fetchall(PDO::FETCH_COLUMN);	// An array with all the returned entries from the single column
			
		(8)
			$sql = "SELECT f0, f1, f2, f3 FROM table";
			$result = $dbObject->selectSQL($sql);
			while(($columnValue = $result->fetchcolumn(2)) !== false)	// Do NOT use on Boolean fields.  See http://php.net/manual/en/pdostatement.fetchcolumn.php
				echo $columnValue;
			// fetchColumn returns a single column from the next row of a result set 
			
		(9)
			Use fetch(PDO::FETCH_CLASS) or fetch(PDO::FETCH_INTO) to save the results into objects
	 */ 
	{
		if (! $this->dbHandler)  {
			$status = $this->dbaseStart();		// If no database handle found, create one (start the database connection)
		
			if (! $status) 		// If the database connection failed
				return false;
		}
			
		$dbh = $this->dbHandler;

		try  {
			$result = $this->performSelectSQL($sql, $bindings);
			return $result;
		}
		catch(\PDOException  $e)    	// The \ indicates the global namespace
		{
			if ($this->retry)  {		// Since our 1st attempt failed, pursue a re-try, after reconnecting to dbase
				$this->logErrorMessage("selectSQL(): executing query failed on FIRST attempt! Error msg: '" . $e->getMessage() . "'");
				$this->logMessage(1, "selectSQL(): will try executing the SQL query again after re-connecting to dbase");
				
				// Re-start the database connection (force re-connection), useful in case the failure stemmed from lost connection	
				$status = $this->dbaseStart();					
				if (! $status)
					return false;
	
				// Re-run the SQL query, and pass thru whatever it returns
				return $this->selectSQLRetry($sql, $bindings);		// Re-try (it will be last attempt)
			}
			
			else  {			// The re-try flag is off; give up!
				$this->logErrorMessage("selectSQL(): executing query failed! Giving up! Error msg: '" . $e->getMessage() . "'. SQL: $sql | Bindings: " . print_r($bindings, true));
				$this->errorSummary = "Failure of database query in selectSQL(). Details: " . $e->getMessage();
				
				return false;		// giving up...
			}
		}
		
	}  // selectSQL()



	public function selectSQLarrayNum($sql, $bindings = false)
	/* 	Shortcut to invoking selectSQL() and then converting the entries of the returned dataset into a numeric array.
		An empty array is returned if there are zero results to fetch, or FALSE on failure.
		
		Example of return value:
			Array(
				Array ( [0] => 123  [1] => 222) 
				Array ( [0] => 666  [1] => 333)
			}
		
		Example of usage:
					$resultArray = selectSQLarrayNum($sql);
					foreach($resultArray as $row)  {
						echo $row[0] . "<br>";
					}
	 */
	{
		$resultPDOstatement = $this->selectSQL($sql, $bindings);
		
		if ($resultPDOstatement === false)		// In case selectSQL() resulted in error
			return false;
			
		$resultArray = $resultPDOstatement->fetchall(\PDO::FETCH_NUM);
		
		return $resultArray;
	}


	public function selectSQLarrayAssoc($sql, $bindings = false)
	/* 	Shortcut to invoking selectSQL() and then converting the entries of the returned dataset into an associative array.
		An empty array is returned if there are zero results to fetch, or FALSE on failure.
		
		Example of return value:
			Array(
				Array ( [field1] => 123  [field2] => 222) 
				Array ( [field1] => 666  [field2] => 333)
			}
			
		Example of usage:
					$resultArray = selectSQLarrayNum($sql);
					foreach($resultArray as $row)  {
						echo $row["field1"] . "<br>";
					}
	 */
	{
		$resultPDOstatement = $this->selectSQL($sql, $bindings);
		
		if ($resultPDOstatement === false)		// In case selectSQL() resulted in error
			return false;
			
		$resultArray = $resultPDOstatement->fetchall(\PDO::FETCH_ASSOC);
		
		return $resultArray;
	}



	public function selectSQLFirstRecord($sql, $bindings = false)
	/*	Run the specified SQL SELECT statement, and return the first row of the result (as an assoc/num array.) 
		A "LIMIT 1" statement is automatically added to the SQL.  WARNING: make sure that the $sql passed to it does not already have  "LIMIT" statement; if it does, use the method selectSQLOneRecord() instead
		In case of no records, return null.
		In case of error, return false.
		
		Typically used with SELECT statements that return a single record.
		Note: pdo_num_rows() cannot be used after this function call, 
				because the PDOstatement is not returned (maybe it could be saved in the object?)
	
		
		EXAMPLE 1:
			$sql = "SELECT a, b, c FROM myTable";
			list($a, $b, $c) = $db->selectSQLFirstRecord($sql);
	
		EXAMPLE 2:
			$sql = "SELECT * FROM myTable";
			$resultRow = $db->selectSQLFirstRecord($sql);
			$a = $resultRow["a"];
			$b = $resultRow["b"];
			$c = $resultRow["c"];
	 */
	{
		$sql .= " LIMIT 1";			// For efficiency
		

		return $this->selectSQLOneRecord($sql, $bindings);		
		
	} // selectSQLFirstRecord()
	
	
	
	public function selectSQLOneRecord($sql, $bindings = false)
	/*	Run the specified SQL SELECT statement, and return the first row of the result (as an assoc/num array.) 
		In case of no records, return null.
		In case of error, return false.
		
		Typically used with SELECT statements that return a single record.
		Note: pdo_num_rows() cannot be used after this function call, 
				because the PDOstatement is not returned (maybe it could be saved in the object?)
	
		
		EXAMPLE 1:
			$sql = "SELECT a, b, c FROM myTable ORDER BY a LIMIT 1";
			list($a, $b, $c) = $db->selectSQLFirstRecord($sql);
	
		EXAMPLE 2:
			$sql = "SELECT * FROM myTable";
			$resultRow = $db->selectSQLFirstRecord($sql);
			$a = $resultRow["a"];
			$b = $resultRow["b"];
			$c = $resultRow["c"];
	 */
	{
		$PDOstatement = $this->selectSQL($sql, $bindings);
		
		if ($PDOstatement === false)
			return false;								// The SELECT query generated an error
		
		if ($PDOstatement->rowCount() < 1)
			return null;								// The SELECT query returned no records
				
		$row = $PDOstatement->fetch(\PDO::FETCH_BOTH);	// Fetch the first record (as an assoc/num array); the PDO::FETCH_BOTH part is redundant, because it was set as a default in selectSQL()
														// Note: if the dataset is empty, fetch() will return false [which is why we're testing for that in the previous line]
	
		return $row;
		
	} // selectSQLOneRecord()
	
	
	
	
	public function selectSQLOneValue($sql, $bindings = false)
	/*	Run the specifed SQL query (expected to be a SELECT statement.)
	
		RETURN the first (zero-th) column of the first (zero-th) row.
		In case of no records or error, return null.  
		The returned value might be a null if that's what contained in the database or returned by a database function in the SELECT statement.
		Typically used with SELECT statements that return a single value, such as a count(*)
		In case of error, return false.
		
		EXAMPLE:
			$myCount = selectSQLOneValue("SELECT count(*) FROM myTable WHERE ID > 7");
			
		IMPORTANT NOTE: Aggregate functions sometimes return null.  For example, "SELECT max(ID) myTable WHERE ID > 100" will return null if no records satisfy the condition;
			the calling function may test for this with the "===" identity check, as follows :   
			if ($result === null) // to distinguish no records found vs. a returned value of zero
		
		Note: pdo_num_rows() cannot be used after this function call, 
				because the PDOstatement is not returned (maybe it could be saved in the object?)
	 */
	{
		$PDOstatement = $this->selectSQL($sql, $bindings);
		
		if ($PDOstatement === false)
			return false;								// The SELECT query generated an error.  More information available in $this->errorSummary
		
			
		$row = $PDOstatement->fetch(\PDO::FETCH_NUM);	// Fetch the first record, as a numeric array; note: if the dataset is empty, fetch() will return false
		
		if (! $row)
			return null;								// Empty dataset
		else
			return $row[0];
			
	} // selectSQLOneValue()
	
	
	
	public function selectSQLOneColumn($sql, $columnPosition, $bindings = false)
	/* 	Return a numeric array with the values of the specified column from the result of the SQL query;
		in case of error, return false.
		Column positions are counted from zero (i.e., 0 corresponds to the first column)
		WARNING: do NOT use on Boolean fields.  See http://php.net/manual/en/pdostatement.fetchcolumn.php
	 */
	{
		$result = $this->selectSQL($sql, $bindings);
		
		if (! $result)
			return false;	// Error
			
		
		$columnArray = array();		// Running array of values
		
		while(($columnValue = $result->fetchcolumn($columnPosition)) !== false)		// FALSE indicates there are no more rows (that's why it cannot retrieve boolean columns)
			$columnArray[] = $columnValue;
			
		return $columnArray;
	}
	
	
	
	public function isInTable($table, $field, $value)
	/* 	Return true if the given value is found at least once (in any row) in the specified field in the given table;
		false, otherwise
		
		EXAMPLE:
			isInTable("myTable", "field1", 123)
	 */
	{
		$sql = "SELECT count(*) 
					FROM $table 
					WHERE `$field` = ?";
					
		$count = $this->selectSQLOneValue($sql, $value);
		
		if ($count > 0)
			return true;	// Found
		else
			return false;	// Not found
	}


	
	public function pdo_num_rows($PDOstatement)
	/* 	This function emulates mysql_num_rows(), but only works for MySQL databases.
	
		1) 	It returns the number of rows affected by the last DELETE, INSERT, or UPDATE statement executed by the specified PDOStatement object. 
		
		2) 	If the last SQL statement executed by the associated PDOStatement was a SELECT statement, some databases may return the number of rows returned by that statement. 
			However, this behaviour is not guaranteed for all databases and should not be relied on for portable applications. 
			(See  http://php.net/manual/en/pdostatement.rowcount.php  and  http://wiki.hashphp.org/PDO_Tutorial_for_MySQL_Developers)
			
		In case of error, it return false
	 */
	{
		if(! $PDOstatement instanceof PDOStatement)  { 	// If the argument is not an expected "PDOStatement" object
			$this->logErrorMessage("pdo_num_rows(): the argument is not a PDO statement");
			$this->errorSummary = "Failure of looking up the number of records in the database";
			$this->errorDetails = "Reason: the argument is not a PDO statement";
			return false;
		}

		return $PDOstatement->rowCount();

	} // pdo_num_rows()


	public function pdo_result($PDOstatement, $rowCount, $field)
	/* Emulates mysql_result()
	 */
	{
		for ($i = 0; $i < $rowCount; ++$i)  {
			$row = $PDOstatement->fetch();
		}
		
		if (is_array($row))
			return $row[$field]; 
		else
			return false;
	}


	public function countRecords($table, $selectSubquery)
	/* 	Return the number of records in the given table when the specified subquery (the part *after* the WHERE in the sql query) is applied
		Example: $table = "myTable"  and  $selectSubquery = "`ID` > `0"
	 */
	{
		$sql = "SELECT count(*) FROM $table WHERE $selectSubquery";
		
		return $this->selectSQLOneValue($sql);
	}



	public function modificationSQL($sql, $bindings = false) 
	/*	Run the specified "modification" SQL (i.e. UPDATE, INSERT, DELETE or CREATE TABLE query.)
			
		If the optional argument $bindings is specified, the SQL must contain one or more "?" (without quotes), 
			and $bindings must contain an array of values, or a single value.
			Example1: 	$sql = "UPDATE myTable SET field = ? WHERE ID = ?";
								and
						$bindings = array($value1, $value2);
						
						A single binding value can also be passed without an array. [i.e. a scalar $s gets converted to array($s)]
		
			Example2: 	$sql = "UPDATE myTable SET x = :p1 AND y = :p2";
								and
						$bindings = array(":p1" => $value1, ":p2" => $value2);
						
			Example3:	$sql = "INSERT INTO myTable (`col1`, `col2`)
               							     VALUES (:username, :email)";
								and
						$bindings = array(":username" => "me myself", ":email" => "a@b.com");
		
		RETURN VALUE
			In case of successful insert, update or delete operations, return the number of affected rows.
			In case of successful create table operations, return 0	.
			In case of error, -1 is returned, error messages are logged, and some error properties get set.
	 */ 
	{	
		if (! $this->dbHandler)  {
			$status = $this->dbaseStart();		// If no database handle found, create one (start the database connection)
				
			if (! $status)
				return -1;
		}

		$dbh = $this->dbHandler;
		
		try {
			$affected_rows = $this->performModificationSQL($sql, $bindings);
			return $affected_rows;
		}
		catch(\PDOException  $e)    	// The \ indicates the global namespace
		{
			if ($this->retry)  {		// Since our 1st attempt failed, pursue a re-try, after reconnecting to dbase
				$this->logErrorMessage("modificationSQL(): executing query failed on FIRST attempt! Error msg: '" . $e->getMessage() . "'");
				$this->logMessage(1, "modificationSQL(): will try executing the SQL query again after re-connecting to dbase");
				
				// Re-start the database connection (force re-connection), useful in case the failure stemmed from lost connection
				$status = $this->dbaseStart();	
				if (! $status)
					return -1;
					
				// Re-run the SQL query, and pass thru whatever it returns
				return $this->modificationSQLRetry($sql, $bindings);		// Re-try (it will be last attempt)
			}
			
			else  {		// The re-try flag is off; give up!
				$this->logErrorMessage("modificationSQL(): executing query failed! Giving up! Error msg: '" . $e->getMessage() . "'. SQL: $sql | Bindings: " . print_r($bindings, true));
				$this->errorSummary = "Failure of database query in modificationSQL()";
				$this->errorDetails = "Reason: " . $e->getMessage();
				
				return -1;		// giving up...
			}
		}
	} // modificationSQL()


	public function retrieveInsertID()
	/* Return the auto-increment value from the last INSERT operation
	 */
	{
		$dbh = $this->dbHandler;
		
		return  $dbh->lastInsertId();		// See http://wiki.hashphp.org/PDO_Tutorial_for_MySQL_Developers

	} // retrieveInsertID()
	
	
	
	public function createTable($tableName, $columnDefinitions, $primaryKey, $otherKeys = false, $tableOptions = "ENGINE=InnoDB , DEFAULT CHARSET=latin1")
	/*	Create a mySQL table.
		If successful, RETURN true; otherwise, return false and set error properties.

		See https://dev.mysql.com/doc/refman/5.5/en/create-table.html
	
		EXAMPLES of arguments:
			$tableName					"myTable"
			$columnDefinitions			"`ID` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
										 `field1` varchar(16) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,  
                          				 `field2` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT NULL,
                         				 `field3` smallint(5) unsigned DEFAULT NULL,                                                          
                          				 `field4` text CHARACTER SET latin1 COLLATE latin1_general_ci, 
                          				 `timeStamp` timestamp DEFAULT CURRENT_TIMESTAMP
										 
			$primaryKey					"`field1`, `field2`"
			
			$otherKeys  [OPTIONAL]		"UNIQUE KEY `someNameForKey` (`field3`,`field4`)"
										"KEY `sessionID` (`sessionID`), KEY `subID` (`subID`)"
										
			$tableOptions  [OPTIONAL]	"ENGINE=MyISAM , DEFAULT CHARSET=latin1", AUTO_INCREMENT=69
	 */
	{
		if (! $tableName)  {
			$this->errorSummary = "Unable to create a new table";
			$this->errorDetails = "the name for the new table was not specified";
			return false;
		}
	
		/* Assemble an SQL to create the desired table
		 */
		$sql = "CREATE TABLE `" . $tableName . "`";
		
		$sql .= "
					( ";	// Start create_definition's (column name definitions, or indices, foreign keys, etc)
		
		$sql .= $columnDefinitions;
		
		if ($primaryKey)
			$sql .= ",                                                 
						PRIMARY KEY ($primaryKey) ";
		
		if ($otherKeys)
			$sql .= ", $otherKeys ";
									
		$sql .= "				                                                                
					) ";	// End create_definition's
						
		$sql .= $tableOptions; 

		//echo "<h2>SQL to create table</h2>:<br><pre>$sql</pre><br>";


		$result = $this->modificationSQL($sql);		// In case of failure, modificationSQL() returns -1

		if ($result != -1)
			return true;	// Successful table creation
		else  {
			$this->errorSummary = "Unable to create new table";
			return false;	// Failure
		}
		
	} //createTable()
	
	
	public function getFieldNames($tableName)
	/*	Return an array of field names in the given table (or false in case of failure).
		Only tested on MySQL.
		
		ARGUMENT:
			$tableName	A string with the table's name.  Example: "myTable" (do not wrap the name in database back quotes)
	 */
	{
		if (!$tableName)
			return false;	// Missing table name
		
		$sql = "SHOW COLUMNS FROM `" . $tableName . "`"; 
		
		// Note: an alternative SQL statement to retrieve the table field names is:
		//			select column_name from information_schema.columns where table_name = "$tableName"
		
		$result = $this->selectSQL($sql);
		// The query returns a table with a few fields; the one of interest (with the name) is called "Field"
		
		if (! $result)
			return false;		// Was unable to run the SQL to extract the field names
		
		// Populate the array of table field names
		$fieldNameArray = array();
		
		foreach ($result as $row)  {
			$fieldNameArray[] = $row["Field"];
		}
		
		return $fieldNameArray; 
		   
	} // getFieldNames()



	public function tableExists($table)
	// Return true iff the specified table already exists in the current database
	{
		$result = $this->selectSQL("SHOW TABLES LIKE '" . $table . "'");

		if ($this->pdo_num_rows($result) == 1)
			return true;		// Table found
		else
			return false;		// Table not found
	}
	
	
	public function allTables()
	// Return a numeric array with the names of all the tables in the current database, or false in case of error
	{
		$tableLookupSQL = "SHOW TABLES";
		
		return $this->selectSQLOneColumn($tableLookupSQL, 0);
	}
	
	
	
	public function extractForeignKeys($tableName)
	/* 	Look up and return all the foreign keys of the given table.
		This function may only work on MySQL.
		
		RETURN: a numeric array whose entries are associative arrays of the form  [columnName, foreignTable, foreignColumn]
				If no foreign keys are present, an empty array is returned, or false in case of error.
				Example:
					Array ( [0] => Array ( [columnName] => customerID [foreignTable] => customers [foreignColumn] => ID ) 
							[1] => Array ( [columnName] => shipperID  [foreignTable] => shippers  [foreignColumn] => ID ) 
						  ) 
	 */
	{
		$sql = 
			"SELECT
				`column_name` AS columnName, 
				`referenced_table_name` AS foreignTable, 
				`referenced_column_name` AS foreignColumn 
			FROM
				`information_schema`.`KEY_COLUMN_USAGE`
			WHERE
				`constraint_schema` = SCHEMA()
				AND
				`table_name` = '$tableName'
				AND
				`referenced_column_name` IS NOT NULL
			ORDER BY
				`column_name`";
	

		$foreignKeysArray = $this->selectSQLarrayAssoc($sql);
		
		return $foreignKeysArray;

	} // extractForeignKeys()
	
	
	public function mapFieldsToForeignKeys($tableName)
	/* 
		RETURN:
			If no foreign keys are present, an empty array is returned, or false in case of error.
			
			Example:
				Array( "customerID" => Array( [0] =>"customers" , [1] => "ID") ,
					   "shipperID"  => Array( [0] =>"shippers"  , [1] => "ID")
					  )
	 */
	{
		$foreignKeysArray = $this->extractForeignKeys($tableName);
			
		if ($foreignKeysArray === false)
			return false;		// error
		
		
		$mapping = array();
		
		foreach ($foreignKeysArray as $foreignKey)  {
			$fieldName = $foreignKey["columnName"];
			$mapping[$fieldName] = array($foreignKey["foreignTable"] , $foreignKey["foreignColumn"]);
		}
		
		return $mapping;

	} // mapFieldsToForeignKeys()
	
	
	/*************************************
				DEBUGGING METHODS
	 *************************************/


	public function debugPrintSQL($sql, $bindings, $expandSQL = true)
	/* 	Return an HTML-formatted string with the SQL and the bindings.
		If requested, reconstruct and print out the full SQL (typically for debugging purposes) after expanding the bindings.  TO-DO: take care of named bindings
	 */
	{
		$text = "<b>SQL:</b> &nbsp; $sql<br>";
		$text .= "<b>Bindings:</b> ";
		$text .= print_r($bindings, true);
		
		if ($expandSQL)  {
			$expandedSQL = str_replace("?" , "'". $bindings . "'", $sql);	// Only covers one scenario, namely the unnamed binding.  TO-DO: take care of named bindings
			$text .= "<br><b>Full SQL:</b> &nbsp; $expandedSQL<br>";
		}
		
		return $text;
	}


	public function debugPrintTextSQL($sql, $bindings, $expandSQL = true)
	/* 	Return a plain-text string the SQL and the bindings.
		If requested, reconstruct and print out the full SQL (typically for debugging purposes) after expanding the bindings.  TO-DO: take care of named bindings
	 */
	{
		$text = "SQL: /$sql/ ";
		$text .= "  ||  Bindings:  ";
		$text .= print_r($bindings, true);
		
		if ($expandSQL)  {
			$expandedSQL = str_replace("?" , "'". $bindings . "'", $sql);	// Only covers one scenario, namely the unnamed binding.  TO-DO: take care of named bindings
			$text .= "  ||  Full SQL: /$expandedSQL/";
		}
		
		return $text;
	}


	public function debugLogSQL($sql, $bindings, $expandSQL = true)
	/* 	Write to the log file a plain-text string the SQL and the bindings.
		If requested, reconstruct and log the full SQL (typically for debugging purposes) after expanding the bindings.  TO-DO: take care of named bindings
	 */
	{
		$msg = $this->debugPrintTextSQL($sql, $bindings, $expandSQL);
		$this->logMessage(1, $msg);
	}




	/*************************************
				PRIVATE METHODS
	 *************************************/

		
	private function performSelectSQL($sql, $bindings)
	/* 	Execute the requested SELECT SQL query, with the specified bindings (which may be false to indicate no bindings.)
	
		Arguments:
			$sql			an SQL SELECT query.   Example:  "SELECT * from myTable WHERE `field1` = :par1 AND `field2` = :par2"
			$bindings		a single value, or an array, or false (indicating no bindings).  Example: array(":par1" => 123, ":par2" => "hello")
	
		RETURN the resulting "PDO statement" object (which contains a traversable dataset.)
		
		No error-catching is done; in particular, no testing is done to verify that the SQL query is indeed a SELECT one.
		This is meant to be a LOW-LEVEL private method, invoked by code that catches errors in the call to this function.
		
		The default mode for later calls to fetch() or fetchall() is set to PDO::FETCH_BOTH (associative & numeric.)		
	 */
	{
		$dbh = $this->dbHandler;

		if ($bindings === false)  {
			/* If no bindings were specified, simply run the SQL statement */
			$PDOstatement = $dbh->query($sql);			// It returns a traversable PDOStatement object
		}
		else  {
			/* If bindings were specified, "prepare" the SQL statement, and then carry out the bindings and run the SQL */
			if (!is_array($bindings))
				$bindings = array($bindings);			// Turn a single value into an array

			$PDOstatement = $dbh->prepare($sql);
			$PDOstatement->execute($bindings);			// It returns a traversable PDOStatement object
		}
		
		$PDOstatement->setFetchMode(\PDO::FETCH_BOTH);	//  Set the default fetch mode for this "PDO statement"; it can later be over-ridden in calls to fetch() or fetchall()
		// PDO::FETCH_BOTH makes it like mysql_fetch_array()
		// Alternatively, PDO::FETCH_ASSOC to make it similar to mysql_fetch_assoc() , or PDO::FETCH_NUM to make it similar to mysql_fetch_row()

		/* Return the "PDO statement" (dataset) */
		return $PDOstatement;

	} // performSelectSQL()
	

	
	private function selectSQLRetry($sql, $bindings = false) 
	/*	Re-run (for one last time) the specified SELECT query, and return the dataset as a traversable associative array.
		In case of error, false is returned.
	 */ 
	{
		try  {
			$result = $this->performSelectSQL($sql, $bindings);
			$this->logMessage(1, "selectSQLRetry(): executing query succeeded on the second attempt");
			return $result;
		}
		catch(\PDOException  $e)    		// The \ indicates the global namespace
		{	
			// We are on the 2nd (and last) attempt - and it also failed
			$this->logErrorMessage("selectSQL(): executing query failed on LAST attempt! Giving up! Error msg: '" . $e->getMessage() . "'. SQL: $sql | Bindings: " . print_r($bindings, true));
			$this->errorSummary = "Failure of database query in selectSQL(). Details: " . $e->getMessage();
			
			return false;		// giving up...
		}
	}



	private function performModificationSQL($sql, $bindings = false) 
	/*	Run the specified "modification" SQL (i.e. UPDATE or INSERT query), 
			with the specified bindings (which may be absent to indicate no bindings.)
			
		No error-catching is done.  This is meant to be a low-level function, invoked by code that catches errors in the call to this method
	 */ 
	{
		$dbh = $this->dbHandler;
	
		if ($bindings === false)  {
			/* If no bindings were spedified, simply run the SQL statement */
			$affected_rows = $dbh->exec($sql);
		}
		else  {
			/* If bindings were specified, "prepare" the SQL statement, carry out the bindings and run the SQL */
			if (!is_array($bindings))
				$bindings = array($bindings);		// Turn a single value into an array


			$PDOstatement = $dbh->prepare($sql);
			$PDOstatement->execute($bindings);			// Equivalent to running bindParam() method followed by call to execute()
			$affected_rows = $PDOstatement->rowCount();
		}
	
		/* Return the number of affected rows */
		return $affected_rows;
		
	} // performModificationSQL()
	
	
	
	private function modificationSQLRetry($sql, $bindings = false) 
	/*	Re-run (for one last time) the specified modification query, and return the number of affected records.
		In case of error, -1 is returned.
	 */ 
	{
		try {
			$affected_rows = $this->performModificationSQL($sql, $bindings);
			return $affected_rows;
		}
		catch(\PDOException  $e)    	// The \ indicates the global namespace
		{
			// We are on the 2nd (and last) attempt - and it also failed
			$this->logErrorMessage("modificationSQL(): executing query failed on LAST attempt! Giving up! Error msg: '" . $e->getMessage() . "'. SQL: $sql | Bindings: " . print_r($bindings, true));
			$this->errorSummary = "Failure of database query in last attempt at modificationSQL()";
			$this->errorDetails = "Reason: " . $e->getMessage();

			return -1;		// giving up...
		}

	} // modificationSQLRetry()




	/******************************************************************************************************
			LOGGING METHODS (kept public for the benefit of modules using this class)
	 ******************************************************************************************************/
	 

	public function logMessage($level, $msg, $style = "")
	/* 	Log the given text message in the designated log file (specified by the global parameter LOG_FILE).  
		A carriage return+new line is added after each entry.
		If the log file doesn't exist, it gets created.
		Arguments:
			level		An integer indicating the "importance": 0 (most important), 1, 2, ...
			msg			The message to record
			style		Optional HTML styling to apply to the message, using a <span> tag
	 */
	{	
		if ($this->initializeLogging())
			return $this->logObject->logMessage($level, $msg, $style);
	}
	
	
	
	public function logErrorMessage($msg)
	/*	Log an error message.  Same as regular logging, but with more emphasis, and the backtrace of the call stack
	 */
	{
		if ($this->initializeLogging())
			return  $this->logObject->logErrorMessage($msg);
	}


	private function initializeLogging()
	/* 	If a log file is specified and no logging object has yet been instantiated, instantiate it.
		Return true if successful, or false otherwise
	 */
	{
		if (! $this->dbLogFile)
			return false;			// No log file was specified

		if (! $this->logObject)  
			$this->logObject = new logging($this->dbLogFile);	// Instantiate only if not already done
			
		return true;
	}


} // class "dbasePDO"
?>