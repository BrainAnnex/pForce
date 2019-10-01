<?php
/* 
  	Last revised  9/29/2019.  Distributed as part of the "pForce Web Framework".  For more info: https://github.com/BrainAnnex/pForce
 
	Class to log SYSTEM MESSAGES (such as alerts or diagnostics) into a text file or an HTML file
 	
 	
 	DEPENDENCIES:  None
 
 
 	EXAMPLE OF USAGE:
 
 		$myLog = new logging("logFile.htm");
		
		$myLog->insertSeparator();
		$myLog->fileLogging("Operation started");
		$myLog->logMessage(1, "some detail", "font-weight:bold");
		$myLog->logErrorMessage("Operation FAILED");


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


interface loggingInterface
{
    public function fileLogging($msg);
    public function insertSeparator();
	public function logMessage($level, $msg, $style = "");
	public function logErrorMessage($msg);
}




class logging implements loggingInterface
{
	// ======================================================= START OF CONFIGURABLE PART =======================================================
	public $logFile;					// Name of file where to store the log messages.  If file already exists, the message gets appended; otherwise, the file gets created
	public $htmlLogging = true;			// Flag indicating whether the log file is meant to be an HTML file; if false, it will be regarded as a text file
	public $style = "";					// Optional HTML styling to apply to the message; if present, a <span> tag is used
	public $indentAmount = 0;			// 0 indicates no indentation; higher integers represent progressively-increasing indentation (in points for HTML messages, or number of blanks for text ones)
	public $indentIncrementHTML = 15;	// Indent amount , relative to the previous message "level", in points (for HTML messages only)
	public $indentIncrementText = 3;	// Indent amount , relative to the previous message "level", in characters (for text messages only)
	public $timeStamp = true;			// Whether to timestamp the message
	public $extraPrefix = "";			// Optional extra prefix (at the very beginning)
	public $separator = "_____________________________________________________________________________________________________________________";
	
	// PRIVATE PROPERTIES
	private $newline = "\r\n";			// The default "\r\n" somehow worked well for Linux webhosts(?)  Change as needed on other OS -> Windows: '\r\n' , Unix/Linux: '\n'
	// ======================================================= END OF CONFIGURABLE PART =======================================================
	
	
		
		
	/***************************** 
			  CONSTRUCTOR
	 *****************************/

	function  __construct($logFilename)  
	{
		if ($logFilename)  {		// A simple check for the presence of a name; TO-DO: check whether the filename is legit
			$this->logFile = $logFilename;
			return true;
		}
		else
			return false;
	}
	
	
	
	/***************************** 
			PUBLIC METHODS
	 *****************************/
	 
	public function fileLogging($msg)
	/* 	LOW-level function to append the given message to the log file (or to create a log file, if not present.)
		A newline is automatically added.
		If htmlLogging is enabled, then any HTML in the message (which could mess up the log file's viewing) is protected with htmlentities
		
		The message is optionally modified by:
				- the string: "date time", where date is mm/dd/yy
				- an indentation
				- a user-specified prefix
				- a user-specifief HTML style
				
		RETURN VALUE: true if successful, or false if not.
	 */
	{
		// Protect any HTML in $msg that could mess up the log file's viewing
		if ($this->htmlLogging)
			$msg = htmlentities($msg);
		
		

		/*	Handle optional message indentation and optional message styling
		 */
		
		if ($this->indentAmount == 0)  
		{							
			// No indent (for example, to indicate an important top-level entry)		 
			if ($this->htmlLogging  &&  $this->style != "")
				$msg = "<span style='" . $this->style . "'>" . $msg . "</span>";	// Apply the given styling to the message
		}
		else
		{	
			// If an indent was requested (for example, to indicate a sub-entry)
			if ($this->htmlLogging)	 {		// For HTML message, use CSS to indent
				if ($this->style != "")
					$msg = "<span style='margin-left:" . $this->indentAmount . "px;" . $this->style ."'>" . $msg . "</span>";	// Indent + styling
				else
					$msg = "<span style='margin-left:" . $this->indentAmount . "px'>" . $msg . "</span>";						// Just indent
			}
			else  {									
				// For text messages, insert as many blanks as specifided by the indent amount
				$prefix = "  " . str_repeat(" ", $this->indentAmount);
				$msg = $prefix . $msg;
			}
		}
	  
	
	
		/*	Handle optional timestamp prefix
		 */
		 
		if ($this->timeStamp)  {		
			$now = @date('m/d/y H:i:s');		// Timestamp, in format "date time", where date is mm/dd/yy
			$msg = $now . " " . $msg;			// Prefix the timestamp, followed by a blank
		}
		
		
		/*	Handle optional extra prefix (at the VERY front)
		 */
		if ($this->extraPrefix != "")
			$msg = $this->extraPrefix . $msg;	// Optional extra prefix
		
		
		/*	Handle the new line
		 */
		if ($this->htmlLogging)
			$msg .= "<br>";						
			
		$msg .= $this->newline;
		

		$status = $this->logIntoFile($msg);		// Perform the file-append operation
		
		return $status;
	}

	
	public function insertSeparator()
	// Append the standard separator (and a new line) to the log file
	{
		if ($this->htmlLogging)
			$msg = $this->separator . "<br>";
		else
			$msg = $this->separator;					
			
		$msg .= $this->newline;
		
		$this->logIntoFile($msg);
	}
	
	
	
	public function logMessage($level, $msg, $style = "")
	/* 	HIGH-level function to log the given text message to the designated log file (whose name is stored in the class property "logFile").  If the log file doesn't exist, it gets created.
		A new line is added after each entry.
		
		ARGUMENTS:
			level		An integer indicating the "importance": 0 (most important), 1, 2, ...
			msg			The message to log
			style		Optional HTML styling to apply to the message, using a <span> tag.  Do NOT include the <span> tag
			
		RETURN VALUE: true if successful, or false if not.
	 */
	{
		if ($level == 0)  {					// Highest-imporance message
			$this->insertSeparator();
			$msg = "~~~~ ". $msg;
		}
		else
			$level += 1;	// To better differentiate between non-indent and indent entries

		if ($this->htmlLogging)
			$this->indentAmount = $this->indentIncrementHTML * $level;
		else
			$this->indentAmount = 2 + $this->indentIncrementText * $level;
			
		$this->style = $style;
		
		return $this->fileLogging($msg);
	}
		
	
	public function logErrorMessage($msg)
	/*	Log an error message.  Same as regular logging, but with more emphasis, and the backtrace of the call stack
	 */
	{
		$this->logMessage(0, "************** ERROR: $msg *****************", "color:red");
		
		$this->logDebugBacktrace();
	}
	
	
	
	
	/*****************************
			PRIVATE METHODS
	 *****************************/
	 
	private function logIntoFile($msg)
	/* 	Perform the actual file-append operation.  The specified string is appended to the file specified in the attribute "logFile".
		Return true if successful, or false if not.
	 */
	{
		if (! $this->logFile)
			return false;										// If a log file isn't specified, no action is taken
			
		if (! $msg)
			return false;										// Missing or empty message
	
		$fp = fopen($this->logFile, 'a');						// If the file doesn't exist, it gets created
		
		if ($fp)  {
			$result = fwrite($fp, $msg);						// Perform the actual writing
			fclose($fp);
			
			if ($result == false)
				return false;	// fwrite failed, or wrote 0 bytes
			else
				return true;
		}
		else
			return false;		// fopen failed

	} // logIntoFile()
	


	private function logDebugBacktrace() 
	/* 	A pretty-print for logging debugging info from the call stack backtrace
		Example:
				#1 C:\myPath\file1.php(306): foo1()							[Most recent call]
				#2 C:\myPath\file2.php(294): someMethod->foo2()
				#3 C:\myPath\file2.php((70): otherMethod->someFunction()
		
		An alternative might be to use getTraceAsString()
	 */
	{
		$trace = debug_backtrace();		// Generate a backtrace of the call stack (an array of data; the 0-th element is the most recent call)
		
		//unset($trace[0]);				// Remove the call to this function from the stack trace	[Actually, leaving it in, makes it more readable]
		
		$this->logMessage(0, "Backtrace of error (most RECENT call at the TOP):");
		
		$count = 1;
		foreach($trace as $node) {
			// Prepare a readable line, such as: "#1 C:\myPath\file1.php(306): foo1()"
			
			$stackInfo = "#$count " . @$node['file'] . "(" . @$node['line'] . "): ";	// Under some circumstances, the 'file' and 'line' keys may be missing; @ suppresses notices
			
			if(isset($node['class'])) {
				$stackInfo .= $node['class'] . "->"; 
			}
			
			$stackInfo .= $node['function'] . "()";
			
			$this->logMessage(2, $stackInfo);
				
			
			if (sizeof($node['args']) == 0)			// $node['args'] is always an array, possibly empty
				$this->logMessage(4, "No arguments", "color:#CCC");
			else  {
				foreach($node['args'] as $i => $singleArg)  {
					if (is_array($singleArg))
						$singleArg = print_r($singleArg, true);
					elseif (is_object($singleArg))
						$singleArg = "[OBJECT]";
					// TO-DO: cover other variable types that cannot be simply converted into a string

					$argPosition = $i + 1;
					
					$this->logMessage(4, "Argument $argPosition: \"" . $singleArg . "\"", "color:#CCC");	// A test with try/catch (for the string converstion of $singleArg) didn't do any catching...
				}
			}
						
			$count++;
		}

		$this->insertSeparator();

	} // logDebugBacktrace()
	
}  // class "logging"
?>