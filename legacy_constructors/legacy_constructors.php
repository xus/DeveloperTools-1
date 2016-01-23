#!/usr/bin/env php
<?php
/* Copyright (c) 1998-2016 ILIAS open source, Extended GPL */

namespace il\Php7Compliance;

/**
 * Class ClassInfo
 * @package il\Php7Compliance
 * @author  Michael Jansen <mjansen@databay.de>
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
	protected $methods = array();

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
	 * @param string $method
	 * @param int    $line
	 */
	public function pushMethod($method, $line)
	{
		$this->methods[self::normalize($method)] = $line;
	}

	/**
	 * @return bool
	 */
	public function hasLegacyConstructor()
	{
		return in_array(self::normalize($this->class), array_keys($this->methods));
	}

	/**
	 * @return int
	 */
	public function getLegacyConstructorLine()
	{
		return $this->methods[self::normalize($this->getClass())];
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
	 * @param $string
	 * @return string
	 */
	protected static function normalize($string)
	{
		return strtolower($string);
	}

	/**
	 * @param string $file_path
	 * @return \Generator
	 */
	public static function parseClassInfosFromFile($file_path)
	{
		$tokens     = token_get_all(file_get_contents($file_path));
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

				case T_FUNCTION:
					$function_name = null;

					if (is_array($tokens[$i + 2]) && $tokens[$i + 2][0] == T_STRING) {
						$function_name = $tokens[$i + 2][1];
					} else if ($tokens[$i + 2] == '&' && is_array($tokens[$i + 3]) && $tokens[$i + 3][0] == T_STRING) {
						$function_name = $tokens[$i + 3][1];
					}

					if ($function_name && $last_class) {
						$last_class->pushMethod($function_name, $line);
					}
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
 * Class LegacyConstructors
 * @package il\Php7Compliance
 * @author  Michael Jansen <mjansen@databay.de>
 */
class LegacyConstructors
{
	/**
	 * @var string
	 */
	const ILIAS_CLASS_FILE_RE = 'class\..*\.php$';

	/**
	 * Main
	 * @param array $args
	 */
	public function run(array $args)
	{
		if (!isset($args[1])) {
			$this->printUsage();
		}

		$t0 = microtime(true);
		try {
			foreach ($this->getIliasClasses($args[1]) as $class_info) {
				/** @var $class_info ClassInfo */
				if ($class_info->hasLegacyConstructor()) {
					echo sprintf("%s (Path: %s, Line: %s)\n", $class_info->getClass(), $class_info->getPath(), $class_info->getLegacyConstructorLine());
				}
			}
		} catch (\Exception $e) {
			echo $e->getMessage() . "\n";
		}
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
		echo sprintf("Usage: %s directory]", __FILE__);
		exit(1);
	}
}

if (isset($_SERVER['argv']) && basename($_SERVER['argv'][0]) == basename(__FILE__)) {
	$application = new LegacyConstructors();
	$application->run((array)$_SERVER['argv']);
}