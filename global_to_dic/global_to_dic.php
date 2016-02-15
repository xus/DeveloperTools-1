<?php
/**
*	Call this script via 
*	php SIG_refactoring_script.php {relative_path_containing_php-files}
*	Make sure to put this script above processed folders.
*	Allthough nothing terrible should happen, if you have your files versioned, and you may rewind any changes performed
	USE THIS WITH CARE AND AT YOUR OWN RISK ONLY!
	ALWAYS CONTROL THE CHANGES MADE BY IT AND WATCH FOR WARNINGS IT MAY HAVE WRITTEN INTO FILE OUTPUTS!
*/
function getMatches($regex, $target_string, $mod = '') {		//check string for regex and return matches
	$matches = array();
	preg_match_all('/'.$regex.'/'.$mod, $target_string, $matches);
	return $matches[0];
}

define('REGEX_PHP_FILENAME','.+\.php$');						//regex for filenames containing .php
define('REGEX_VARNAME','(?<=\$)[_a-zA-Z]+$');					//regex for php-variable names (having $ in front of them)
define('REGEX_VAR','(\$[_a-zA-Z]+)');							//regex for php-variables
define('REGEX_VAR_I', '\s*'.REGEX_VAR.'\s*,');					//note, that you may announce globals by 
define('REGEX_VAR_O', '\s*'.REGEX_VAR.'\s*;');					//	global $var_1,$var_2,...,var_n;
define('REGEX_GLOBAL_DEC', 										//	to construct the regex we use sub-regexes for inner type (1 to (n-1))
	'global\s+(?!\$DIC)('.REGEX_VAR_I.')*('.REGEX_VAR_O.')+');	//	and for outer type (n). we exclude explicitly $DIC from our query.
define('REGEX_GLOBAL_ARRAY', 											//regexp for $GLOBALS["..."] and $GLOBALS['...'']
	'(\$GLOBALS)\s*(\[(?!\'DIC\')\s*(\'[^\'\s]+\'|"[^"\s]+")\s*\])');	//	but not $GLOBALS['DIC']
																//	excluding $GLOBALS['DIC'] and global $DIC; makes this script idempotent.
function transformFile($file) {
	$output_contents = array();									//read the file line-wise, do transformations,if needed,
	$file_handle = fopen($file,'r');							//	and write result into array $output_contents
	while($line = fgets($file_handle)) {		
		if($aux = getMatches(REGEX_GLOBAL_DEC,$line,'i')) {		//if something like 
																//	{some prefix goes here}global $var_1,$var_2,...,var_n;{some suffix}
			$fix = explode($aux[0],$line);						//	is found, replace it by
																//	{some prefix goes here}global $DIC;{some suffix}
																//	{some prefix goes here}$var_1 = $DIC['var_1'];
																//	{some prefix goes here}...
			$prefix = $fix[0];									
			$suffix = $fix[1];
			$output_contents[] = $prefix.'global $DIC;'.$suffix;
			foreach($vars = getMatches(REGEX_VAR, $aux[0]) as $var) {
				$output_contents[] = $prefix.$var
							.' = $DIC[\''.getMatches(REGEX_VARNAME,$var)[0].'\'];'
							.PHP_EOL;
			}
		} else if(preg_match('/'.REGEX_GLOBAL_ARRAY.'/',$line)) {	//if something like {stuff}$GLOBALS["var"]{more_stuff}
			$output_contents[] = 									//	is found, replace it by {stuff}$GLOBALS['DIC']["var"]{more_stuff}
			preg_replace_callback('/'.REGEX_GLOBAL_ARRAY.'/', 
				function($match) {
					return $match[1].'[\'DIC\']'.$match[2];
				}
				, $line);
		} else {
			$output_contents[] = $line;			//if we find isolated keywords global or $GLOBALS, something may be fishy,
												//	better write a warning that is easily spotted 
												//	but does not change code functionality
			if(1 === preg_match('/\sglobal\s/i',$line) && 0 === preg_match('/\sglobal\s\$DIC/i',$line)) {
				$output_contents[] = 	
						'// !!!DIC refactoring-script warning.!!!'.PHP_EOL
						.'// There is an isolated \'global\' whithout any variable behind.'.PHP_EOL
						.'// Either this is a comment, or something is seriously wrong'.PHP_EOL;
			}
			if(1 === preg_match('/\$GLOBALS/',$line) && 0 === preg_match('/\$GLOBALS\s*\[\'DIC\'\]/',$line)) {
				$output_contents[] = 
						'// !!!DIC refactoring-script warning.!!!'.PHP_EOL
						.'// There is an isolated \'$GLOBALS\' whithout any key behind.'.PHP_EOL
						.'// Either this is a comment, or something is seriously wrong'.PHP_EOL;
			}
		}
	}
	fclose($file_handle);
	return $output_contents;
}
$directories = array($argv[1]); 			//input directory-path relative to execution dir
while($aux_dir = current($directories)) {	//	iterate directories recursively by appending them to $directories var 
	if($handle = opendir($aux_dir)) {		//	iteration starts with input
		$aux_dir .= DIRECTORY_SEPARATOR;
		while (false !== ($entry = readdir($handle))) {
			if($entry === '.' || $entry === '..') {	//skip any . and .. subfolder
				continue;
			}
			if(is_dir($aux_dir.$entry)) {			//if an object in a directoy is directory, store it for later treatment
				$directories[] = $aux_dir.$entry;
			} else {
				if(1 === preg_match('/'.REGEX_PHP_FILENAME.'/', $entry)) {	//only consider .php-files
					$out = transformFile($aux_dir.$entry);					//	and transform them
					file_put_contents($aux_dir.$entry, $out);				//	rewrite file by its transformed content
				}
			}
		}
	}
	next($directories);
}