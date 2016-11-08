<?php

namespace ilCodingStandard\Sniffs\NameSniff;

abstract class NameSniffBase implements \PHP_CodeSniffer_Sniff
{

	const NO_NAME_ERROR =
			'Can not locate name of object.';

	protected function getTokenName(
		array $tokens,
		$start_ptr,
		$end_ptr,
		array $stop_at_token_codes = array()
	) {

		$stack_ptr = $start_ptr + 1;
		$token = $tokens[$stack_ptr];
		while (!in_array($token['code'], $stop_at_token_codes) && $stack_ptr < $end_ptr) {
			if (T_STRING === $token['code']) {
				return $token['content'];
			}
			$stack_ptr++;
			$token = $tokens[$stack_ptr];
		}
		return null;
	}

	protected function validName($name)
	{
		return 1 === preg_match(static::$valid_name_regexp, $name);
	}

	protected function handleError(\PHP_CodeSniffer_File $phpcs_file, $error, $stack_ptr, array $replace)
	{
		$phpcs_file->addWarning($error, $stack_ptr, 'Found', $replace);
	}
}
