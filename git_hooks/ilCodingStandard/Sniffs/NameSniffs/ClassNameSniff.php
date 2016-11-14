<?php

use ilCodingStandard\Sniffs\NameSniff as NameSniff;

class ilCodingStandard_Sniffs_NameSniffs_ClassNameSniff extends NameSniff\NameSniffBase
{

	protected static $valid_name_regexp =
		'#^[a-zA-Z]+([A-Z][a-z]*)*$#';

	const INVALID_CLASS_NAME_ERROR =
		'Class name invalid: %s';

	public function register()
	{
		return array(T_CLASS, T_INTERFACE, T_TRAIT);
	}

	public function process(PHP_CodeSniffer_File $phpcs_file, $stack_ptr)
	{
		$tokens = $phpcs_file->getTokens();
		$class_name = $this->getTokenName(
			$tokens,
			$stack_ptr,
			$tokens[$stack_ptr]['scope_opener'],
			array(T_EXTENDS, T_IMPLEMENTS)
		);
		if (null !== $class_name) {
			if (!$this->validName($class_name)) {
				$this->handleError($phpcs_file, self::INVALID_CLASS_NAME_ERROR, $stack_ptr, array($class_name));
			}
		} else {
			$this->handleError($phpcs_file, self::NO_NAME_ERROR, $stack_ptr);
		}
	}
}
