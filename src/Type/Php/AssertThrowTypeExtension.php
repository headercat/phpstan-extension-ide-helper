<?php 

namespace PHPStan\Type\Php;
return;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\DynamicFunctionThrowTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Throwable;
use function count;

final class AssertThrowTypeExtension implements DynamicFunctionThrowTypeExtension
{

	public function isFunctionSupported(FunctionReflection $functionReflection): bool
	{
		return $functionReflection->getName() === 'assert';
	}

	public function getThrowTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $funcCall, Scope $scope): ?Type
	{
		if (count($funcCall->getArgs()) < 2) {
			return $functionReflection->getThrowType();
		}

		$customThrow = $scope->getType($funcCall->getArgs()[1]->value);
		if ((new ObjectType(Throwable::class))->isSuperTypeOf($customThrow)->yes()) {
			return $customThrow;
		}

		return $functionReflection->getThrowType();
	}

}
