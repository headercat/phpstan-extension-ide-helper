<?php 

namespace PHPStan\Type\Php;
return;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\Type;

final class ArraySpliceFunctionReturnTypeExtension implements DynamicFunctionReturnTypeExtension
{

	public function isFunctionSupported(FunctionReflection $functionReflection): bool
	{
		return $functionReflection->getName() === 'array_splice';
	}

	public function getTypeFromFunctionCall(
		FunctionReflection $functionReflection,
		FuncCall $functionCall,
		Scope $scope,
	): ?Type
	{
		if (!isset($functionCall->getArgs()[0])) {
			return null;
		}

		$arrayArg = $scope->getType($functionCall->getArgs()[0]->value);

		return new ArrayType($arrayArg->getIterableKeyType(), $arrayArg->getIterableValueType());
	}

}
