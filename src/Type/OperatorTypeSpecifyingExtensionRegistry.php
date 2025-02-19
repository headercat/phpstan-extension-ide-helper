<?php 

namespace PHPStan\Type;
return;

use function array_filter;
use function array_values;

final class OperatorTypeSpecifyingExtensionRegistry
{

	/**
	 * @param OperatorTypeSpecifyingExtension[] $extensions
	 */
	public function __construct(
		private array $extensions,
	)
	{
	}

	/**
	 * @return OperatorTypeSpecifyingExtension[]
	 */
	public function getOperatorTypeSpecifyingExtensions(string $operator, Type $leftType, Type $rightType): array
	{
		return array_values(array_filter($this->extensions, static fn (OperatorTypeSpecifyingExtension $extension): bool => $extension->isOperatorSupported($operator, $leftType, $rightType)));
	}

}
