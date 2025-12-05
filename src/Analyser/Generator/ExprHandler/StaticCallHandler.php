<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use Generator;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\ArgumentsNormalizer;
use PHPStan\Analyser\ExpressionContext;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\ExprAnalysisResult;
use PHPStan\Analyser\Generator\ExprHandler;
use PHPStan\Analyser\Generator\GeneratorNodeScopeResolver;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\InternalThrowPoint;
use PHPStan\Analyser\Generator\NodeHandler\ArgsHandler;
use PHPStan\Analyser\Generator\NoopNodeCallback;
use PHPStan\Analyser\Generator\PersistStorageRequest;
use PHPStan\Analyser\Generator\RestoreStorageRequest;
use PHPStan\Analyser\Generator\RunInFiberRequest;
use PHPStan\Analyser\ImpurePoint;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\DependencyInjection\AutowiredParameter;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\DependencyInjection\Type\DynamicThrowTypeExtensionProvider;
use PHPStan\Reflection\Callables\SimpleImpurePoint;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\ErrorType;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StaticType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeWithClassName;
use ReflectionProperty;
use Throwable;
use function array_merge;
use function count;
use function in_array;

/**
 * @implements ExprHandler<StaticCall>
 * @phpstan-import-type GeneratorTValueType from GeneratorNodeScopeResolver
 * @phpstan-import-type GeneratorTSendType from GeneratorNodeScopeResolver
 */
#[AutowiredService]
final class StaticCallHandler implements ExprHandler
{

	public function __construct(
		private readonly ArgsHandler $argsHandler,
		private readonly DynamicThrowTypeExtensionProvider $dynamicThrowTypeExtensionProvider,
		private readonly NullsafeShortCircuitingHelper $nullsafeShortCircuitingHelper,
		private readonly MethodCallHelper $methodCallHelper,
		private readonly VoidTypeHelper $voidTypeHelper,
		#[AutowiredParameter(ref: '%exceptions.implicitThrows%')]
		private readonly bool $implicitThrows,
	)
	{
	}

	public function supports(Expr $expr): bool
	{
		return $expr instanceof StaticCall && !$expr->isFirstClassCallable();
	}

