<?php 

namespace PHPStan\DependencyInjection;
return;

use Exception;
use function implode;

final class InvalidIgnoredErrorPatternsException extends Exception
{

	/**
	 * @param string[] $errors
	 */
	public function __construct(private array $errors)
	{
		parent::__construct(implode("\n", $this->errors));
	}

	/**
	 * @return string[]
	 */
	public function getErrors(): array
	{
		return $this->errors;
	}

}
