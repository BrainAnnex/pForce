<?php
/*
	Last revised 9/29/2019.  Distributed as part of the "pForce Web Framework".  For more info: https://github.com/BrainAnnex/pForce
	
	Class for Template Evaluation: bare-bones version of the Python library "Jinja"
	
	
	DEPENDENCIES:  None
	
	
	DESIGN IDEAS (not yet implemented) for a simpler/more secure solution for complex (such as conditional) template evaluations.  
	Outermost special delimiters are curly brackets.  
	Not using triangular brackets to avoid interfering with HTML/XML that might be in the string.  
	Not using square or round brackets because those are more common.
	
	TODO: make more Jinja-like
	
	1) simple field substitution:
			"Dear {{name}}, see you on {{day}}"  .  This is Jinja-like (different delimeters may be requested)
			
	2) if/then/else constructs:
	
		{ [test] [if-branch string] [else-branch string] }
		
		where test can be: 1) a field (true if not null) ; or 2) "field=string"
		
		Example 1:  "{ [phone] [are you still at {phone}?] [I lack your phone #] }"
		
		Example 2:  "{ [site=c] [WC] [WoA] }"
		
	3) pre-registered, site-provided (NOT user-provided!), functions:
	
		$templ_eval->registerFunction("foo");		// foo() become Registed Function 1 for this site
		
		Example: "Go to link {RF(1, {productID})}"


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

/*
// Tester code:

$replaceArray = array("name" => "Valerie" , "day" => "Sat");		// Sometimes referred to as the "paramArray"

$templ_eval = new templateEvaluator();

// Use default tags
$template = "Dear {{name}}, see you on <b>{{day}}</b>!";
echo $templ_eval->evaluateTemplate($template, $replaceArray);

// Use alternate tags
$templ_eval->setTags("[+" , "]");
$template2 = "<br>Hello [+name], can't wait to see you this [+day]!";
echo $templ_eval->evaluateTemplate($template2, $replaceArray);
*/


namespace pforce;		// Part of the "pForce" Web Framework


class templateEvaluator  {
	
	// Token delimiters.  The default values are Jinja-like
	private  $OPEN_TAG = "{{";		// To change dynamically, use the setTags() method
	private  $CLOSE_TAG = "}}";		// To change dynamically, use the setTags() method
	
	public  $errors = "";
	
	// Ideas for tags: {{name}}   {name}  [name]  [[name]]  [@name]     [+name]
	

	
	public function setTags($openTag, $closeTag)
	// To replace the default tags, if desired
	{
		$this->OPEN_TAG = $openTag;
		$this->CLOSE_TAG = $closeTag;
	}
	
	
	
	public function  evaluateTemplate($template, $replaceArray, $outputErrors = true)
	/*  Example of template:  "Dear {{name}}, see you on {{day}}!"
		Example of replaceArray:  array("name" => "Valerie" , "city" => "SF")
		
		Case-sensitive. (TO-DO: maybe change to case-insensitive)
	 */
	{
		//echo "TEMPLATE: $template<br>PARAM ARRAY:";
		//print_r($replaceArray);
		
		if (! is_array($replaceArray) )  {
			$this->errors = "The expected array of replacement values in the template substitution (2nd argument) is actually not an array! Template left unchanged";
			
			if ($outputErrors)
				$template = "$this->errors: $template";
				
			return $template;
		}
			
		foreach ($replaceArray as $key => $value) {		// For each requested substitution
			$tagToReplace = $this->OPEN_TAG . $key . $this->CLOSE_TAG;		// Assemble the full tag, such as "{{name}}"
			$template = str_replace($tagToReplace, $value, $template);		// Case-sensitive
		}
		
		$this->errors = "";		// No errors encountered
		
		
		// Finally, eliminate any remaining (non-substituted) token in the template.  This conforms to what Jinja does
		$pattern = "/{{.+?}}/";			// Non-greedy (indicated by the "?") matching of any series of characters (except newlines) within the tags
		$template = preg_replace("/{{.+?}}/", "", $template);
		
		return $template;
		
		/* Code ideas */
		
		/*
		// This is overkill for simple tags, but may fit the bill for more complex constructors
		if (preg_match_all("#{{(.*?)}}#", $template, $m)) {
		  foreach ($m[1] as $i => $varname) {
			$template = str_replace($m[0][$i], sprintf('%s', $varname), $template);
		  }
		}
		*/
		
		/*
		preg_replace("#{{(\w*)}}#e", '$replaceArray["$1"]', $template);`
		*/
	}
	
		
	
	function exactStringDisplay($str, $caption = "String")
	/*
		This function is just for debugging.
		
		Assemble and return the HTML to display, inside a boxed div element, the given string EXACTLY as it is, 
		without any of the usual HTML printing substitutions.  
		For example "one<br><b>two</b>" will show up exactly as composed.
	 */
	{
		return "<b>$caption:</b><br><div style='border: 1px solid gray'><pre>" . htmlspecialchars($str) . "</pre></div>";
	}
	
} // class "templateEvaluator"
?>