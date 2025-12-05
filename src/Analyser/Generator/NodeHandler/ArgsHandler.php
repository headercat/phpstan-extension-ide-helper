<?php 

namespace PHPStan\Analyser\Generator\NodeHandler;
return;

use Generator;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeFinder;
use PHPStan\Analyser\ArgumentsNormalizer;
use PHPStan\Analyser\ExpressionContext;
use PHPStan\Analyser\Generator\AssignHelper;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\ExprHandler\ArrowFunctionHandler;
use PHPStan\Analyser\Generator\ExprHandler\ClosureHandler;
use PHPStan\Analyser\Generator\ExprHandler\ImmediatelyCalledCallableHelper;
use PHPStan\Analyser\Generator\GeneratorNodeScopeResolver;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\InternalThrowPoint;
use PHPStan\Analyser\Generator\NodeCallbackRequest;
use PHPStan\Analyser\Generator\RunInFiberRequest;
use PHPStan\Analyser\Generator\StmtAnalysisResult;
use PHPStan\Analyser\Generator\TypeExprRequest;
use PHPStan\Analyser\Generator\VirtualAssignHelper;
use PHPStan\Analyser\ImpurePoint;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredParameter;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\DependencyInjection\Type\ParameterClosureThisExtensionProvider;
use PHPStan\DependencyInjection\Type\ParameterClosureTypeExtensionProvider;
use PHPStan\DependencyInjection\Type\ParameterOutTypeExtensionProvider;
use PHPStan\Node\Expr\TypeExpr;
use PHPStan\Node\InvalidateExprNode;
use PHPStan\Reflection\Callables\SimpleImpurePoint;
use PHPStan\Reflection\Callables\SimpleThrowPoint;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\ExtendedParameterReflection;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\ShouldNotHappenException;
use PHPStan\TrinaryLogic;
use PHPStan\Type\ClosureType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectWithoutClassType;
use PHPStan\Type\ResourceType;
use PHPStan\Type\ThisType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use function array_filter;
use function array_map;
use function array_merge;
use function array_values;
use function count;
use function is_string;

/**
 * @phpstan-import-type GeneratorTValueType from GeneratorNodeScopeResolver
 * @phpstan-import-type GeneratorTSendType from GeneratorNodeScopeResolver
 */
#[AutowiredService]
final class ArgsHandler
{

	public function __construct(
		private readonly ParameterClosureTypeExtensionProvider $parameterClosureTypeExtensionProvider,
		private readonly ParameterClosureThisExtensionProvider $parameterClosureThisExtensionProvider,
		private readonly ParameterOutTypeExtensionProvider $parameterOutTypeExtensionProvider,
		private readonly AssignHelper $assignHelper,
		private readonly VirtualAssignHelper $virtualAssignHelper,
		private readonly ClosureHandler $closureHandler,
		private readonly ArrowFunctionHandler $arrowFunctionHandler,
		private readonly ImmediatelyCalledCallableHelper $immediatelyCalledCallableHelper,
		#[AutowiredParameter(ref: '%exceptions.implicitThrows%')]
		private readonly bool $implicitThrows,
	)
	{
	}

