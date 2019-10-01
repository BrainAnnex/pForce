<?php
/* 	
	Last revised 9/29/2019.  Distributed as part of the "pForce Web Framework".  For more info: https://github.com/BrainAnnex/pForce

	Classes to easily build HTML forms, as well as "Control Panels" consisting of a table of such forms (each of which is referred to as a "pane")
	
	2 CLASSES:  "controlPanel" and "formBuilder"
	
	
	DEPENDENCIES:  None


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



/**************************************************************************************************************************

												CLASS  controlPanel
	
 **************************************************************************************************************************/
 
class controlPanel  {
	/* A Control Panel is defined as a table of forms: 
			a series of rows and columns of (typically small) forms - each of which 
			is referred to as a "pane" (an object of class "formBuilder")
	 */
	public $paneTitleStyle;					// If provided, it will be used for each pane (containing a form)
	public $tdWrapOptions;					// Options for the <td> element of each cell (containing a form)
	public $nCols;							// Number of columns in the table of forms.  This attribute needs to be public, because in some cases
											//			(involving table cells spanning multiple rows) it must be modified during execution
	public $tableOptions = "border='1'";	// Styling for the larger table
	
	private $row = 1;			// Current row in the construction of the Control Panel
	private $col = 1;			// Current column
	private $CPhtml = "";		// HTML forming the Control Panel
	
	
	/* CLASS METHODS
	 */
	 
	function __construct($nCols)
	{
		$this->nCols = $nCols;
	}
	
	
	function addForm($form)
	// Add a form (an object of type "formBuilder") to the Control Panel being built
	{
		if ($this->paneTitleStyle)
			$form->titleStyle = $this->paneTitleStyle;		// Use the Control Panel style, if provided, for all the individual forms
			
		
		if ($this->col == 1)
			$this->CPhtml .= "<tr>";		// Start a new row
		
		
		// Start the cell (containing the form)
		if ($this->tdWrapOptions)
			$this->CPhtml .= "<td $this->tdWrapOptions>";
		else
			$this->CPhtml .= "<td>";
		
		
		// Generate the form (wrapped with a <td>) and append it to the Control Panel
		$this->CPhtml .= $form->generateHTML();
		
		$this->CPhtml .= "</td>";	// End the cell
		
		
		if ($this->col == $this->nCols)  {		// Terminate the row
			$this->CPhtml .= "</tr>\n";
			$this->col = 1;
			$this->row += 1;
		}
		else
			$this->col += 1;
	}
	
	
	function addGeneralPane($html)
	// Add a general pane (HTML consisting of <td> element and its content) to the Control Panel being built
	{
		if ($this->col == 1)
			$this->CPhtml .= "<tr>";		// Start a new row
		
		
		// Add the pane (it is expected to be wrapped with an <td> element)
		$this->CPhtml .= $html;
		
		
		if ($this->col == $this->nCols)  {		// Terminate the row
			$this->CPhtml .= "</tr>\n";
			$this->col = 1;
			$this->row += 1;
		}
		else
			$this->col += 1;
	}
	
	
	function generateCP()
	// Generate and return the Control Panel
	{
		return "<table $this->tableOptions>" . $this->CPhtml . "</table>\n";
	}
	
} // class "controlPanel"





/**************************************************************************************************************************

												CLASS  formBuilder
	
 **************************************************************************************************************************/

class formBuilder  {
	/* 	A class to build and return simple HTML forms, 
			especially suited for "Control Panels" (table of forms, each of which is referred to as a "pane").
			
		The main part of the form consists of 2 columns: one of labels and one of controls (such as text inputs or pulldown menus)
	 */


	// PUBLIC PROPERTIES
	public $title;					// Optional title to display just before the <form> element
	public $titleStyle = "font-weight:bold; color:brown; font-size:12px";	// Default style to be used for the title
	public $formStyle;				// CSS used on the form
	public $handler;				// URL of script that will handle the form
	
	
	// PRIVATE PROPERTIES (To-do: turn the remain public properties into private by providing setter methods)
	private $labels = array();		// Array of labels (shown to the left of the form controls)
	private $controls = array();	// Array of form controls.  Each control is an HTML string; for example:
									//		 "<input type='text' size='20' maxlength='50' name='myName' value='myValue'>"
	public $submitLabel = "Enter";	// Text to show on the Submit button
	private $method = "POST";		// Method ("POST" or "GET") to be used by the form to invoke its handler script
	private $submitOptions = "";	// Optional additional HTML attributes for the Submit button
	private $submitForm = true;		// Flag indicating whether the form's button will actually submit the form
	
