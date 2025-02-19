<?php 

namespace PHPStan\Analyser\Ignore;
return;

use Exception;

final class IgnoreParseException extends Exception
{

	public function __construct(string $message, private int $phpDocLine)
	{
		parent::__construct($message);
	}

	public function getPhpDocLine(): int
	{
		return $this->phpDocLine;
	}

}
