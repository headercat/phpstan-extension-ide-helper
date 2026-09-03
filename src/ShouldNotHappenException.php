<?php 

namespace PHPStan;
return;

use Exception;
use PHPStan\Turbo\ReferencedByTurboExtension;

#[ReferencedByTurboExtension(key: 'shouldNotHappenException')]
final class ShouldNotHappenException extends Exception
{

	/** @api */
	public function __construct(string $message = 'Internal error.')
	{
		parent::__construct($message);
	}

}
