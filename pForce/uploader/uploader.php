<?php
/* 	
	Last revised 9/30/2019.  Distributed as part of the "pForce Web Framework".  For more info: https://github.com/BrainAnnex/pForce

	Class to facilitate File Uploads
	
	
	DEPENDENCIES:
	
		- logging


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



class uploader
{
	/********************************************
	
				FUNCTIONS FOR UPLOAD
		
	 *********************************************/
	
	// PUBLIC PROPERTIES
	public $logger;				// Object of class "logging".  Use to log messages (diagnostics, etc) on the server
	
	// PRIVATE PROPERTIES
	private $errorMessage;		// Last error message generated by any method
		
	
	
	/****************************
	     	PUBLIC METHODS 
	 ****************************/
	
	public function errorInfo()
	// Return the last error message generated by any method
	{
		return $this->errorMessage;
	}
	
	

	public function uploadFiles($upload_fileset_info, $dest_dir, $nameHandler, $postUploadHandler, $verbose = false)  
	/* 	Attempt to upload ONE OR MULTIPLE files to the specified destination directory.
		Whenever each of the uploads is initially successful (got stored in a temporary file on the server), invoke the optional function stored in $nameHandler
	
		RETURN VALUE: the number of successful uploads.  In case of error, the property "errorMessage" is set to an explanation of the error
		
		If any upload fails, the uploader skips to the next file upload
		
		USAGE:
			For a SINGLE file upload:
				A) the calling form has a form with an entry such as:	<INPUT type='file' name='myFile'>	  
																
				B) the form-processing script contains:		       		$upload_fileset_info = $_FILES["myFile"];
	
			For a MULTIPLE file uploads:
				A) the calling form has a set of entries such as:		<INPUT type='file' name='myFileList[]'>			(one per file to upload)
																
				B) the form-processing script contains:		        	$upload_fileset_info = $_FILES["myFileList"];
				
				
		ARGUMENTS:
			$upload_fileset_info	(see USAGE, above)
			$dest_dir 				a relative or absolute path in the file system, incl. the final "/"  .  (For the same folder, use "./") Any already-existing file with the same name will get over-written
			$nameHandler			If FALSE, it indicates "just use the same original name for the uploaded file"
									If TRUE, it's expected to be the name of a function to invoke with 4 arguments: f($name, $tmp_name, $type, $size) 
												$name			the original name of the file being uploaded
												$tmp_name		the temporary filename assigned by the server mid-upload (with full path)
												$type			the uploaded file's MIME type
												$size			size of uploaded file's size in Bytes
												
									Tha function needs to return a file name to use for permanent file storage (possibly the same as the original name), or false in case of error (for example, a database error).  
									
									That function is also a convenient place for the invoking script to do all the necessary database operations.
									
			$postUploadHandler		name of a function that gets invoked upon a successful file upload (for example, to take care of additional database update).  Use FALSE to indicate no function
			$verbose				whether to print out (in addition to logging) any information or error messages
		
		$upload_fileset_info is an array of 5 elements, called: 
			  "name",
			  "type",
			  "tmp_name",
			  "error",
			  "size"
		Each element is either a value (in case of single uploads) or an array whose dimension equals the numbers of files being uploaded.
			
			
		EXAMPLE of $upload_fileset_info in case of a SINGLE UPLOADED FILE:	 
			Array
			(
				[name] => sample-file.doc
				[type] => application/msword
				[tmp_name] => /tmp/path/phpVGCDAJ
				[error] => 0
				[size] => 450
			)
			
		EXAMPLE of $upload_fileset_info in case of MULTIPLE FILES:
				Array
				(
					[name] => Array
						(
							[0] => sample-file1.doc
							[1] => focus.jpg
						)
				
					[type] => Array
						(
							[0] => application/msword
							[1] => image/jpeg
						)
				
					[tmp_name] => Array
						(
							[0] => /tmp/phprS2u1w
							[1] => /tmp/php3oOgSr
						)
				
					[error] => Array
						(
							[0] => 0
							[1] => 0
						)
				
					[size] => Array
						(
							[0] => 22685
							[1] => 30733
						)
				)
	 */
	{
		$number_successful_uploads = 0;			// No successful uploads yet performed
		
		$this->logger->insertSeparator();
		
		$this->logger->logMessage(1, "Invoked uploadFiles()");
		
		// Validate that $upload_fileset_info is an array
		if (!is_array($upload_fileset_info))  {
			$this->logger->logMessage(2, "The Upload Fileset Data is NOT an array!  No file upload specified");
			if ($verbose)
				echo "<br>The Upload Fileset Data is NOT an array!  No file upload specified<br>";
			
			return $number_successful_uploads;		
		}	
		
		
		// For debugging, place the fileset array into a string, and log it	
		$uploadInfo = print_r($upload_fileset_info, true);
		$this->logger->logMessage(2, "Upload Fileset Data: $uploadInfo");
		if ($verbose)
			echo "Upload Fileset Data: $uploadInfo<br>";
	
	
		if (!is_array($upload_fileset_info["error"]))  {	// Single-file upload
			$this->logger->logMessage(2, "SINGLE-file upload to directory '$dest_dir'");
			
			// Extract the data for this file upload
			$name = $upload_fileset_info["name"];	
			$type = $upload_fileset_info["type"];
			$size = $upload_fileset_info["size"];
			$tmp_name = $upload_fileset_info["tmp_name"];
			$error = $upload_fileset_info["error"];
				
			// Finalize the upload
			$number_successful_uploads = $this->uploadSingleFile($name, $type, $size, $tmp_name, $error, $dest_dir, $nameHandler, $postUploadHandler, $verbose);
		}
		else  {							// Multiple-file upload
		
			$this->logger->logMessage(2, "MULTIPLE-file upload to directory '$dest_dir'");
				
			/* 
				Loop over the "error" array, and extract the corresponding info (with the same index offset) in the other 4 arrays
			 */
			foreach ($upload_fileset_info["error"]  as  $index => $error) {
				$name = $upload_fileset_info["name"][$index];
				if ($name == "")  {		// This wil be the case if a file was skipped over in a form with multiple-file upload
					$this->logger->logMessage(2, "No file upload requested for the $index-th upload");
					if ($verbose)
						echo "<br>No file upload requested for the $index-th upload<br>";
						
					continue;		// If no upload file was given, skip to the next entry
				}
				
				// Extract the data for this file upload	
				$type = $upload_fileset_info["type"][$index];
				$size = $upload_fileset_info["size"][$index];
				$tmp_name = $upload_fileset_info["tmp_name"][$index];
				
				// Finalize the upload of this file
				$status = $this->uploadSingleFile($name, $type, $size, $tmp_name, $error, $dest_dir, $nameHandler, $postUploadHandler, $verbose);
				
				$number_successful_uploads += $status;
			} // foreach
		}
	
		
		$this->logger->logMessage(2, "Number of successful uploads: $number_successful_uploads");
		if ($verbose)
			echo "<br><span style='color:purple'><b>Number of successful uploads: $number_successful_uploads</b></span><br>";
		
		return $number_successful_uploads;
		
	} // uploadFiles()
	
	
	
	public function uploadSingleFile($name, $type, $size, $tmp_name, $error, $dest_dir, $nameHandler, $postUploadHandler, $verbose = false)  
	/* 	The first 5 arguments are the standard $_FILES array component.  For an explanation of the remaining arguments, see uploadFiles()
		If a file with the requested name already exists, it gets overwritten.
		Return 1 if upload is successful, or 0 if not (i.e. the number of successful uploads).  In case of error, the property "errorMessage" is set to an explanation of the error
	 */
	{
		$this->logger->logMessage(2, "Name of file being upload: \"$name\"");
		$this->logger->logMessage(3, "Upload status code: $error | File type: '$type' | File size: " . number_format($size / 1024, 1) . " KBytes | Upload temp name: '$tmp_name'");
		if ($verbose) {
				echo "&nbsp;&nbsp;&nbsp;&nbsp;* Name of file being uploaded: <b>\"$name\"</b><br>";
				echo "&nbsp;&nbsp;&nbsp;&nbsp;Upload status code: $error | File type: '$type' | File size: " . number_format($size / 1024, 1) . " KBytes | Upload temp name: '$tmp_name'<br>";
		}
			
		if ($error != UPLOAD_ERR_OK) {	
			// If upload FAILED, spell out the nature of the problem
			$this->logger->logMessage(2, "Upload FAILED, for this reason : " . $this->errorUploadExplanation($error));
			if ($verbose)
				echo "&nbsp;&nbsp;&nbsp;&nbsp;Upload FAILED, for this reason : " . $this->errorUploadExplanation($error);
		}
		else  {
			// If upload was SUCCESSFUL
			$this->logger->logMessage(3, "Initial upload is successful");
			if ($verbose)
				echo "&nbsp;&nbsp;&nbsp;&nbsp;<b>Initial upload is successful</b><br>";	
				
			// Set the destination's filename, using the provided handler function
			if (! $nameHandler)
				$dest_filename = $name;
			else  {
				$dest_filename = $nameHandler($name, $tmp_name, $type, $size);
				if ($dest_filename === false)  {
					$this->errorMessage = "Name Handler failed to process the file named \"$name\"";
					$this->logger->logErrorMessage("Name Handler failed to process the file named \"$name\"");
					return 0;	// File was not uploaded
				}
			}
				
			// Set the destination's path
			$dest_full_path = $dest_dir . $dest_filename;
				
			$this->logger->logMessage(3, "Full path of Target destination: '$dest_full_path' ");
			if ($verbose)
				echo "&nbsp;&nbsp;&nbsp;&nbsp;Full path of Target destination: '$dest_full_path' ";
				
			// Move the temporary upload file to its desired destination on the server
			$status = move_uploaded_file($tmp_name, "$dest_full_path");		// If a file with that name already exists, it gets overwritten
			
			if (! $status)  {
				$this->errorMessage = "Couldn't move the file from its temporary upload destination to the desired destination";
				$this->logger->logErrorMessage("Couldn't move the file from its temporary upload destination to the desired destination.");
				return 0;		// File was not uploaded
			}
				
			$this->logger->logMessage(2, "-> moved successfully to final destination");
			if ($verbose)
				echo "&nbsp;&nbsp;<b> -> moved successfully to final destination</b><br>";
				
				
			// Perform an optional final round of processing, if specified
			if ($postUploadHandler)
				return $postUploadHandler($name, $type, $size);
			else
				return 1;		// File correctly uploaded
				
		} // if ($error != UPLOAD_ERR_OK)
	
	} // uploadSingleFile()
	
	
	
	
	/****************************************
				PRIVATE METHODS
	 ****************************************/
	
	private function errorUploadExplanation($errorCode)
	/* 	Return a string with an explanation of the given upload error code.
		See http://php.net/manual/en/features.file-upload.errors.php
	 */
	{
		switch ($errorCode)  {
			case (UPLOAD_ERR_INI_SIZE):		// Value: 1
				return "The uploaded file exceeds the upload_max_filesize directive in php.ini";
				break;
				
			case (UPLOAD_ERR_FORM_SIZE):	// Value: 2
				return "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
				break;
				
			case (UPLOAD_ERR_PARTIAL):		// Value: 3
				return "The uploaded file was only partially uploaded";
				break;
				
			case (UPLOAD_ERR_NO_FILE):		// Value: 4
				return "No file was uploaded";
				break;
				
			case (UPLOAD_ERR_NO_TMP_DIR):	// Value: 6
				return "Missing a temporary folder";
				break;
				
			case (UPLOAD_ERR_CANT_WRITE): 	// Value: 7
				return "Failed to write file to disk";
				break;
				
			case (UPLOAD_ERR_EXTENSION):	// Value: 8
				return "A PHP extension stopped the file upload. PHP does not provide a way to ascertain which extension caused the file upload to stop; examining the list of loaded extensions with phpinfo() may help";
				break;
			
			default:
				return "Unknown error code: $errorCode";
				break;
		}
	}
	
	
} // class "upload"
?>