	public function analyseExpr(Stmt $stmt, Expr $expr, GeneratorScope $scope, ExpressionContext $context, ?callable $alternativeNodeCallback): Generator
	{
		$throwPoints = [];
		$impurePoints = [];
		if ($expr->class instanceof Expr) {
			$exprResultGen = $this->processClassExpr($stmt, $expr, $expr->class, $scope, $context, $alternativeNodeCallback);
			yield from $exprResultGen;
			return $exprResultGen->getReturn();
		}

		$hasYield = false;

		if ($expr->name instanceof Expr) {
			$nameResult = yield new ExprAnalysisRequest($stmt, $expr->name, $scope, $context->enterDeep(), $alternativeNodeCallback);
			$hasYield = $nameResult->hasYield;
			$throwPoints = array_merge($throwPoints, $nameResult->throwPoints);
			$impurePoints = array_merge($impurePoints, $nameResult->impurePoints);
			$scope = $nameResult->scope;

			//              $nameType = $this->getType($node->name);
			//              if (count($nameType->getConstantStrings()) > 0) {
			//                  $methodReturnType = TypeCombinator::union(
			//                      ...array_map(fn($constantString) => $constantString->getValue() === '' ? new ErrorType() : $this
			//                      ->filterByTruthyValue(new BinaryOp\Identical($node->name, new String_($constantString->getValue())))
			//                      ->getType(new Expr\StaticCall($node->class, new Identifier($constantString->getValue()), $node->args)), $nameType->getConstantStrings()),
			//                  );
			//              }
			$methodReturnType = new ErrorType();
			$nativeMethodReturnType = new ErrorType();
		}

		$parametersAcceptor = null;
		$methodReflection = null;
		$classType = $scope->resolveTypeByName($expr->class);

		$normalizedMethodCall = $expr;
		if ($expr->name instanceof Identifier) {
			$staticMethodCalledOnType = $this->resolveTypeByNameWithLateStaticBinding($scope, $expr->class, $expr->name);
			$nativeMethodReflection = $scope->getMethodReflection(
				$staticMethodCalledOnType,
				$expr->name->name,
			);
			if ($nativeMethodReflection === null) {
				$nativeMethodReturnType = new ErrorType();
			} else {
				$nativeMethodReturnType = ParametersAcceptorSelector::combineAcceptors($nativeMethodReflection->getVariants())->getNativeReturnType();
			}

			$methodName = $expr->name->name;
			if ($classType->hasMethod($methodName)->yes()) {
				$methodReflection = $classType->getMethod($methodName, $scope);
				$storage = yield new PersistStorageRequest();
				$parametersAcceptor = (yield new RunInFiberRequest(static fn () => ParametersAcceptorSelector::selectFromArgs(
					$scope,
					$expr->getArgs(),
					$methodReflection->getVariants(),
					$methodReflection->getNamedArgumentsVariants(),
				)))->value;
				yield new RestoreStorageRequest($storage);

				$normalizedMethodCall = ArgumentsNormalizer::reorderStaticCallArguments($parametersAcceptor, $expr) ?? $normalizedMethodCall;

				/*$closureBindScope = null;
				$declaringClass = $methodReflection->getDeclaringClass();
				if (
					$declaringClass->getName() === 'Closure'
					&& strtolower($methodName) === 'bind'
				) {
					$thisType = null;
					$nativeThisType = null;
					if (isset($expr->getArgs()[1])) {
						$argType = $scope->getType($expr->getArgs()[1]->value);
						if ($argType->isNull()->yes()) {
							$thisType = null;
						} else {
							$thisType = $argType;
						}

						$nativeArgType = $scope->getNativeType($expr->getArgs()[1]->value);
						if ($nativeArgType->isNull()->yes()) {
							$nativeThisType = null;
						} else {
							$nativeThisType = $nativeArgType;
						}
					}
					$scopeClasses = ['static'];
					if (isset($expr->getArgs()[2])) {
						$argValue = $expr->getArgs()[2]->value;
						$argValueType = $scope->getType($argValue);

						$directClassNames = $argValueType->getObjectClassNames();
						if (count($directClassNames) > 0) {
							$scopeClasses = $directClassNames;
							$thisTypes = [];
							foreach ($directClassNames as $directClassName) {
								$thisTypes[] = new ObjectType($directClassName);
							}
							$thisType = TypeCombinator::union(...$thisTypes);
						} else {
							$thisType = $argValueType->getClassStringObjectType();
							$scopeClasses = $thisType->getObjectClassNames();
						}
					}
					$closureBindScope = $scope->enterClosureBind($thisType, $nativeThisType, $scopeClasses);
				}*/
			}
		}

		$argsResultGen = $this->argsHandler->processArgs($stmt, $methodReflection, null, $parametersAcceptor, $normalizedMethodCall, $scope, $context, $alternativeNodeCallback, /*$closureBindScope ?? */null);
		yield from $argsResultGen;
		$argsResult = $argsResultGen->getReturn();

		if ($methodReflection !== null && $parametersAcceptor !== null) {
			$methodReturnTypeGen = $this->methodCallHelper->methodCallReturnType(
				$scope,
				$methodReflection,
				$parametersAcceptor,
				$normalizedMethodCall,
				$classType->getObjectClassNames(),
			);
			yield from $methodReturnTypeGen;
			$methodReturnType = $methodReturnTypeGen->getReturn();
			if ($methodReturnType === null) {
				$methodReturnType = new ErrorType();
			}

			$methodThrowPointGen = $this->getStaticMethodThrowPoint($methodReflection, $expr, $normalizedMethodCall, $methodReturnType, $scope);
			yield from $methodThrowPointGen;
			$methodThrowPoint = $methodThrowPointGen->getReturn();
			if ($methodThrowPoint !== null) {
				$throwPoints[] = $methodThrowPoint;
			}
			$impurePoint = SimpleImpurePoint::createFromVariant($methodReflection, $parametersAcceptor, $scope, $expr->getArgs());
			if ($impurePoint !== null) {
				$impurePoints[] = new ImpurePoint($scope, $expr, $impurePoint->getIdentifier(), $impurePoint->getDescription(), $impurePoint->isCertain());
			}
		} else {
			$throwPoints[] = InternalThrowPoint::createImplicit($scope, $expr);
			$methodReturnType = new ErrorType();
			$impurePoints[] = new ImpurePoint(
				$scope,
				$expr,
				'methodCall',
				'call to unknown method',
				false,
			);
		}

		$scope = $argsResult->scope;
		$scopeFunction = $scope->getFunction();

		if (
			$methodReflection !== null
			&& (
				$methodReflection->hasSideEffects()->yes()
				|| (
					!$methodReflection->isStatic()
					&& $methodReflection->getName() === '__construct'
				)
			)
			&& $scope->isInClass()
			&& $scope->getClassReflection()->is($methodReflection->getDeclaringClass()->getName())
		) {
			$scope = $scope->invalidateExpression(new Variable('this'), true);
		}

		if (
			$methodReflection !== null
			&& !$methodReflection->isStatic()
			&& $methodReflection->getName() === '__construct'
			&& $scopeFunction instanceof MethodReflection
			&& !$scopeFunction->isStatic()
			&& $scope->isInClass()
			&& $scope->getClassReflection()->isSubclassOfClass($methodReflection->getDeclaringClass())
		) {
			$thisType = $scope->getExpressionType(new Variable('this'));
			if ($thisType === null) {
				throw new ShouldNotHappenException();
			}
			$methodClassReflection = $methodReflection->getDeclaringClass();
			foreach ($methodClassReflection->getNativeReflection()->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED) as $property) {
				if (!$property->isPromoted() || $property->getDeclaringClass()->getName() !== $methodClassReflection->getName()) {
					continue;
				}

				$scope = $scope->assignInitializedProperty($thisType, $property->getName());
			}
		}