	/**
	 * @param MethodReflection|FunctionReflection|null $calleeReflection
	 * @param (callable(Node, Scope, callable(Node, Scope): void): void)|null $alternativeNodeCallback
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, StmtAnalysisResult>
	 */
	public function processArgs(
		Node\Stmt $stmt,
		$calleeReflection,
		?ExtendedMethodReflection $nakedMethodReflection,
		?ParametersAcceptor $parametersAcceptor,
		CallLike $callLike,
		GeneratorScope $scope,
		ExpressionContext $context,
		?callable $alternativeNodeCallback,
		?GeneratorScope $closureBindScope = null,
	): Generator
	{
		$args = $callLike->getArgs();

		if ($parametersAcceptor !== null) {
			$parameters = $parametersAcceptor->getParameters();
		}

		$hasYield = false;
		$throwPoints = [];
		$impurePoints = [];
		$isAlwaysTerminating = false;
		foreach ($args as $i => $arg) {
			$assignByReference = false;
			$parameter = null;
			$parameterType = null;
			$parameterNativeType = null;
			if (isset($parameters) && $parametersAcceptor !== null) {
				if (isset($parameters[$i])) {
					$assignByReference = $parameters[$i]->passedByReference()->createsNewVariable();
					$parameterType = $parameters[$i]->getType();

					if ($parameters[$i] instanceof ExtendedParameterReflection) {
						$parameterNativeType = $parameters[$i]->getNativeType();
					}
					$parameter = $parameters[$i];
				} elseif (count($parameters) > 0 && $parametersAcceptor->isVariadic()) {
					$lastParameter = array_last($parameters);
					$assignByReference = $lastParameter->passedByReference()->createsNewVariable();
					$parameterType = $lastParameter->getType();

					if ($lastParameter instanceof ExtendedParameterReflection) {
						$parameterNativeType = $lastParameter->getNativeType();
					}
					$parameter = $lastParameter;
				}
			}

			$lookForUnset = false;
			if ($assignByReference) {
				$isBuiltin = false;
				if ($calleeReflection instanceof FunctionReflection && $calleeReflection->isBuiltin()) {
					$isBuiltin = true;
				} elseif ($calleeReflection instanceof ExtendedMethodReflection && $calleeReflection->getDeclaringClass()->isBuiltin()) {
					$isBuiltin = true;
				}
				if (
					$isBuiltin
					|| ($parameterNativeType === null || !$parameterNativeType->isNull()->no())
				) {
					$scope = $this->assignHelper->lookForSetAllowedUndefinedExpressions($scope, $arg->value);
					$lookForUnset = true;
				}
			}

			if ($calleeReflection !== null) {
				$scope = $scope->pushInFunctionCall($calleeReflection, $parameter);
			}

			$originalArg = $arg->getAttribute(ArgumentsNormalizer::ORIGINAL_ARG_ATTRIBUTE) ?? $arg;
			yield new NodeCallbackRequest($originalArg, $scope, $alternativeNodeCallback);

			$originalScope = $scope;
			$scopeToPass = $scope;
			if ($i === 0 && $closureBindScope !== null) {
				$scopeToPass = $closureBindScope;
			}

			$parameterCallableType = null;
			if ($parameterType !== null) {
				$parameterCallableType = TypeUtils::findCallableType($parameterType);
			}

			if ($parameter instanceof ExtendedParameterReflection) {
				$parameterCallImmediately = $parameter->isImmediatelyInvokedCallable();
				if ($parameterCallImmediately->maybe()) {
					$callCallbackImmediately = $parameterCallableType !== null && $calleeReflection instanceof FunctionReflection;
				} else {
					$callCallbackImmediately = $parameterCallImmediately->yes();
				}
			} else {
				$callCallbackImmediately = $parameterCallableType !== null && $calleeReflection instanceof FunctionReflection;
			}

			if ($arg->value instanceof Node\Expr\Closure) {
				$restoreThisScope = null;
				if (
					$closureBindScope === null
					&& $parameter instanceof ExtendedParameterReflection
					&& !$arg->value->static
				) {
					$closureThisTypeGen = $this->resolveClosureThisType($callLike, $calleeReflection, $parameter, $scopeToPass);
					yield from $closureThisTypeGen;
					$closureThisType = $closureThisTypeGen->getReturn();
					if ($closureThisType !== null) {
						$restoreThisScope = $scopeToPass;
						$scopeToPassGen = $scopeToPass->assignVariable('this', $closureThisType, new ObjectWithoutClassType(), TrinaryLogic::createYes());
						yield from $scopeToPassGen;
						$scopeToPass = $scopeToPassGen->getReturn();
					}
				}

				if ($parameter !== null) {
					// todo should be already baked into $parameter when calling pushInFunctionCall
					$overwritingParameterTypeGen = $this->getParameterTypeFromParameterClosureTypeExtension($callLike, $calleeReflection, $parameter, $scopeToPass);
					yield from $overwritingParameterTypeGen;
					$overwritingParameterType = $overwritingParameterTypeGen->getReturn();

					if ($overwritingParameterType !== null) {
						$parameterType = $overwritingParameterType;
					}
				}

				yield new NodeCallbackRequest($arg->value, $context->isDeep() ? $scopeToPass->exitFirstLevelStatements() : $scopeToPass, $alternativeNodeCallback);
				$closureResultGen = $this->closureHandler->processClosureNode($stmt, $arg->value, $scopeToPass, $context, $alternativeNodeCallback);
				yield from $closureResultGen;
				[$closureResult] = $closureResultGen->getReturn();
				if ($callCallbackImmediately) {
					$throwPoints = array_merge($throwPoints, array_map(static fn (InternalThrowPoint $throwPoint) => $throwPoint->explicit ? InternalThrowPoint::createExplicit($scope, $throwPoint->type, $arg->value, $throwPoint->canContainAnyThrowable) : InternalThrowPoint::createImplicit($scope, $arg->value), $closureResult->throwPoints));
					$impurePoints = array_merge($impurePoints, $closureResult->impurePoints);
					$isAlwaysTerminating = $isAlwaysTerminating || $closureResult->isAlwaysTerminating;
				}

				$uses = [];
				foreach ($arg->value->uses as $use) {
					if (!is_string($use->var->name)) {
						continue;
					}

					$uses[] = $use->var->name;
				}

				$scope = $closureResult->scope;
				$closureType = $closureResult->type;
				if (!$closureType instanceof ClosureType) {
					throw new ShouldNotHappenException();
				}
				$invalidateExpressions = $closureType->getInvalidateExpressions();
				if ($restoreThisScope !== null) {
					$nodeFinder = new NodeFinder();
					$cb = static fn ($expr) => $expr instanceof Variable && $expr->name === 'this';
					foreach ($invalidateExpressions as $j => $invalidateExprNode) {
						$foundThis = $nodeFinder->findFirst([$invalidateExprNode->getExpr()], $cb);
						if ($foundThis === null) {
							continue;
						}

						unset($invalidateExpressions[$j]);
					}
					$invalidateExpressions = array_values($invalidateExpressions);
					$scope = $scope->restoreThis($restoreThisScope);
				}

				$scope = $this->immediatelyCalledCallableHelper->processImmediatelyCalledCallable($scope, $invalidateExpressions, $uses);
			} elseif ($arg->value instanceof Node\Expr\ArrowFunction) {
				if (
					$closureBindScope === null
					&& $parameter instanceof ExtendedParameterReflection
					&& !$arg->value->static
				) {
					$closureThisTypeGen = $this->resolveClosureThisType($callLike, $calleeReflection, $parameter, $scopeToPass);
					yield from $closureThisTypeGen;
					$closureThisType = $closureThisTypeGen->getReturn();
					if ($closureThisType !== null) {
						$scopeToPassGen = $scopeToPass->assignVariable('this', $closureThisType, new ObjectWithoutClassType(), TrinaryLogic::createYes());
						yield from $scopeToPassGen;
						$scopeToPass = $scopeToPassGen->getReturn();
					}
				}

				if ($parameter !== null) {
					// todo should be already baked into $parameter when calling pushInFunctionCall
					$overwritingParameterTypeGen = $this->getParameterTypeFromParameterClosureTypeExtension($callLike, $calleeReflection, $parameter, $scopeToPass);
					yield from $overwritingParameterTypeGen;
					$overwritingParameterType = $overwritingParameterTypeGen->getReturn();

					if ($overwritingParameterType !== null) {
						$parameterType = $overwritingParameterType;
					}
				}

				yield new NodeCallbackRequest($arg->value, $context->isDeep() ? $scopeToPass->exitFirstLevelStatements() : $scopeToPass, $alternativeNodeCallback);
				$arrowFunctionResultGen = $this->arrowFunctionHandler->processArrowFunctionNode($stmt, $arg->value, $scopeToPass, $alternativeNodeCallback);
				yield from $arrowFunctionResultGen;
				$arrowFunctionResult = $arrowFunctionResultGen->getReturn();
				if ($callCallbackImmediately) {
					$throwPoints = array_merge($throwPoints, array_map(static fn (InternalThrowPoint $throwPoint) => $throwPoint->explicit ? InternalThrowPoint::createExplicit($scope, $throwPoint->type, $arg->value, $throwPoint->canContainAnyThrowable) : InternalThrowPoint::createImplicit($scope, $arg->value), $arrowFunctionResult->throwPoints));
					$impurePoints = array_merge($impurePoints, $arrowFunctionResult->impurePoints);
					$isAlwaysTerminating = $isAlwaysTerminating || $arrowFunctionResult->isAlwaysTerminating;
				}
			} else {
				$exprResult = yield new ExprAnalysisRequest($stmt, $arg->value, $scopeToPass, $context->enterDeep(), $alternativeNodeCallback);
				$exprType = $exprResult->type;
				$throwPoints = array_merge($throwPoints, $exprResult->throwPoints);
				$impurePoints = array_merge($impurePoints, $exprResult->impurePoints);
				$isAlwaysTerminating = $isAlwaysTerminating || $exprResult->isAlwaysTerminating;
				$scope = $exprResult->scope;
				$hasYield = $hasYield || $exprResult->hasYield;

				if ($exprType->isCallable()->yes()) {
					$acceptors = $exprType->getCallableParametersAcceptors($scope);
					if (count($acceptors) === 1) {
						$scope = $this->immediatelyCalledCallableHelper->processImmediatelyCalledCallable($scope, $acceptors[0]->getInvalidateExpressions(), $acceptors[0]->getUsedVariables());
						if ($callCallbackImmediately) {
							$callableThrowPoints = array_map(static fn (SimpleThrowPoint $throwPoint) => $throwPoint->isExplicit() ? InternalThrowPoint::createExplicit($scope, $throwPoint->getType(), $arg->value, $throwPoint->canContainAnyThrowable()) : InternalThrowPoint::createImplicit($scope, $arg->value), $acceptors[0]->getThrowPoints());
							if (!$this->implicitThrows) {
								$callableThrowPoints = array_values(array_filter($callableThrowPoints, static fn (InternalThrowPoint $throwPoint) => $throwPoint->explicit));
							}
							$throwPoints = array_merge($throwPoints, $callableThrowPoints);
							$impurePoints = array_merge($impurePoints, array_map(static fn (SimpleImpurePoint $impurePoint) => new ImpurePoint($scope, $arg->value, $impurePoint->getIdentifier(), $impurePoint->getDescription(), $impurePoint->isCertain()), $acceptors[0]->getImpurePoints()));
							$returnType = $acceptors[0]->getReturnType();
							$isAlwaysTerminating = $isAlwaysTerminating || ($returnType instanceof NeverType && $returnType->isExplicit());
						}
					}
				}
			}

			if ($assignByReference && $lookForUnset) {
				$scope = $this->assignHelper->lookForUnsetAllowedUndefinedExpressions($scope, $arg->value);
			}

			if ($calleeReflection !== null) {
				$scope = $scope->popInFunctionCall();
			}

			if ($i !== 0 || $closureBindScope === null) {
				continue;
			}

			$scope = $scope->restoreOriginalScopeAfterClosureBind($originalScope);
		}
		foreach ($args as $i => $arg) {
			if (!isset($parameters) || $parametersAcceptor === null) {
				continue;
			}

			$byRefType = new MixedType();
			$assignByReference = false;
			$currentParameter = null;
			if (isset($parameters[$i])) {
				$currentParameter = $parameters[$i];
			} elseif (count($parameters) > 0 && $parametersAcceptor->isVariadic()) {
				$currentParameter = array_last($parameters);
			}

			if ($currentParameter !== null) {
				$assignByReference = $currentParameter->passedByReference()->createsNewVariable();
				if ($assignByReference) {
					if ($currentParameter instanceof ExtendedParameterReflection && $currentParameter->getOutType() !== null) {
						$byRefType = $currentParameter->getOutType();
					} elseif (
						$calleeReflection instanceof MethodReflection
						&& !$calleeReflection->getDeclaringClass()->isBuiltin()
					) {
						$byRefType = $currentParameter->getType();
					} elseif (
						$calleeReflection instanceof FunctionReflection
						&& !$calleeReflection->isBuiltin()
					) {
						$byRefType = $currentParameter->getType();
					}
				}
			}

			if ($assignByReference) {
				if ($currentParameter === null) {
					throw new ShouldNotHappenException();
				}

				$argValue = $arg->value;
				if (!$argValue instanceof Variable || $argValue->name !== 'this') {
					$paramOutTypeGen = $this->getParameterOutExtensionsType($callLike, $calleeReflection, $currentParameter, $scope);
					yield from $paramOutTypeGen;
					$paramOutType = $paramOutTypeGen->getReturn();
					if ($paramOutType !== null) {
						$byRefType = $paramOutType;
					}

					$virtualAssignGen = $this->virtualAssignHelper->processVirtualAssign(
						$scope,
						$stmt,
						$argValue,
						new TypeExpr($byRefType),
					);
					yield from $virtualAssignGen;
					$scope = $virtualAssignGen->getReturn()->scope;
				}
			} elseif ($calleeReflection !== null && $calleeReflection->hasSideEffects()->yes()) {
				$argType = (yield new TypeExprRequest($arg->value))->type;
				if (!$argType->isObject()->no()) {
					$nakedReturnType = null;
					if ($nakedMethodReflection !== null) {
						$nakedParametersAcceptor = (yield new RunInFiberRequest(static fn () => ParametersAcceptorSelector::selectFromArgs(
							$scope,
							$args,
							$nakedMethodReflection->getVariants(),
							$nakedMethodReflection->getNamedArgumentsVariants(),
						)))->value;
						$nakedReturnType = $nakedParametersAcceptor->getReturnType();
					}
					if (
						$nakedReturnType === null
						|| !(new ThisType($nakedMethodReflection->getDeclaringClass()))->isSuperTypeOf($nakedReturnType)->yes()
						|| $nakedMethodReflection->isPure()->no()
					) {
						yield new NodeCallbackRequest(new InvalidateExprNode($arg->value), $scope, $alternativeNodeCallback);
						$scope = $scope->invalidateExpression($arg->value, true);
					}
				} elseif (!(new ResourceType())->isSuperTypeOf($argType)->no()) {
					yield new NodeCallbackRequest(new InvalidateExprNode($arg->value), $scope, $alternativeNodeCallback);
					$scope = $scope->invalidateExpression($arg->value, true);
				}
			}
		}

		return new StmtAnalysisResult(
			$scope,
			hasYield: $hasYield,
			isAlwaysTerminating: $isAlwaysTerminating,
			throwPoints: $throwPoints,
			impurePoints: $impurePoints,
			exitPoints: [],
		);
	}

