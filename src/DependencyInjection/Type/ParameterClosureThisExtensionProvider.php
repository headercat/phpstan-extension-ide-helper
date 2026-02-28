<?php 

namespace PHPStan\DependencyInjection\Type;
return;

use PHPStan\Type\FunctionParameterClosureThisExtension;
use PHPStan\Type\MethodParameterClosureThisExtension;
use PHPStan\Type\StaticMethodParameterClosureThisExtension;

interface ParameterClosureThisExtensionProvider
{

	/**
	 * @return FunctionParameterClosureThisExtension[]
	 */
	public function getFunctionParameterClosureThisExtensions(): array;

	/**
	 * @return MethodParameterClosureThisExtension[]
	 */
	public function getMethodParameterClosureThisExtensions(): array;

	/**
	 * @return StaticMethodParameterClosureThisExtension[]
	 */
	public function getStaticMethodParameterClosureThisExtensions(): array;

}