	private $footer = "";			// Optional string shown next to (on the right of) the form's Submit button
	public $hiddenOptions;			// Array of HTML attributes for an optional <hidden> control. E.g.
									//		array("name='group' value='vert'" , 
									//			  "name='plugin' value='t'")
									//		If just 1 element, it may optionally be passed as a string instead
	
	
	/* CLASS METHODS
	 */
	 
	function __construct($title, $handlerScript, $method = "POST")  {		// CONSTRUCTOR
		$this->title = $title;					// Optional title (header text) to display just before the <form> element
		$this->handler = $handlerScript;		// URL of script that will handle the form.  Use NULL if employing a JS handler
		$this->method = $method;				// Method ("POST" or "GET") to be used by the form to invoke its handler script
	}
	
	
	public function addJS_handler($func)
	// Specify a JS handler function, invoked when the form's submit button is clicked;
	// 		it will be the JS handler's responsibility, if desired, to submit the form with document.testform.submit()
	//		An object containing the form is passed as argument to the JS function; for example, the function can extract the value of a field as:  formObject.someFieldName.value
	//		See https://www.javaworld.com/article/2077176/using-javascript-and-forms.html?page=2
	{
		$this->submitOptions = "onClick=" . $func . "(this.form)";
		$this->submitForm = false;
	}


	public function addHeaderLine($labelString)
	// Append a row with the specififed label and a blank control.  This pair might be used as a header at the top of the form, or as a mid-form sub-header/separator text
	{
		$this->addControl_General($labelString, null);
	}
	
	public function addFooter($text)
	// Insert the given text (possibly HTML) at the very bottom of the form, right after the Submit button
	{
		$this->footer = $text;
	}
	
	public function addBlankLine()
	// Append a row with a blank label and a blank control
	{
		$this->addHeaderLine("");
	}


	public function addControl_General($labelString, $controlHTML)	
	/* 	Append a row with the specififed label and a general control (its HTML text).
		Example of a text-input control:  "<input type='text' name='someName'>"
		More specific methods in this class are provided for many common controls (such as text, pulldown menus, etc) 
	 */
	{
		$this->labels[] = $labelString;
		
		$this->controls[] = $controlHTML;
	}
	
	
	public function addControlText($label, $controlName, $size = null)					// *** OBSOLETE (or maybe keep???   Change name to "addControl_SimpleText" ? Only used by plotting.php) ***
	// Append a row with the specififed label and a text control, optionally specifying the size of the text field
	{
		if ($size)
			$this->addControl_General($label, "<input type='text' name='$controlName' size='$size'>");
		else
			$this->addControl_General($label, "<input type='text' name='$controlName'>");
	}
	
	
	
	public function addControl_Text($label, $controlName, $parArray = false)
	/* 	Append a row with the specified label, and a text-input control with the given name and parameters.
	
		The optional parameters of the text-input control are specified using entries of the $parArray argument; allowed values: 
						"value"
						"size"
						"maxlength"
						"id"
						"onclick"
						"onchange"
						
						EXAMPLE: $parArray = array("size" => "3" , "maxlength" => 5, "value" => "123" , "onclick" => "myClickFunction()")
						
						For now, a few JS events are handled on a case-by-case.  
						Later on, maybe turn them into an array such as  "js" => array("onclick" => "myClickFunction()" , "onchange" => "myChangeFunction()")
														OR maybe:  "js" => "onclick='myClickFunction()' onchange='myChangeFunction()'"
	 */
	{
		$html = "<input type='text' name='" . $controlName . "'";		// Note that the <input> tag is not yet closed
		
		/* Process all the optional parameters, if any
		 */
		if (is_array($parArray))  {
			if ($parArray["id"]) 
				$html .= " id='". $parArray["id"] . "'";

			if ($parArray["size"]) 
				$html .= " size='". $parArray["size"] . "'";
				
			if ($parArray["maxlength"]) 
				$html .= " maxlength='". $parArray["maxlength"] . "'";
	
			if ($parArray["value"])  {
				$valueWithoutSingleQuotes =  str_replace("'" , '&#39;' , $parArray["value"]);
				$html .= " value='". $valueWithoutSingleQuotes . "'";
			}
			
			if ($parArray["onclick"]) 
				$html .= " onclick='". $parArray["onclick"] . "'";
			
			if ($parArray["onchange"]) 
				$html .= " onchange='". $parArray["onchange"] . "'";
		}
		
		$html .= ">";	// Close <input> tag
		
		$this->addControl_General($label, $html);

	} // addControl_Text()
	
	
	
