<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use Generator;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Generator\GeneratorNodeScopeResolver;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\RunInFiberRequest;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\DependencyInjection\Type\DynamicReturnTypeExtensionRegistryProvider;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use function count;

/**
 * @phpstan-import-type GeneratorTValueType from GeneratorNodeScopeResolver
 * @phpstan-import-type GeneratorTSendType from GeneratorNodeScopeResolver
 */
#[AutowiredService]
final class MethodCallHelper
{

	public function __construct(
		private DynamicReturnTypeExtensionRegistryProvider $dynamicReturnTypeExtensionRegistryProvider,
	)
	{
	}

	/**
	 * @param list<non-empty-string> $objectClassNamesFromType
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, ?Type>
	 */
	public function methodCallReturnType(
		GeneratorScope $scope,
		MethodReflection $methodReflection,
		ParametersAcceptor $parametersAcceptor,
		MethodCall|Expr\StaticCall|null $normalizedMethodCall,
		array $objectClassNamesFromType,
	): Generator
	{
		if ($normalizedMethodCall === null) {
			return $parametersAcceptor->getReturnType();
		}

		$resolvedTypes = [];
		foreach ($objectClassNamesFromType as $className) {
			if ($normalizedMethodCall instanceof MethodCall) {
				foreach ($this->dynamicReturnTypeExtensionRegistryProvider->getRegistry()->getDynamicMethodReturnTypeExtensionsForClass($className) as $dynamicMethodReturnTypeExtension) {
					if (!$dynamicMethodReturnTypeExtension->isMethodSupported($methodReflection)) {
						continue;
					}

					$resolvedType = (yield new RunInFiberRequest(static fn () => $dynamicMethodReturnTypeExtension->getTypeFromMethodCall($methodReflection, $normalizedMethodCall, $scope)))->value;
					if ($resolvedType === null) {
						continue;
					}

					$resolvedTypes[] = $resolvedType;
				}
			} else {
				foreach ($this->dynamicReturnTypeExtensionRegistryProvider->getRegistry()->getDynamicStaticMethodReturnTypeExtensionsForClass($className) as $dynamicStaticMethodReturnTypeExtension) {
					if (!$dynamicStaticMethodReturnTypeExtension->isStaticMethodSupported($methodReflection)) {
						continue;
					}

					$resolvedType = (yield new RunInFiberRequest(static fn () => $dynamicStaticMethodReturnTypeExtension->getTypeFromStaticMethodCall(
						$methodReflection,
						$normalizedMethodCall,
						$scope,
					)))->value;
					if ($resolvedType === null) {
						continue;
					}

					$resolvedTypes[] = $resolvedType;
				}
			}
		}

		if (count($resolvedTypes) > 0) {
			return TypeCombinator::union(...$resolvedTypes);
		}

		return $parametersAcceptor->getReturnType();
	}

}
