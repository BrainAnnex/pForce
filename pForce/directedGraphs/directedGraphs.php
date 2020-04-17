<?php
/* 	
	Last revised 4/16/2020.  Distributed as part of the "pForce Web Framework".  For more info: https://github.com/BrainAnnex/pForce

	Class to implement a Traversable Directed Acyclic Graph (DAG), consisting of Nodes and Directional Edges between 2 Nodes
	
	Each node can carry a set of user-defined "semantics" (such as "name" and "remarks"); likewise for each edges (for example "childSequenceNo")
	
	An optional SQL clause can optionally be set by the invoking module (typically to enforce access restrictions, permissions, etc.)

	
	DEPENDENCIES:  
	
		1) dbasePDO
		2) parameterValidation
	
	
	For debugging or logging purposes, the debugLogSQL() function available to thru the dbasePDO object may be used
	
	Applications of DAGs include implementation of inter-related Categories or of social networks.


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

use pforce\parameterValidation;



// Import this module's configuration file.  The use of absolute path allows inclusion of the calling script into files that might reside anywhere		
require_once( dirname(__FILE__) . "/config.php");		// It takes care of the inclusion of dependent modules




class directedGraph  {
	/* PUBLIC PROPERTIES */
	
	public  $methodLog = "";			// Optional descriptive text log of the actions (typically modifications) 
										//		done by an individual class method.  Each method that uses this, will first reset it
										
	public  $methodErrors = "";			// Optional descriptive text that some methods use to pass error info to their calling code
										//		Each method that uses this, will first reset it
										
	public  $methodResults = false;		// Optional variable that some methods use to pass additional info to their calling code
										//		Each method that uses this, will first reset it
	
	public  $MAX_DEPTH = 50;			// Large enough so that anything bigger is likely a runaway recursion
	
	public  $CATEGORY_ROOT_NODE = 1;	// Not recommended to use 0 because nodeID's are autoincrement fields, and MySQL can cause problems when 0 is stored in an autoincrement field
	
	
	
	/* PRIVATE PROPERTIES */

	private $db = null;					// Database object (of the class "dbasePDO").  TO-DO: maybe switch to dbaseRestricted
	
	private $nodesTable = "";			// Name of table used to store the graph's nodes.
										//		3 fields are expected:  ID (autoincrement), name, remarks.  Additional fields (not interpreted by this class) are allowed
										
	private $edgesTable = "";			// Name of table used to store the graph's edges
										//		3 fields are expected:  parentID, childID, childSequenceNo
										
	private $traversalTable = "";		// Name of table used to store a representation of the graph's traversal
										//		3 fields are expected:  index (autoincrement), nodeID, depth							
	

	private $SQLclause = "";			// Optional clause to insert in all the lookup statements (e.g., the calling module might use this to enforce restrictions, permissions, etc.)
	private $sqlANDClause = "";			// Same as SQLclause, but with an "AND " in front, unless empty string


	// The following private properties are used to avoid recomputing some results
	private $children = array();		// Indexed by nodeID
	private $parents = array();			// Indexed by nodeID
	private $siblings = array();		// Indexed by nodeID
	
	
	
	
	/*
		CONSTRUCTOR
	 */

	public function __construct($db, $nodesTable, $edgesTable, $traversalTable = "")  
	/* 	It's ok if the specified tables don't yet exist in the database.
	
		ARGUMENTS:
			db		Object of class "dbasePDO"
	 */
	{
		$this->db = $db;
		$this->nodesTable = $nodesTable;
		$this->edgesTable = $edgesTable;
		$this->traversalTable = $traversalTable;
	}
	
	
	
	/*
		PUBLIC METHODS
	 */

	public function setSQLclause($clause)
	/* Set an optional clause to insert in all the lookup statements (e.g., the calling module might use this to enforce restrictions, permissions, etc.)
	 */
	{
		$this->SQLclause = $clause;
		
		// Also set a convenient, derived, property
		if ($clause)
			$this->sqlANDClause = "AND $clause";
		else
			$this->sqlANDClause = "";
	}


						
	public function createNodesTable($semanticsDefs)
	/*	Create a database table for the nodes, only if it does not already exist.
		Each node can carry a set of user-defined "semantics", with the their definitions
		Example:  $semanticsDefs = array("name" => "varchar(60) CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT NULL", 
										 "remarks" => "varchar(100) CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT NULL");
		
		Return 0 if no errors (table may or may not be created) and -1 in case of errors.
		
		Note: the table's autoincrement field starts at 1; there is no way (without access to the mysql server) to make it start at 0
	 */
	{
		$sql = "CREATE TABLE IF NOT EXISTS  `" . $this->nodesTable . "` (                                                                  
					  `ID`		 smallint(5) unsigned NOT NULL AUTO_INCREMENT, ";
					  
		foreach ($semanticsDefs as $fieldName => $fieldDefinition)		  
        	$sql .=  "`$fieldName`  $fieldDefinition , ";

		$sql .= " PRIMARY KEY (`ID`),
					  KEY `nameIndex` (`name`)                                                                                    
           		) ENGINE=InnoDB DEFAULT CHARSET=latin1";
				
		return  $this->db->modificationSQL($sql);

	} // createNodesTable()
	
	
	
	public function createEdgesTable($semanticsDefs)
	/*	Create a database table for the edges, only if it does not already exist.
		Each edge can carry a set of user-defined "semantics", with the their definitions
		Example:  $semanticsDefs = array("childSequenceNo" => "smallint(5) unsigned DEFAULT '0' COMMENT 'used to list a node s children in a particular order'");
		
		Return 0 if no errors (table may or may not be created) and -1 in case of errors
	 */	
	 {
		$sql = "CREATE TABLE IF NOT EXISTS  `" . $this->edgesTable . "` (                                                                  
					 `parentID` smallint(5) unsigned NOT NULL,
					 `childID` 	smallint(5) unsigned NOT NULL, ";
					 
		foreach ($semanticsDefs as $fieldName => $fieldDefinition)		  
        	$sql .=  "`$fieldName`  $fieldDefinition , ";			 
					  
		$sql .= " PRIMARY KEY (`parentID`,`childID`) 
               ) ENGINE=InnoDB DEFAULT CHARSET=latin1";
				
		return  $this->db->modificationSQL($sql);
	}


	public function createTraversalTable()
	/*	Create a database table for the saved graph traversal, only if it does not already exist.
		Return 0 if no errors (table may or may not be created) and -1 in case of errors
	 */	
	 {
		$sql = "CREATE TABLE IF NOT EXISTS  `" . $this->traversalTable . "` (                                                                  
						 `index` 	smallint(5) unsigned NOT NULL AUTO_INCREMENT,
						 `nodeID` 	smallint(5) unsigned NOT NULL, 
						 `depth` 	smallint(5) unsigned NOT NULL,  
					 PRIMARY KEY (`index`),
					 KEY `nodeIndex` (`nodeID`)
               ) ENGINE=InnoDB DEFAULT CHARSET=latin1";
				
		return  $this->db->modificationSQL($sql);
	}
	
	
	public function setNodeAsRoot($nodeID)
	/* 	Mark the specified node as the ROOT by setting its ID to a pre-specified value (such as 1) stored in property CATEGORY_ROOT_NODE
		
		In case of failure, return an error message; if successful, a blank string is returned
	 */
	{
		if ($nodeID == $this->CATEGORY_ROOT_NODE)
			return  "The node is ALREADY marked as a root (ID = " . $this->CATEGORY_ROOT_NODE .").  No action taken.";
	
		if (! parameterValidation::validatePositiveInteger($nodeID))
			return "The ID of the node to be set as a root MUST be a positive integer; value given: $nodeID";
			
		if (! $this->db->isInTable($this->nodesTable, "ID", $nodeID))
			return "The specified node (ID: $nodeID) does NOT exist.  No action taken.";
			
		if ($this->db->isInTable($this->nodesTable, "ID", 0))
			return "The node table ALREADY has a rood (node with ID 0).  No action taken.";
			
		$sql = "UPDATE $this->nodesTable
					SET ID = $this->CATEGORY_ROOT_NODE 
					WHERE ID = ?";
					
		$result = $this->db->modificationSQL($sql, $nodeID);
		
		if ($result != 1)
			return "Failed database update operation";
	}
	




	public function addChild($parentID, $semantics = false)
	/* 	Add a Node with a child relationship to the specified parent; optionally populate the newly-added node with the specified semantics

		If the optional $semantics argument is present, it is expected to be an array of [fieldName => fieldValue]
		elements to be added in the table of nodes for the newly-created record.  Example:  ["name" => "myName" , "numField" => 123]   Do NOT place SQL quotes around the names!
		
		The property methodLog records the actions taken by this method (except error conditions.)
		
		RETURN VALUE: In case of failure, return an error message;
		if successful, a blank string is returned, the "methodResults" property is set to the AutoIncrement value for new node ID,
		and the "methodLog" property is set to details of the process
	 */
	{
		$this->methodResults = false;
		$this->methodLog = "";
		
		/* Validate the parameters 
		 */
		if (! parameterValidation::validateInteger($parentID))  
			return "ParentID MUST be an integer; value given: $parentID";
	
		$errorStatus = $this->addNode($semantics);	// If successful, it sets the property "methodResults"
		
		if ($errorStatus != "") 
			return "Cannot add the new child node to the '$this->nodesTable' table. Reason: $errorStatus";

		$childID = $this->methodResults;
		
		$this->methodLog .= "New child node successfully added to '$this->nodesTable' table, and assigned ID $childID";	
		
		
		/* Now create a child-parent relationship
		 */
		$errorStatus = $this->addEdge($parentID, $childID);
					
		if ($errorStatus != "")  
			return "Cannot add a parent/child relationship for the newly-added child to the '$this->edgesTable' table. Reason: " . $errorStatus;
		
		$this->methodLog .= " New relationship successfully created (parent ID $parentID -> child ID $childID) ";
			
		// If we get thus far, the operation was successful
		$this->methodResults = $childID;
		return "";		// No error

	} // addChild()
	

	
	public function addEdge($parentID, $childID)
	/* 	Add a directed edge between the specified nodes (from parent to child.)
		
		In case of failure, return an error message, and possibly set the "errorSummary" and "errorDetails" properties;
		if successful, a blank string is returned
		
		TO-DO: must verify that no circularities get introduced
	 */
	{
		/* Validate the parameters 
		 */
		if (! parameterValidation::validateInteger($parentID))
			return "ParentID MUST be an integer; value given: $parentID";
			
		if (! parameterValidation::validatePositiveInteger($childID))
			return "ChildID MUST be a positive (non-zero) integer; value given: $childID";
		
		if ($parentID == $childID)
			return "ParentID and ChildID should differ; instead, they are the same value: $parentID";


		$binding = array(":childID" => $childID , ":parentID" => $parentID);
		
		
		// Check if the specified edge (parent/child relationship) already exists...
		$sql = "SELECT count(*) FROM $this->edgesTable
					WHERE childID = :childID  AND  parentID = :parentID";
					
		$found = $this->db->selectSQLOneValue($sql, $binding);
		
		if ($found)
			return "The specified parent/child relationship ($parentID/$childID) ALREADY exists. No action necessary";
		
		
		/* Carry out the addition of parent/child relationship
		 */

		// Append to table of category relationships
		$sql = "INSERT INTO $this->edgesTable (childID, parentID) VALUES (:childID, :parentID)";
		
		$numberAffected = $this->db->modificationSQL($sql, $binding);
		
		if ($numberAffected != 1)
			return "Cannot add the new parent/child relationship to the '$this->edgesTable' table: Parent $ParentID --> Child $childID";


		// If we get thus far, the operation was successful
		return "";		// No error message to return

	} // addEdge()
	
	
	
	public function removeEdge($parentID, $childID)
	/* 	Remove the edge between the specified nodes.  
	
		IMPORTANT: No check is done about the child node possibly becoming orphaned as a result.  The calling function might opt to use the method numberParents() to determine if the node is an only child.
		
		In case of failure, return an error message, and possibly set the "errorSummary" and "errorDetails" properties;
		if successful, return a blank string.
	 */
	{
		/* Validate the parameters 
		 */
		if (! parameterValidation::validateInteger($parentID))
			return "ParentID MUST be an integer; value given: $parentID";
			
		if (! parameterValidation::validatePositiveInteger($childID))
			return "ChildID MUST be a positive (non-zero) integer; value given: $childID";
		
		if ($parentID == $childID)
			return "ParentID and ChildID should differ; instead, they are the same value: $parentID";


		$binding = array(":childID" => $childID , ":parentID" => $parentID);
		
		
		// Check if the specified edge exists...
		$sql = "SELECT count(*) FROM $this->edgesTable
					WHERE childID = :childID  AND  parentID = :parentID";
					
		$found = $this->db->selectSQLOneValue($sql, $binding);
		
		if (! $found)
			return "The specified parent/child relationship ($parentID/$childID) does NOT exists. No action necessary";
		
		
		/* If we get this far, the edge was located.  Carry out the edge deletion
		 */

		// Delete from table of category relationships
		$numberAffected = $this->db->modificationSQL("DELETE FROM $this->edgesTable 
															WHERE (childID = ?  AND  parentID = ?)",
													 		array($childID, $parentID));

		
		if ($numberAffected != 1)		// TO-DO: set the "errorSummary" and "errorDetails" properties using their database counterparts
			return "Failed to remove the parent/child relationship from the database: Parent $ParentID --> Child $childID";


		// If we get thus far, the operation was successful
		return "";		// No error message to return

	} // removeEdge()



	public function switchParentNode($childID, $oldParentID, $newParentID)
	/* 	Switch a parent/child relationship between the specified nodes.
		Take the child away from the old parent, and re-assign to the new one.
		
		In case of failure, return an error message, and possibly set the "errorSummary" and "errorDetails" properties;
		if successful, a blank string is returned
	 */
	{
		/* Validate the parameters 
		 */
		if (! parameterValidation::validatePositiveInteger($childID))
			return "ChildID MUST be a positive (non-zero) integer; value given: $childID";

		if (! parameterValidation::validateInteger($oldParentID))
			return "Old Parent ID MUST be an integer; value given: $oldParentID";
			
		if (! parameterValidation::validateInteger($newParentID))
			return "New Parent ID MUST be an integer; value given: $newParentID";	

	
		// Verify that the old and the new parent actually differ
		if ($oldParentID == $newParentID)  {
			return "Old and New Parent IDs should differ; instead, they are the same: $oldParentID";
		}
		
	
		// Check that the old relationship exists...
		$sql = "SELECT count(*) FROM $this->edgesTable
					WHERE childID = :childID  AND  parentID = :oldParentID";
					
		$binding = array(":childID" => $childID , ":oldParentID" => $oldParentID);				
		$found = $this->db->selectSQLOneValue($sql, $binding);
		
		if (! $found)
			return "The specified old parent/child relationship ($oldParentID/$childID) does NOT exists. No action taken";


		/* 
			Add the relationship with the NEW parent
		 */
		 
		$errorMessage = $this->addEdge($newParentID, $childID);
		
		if ($errorMessage != "")
			return $errorMessage;
		 
		
		/* 
			Delete relationships with the OLD parent
		 */
		 
		return $this->removeEdge($oldParentID, $childID);
		
	} // switchParentNode()



	public function switchChildNode($parentID, $oldChildID, $newChildID)
	/* 	Switch a parent/child relationship between the specified nodes.
		From the parent, take the old child away , and relace it with the new child.
		
		In case of failure, return an error message, and possibly set the "errorSummary" and "errorDetails" properties;
		if successful, a blank string is returned
	 */
	{
		/* Validate the parameters 
		 */
		if (! parameterValidation::validatePositiveInteger($oldChildID))
			return "Old ChildID MUST be a positive (non-zero) integer; value given: $childID";
			
		if (! parameterValidation::validatePositiveInteger($newChildID))
			return "New ChildID MUST be a positive (non-zero) integer; value given: $newChildID";
			
		if (! parameterValidation::validateInteger($parentID))
			return "Parent ID MUST be an integer; value given: $parentID";
			
		
		// Verify that the old and the new child actually differ
		if ($oldChildID == $newChildID)  {
			return "Old and New Child IDs should differ; instead, they are the same: $oldChildID";
		}


		// Check that the old relationship exists...
		$sql = "SELECT count(*) FROM $this->edgesTable
					WHERE childID = :oldChildID  AND  parentID = :parentID";
					
		$binding = array(":oldChildID" => $oldChildID , ":parentID" => $parentID);				
		$found = $this->db->selectSQLOneValue($sql, $binding);
		
		if (! $found)
			return "The specified old parent/child relationship ($parentID/$oldChildID) does NOT exists. No action taken";


		/* 
			Add the relationship with the NEW child
		 */
		 
		$errorMessage = $this->addEdge($parentID, $newChildID);
		
		if ($errorMessage != "")
			return $errorMessage;
		 
		
		/* 
			Delete relationships with the OLD child
		 */
		 
		return $this->removeEdge($parentID, $oldChildID);
		
	} // switchChildNode()



	public function deleteNode($nodeID)
	/* 	Delete the specified node in the graph, including all the graph edges entering or leaving the node.
	
		(The caller function ought to first to determine whether ok to delete the node.  Also, no check is done to prevent deletion of the root)										
		
		In case of failure, return an error message, and possibly set the "errorSummary" and "errorDetails" properties;
		if successful, a blank string is returned
	 */
	{
		/* Validate the parameter 
		 */
		if (! parameterValidation::validateInteger($nodeID))
			return "Node ID MUST be an integer; value given: $nodeID";
				
		
		// Check if the specified node exists and is unique...
		$sql = "SELECT count(*) FROM $this->nodesTable
					WHERE ID = ?";
					
		$found = $this->db->selectSQLOneValue($sql, $nodeID);
		
		if ($found == 0)	// Not found
			return "The specified node (ID $nodeID) does NOT exists. No action necessary";
		
		if ($found > 1)		// More than one found
			return "Irregular situation with multiple nodes found with the same ID ($nodeID) exist. No action taken";

		
		/* Carry out the deletion of the node
		 */
		 
		// TO DO: count them first, and verify that they were removed successfully

		// Delete all the graph edges entering or leaving the node
		$numberAffected = $this->db->modificationSQL("DELETE FROM $this->edgesTable 
												WHERE parentID = ?",
												$nodeID);		// Delete the node's children
											
		$numberAffected = $this->db->modificationSQL("DELETE FROM $this->edgesTable 
												WHERE childID = ?",
												$nodeID);		// Delete the node's parents

		// Finally, nuke the node itself
		$numberAffected = $this->db->modificationSQL("DELETE FROM $this->nodesTable 
												WHERE ID = ?",
												$nodeID);		// Delete the node itself
		if ($numberAffected != 1)
			return "Cannot delete the node (ID $nodeID) from the table '$this->nodesTable'";


		// If we get thus far, the operation was successful
		return "";		// No error message to return

	} // deleteNode()



	public function retrieveNodes($clause = "", $sortOrder = "")
	/* 	Locate all the nodes, subject to the optional restriction, expressed as an SQL subquery clause: for example "not isnull(`remarks`)"  [Note: this is above and beyond 
			the optional SQL clause specified in the class property "SQLclause"]
		If a sort order is specified, it must be of a form  suitable for a SQL query (example1: "`name`"  ;  example2:  "`name`, `ID` DESC")
		RETURN a traversable data set of nodes, sorted by the specified field.  Each entry is an array of 1 or more parts: ID and any present semantic fields (for example [ID, name, remarks, permissions])
		In case of error, return false.
	 */
	{
		if ($clause)		// If an SQL clause was provided, use it (above and beyond the optional SQL clause in the class property)
			$sql = "SELECT * 
							FROM $this->nodesTable 
							WHERE $clause
								  $this->sqlANDClause";
		else  {
			$sql = "SELECT * 
							FROM $this->nodesTable";

			if ($this->SQLclause)
				$sql .= " WHERE $this->SQLclause";
		}
		
		if ($sortOrder)
			$sql .= " ORDER BY $sortOrder";
		
		// Run the SELECT query	
		$result = $this->db->selectSQL($sql);
			
		if (! $result)
			return false;					// There was a database execution error
		
		return $result;	
		//return  $result->fetchall();		// Convert data set to array	

	} // retrieveNodes()
	
	
	
	public function nodeSemanticsMatches($fieldName, $fieldValue)
	/* 	Locate all the nodes whose value in the specified semantic field matches the given string (as a substring.)
		RETURN an array of matches, sorted by the specified semantic field; each entry is an associative/enum array of at least 2 parts: [node ID, fieldName, any_other_semantic_fields].
			   In case of error, return false.
		This function is useful for searches.
	 */
	{
		$sql = "SELECT *
					FROM $this->nodesTable 
					WHERE `$fieldName` LIKE ? 
							$this->sqlANDClause
					ORDER BY $fieldName";
				
		$result = $this->db->selectSQL($sql, "%" . $fieldValue . "%");		// The "%" are SQL wildcards placed before and after the value
		
		if (! $result)
			return false;					// There was a database execution error
			
		return  $result->fetchall();		// Convert data set to an array

	} // nodeSemanticsMatches()
	



	public function semanticsLookupSingleField($nodeID, $fieldName)
	/* 	Look up and return the value of the specified semantic field for the given node.
			Example:  semanticsLookupSingleField(123, "`name`")
		
		A blank string is returned if no semantics are found, or in case of error.
	 */
	{
		/* Validate the parameter 
		 */
		if (! parameterValidation::validateInteger($nodeID))
			return "";		// Unable to look up a name for a bad node ID
		
		// Look up all the attributes of the specified node 
		$sql = "SELECT $fieldName
				FROM  $this->nodesTable
				WHERE ID = ?
						$this->sqlANDClause";
		//echo "SQL: $sql | nodeID: $nodeID<br>";
		//$this->db->debugLogSQL($sql, $nodeID);
		
		return $this->db->selectSQLOneValue($sql, $nodeID);		
	}
	
	
	public function semanticsLookup($nodeID, $fields = "*")
	/* 	Look up and return the semantics data of the specified node from its ID, as an array $fieldName => $fieldValue
		A blank string is returned if no semantics are found, or in case of error.
	 */
	{
		/* Validate the parameter 
		 */
		if (! parameterValidation::validateInteger($nodeID))
			return "";		// Unable to look up a name for a bad node ID
		
		// Look up all the attributes of the specified node 
		$sql = "SELECT $fields
				FROM  $this->nodesTable
				WHERE ID = ?
						$this->sqlANDClause";

		return $this->db->selectSQLFirstRecord($sql, $nodeID);		
	}
	


	public function updateNodeSemantics($nodeID, $newSemantics)
	/* 	Change the semantic values attached to the specified node, as directed.
		
		Example:  $newSemantics = array("name" => "someName" , "remarks" => "someRemarks" , "permissions" => "somePermissions")
		
		RETURN VALUE: 1 if the record was modified, 0 if not (0 indicates an attempt to update to the same values that the record already had.)
		
		In case of failure, an exception is thrown.
	 */
	{
		
		/* Validate the parameters
		 */
		 if (!parameterValidation::validatePositiveInteger($nodeID))
			throw new Exception("Node ID MUST be a positive (non-zero) integer; value given: $nodeID");
			
		if (! $newSemantics)
			throw new Exception("Missing semantic data specified for the update operation");
			
		if (! is_array($newSemantics))
			throw new Exception("The new semantic data for the node must be specified as an array");
			
		/*
		if (!parameterValidation::validatePositiveInteger($nodeID))
			return "Node ID MUST be a positive (non-zero) integer; value given: $nodeID";
			
		if (! $newSemantics)
			return "No new semantic data specified for the update operation";
			
		if (! is_array($newSemantics))
			return "The new semantic data for the node must be specified as an array"; 
		*/
					
		/* Carry out the updating of the node's semantics
		 */
		 
		$setStatement = "";
		$bindings = array(":nodeID" => $nodeID);
		$firstItem = true;
		foreach ($newSemantics as $fieldName => $fieldValue)  {
			if (! $firstItem)
				$setStatement .= ", ";
			else 
				$firstItem = false;

			$setStatement .= "`$fieldName` = :$fieldName";		// This will gradually build into a string such as "`f1` = :f1, `f2` = :f2, `f3` = :f3"

			$bindings[":$fieldName"] = $fieldValue;
		}
			
		$sql = "UPDATE $this->nodesTable 
					SET $setStatement
					WHERE ID = :nodeID";
		
		$numberAffected = $this->db->modificationSQL($sql, $bindings);
	
		//if ($numberAffected != 1)
			//return "No changes made to the node's semantic data";
		
			
		// If we get thus far, the operation was successful
		return $numberAffected;

	} // updateNodeSemantics()
	


	public function fetchChildren($nodeID, $sortby = false)
	/* 	Fetch all the children of the given node, optionally sorted as requested, within the constraints of the optional "SQLclause" property.
		Return a (possibly empty) an associative/enum array of elements that contain: [childID, <all the semantic fields>]
		In case of failure, false is returned.
		Note: it's convenient to have an array as a result, because it can be rewound if desired, with the reset() function.
	 */
	{
		// For now, re-using stored values is turned off (because unclear how to re-use if sort order may have changed)
		//if (isset($this->children[$nodeID]))				// Using isset() because the value might be present but null
			//return  $this->children[$nodeID];				// If it has already been computed and saved, no need to re-compute.  BE CAREFUL ABOUT POTENTIALLY INCORRECT/OUTDATED INFO STORED HERE!!
	
	
		// Look up all the children of this node (their ID's, names and remarks)
		$sql = "SELECT *
				FROM $this->edgesTable JOIN $this->nodesTable
					ON $this->edgesTable.childID = $this->nodesTable.ID
					WHERE parentID = ?
						$this->sqlANDClause";
		
		if ($sortby)
			$sql .= " ORDER BY $sortby";

		//echo $this->db->debugPrintSQL($sql, $nodeID);
		
		$result = $this->db->selectSQL($sql, $nodeID);
		
		if ($result === false)
			return false;		// Failure was encountered in running the SQL statement
			
		$resultArray = $result->fetchall();					// Save the result (a traversable object) into an array, possibly empty
		
		$this->children[$nodeID] = $resultArray;			// Save in the "children" property for possible future use
		
		return $resultArray;

	} // fetchChildren()
	
	
	
	public function fetchParents($nodeID, $sortby = false)
	/* 	Fetch all the parents of the given node, optionally sorted as requested, within the constraints of the optional "SQLclause" object property.
		RETURN a (possibly empty) associative/enum array of elements; each of them is an array that contains: [parentID, <all the semantic fields>]
		In case of failure, false is returned.
		Note: it's convenient to have an array as a result, because it can be rewound if desired, with the reset() function.
	 */
	{
		if ($nodeID == $this->CATEGORY_ROOT_NODE)	// If it is the root...
			return  array();						// ... return an empty array

		// For now, re-using stored values is turned off (because unclear how to re-use if sort order may have changed)
		//if (isset($this->parents[$nodeID]))			// Using isset() because the value might be present but null
			//return  $this->parents[$nodeID];		// If it has already been computed and saved, no need to re-compute.  BE CAREFUL ABOUT POTENTIALLY INCORRECT/OUTDATED INFO STORED HERE!!


		/* If we get here, we're NOT at the root.  Look up all the parents of this node (their ID's, names and remarks), within the constraints of the optional "SQLclause" object property
		 */

		$sql = "SELECT *
				FROM $this->edgesTable JOIN $this->nodesTable
					ON $this->edgesTable.parentID = $this->nodesTable.ID
					WHERE childID = ?
						$this->sqlANDClause";
					
		if ($sortby)
			$sql .= " ORDER BY $sortby";

		//echo $this->db->debugPrintSQL($sql, $nodeID);
		
		$result = $this->db->selectSQL($sql, $nodeID);
		
		if ($result === false)
			return false;		// Failure was encountered in running the SQL statement

		$resultArray = $result->fetchall();			// Save the result (a traversable object) into an array
		
		$this->parents[$nodeID] = $resultArray;		// Also save for future reference
		
		return $resultArray;

	} // fetchParents()
	
	
	public function numberParents($nodeID)
	/* 	Return the number of parents of the given node, within the constraints of the optional "SQLclause" object property.
		In case of failure, false is returned.
	 */
	{
		if ($nodeID == $this->CATEGORY_ROOT_NODE)	// If it is the root...
			return  0;								// ... the roor has no parents


		/* If we get here, we're NOT at the root.  Count all the parents of this node, within the constraints of the optional "SQLclause" object property.
		 */

		$sql = "SELECT COUNT(*)
				FROM $this->edgesTable JOIN $this->nodesTable
					ON $this->edgesTable.parentID = $this->nodesTable.ID
					WHERE childID = ?
						$this->sqlANDClause";
		
		$result = $this->db->selectSQLOneValue($sql, $nodeID);
		
		return $result;
	}
	


	public function fetchSiblings($nodeID, $sortby = false)
	/* 	Fetch all the siblings of the given node, optionally sorted as requested, within the constraints of the optional SQL WHERE clause.
		Return a (possibly empty) associative/enum array of elements, each of whis is an associative array that contains: [ID, name, remarks].
		In case of failure, false is returned.
		Note: it's convenient to have an array as a result, because it can be rewound if desired, with the reset() function.
	 */
	{
		if ($nodeID == $this->CATEGORY_ROOT_NODE)		// If it is the root...
			return  array();							// ... return an empty array

		// For now, re-using stored values is turned off (because unclear how to re-use if sort order may have changed)
		//if (isset($this->siblings[$nodeID]))		// Using isset() because the value might be present but null
			//return  $this->siblings[$nodeID];		// If it has already been computed and saved, no need to re-compute.  BE CAREFUL ABOUT POTENTIALLY INCORRECT/OUTDATED INFO STORED HERE!!


		/* If we get here, we're NOT at the root.  Look up all the siblings of this node (their ID's, names and remarks)
		 */

		// 		On s1, go up from the child (the given node) to its parents, and use s2 to locate their children.
		//		Use "$this->nodesTable" to look up name of all the found siblings (s2.childID)
		//		The  (s2.childID != :nodeID) is to avoid returning the given element as its own sibling
		//		The DISTINCT clause is needed because a sibling might re-occur from different parents
		
		$sql = "SELECT DISTINCT s2.`childID` AS `siblingID`, $this->nodesTable.*
				FROM  $this->edgesTable AS s1  JOIN $this->edgesTable AS s2  JOIN $this->nodesTable
					ON s1.parentID = s2.parentID
					AND s2.childID = $this->nodesTable.ID
					WHERE s1.childID = :nodeID
						AND s2.childID != :nodeID
						$this->sqlANDClause";	
					
		if ($sortby)
			$sql .= " ORDER BY $sortby";				
		//echo $this->db->debugPrintSQL($sql, array(":nodeID" => $nodeID));
		
		$result = $this->db->selectSQL($sql, array(":nodeID" => $nodeID));
		
		if ($result === false)
			return false;		// Failure was encountered in running the SQL statement

		$resultArray = $result->fetchall();		// Save the result (a traversable object) into an array
		
		$this->siblings[$nodeID] = $resultArray;
		
		return $resultArray;
		
	} // fetchSiblings()
	
		
	
	
	function locateDanglingEdges()
	// Locate all the edges (ideally none) that lack a corresponding node at either (child or parent) end.
	// Return an array of all such edges
	{
		$danglingArray = array();
		
		$sql = "SELECT childID, parentID FROM $this->edgesTable WHERE childID not in (select ID from $this->nodesTable)";
		$result = $this->db->selectSQL($sql);
		if ($result)
			$danglingArray = $result->fetchall();	
		
		$sql = "SELECT childID, parentID FROM $this->edgesTable WHERE parentID not in (select ID from $this->nodesTable)";
		$result = $this->db->selectSQL($sql);
		if ($result)
			$danglingArray = array_merge($danglingArray, $result->fetchall());
			
		return $danglingArray;

	} // locateDanglingEdges()
	

	
	
	function traverseGraphAndSave($sortby) 
	/* 	Traverse the graph from its root, and build (or rebuild) the array of graph-traversal information, 
		stored as the "graphTraversal" property, and saved in the table whose name is stored in the property "traversalTable".
		
		Traversal is done in pre-order (node first, then the traversals of its children in turn.)
		
		RETURN VALUE: an empty string if successful, or an error message in case of errors.
	 */ 
	{	
		$errorMessage = $this->traverseGraph($sortby);	// To set/update the array stored in the "graphTraversal" property
		
		if ($errorMessage)
			return $errorMessage;
		
		$sql = "DELETE FROM $this->traversalTable";
		$this->db->modificationSQL($sql);
		
		$sql = "ALTER TABLE $this->traversalTable AUTO_INCREMENT=1";
		$this->db->modificationSQL($sql);
		
		
		$sql = "INSERT INTO $this->traversalTable (`nodeID`, `depth`) VALUES ";

		foreach($this->graphTraversal as $entry)  {
			$ID = $entry["ID"];
			$depth = $entry["depth"];
			
			$sql .= "($ID,$depth),";
		}
		$sql = substr($sql, 0, -1);			// Drop the last character
		
		if ($this->db->modificationSQL($sql) == -1)
			return "Unable to create a table of graph-traversal information";
		else
			return "";
	}
	

	/*
	function graphTraversalArray()
	// Return the current array with the graph traversal info; if missing, computer it first

	{
		if (! $this->graphTraversal)
			$this->traverseGraph();
			
		return $this->graphTraversal;
	}
	*/
	
	
	function traverseGraph($sortby) 
	/* 	Traverse the graph from its root, and build (or rebuild) the array of graph-traversal information, 
		stored as the "graphTraversal" property.
		Traversal is done in pre-order (node first, then the traversals of its children in turn.)
		
		RETURN VALUE: an empty string if successful, or an error message in case of errors.
	 */
	{	
		/* First, load into an array (edgeArray) the entire contents of the edgesTable, appropriately sorted
		 */

		// The JOIN with the nodesTable is done in order to sort entries that have the same childSequenceNo, by name
		$sql = "SELECT parentID, childID 
					FROM $this->edgesTable JOIN $this->nodesTable
					ON $this->edgesTable.childID = $this->nodesTable.ID
					ORDER BY `parentID`";
					
		if ($sortby)
			$sql .= ", $sortby";
					
		$result = $this->db->selectSQL($sql);
		
		if (! $result)
			return  "Unable to look up the graph edge data";
			
		$this->edgeArray = $result->fetchall(\PDO::FETCH_ASSOC);		// The \ indicates the global namespace


		// Clear out the old graphTraversal array
		unset($this->graphTraversal);
				
		// Build the graphTraversal array, starting at the graph's root (root node, at depth 0)
		return $this->recursiveGraphTraversalInMemory($this->CATEGORY_ROOT_NODE, 0);
		
	} // traverseGraph()



	function recursiveGraphTraversalInMemory($nodeID, $depth)
	/*  Recursively traverse the directed graph, starting at the specified node, and update accordingly the array 
		in the "graphTraversal" property, adding to it info about the specified node (its ID and depth) and, recursively, about its children.
		
		Information about the node's children is expected to be in memory, stored in the property "edgeArray".
		To catch possible runaway recursions, the recursion is aborted if a depth of $MAX_DEPTH is reached.
		
		ARGUMENTS
				nodeID: 	starting point
				depth:		depth (in the graph traversal) of the starting-point node
				
		RETURN VALUE: an empty string if successful, or an error message in case of errors.
		
		SIDE EFFECTS: update the array in the "graphTraversal" property
	 */
	{
		if ($depth  >  $this->MAX_DEPTH)
			return "Max recursion depth reached at node $nodeID - traversal won't go past that. ";	// Trim anything that looks like a runaway recursion
			
		$this->graphTraversal[] = array("ID" => $nodeID, "depth" => $depth); 			// Append this array to the array graphTraversal
		
		// Locate all of the node's children, from in-memory data
		$errorMessage = "";
		foreach($this->edgeArray as $edge)  {
			if ($edge["parentID"] == $nodeID)  {
				$childID = $edge["childID"];
				$errorMessage .= $this->recursiveGraphTraversalInMemory($childID, $depth+1);		// Recursive call
			}
		}
		
		return $errorMessage;		// ...recursive exit (applies when the foreach loop is empty)
	}
	



	/***********************************
	    		PRIVATE METHODS 
	 ***********************************/	
	
	private function addNode($semantics = false)
	/* 	Add a new Node, and optionally populate its semantics ("payload").
	
		If the optional $semantics argument is present, it is expected to be an array of [fieldName => fieldValue]
		elements to be added in the table of nodes for the newly-created record.  Example:  ["name" => "myName" , "numField" => 123]   Do NOT place SQL quotes around the names!
		
		RETURN VALUE: In case of failure, return an error message;
		if successful, a blank string is returned, and the "methodResults" property is set to the AutoIncrement value for new node ID 
		(the first value for a new graph is 1)
		
		This method is private because adding a new node by itself will break the graph's traversability.  The caller method will ensure
		that an eldge is also added as part of an atomic operation.  TO-DO: turn the process into a database transaction that can be rolled back
	 */
	{
		$this->methodResults = false;

		if ($semantics)  {
			// If present, validate this argument
			if (! is_array($semantics))
				return "Optional semantic data, if present, must be specified as an array";
			
			  
			$allFieldNames = "";
			$allplaceHolders = "";
			$bindings = array();
			$firstItem = true;
			foreach ($semantics as $fieldName => $fieldValue)  {
				if (! $firstItem)  {
					$allFieldNames   .= ", ";
					$allplaceHolders .= ", ";
				}
				else 
					$firstItem = false;

				$allFieldNames   .= "`$fieldName`";			// This will gradually build into a string such as "`f1`, `f2`, `f3`"
				$allplaceHolders .= ":$fieldName";			// This will gradually build into a string such as ":f1, :f2, :f3"

				$bindings[":$fieldName"] = $fieldValue;
			}
				  
			// Append the new node to the database, also setting the extra fields
			$sql = "INSERT INTO $this->nodesTable ($allFieldNames) 
										   VALUES ($allplaceHolders)";
			
		}
		else  {  // If not storing semantic data, just add a blank record (which will have an autoincremented ID)
		
			// Append the new node to the database
			$sql = "INSERT INTO $this->nodesTable () VALUES()";															   
		}

		
		// Run the INSERT SQL
		$numberAffected = $this->db->modificationSQL($sql, $bindings);
		
		if ($numberAffected != 1)
			return "Unable to add the new node to the '$this->nodesTable' table";

	

		// Look up the AutoIncrement ID assigned to the new node (the child)
		$childID = $this->db->retrieveInsertID();
		
		if ($childID === false)
			return "Unable to retrieve the ID assigned to the newly-created node";

		
		// If we get thus far, the operation was successful
		$this->methodResults = 	$childID;
		return "";

	} // addNode()


} // class "directedGraph"
?>