	public function addControl_Checkbox($label, $controlName, $parArray = false)
	/* 	Append a row with the specififed label, and a chebox control with the given name and parameters.
		EXAMPLE:  addControl_Checkbox("Gift wrapped?", "gift", array("checked" => true))
	
		Optional parameters are specified using elements of the $parArray argument; allowed values: 
						"checked"
						"onclick"
						"onchange"
						EXAMPLE: $parArray = array("size" => "3" , "maxlength" => 5, "value" => "123" , "onclick" => "myClickFunction()")
						
						For now, a few JS events are handled on a case-by-case.  
						Later on, maybe turn them into an array such as  "js" => array("onclick" => "myClickFunction()" , "onchange" => "myChangeFunction()")
														OR maybe:  "js" => "onclick='myClickFunction()' onchange='myChangeFunction()'"
	 */
	{
		$html = "<input type='checkbox' name='" . $controlName . "'";		// Note that the <input> tag is not yet closed
		
		/* Process all the optional parameters, if any
		 */
		if (is_array($parArray))  {
			if ($parArray["checked"]) 
				$html .= "  checked";
			
			if ($parArray["onclick"]) 
				$html .= " onclick='". $parArray["onclick"] . "'";
			
			if ($parArray["onchange"]) 
				$html .= " onchange='". $parArray["onchange"] . "'";
		}
		
		$html .= ">";	// Close <input> tag
		
		$this->addControl_General($label, $html);

	} // addControl_Checkbox()
	
	
	
	public function addControl_Password($label, $controlName, $parArray = false)
	/* 	Append a row with the specififed label, and a password-input control.
	
		Optional parameters are specified using elements of the $parArray argument; allowed values: 
						"size"
						"maxlength"
						"id"
						"onclick"
						"onchange"
						EXAMPLE: $parArray = array("size" => "3" , "maxlength" => 5, "value" => "123" , "onclick" => "myClickFunction()")
						
						TO-DO: maybe combine with addControl_Text() ?
						
						For now, a few JS events are handled on a case-by-case.  
						Later on, maybe turn them into an array such as  "js" => array("onclick" => "myClickFunction()" , "onchange" => "myChangeFunction()")
														OR maybe:  "js" => "onclick='myClickFunction()' onchange='myChangeFunction()'"
	 */
	{
		$html = "<input type='password' name='" . $controlName . "'";		// Note that the <input> tag is not yet closed

		/* Process all the optional parameters, if any
		 */
		if (is_array($parArray))  {
			if ($parArray["id"]) 
				$html .= " id='". $parArray["id"] . "'";

			if ($parArray["size"]) 
				$html .= " size='". $parArray["size"] . "'";
				
			if ($parArray["maxlength"]) 
				$html .= " maxlength='". $parArray["maxlength"] . "'";
			
			if ($parArray["onclick"]) 
				$html .= " onclick='". $parArray["onclick"] . "'";
			
			if ($parArray["onchange"]) 
				$html .= " onchange='". $parArray["onchange"] . "'";
		}
		
		$html .= ">";	// Close <input> tag
		
		$this->addControl_General($label, $html);

	} // addControl_Password()
	
	
	
