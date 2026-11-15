<?php 

namespace PHPStan\Reflection;
return;

use Exception;
use PHPStan\Turbo\ReferencedByTurboExtension;
use function sprintf;

#[ReferencedByTurboExtension(key: 'missingConstantFromReflectionException')]
final class MissingConstantFromReflectionException extends Exception
{

	public function __construct(
		string $className,
		string $constantName,
	)
	{
		parent::__construct(
			sprintf(
				'Constant %s was not found in reflection of class %s.',
				$constantName,
				$className,
			),
		);
	}

}
