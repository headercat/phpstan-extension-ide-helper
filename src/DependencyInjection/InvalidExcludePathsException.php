<?php 

namespace PHPStan\DependencyInjection;
return;

use Exception;
use function implode;

final class InvalidExcludePathsException extends Exception
{

	/**
	 * @param string[] $errors
	 * @param array{analyse?: list<string>, analyseAndScan?: list<string>} $suggestOptional
	 */
	public function __construct(private array $errors, private array $suggestOptional)
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

	/**
	 * @return array{analyse?: list<string>, analyseAndScan?: list<string>}
	 */
	public function getSuggestOptional(): array
	{
		return $this->suggestOptional;
	}

}
