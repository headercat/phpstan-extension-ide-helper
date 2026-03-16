<?php 

namespace PHPStan\DependencyInjection;
return;

use Exception;
use function sprintf;

final class MissingImplementedInterfaceInServiceWithTagException extends Exception
{

	public function __construct(string $className, string $tag, string $interface)
	{
		parent::__construct(sprintf('Service of type %s with tag %s does not implement interface %s.', $className, $tag, $interface));
	}

}