	public function addControl_PulldownMenu($label, $menuDataset, $controlName, $selectedInstructions = "[SELECT]", $optionsArray = false)
	/* 	Append a row with the specififed label, and a pull-down menu.
	
		ARGUMENTS:
			$menuDataset 		Must be a traversable object; for example an array or something returned by an SQL select query; its entries may be single values or arrays.
								If its entries are single values or arrays with 1 element, they are used for both the picked value and the shown value;
								if they are arrays with 2+ elements, the 1st element is used for the picked value, and the 2nd one is used for the shown value.
								
			$controlName		A string representing an array.  It MUST end in "[]"; for example, "someName[]"
			
			$selectedInstructions	Optional.  If present (and not over-ridden, see below), the first entry of the menu shows the specified string and is selected; its picked value is blanl
			
			$optionsArray		Optional array.  
								Element "style", if present, sets a CSS style for the <select> tag
								Element "selected", if present, specifies a value in the dataset that will selected.  This will only occur if the specified value actually occurs in the dataset; if it occurs, it will override $selectedInstructions, if applicable
	 */
	{
		// Set menu styling
		if ($optionsArray["style"])
			$menuStyle = $optionsArray["style"];
		else
			$menuStyle = "font-size:10px";			// Default styling


		$menu = "\n<select name='$controlName' style='$menuStyle'>";
		
		if ($selectedInstructions)
			$menu .= "\n<option value='' selected>$selectedInstructions</option>"; 
					
			
		foreach ($menuDataset as $row)  {
			if (is_array($row))  {
				$option = $row[0];		// This will be the case if $menuDataset was returned by a SELECT database call
				if (sizeof($row) > 1)
					$shown = $row[1];
			}
			else  {
				$option = $row;			// This will be the case if $menuDataset was provided by an array
				$shown = $row;
			}
			
			if ($optionsArray["selected"] == $option)
				$menu .= "\n<option value='$option' selected>$shown</option>";
			else
				$menu .= "\n<option value='$option'>$shown</option>";
		}
				
		$menu .= "\n</select>\n";
		
		
		$this->addControl_General($label, $menu);
	
	} // addControl_PulldownMenu()
	
	
	
	public function addControl_Hidden($name, $value)
	// Add a "hidden input" to the form, with the given name/value pair
	{
		$this->hiddenOptions[] = "name='$name' value='$value'";
	}
	

	
	public function generateHTML($submitLabel = "") 
	/* 	Create and return an HTML form (preceded by an optional title, specified at object-instantiation time.)  
		The optional argument, if present, conveniently allows specifying a label insude the form' SUBMIT button.
	 */ 
	{
		if ($submitLabel)
			$this->submitLabel = $submitLabel;		// If function argument was present, save it as its corresponding attribute;
													//		otherwise, the default value for the attribute will be used
			
	
		$html = "";			// The HTML form being built
		
		
		if ($this->title)
			$html .= "<span style='$this->titleStyle'>$this->title</span><br><br>\n";
		
		
		// MAIN element, a <form>
		$html .= "<FORM method='$this->method'";
		
		if ($this->handler)
			$html .= " action='$this->handler'";
		if ($this->formStyle)
			$html .= " style='$this->formStyle'";
			
		$html .= ">\n";	
		
		
		if ($this->hiddenOptions)  {  // Add the form's hiddden elements, if any
			if (! is_array($this->hiddenOptions))
				$this->hiddenOptions = array($this->hiddenOptions);
				
			foreach ($this->hiddenOptions as $hiddenOption)
				$html .= "<input type='hidden' $hiddenOption>";
		}

		
		$html .= "\n<table border='0' cellspacing='5' cellpadding='0'>\n";		// Table used to align the form's controls and labels


		if (is_array($this->controls)  &&  is_array($this->labels))  {	// Verify that both the controls and labels are arrays, as expected
			foreach ($this->labels As $i => $label)  {					// For each row in turn
					$html .= "\n<tr>";				// Start row
					
					if ($label == "")
						$labelText = "&nbsp;";		// If label is missing, use a space-holder...
					else
						$labelText = $label;		//		...otherwise, use the label
						
											
					if ($this->controls[$i] == null)
							$html .= "<td colspan='2'>$labelText</td>";		// If the control is missing, extend the label over both columns
						else
							$html .= "<td>$labelText</td><td style='padding-left:5px'>" . $this->controls[$i] . "</td>";
							
					
					$html .= "</tr>";				// End row			
			}
		}
		
		$html .= "\n</table><br> ";
		
		if ($this->submitForm)
			$html .= "<input type='submit'";
		else
			$html .= "<input type='button'";
		
		$html .= " value='$this->submitLabel'";
		 
		if ($this->submitOptions)
			$html .= " $this->submitOptions";
			
		$html .= ">\n";		// Terminate the Submit button
		
		if ($this->footer)
			$html .= " &nbsp; $this->footer";
	
		$html .= "\n</form>\n";
		
				
		return $html;

	} // generateHTML()
	
	
	
	
	/****************************
	    	PRIVATE METHODS 
	 ****************************/
	 

}  // end class "formBuilder"
?>