		return new ExprAnalysisResult(
			$this->voidTypeHelper->transformVoidToNull($methodReturnType),
			$this->voidTypeHelper->transformVoidToNull($nativeMethodReturnType),
			keepVoidType: $methodReturnType,
			scope: $scope,
			hasYield: $hasYield || $argsResult->hasYield,
			isAlwaysTerminating: ($methodReturnType instanceof NeverType && $methodReturnType->isExplicit()) || $argsResult->isAlwaysTerminating,
			throwPoints: array_merge($throwPoints, $argsResult->throwPoints),
			impurePoints: array_merge($impurePoints, $argsResult->impurePoints),
			specifiedTruthyTypes: new SpecifiedTypes(),
			specifiedFalseyTypes: new SpecifiedTypes(),
			specifiedNullTypes: new SpecifiedTypes(),
		);
	}

	/**
	 * @param (callable(Node, Scope, callable(Node, Scope): void): void)|null $alternativeNodeCallback
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, ExprAnalysisResult>
	 */
	private function processClassExpr(Stmt $stmt, StaticCall $expr, Expr $class, GeneratorScope $scope, ExpressionContext $context, ?callable $alternativeNodeCallback): Generator
	{
		$classResult = yield new ExprAnalysisRequest($stmt, $class, $scope, $context->enterDeep(), $alternativeNodeCallback);
		$hasYield = $classResult->hasYield;
		$throwPoints = $classResult->throwPoints;
		$impurePoints = [
			new ImpurePoint(
				$scope,
				$expr,
				'methodCall',
				'call to unknown method',
				false,
			),
		];
		$impurePoints = array_merge($impurePoints, $classResult->impurePoints);
		$isAlwaysTerminating = $classResult->isAlwaysTerminating;
		$objectClasses = $classResult->type->getObjectClassNames();
		if (count($objectClasses) !== 1) {
			$newClassNode = new New_($class);
			$objectClasses = (yield new ExprAnalysisRequest(new Stmt\Expression($newClassNode), $newClassNode, $scope, $context, new NoopNodeCallback()))->type->getObjectClassNames();
		}
		if (count($objectClasses) === 1) {
			$objectExprResult = yield new ExprAnalysisRequest($stmt, new StaticCall(new Name($objectClasses[0]), $expr->name, []), $scope, $context->enterDeep(), new NoopNodeCallback());
			$additionalThrowPoints = $objectExprResult->throwPoints;
		} else {
			$additionalThrowPoints = [InternalThrowPoint::createImplicit($scope, $expr)];
		}

		foreach ($additionalThrowPoints as $throwPoint) {
			$throwPoints[] = $throwPoint;
		}
		$scope = $classResult->scope;

		$normalizedMethodCall = $expr;
		$methodReflection = null;
		$parametersAcceptor = null;
		$staticMethodCalledOnType = TypeCombinator::removeNull($classResult->type)->getObjectTypeOrClassStringObjectType();
		if ($expr->name instanceof Expr) {
			$nameResult = yield new ExprAnalysisRequest($stmt, $expr->name, $scope, $context->enterDeep(), $alternativeNodeCallback);
			$hasYield = $hasYield || $nameResult->hasYield;
			$throwPoints = array_merge($throwPoints, $nameResult->throwPoints);
			$impurePoints = array_merge($impurePoints, $nameResult->impurePoints);
			$isAlwaysTerminating = $isAlwaysTerminating || $classResult->isAlwaysTerminating;
			$scope = $nameResult->scope;

			//              $nameType = $this->getType($node->name);
			//              if (count($nameType->getConstantStrings()) > 0) {
			//                  $methodReturnType = TypeCombinator::union(
			//                      ...array_map(fn($constantString) => $constantString->getValue() === '' ? new ErrorType() : $this
			//                      ->filterByTruthyValue(new BinaryOp\Identical($node->name, new String_($constantString->getValue())))
			//                      ->getType(new Expr\StaticCall($node->class, new Identifier($constantString->getValue()), $node->args)), $nameType->getConstantStrings()),
			//                  );
			//              }
			$methodReturnType = new ErrorType();
			$nativeMethodReturnType = new ErrorType();
		} else {
			$staticMethodCalledOnNativeType = $classResult->nativeType;
			$nativeMethodReflection = $scope->getMethodReflection(
				$staticMethodCalledOnNativeType,
				$expr->name->name,
			);
			if ($nativeMethodReflection === null) {
				$nativeMethodReturnType = new ErrorType();
			} else {
				$nativeMethodReturnType = ParametersAcceptorSelector::combineAcceptors($nativeMethodReflection->getVariants())->getNativeReturnType();
				$nativeMethodReturnTypeGen = $this->nullsafeShortCircuitingHelper->getNullsafeShortCircuitingType($class, $nativeMethodReturnType);
				yield from $nativeMethodReturnTypeGen;
				$nativeMethodReturnType = $nativeMethodReturnTypeGen->getReturn();
			}

			$methodName = $expr->name->toString();
			$staticMethodCalledOnType = $scope->filterTypeWithMethod($staticMethodCalledOnType, $methodName);
			if ($staticMethodCalledOnType !== null && $staticMethodCalledOnType->hasMethod($methodName)->yes()) {
				$methodReflection = $staticMethodCalledOnType->getMethod($methodName, $scope);
				$storage = yield new PersistStorageRequest();
				$parametersAcceptor = (yield new RunInFiberRequest(static fn () => ParametersAcceptorSelector::selectFromArgs(
					$scope,
					$expr->getArgs(),
					$methodReflection->getVariants(),
					$methodReflection->getNamedArgumentsVariants(),
				)))->value;
				yield new RestoreStorageRequest($storage);
				$normalizedMethodCall = ArgumentsNormalizer::reorderStaticCallArguments($parametersAcceptor, $expr) ?? $expr;
			}
		}

		$argsResultGen = $this->argsHandler->processArgs($stmt, null, null, null, $normalizedMethodCall, $scope, $context, $alternativeNodeCallback);
		yield from $argsResultGen;
		$argsResult = $argsResultGen->getReturn();

		if ($methodReflection !== null && $parametersAcceptor !== null && $staticMethodCalledOnType !== null) {
			$methodReturnTypeGen = $this->methodCallHelper->methodCallReturnType(
				$scope,
				$methodReflection,
				$parametersAcceptor,
				$normalizedMethodCall,
				$staticMethodCalledOnType->getObjectClassNames(),
			);
			yield from $methodReturnTypeGen;
			$methodReturnType = $methodReturnTypeGen->getReturn();
			if ($methodReturnType === null) {
				$methodReturnType = new ErrorType();
			} else {
				$methodReturnTypeGen = $this->nullsafeShortCircuitingHelper->getNullsafeShortCircuitingType($class, $methodReturnType);
				yield from $methodReturnTypeGen;
				$methodReturnType = $methodReturnTypeGen->getReturn();
			}
		} else {
			$methodReturnType = new ErrorType();
		}

		$scope = $argsResult->scope;

		return new ExprAnalysisResult(
			$this->voidTypeHelper->transformVoidToNull($methodReturnType),
			$this->voidTypeHelper->transformVoidToNull($nativeMethodReturnType),
			keepVoidType: $methodReturnType,
			scope: $scope,
			hasYield: $hasYield || $argsResult->hasYield,
			isAlwaysTerminating: $isAlwaysTerminating || $argsResult->isAlwaysTerminating,
			throwPoints: array_merge($throwPoints, $argsResult->throwPoints),
			impurePoints: array_merge($impurePoints, $argsResult->impurePoints),
			specifiedTruthyTypes: new SpecifiedTypes(),
			specifiedFalseyTypes: new SpecifiedTypes(),
			specifiedNullTypes: new SpecifiedTypes(),
		);
	}

	/**
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, ?InternalThrowPoint>
	 */
	private function getStaticMethodThrowPoint(MethodReflection $methodReflection, StaticCall $methodCall, ?StaticCall $normalizedMethodCall, Type $methodReturnedType, GeneratorScope $scope): Generator
	{
		if ($normalizedMethodCall !== null) {
			foreach ($this->dynamicThrowTypeExtensionProvider->getDynamicStaticMethodThrowTypeExtensions() as $extension) {
				if (!$extension->isStaticMethodSupported($methodReflection)) {
					continue;
				}

				$throwType = (yield new RunInFiberRequest(static fn () => $extension->getThrowTypeFromStaticMethodCall($methodReflection, $normalizedMethodCall, $scope)))->value;
				if ($throwType === null) {
					return null;
				}

				return InternalThrowPoint::createExplicit($scope, $throwType, $methodCall, false);
			}
		}

		if ($methodReflection->getThrowType() !== null) {
			$throwType = $methodReflection->getThrowType();
			if (!$throwType->isVoid()->yes()) {
				return InternalThrowPoint::createExplicit($scope, $throwType, $methodCall, true);
			}
		} elseif ($this->implicitThrows) {
			if (!(new ObjectType(Throwable::class))->isSuperTypeOf($methodReturnedType)->yes()) {
				return InternalThrowPoint::createImplicit($scope, $methodCall);
			}
		}

		return null;
	}

	private function resolveTypeByNameWithLateStaticBinding(GeneratorScope $scope, Name $class, Identifier $name): TypeWithClassName
	{
		$classType = $scope->resolveTypeByName($class);

		if (
			$classType instanceof StaticType
			&& !in_array($class->toLowerString(), ['self', 'static', 'parent'], true)
		) {
			$methodReflectionCandidate = $scope->getMethodReflection(
				$classType,
				$name->name,
			);
			if ($methodReflectionCandidate !== null && $methodReflectionCandidate->isStatic()) {
				$classType = $classType->getStaticObjectType();
			}
		}

		return $classType;
	}

}
