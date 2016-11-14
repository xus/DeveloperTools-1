<?php

use ilCodingStandard\Sniffs\NameSniff as NameSniff;

class ilCodingStandard_Sniffs_NameSniffs_MethodNameSniff extends NameSniff\NameSniffBase
{
	protected static $valid_name_regexp =
			'#^[a-z]+([A-Z][a-z]*)*$#';

	protected static $magic_methods =
		array(	'__construct',
				'__destruct',
				'__call',
				'__callStatic',
				'__get',
				'__set',
				'__isset',
				'__unset',
				'__slee',
				'__wakeup',
				'__toString',
				'__invoke',
				'__set_state',
				'__clone',
				'__debugInfo');

	const INVALID_METHOD_NAME_ERROR =
			'Method name invalid: %s::%s';
	const NO_METHOD_NAME_ERROR =
			'Can\'t locate method name, check line %i in %s';

	public function register()
	{
		return array(T_FUNCTION);
	}

	public function process(PHP_CodeSniffer_File $phpcs_file, $stack_ptr)
	{

		$tokens = $phpcs_file->getTokens();

		$context = current($tokens[$stack_ptr]['conditions']);
		if (!in_array($context, array(T_CLASS, T_INTERFACE, T_TRAIT))) {
			return;
		}
		$context_token_ptr = key($tokens[$stack_ptr]['conditions']);
		$context_token = $tokens[$context_token_ptr];
		$context_name = $this->getTokenName(
			$tokens,
			$context_token_ptr,
			$context_token['scope_opener'],
			array(T_EXTENDS,T_IMPLEMENTS)
		);


		$method_name = $this->getTokenName(
			$tokens,
			$stack_ptr,
			$tokens[$stack_ptr]['parenthesis_opener']
		);
		if (null !== $method_name) {
			if (!$this->validName($method_name) && !in_array($method_name, self::$magic_methods)) {
				$this->handleError(
					$phpcs_file,
					self::INVALID_METHOD_NAME_ERROR,
					$stack_ptr,
					array($context_name, $method_name)
				);
			}
		} else {
			$this->handleError($phpcs_file, self::NO_METHOD_NAME_ERROR, $stack_ptr, array($tokens[$stack_ptr]['line'], $context_name));
		}
	}
}
