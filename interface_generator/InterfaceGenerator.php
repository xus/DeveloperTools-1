<?php

/**
 * Class Interfacegenerator
 *
 *
 * $Interfacegenerator = new Interfacegenerator('MyClassName');
 * $Interfacegenerator->run();
 *
 * will output a PHP-Interface with all public methods incl. declarations in MyClassName
 *
 * @author Fabian Schmid <fs@studer-raimann.ch>
 */
class Interfacegenerator {

	/**
	 * @var string
	 */
	protected $class_name = '';


	/**
	 * Interfacegenerator constructor.
	 *
	 * @param string $class_name
	 */
	public function __construct($class_name) { $this->class_name = $class_name; }


	public function run() {
		$ref = new ReflectionClass($this->class_name);
		$string = 'interface '. $this->class_name .' {<br><br>';

		foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
			$reflectionReturnType = null;
			$return_type_string = '';
			if (method_exists($reflectionMethod, 'getReturnType')) {
				$reflectionReturnType = $reflectionMethod->getReturnType();
				$return_type_string = "&nbsp;* @return " . $reflectionReturnType;
			}

			$reflectionParameters = $reflectionMethod->getParameters();

			$param = '';
			$param_doc = '';
			foreach ($reflectionParameters as $reflectionParameter) {
				// Type
				if (method_exists($reflectionParameter, 'getType')) {
					$reflectionType = $reflectionParameter->getType();
					if ($reflectionType instanceof ReflectionType) {
						$str_type = $reflectionType->__toString() . ' ';
						$param .= $str_type;
					}
				}
				// Param name
				$name = $reflectionParameter->getName();
				$param .= '$' . $name;
				$param_doc .= '&nbsp;* @param ' . $str_type . ' ' . $name . '<br>';

				try {
					$e = null;
					$defaultValue = $reflectionParameter->getDefaultValue();
				} catch (ReflectionException $e) {
				}
				if (!$e) {
					$param .= " = ";
					$param .= var_export($defaultValue, true); // FSX
				}
				$param .= ", ";
			}

			if (count($reflectionParameters) || $reflectionReturnType) {
				$string .= "/**<br>";
				$string .= $param_doc;
				$string .= $return_type_string;
				$string .= "&nbsp;*/<br>";
			}

			$string .= "public function ";
			$string .= $reflectionMethod->getName();
			$string .= "( ";

			$string .= substr($param, 0, - 2);
			$string .= ");";
			$string .= "<br>";
		}
		$string .= "<br>";
		$string .= "}";

		echo $string;
		exit;
	}
}
