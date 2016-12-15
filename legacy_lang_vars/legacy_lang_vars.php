#!/usr/bin/env php
<?php
/* Copyright (c) 1998-2016 ILIAS open source, Extended GPL */

/* Needs a code directory and lang_legacy_candidates.csv (as obtained from ILIAS lang administration) as input.
   All lang vars that have been found in ILIAS code will be removed and
   output list will be written to lang_legacy_candidates_dep.csv file.
*/

namespace il\Language;

/**
 * Class ClassInfo
 * @package il\Language
 * @author  Alex Killing <killing@leifos.de>
 * Based heavily on code from mjansen@databay.de
 */
class ClassInfo
{
	/**
	 * @var string
	 */
	protected $path = '';

	/**
	 * @var string
	 */
	protected $class = '';

	/**
	 * @var array
	 */
	protected $lang_vars = array();

	/**
	 * ClassInfo constructor.
	 * @param $path    string
	 * @param $class   string
	 */
	public function __construct($path, $class)
	{
		$this->path  = $path;
		$this->class = $class;
	}

	/**
	 * @param string $lang_var
	 */
	public function pushLangVar($lang_var)
	{
		if (!in_array($lang_var, $this->lang_vars))
		{
			$this->lang_vars[] = $lang_var;
		}
	}

	/**
	 * @return \Generator
	 */
	public function usedLangVars($a_candidates)
	{
		foreach ($this->lang_vars as $v)
		{
			if (isset($a_candidates[$v]))
			{
				yield $v;
			}
		}
	}

	/**
	 * @return string
	 */
	public function getPath()
	{
		return $this->path;
	}

	/**
	 * @return string
	 */
	public function getClass()
	{
		return $this->class;
	}

	/**
	 * @param string $file_path
	 * @return \Generator
	 */
	public static function parseClassInfosFromFile($file_path)
	{
		$tokens     = token_get_all(file_get_contents($file_path));
		/*$transl = array_map(function($e) {
			return array(token_name($e[0]), $e[1], $e[2]);
		}, $tokens);
		
		var_dump($transl); exit;*/

		$num_tokens = count($tokens);

		/** @var $last_class self */
		$last_class = null;
		$namespace  = '';

		for ($i = 0; $i < $num_tokens; $i++) {
			if (is_string($tokens[$i])) {
				continue;
			}

			$token = $tokens[$i][0];
			$line  = $tokens[$i][2];
			switch ($token) {
				case T_NAMESPACE:
					$namespace = self::getNamespaceName($tokens, $i);
					break;

				case T_CLASS:
					if ($last_class) {
						yield $last_class;
					}

					$class_name = self::getClassName($namespace, $tokens, $i);
					$last_class = new self($file_path, $class_name);
					break;

				case T_STRING:
					if ($tokens[$i][1] == "txt" && $tokens[$i-1][0] == T_OBJECT_OPERATOR)
					{
						$lv = str_replace(array("'", '"'), "", $tokens[$i+2][1]);
						if ($lv != "" && $last_class) {
							$last_class->pushLangVar($lv);
						}
					}
					break;
			}
		}

		if ($last_class) {
			yield $last_class;
		}
	}

	/**
	 * @param array $tokens
	 * @param int   $i
	 * @return bool|string
	 */
	protected static function getNamespaceName(array $tokens, $i)
	{
		if (isset($tokens[$i + 2][1])) {
			$namespace = $tokens[$i + 2][1];

			for ($j = $i + 3; ; $j += 2) {
				if (isset($tokens[$j]) && $tokens[$j][0] == T_NS_SEPARATOR) {
					$namespace .= '\\' . $tokens[$j + 1][1];
				} else {
					break;
				}
			}

			return $namespace;
		}

		return false;
	}

	/**
	 * @param string $namespace
	 * @param array  $tokens
	 * @param int    $i
	 * @return string
	 */
	protected static function getClassName($namespace, array $tokens, $i)
	{
		$i += 2;
		$namespaced = false;
		$class_name = $tokens[$i][1];

		if ($class_name === '\\') {
			$namespaced = true;
		}

		while (is_array($tokens[$i + 1]) && $tokens[$i + 1][0] !== T_WHITESPACE) {
			$class_name .= $tokens[++$i][1];
		}

		if (!$namespaced && $namespace) {
			$class_name = $namespace . '\\' . $class_name;
		}

		return $class_name;
	}
}


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
			foreach ($this->getIliasClasses($args[1]) as $class_info) {
				/** @var $class_info ClassInfo */
				foreach ($class_info->usedLangVars($this->candidates) as $lv) {
					unset($this->candidates[$lv]);
				}
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
	 * @param $path string
	 * @return \Generator
	 */
	protected function getIliasClasses($path)
	{
		foreach ($this->getIliasClassFiles($path) as $file) {
			/** @var $file \SplFileInfo */
			foreach (ClassInfo::parseClassInfosFromFile($file->getPathname()) as $class_info) {
				/** @var $class ClassInfo */
				yield $class_info;
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