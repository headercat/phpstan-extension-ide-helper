<?php 

namespace PHPStan;
return;

use Exception;

final class ShouldNotHappenException extends Exception
{

	/** @api */
	public function __construct(string $message = 'Internal error.')
	{
		parent::__construct($message);
	}

}
