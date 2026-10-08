<?php 

namespace PHPStan;
return;

use Exception;
use function get_debug_type;
use function sprintf;

final class NeverException extends Exception
{

	/**
	 * @param never $value
	 */
	public function __construct($value)
	{
		parent::__construct(sprintf('Unknown value %s, expected never', get_debug_type($value)));
	}

}