	/**
	 * @param MethodReflection|FunctionReflection|null $calleeReflection
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, ?Type>
	 */
	private function getParameterTypeFromParameterClosureTypeExtension(CallLike $callLike, $calleeReflection, ParameterReflection $parameter, GeneratorScope $scope): Generator
	{
		if ($callLike instanceof FuncCall && $calleeReflection instanceof FunctionReflection) {
			foreach ($this->parameterClosureTypeExtensionProvider->getFunctionParameterClosureTypeExtensions() as $functionParameterClosureTypeExtension) {
				if ($functionParameterClosureTypeExtension->isFunctionSupported($calleeReflection, $parameter)) {
					return (yield new RunInFiberRequest(static fn () => $functionParameterClosureTypeExtension->getTypeFromFunctionCall($calleeReflection, $callLike, $parameter, $scope)))->value;
				}
			}
		} elseif ($calleeReflection instanceof MethodReflection) {
			if ($callLike instanceof StaticCall) {
				foreach ($this->parameterClosureTypeExtensionProvider->getStaticMethodParameterClosureTypeExtensions() as $staticMethodParameterClosureTypeExtension) {
					if ($staticMethodParameterClosureTypeExtension->isStaticMethodSupported($calleeReflection, $parameter)) {
						return (yield new RunInFiberRequest(static fn () => $staticMethodParameterClosureTypeExtension->getTypeFromStaticMethodCall($calleeReflection, $callLike, $parameter, $scope)))->value;
					}
				}
			} elseif ($callLike instanceof MethodCall) {
				foreach ($this->parameterClosureTypeExtensionProvider->getMethodParameterClosureTypeExtensions() as $methodParameterClosureTypeExtension) {
					if ($methodParameterClosureTypeExtension->isMethodSupported($calleeReflection, $parameter)) {
						return (yield new RunInFiberRequest(static fn () => $methodParameterClosureTypeExtension->getTypeFromMethodCall($calleeReflection, $callLike, $parameter, $scope)))->value;
					}
				}
			}
		}

		return null;
	}

