<?php 

namespace PHPStan\DependencyInjection\Type;
return;

use PHPStan\Type\FunctionParameterClosureTypeExtension;
use PHPStan\Type\MethodParameterClosureTypeExtension;
use PHPStan\Type\StaticMethodParameterClosureTypeExtension;

interface ParameterClosureTypeExtensionProvider
{

	/** @return FunctionParameterClosureTypeExtension[] */
	public function getFunctionParameterClosureTypeExtensions(): array;

	/** @return MethodParameterClosureTypeExtension[] */
	public function getMethodParameterClosureTypeExtensions(): array;

	/** @return StaticMethodParameterClosureTypeExtension[] */
	public function getStaticMethodParameterClosureTypeExtensions(): array;

}
