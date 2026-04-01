<?php 

namespace PHPStan\DependencyInjection\Type;
return;

use PHPStan\Type\FunctionParameterOutTypeExtension;
use PHPStan\Type\MethodParameterOutTypeExtension;
use PHPStan\Type\StaticMethodParameterOutTypeExtension;

interface ParameterOutTypeExtensionProvider
{

	/** @return FunctionParameterOutTypeExtension[] */
	public function getFunctionParameterOutTypeExtensions(): array;

	/** @return MethodParameterOutTypeExtension[] */
	public function getMethodParameterOutTypeExtensions(): array;

	/** @return StaticMethodParameterOutTypeExtension[] */
	public function getStaticMethodParameterOutTypeExtensions(): array;

}