	/**
	 * @param FunctionReflection|MethodReflection|null $calleeReflection
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, ?Type>
	 */
	private function resolveClosureThisType(
		?CallLike $call,
		$calleeReflection,
		ParameterReflection $parameter,
		GeneratorScope $scope,
	): Generator
	{
		if ($call instanceof FuncCall && $calleeReflection instanceof FunctionReflection) {
			foreach ($this->parameterClosureThisExtensionProvider->getFunctionParameterClosureThisExtensions() as $extension) {
				if (!$extension->isFunctionSupported($calleeReflection, $parameter)) {
					continue;
				}
				$type = (yield new RunInFiberRequest(static fn () => $extension->getClosureThisTypeFromFunctionCall($calleeReflection, $call, $parameter, $scope)))->value;
				if ($type !== null) {
					return $type;
				}
			}
		} elseif ($call instanceof StaticCall && $calleeReflection instanceof MethodReflection) {
			foreach ($this->parameterClosureThisExtensionProvider->getStaticMethodParameterClosureThisExtensions() as $extension) {
				if (!$extension->isStaticMethodSupported($calleeReflection, $parameter)) {
					continue;
				}
				$type = (yield new RunInFiberRequest(static fn () => $extension->getClosureThisTypeFromStaticMethodCall($calleeReflection, $call, $parameter, $scope)))->value;
				if ($type !== null) {
					return $type;
				}
			}
		} elseif ($call instanceof MethodCall && $calleeReflection instanceof MethodReflection) {
			foreach ($this->parameterClosureThisExtensionProvider->getMethodParameterClosureThisExtensions() as $extension) {
				if (!$extension->isMethodSupported($calleeReflection, $parameter)) {
					continue;
				}
				$type = (yield new RunInFiberRequest(static fn () => $extension->getClosureThisTypeFromMethodCall($calleeReflection, $call, $parameter, $scope)))->value;
				if ($type !== null) {
					return $type;
				}
			}
		}

		if ($parameter instanceof ExtendedParameterReflection) {
			return $parameter->getClosureThisType();
		}

		return null;
	}

