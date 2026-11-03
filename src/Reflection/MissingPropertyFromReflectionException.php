<?php 

namespace PHPStan\Reflection;
return;

use Exception;
use PHPStan\Turbo\ReferencedByTurboExtension;
use function sprintf;

#[ReferencedByTurboExtension(key: 'missingPropertyFromReflectionException')]
final class MissingPropertyFromReflectionException extends Exception
{

	public function __construct(
		string $className,
		string $propertyName,
	)
	{
		parent::__construct(
			sprintf(
				'Property $%s was not found in reflection of class %s.',
				$propertyName,
				$className,
			),
		);
	}

}
