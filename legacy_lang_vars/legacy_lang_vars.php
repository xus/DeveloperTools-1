#!/usr/bin/env php
<?php
/* Copyright (c) 1998-2016 ILIAS open source, Extended GPL */

/* Needs a code directory and lang_legacy_candidates.csv (as obtained from ILIAS lang administration) as input.
   All lang vars that have been found in ILIAS code will be removed and
   output list will be written to lang_legacy_candidates_dep.csv file.
*/

namespace il\Language;


/**
 * Class LegacyLangVars
 * @package il\Language
 */
class LegacyLangVars
{
	/**
	 * @var string
	 */
	const ILIAS_CLASS_FILE_RE = 'class\..*\.php$';
	
	protected $candidates = array();

	/**
	 * Read candidates file
	 *
	 * @param $a_file
	 */
	function readCandidates($a_file)
	{
		$this->candidates = array();
		array_map(function ($row) {
				$v = str_getcsv($row);
				$this->candidates[$v[1]] = $v[0];
			}
			, file($a_file));
		var_dump($this->candidates);
	}
	
	/**
	 * Main
	 * @param array $args
	 */
	public function run(array $args)
	{
		if (!isset($args[1]) || !isset($args[2])) {
			$this->printUsage();
		}

		$t0 = microtime(true);
		try {
			$this->readCandidates($args[2]);
			foreach ($this->getIliasClassFiles($args[1]) as $file) {
				$this->searchLangVars($file->getPathname());
			}
		} catch (\Exception $e) {
			echo $e->getMessage() . "\n";
		}
		
		$pi = pathinfo($args[2]);
		$output_file = $pi["dirname"]."/".$pi["filename"]."_leg.csv";
		$f = fopen($output_file, "w");
		foreach ($this->candidates as $lv => $mod)
		{
			fwrite($f, $mod.",".$lv."\n");
		}
		fclose($f);
		
		echo sprintf("Execution time: %s seconds\n", (microtime(true) - $t0));
		echo sprintf("Memory usage (peak): %s bytes\n", memory_get_peak_usage(true));
	}

	/**
	 * @param string $file_path
	 * @return \Generator
	 */
	public function searchLangVars($file_path)
	{
		$tokens     = token_get_all(file_get_contents($file_path));

		/*if (is_int(strpos($file_path, "TrackingItemsTableGUI")))
		{
			$transl = array_map(function($e) {
				return array(token_name($e[0]), $e[1], $e[2]);
			}, $tokens);

			var_dump($transl); exit;
		}*/


		$num_tokens = count($tokens);

		for ($i = 0; $i < $num_tokens; $i++) {
			if (is_string($tokens[$i])) {
				continue;
			}

			$token = $tokens[$i][0];
			switch ($token) {

				case T_STRING:
				case T_CONSTANT_ENCAPSED_STRING:
					//if ($tokens[$i][1] == "txt" && $tokens[$i-1][0] == T_OBJECT_OPERATOR)
					if (true)
					{
						$lv = str_replace(array("'", '"'), "", $tokens[$i][1]);
						if ($lv != "") {
							unset($this->candidates[$lv]);
						}
					}
					break;
			}
		}
	}


	/**
	 * @param $path string
	 * @return \Generator
	 */
	protected function getIliasClassFiles($path)
	{
		foreach (
			new \RegexIterator(
				new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator($path),
					\RecursiveIteratorIterator::SELF_FIRST,
					\RecursiveIteratorIterator::CATCH_GET_CHILD
				), '/' . self::ILIAS_CLASS_FILE_RE . '/i'
			) as $file
		) {
			yield $file;
		}
	}

	/**
	 * Prints and app usage example
	 */
	protected function printUsage()
	{
		echo sprintf("Usage: %s directory path_to_candidates.csv]", __FILE__);
		exit(1);
	}
}

if (isset($_SERVER['argv']) && basename($_SERVER['argv'][0]) == basename(__FILE__)) {
	$application = new LegacyLangVars();
	$application->run((array)$_SERVER['argv']);
}