	/**
	 * @param MethodReflection|FunctionReflection|null $calleeReflection
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, ?Type>
	 */
	private function getParameterOutExtensionsType(CallLike $callLike, $calleeReflection, ParameterReflection $currentParameter, GeneratorScope $scope): Generator
	{
		$paramOutTypes = [];
		if ($callLike instanceof FuncCall && $calleeReflection instanceof FunctionReflection) {
			foreach ($this->parameterOutTypeExtensionProvider->getFunctionParameterOutTypeExtensions() as $functionParameterOutTypeExtension) {
				if (!$functionParameterOutTypeExtension->isFunctionSupported($calleeReflection, $currentParameter)) {
					continue;
				}

				$resolvedType = (yield new RunInFiberRequest(static fn () => $functionParameterOutTypeExtension->getParameterOutTypeFromFunctionCall($calleeReflection, $callLike, $currentParameter, $scope)))->value;
				if ($resolvedType === null) {
					continue;
				}
				$paramOutTypes[] = $resolvedType;
			}
		} elseif ($callLike instanceof MethodCall && $calleeReflection instanceof MethodReflection) {
			foreach ($this->parameterOutTypeExtensionProvider->getMethodParameterOutTypeExtensions() as $methodParameterOutTypeExtension) {
				if (!$methodParameterOutTypeExtension->isMethodSupported($calleeReflection, $currentParameter)) {
					continue;
				}

				$resolvedType = (yield new RunInFiberRequest(static fn () => $methodParameterOutTypeExtension->getParameterOutTypeFromMethodCall($calleeReflection, $callLike, $currentParameter, $scope)))->value;
				if ($resolvedType === null) {
					continue;
				}
				$paramOutTypes[] = $resolvedType;
			}
		} elseif ($callLike instanceof StaticCall && $calleeReflection instanceof MethodReflection) {
			foreach ($this->parameterOutTypeExtensionProvider->getStaticMethodParameterOutTypeExtensions() as $staticMethodParameterOutTypeExtension) {
				if (!$staticMethodParameterOutTypeExtension->isStaticMethodSupported($calleeReflection, $currentParameter)) {
					continue;
				}

				$resolvedType = (yield new RunInFiberRequest(static fn () => $staticMethodParameterOutTypeExtension->getParameterOutTypeFromStaticMethodCall($calleeReflection, $callLike, $currentParameter, $scope)))->value;
				if ($resolvedType === null) {
					continue;
				}
				$paramOutTypes[] = $resolvedType;
			}
		}

		if (count($paramOutTypes) === 1) {
			return $paramOutTypes[0];
		}

		if (count($paramOutTypes) > 1) {
			return TypeCombinator::union(...$paramOutTypes);
		}

		return null;
	}

}
