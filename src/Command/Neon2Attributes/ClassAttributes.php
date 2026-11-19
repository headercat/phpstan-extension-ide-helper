<?php 

namespace PHPStan\Command\Neon2Attributes;
return;

use ReflectionClass;

final class ClassAttributes
{

	/**
	 * @param ReflectionClass<object> $reflection
	 * @param array<string, AttributeSpec> $parameterAttributes by constructor parameter name
	 */
	public function __construct(
		public ReflectionClass $reflection,
		public AttributeSpec $attribute,
		public array $parameterAttributes,
	)
	{
	}

}
