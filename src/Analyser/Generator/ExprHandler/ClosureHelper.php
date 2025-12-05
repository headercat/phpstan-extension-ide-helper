<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use Generator;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Variable;
use PHPStan\Analyser\Generator\GeneratorNodeScopeResolver;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\InternalThrowPoint;
use PHPStan\Analyser\Generator\TypeExprRequest;
use PHPStan\Analyser\ImpurePoint;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Node\InvalidateExprNode;
use PHPStan\Parser\ArrayMapArgVisitor;
use PHPStan\Parser\ImmediatelyInvokedClosureVisitor;
use PHPStan\Reflection\Callables\SimpleImpurePoint;
use PHPStan\Reflection\Callables\SimpleThrowPoint;
use PHPStan\Reflection\InitializerExprContext;
use PHPStan\Reflection\InitializerExprTypeResolver;
use PHPStan\Reflection\Native\NativeParameterReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\PassedByReference;
use PHPStan\Reflection\Php\DummyParameter;
use PHPStan\ShouldNotHappenException;
use PHPStan\TrinaryLogic;
use PHPStan\Type\ClosureType;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\Generic\TemplateTypeVarianceMap;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;
use function array_key_exists;
use function array_map;
use function count;
use function is_string;

/**
 * @phpstan-import-type GeneratorTValueType from GeneratorNodeScopeResolver
 * @phpstan-import-type GeneratorTSendType from GeneratorNodeScopeResolver
 */
#[AutowiredService]
final class ClosureHelper
{

	public function __construct(private InitializerExprTypeResolver $initializerExprTypeResolver)
	{
	}

	/**
	 * @param InternalThrowPoint[] $throwPoints
	 * @param ImpurePoint[] $impurePoints
	 * @param InvalidateExprNode[] $invalidateExpressions
	 * @param string[] $usedVariables
	 */
	public function createClosureType(
		ArrowFunction|Closure $node,
		GeneratorScope $scope,
		Type $returnType,
		array $throwPoints,
		array $impurePoints,
		array $invalidateExpressions,
		array $usedVariables,
	): ClosureType
	{
		$parameters = [];
		$isVariadic = false;
		$firstOptionalParameterIndex = null;
		foreach ($node->params as $i => $param) {
			$isOptionalCandidate = $param->default !== null || $param->variadic;

			if ($isOptionalCandidate) {
				if ($firstOptionalParameterIndex === null) {
					$firstOptionalParameterIndex = $i;
				}
			} else {
				$firstOptionalParameterIndex = null;
			}
		}

		foreach ($node->params as $i => $param) {
			if ($param->variadic) {
				$isVariadic = true;
			}
			if (!$param->var instanceof Variable || !is_string($param->var->name)) {
				throw new ShouldNotHappenException();
			}
			$parameters[] = new NativeParameterReflection(
				$param->var->name,
				$firstOptionalParameterIndex !== null && $i >= $firstOptionalParameterIndex,
				$scope->getFunctionType($param->type, $scope->isParameterValueNullable($param), false),
				$param->byRef
					? PassedByReference::createCreatesNewVariable()
					: PassedByReference::createNo(),
				$param->variadic,
				$param->default !== null ? $this->initializerExprTypeResolver->getType($param->default, InitializerExprContext::fromScope($scope)) : null,
			);
		}

		foreach ($parameters as $parameter) {
			if ($parameter->passedByReference()->no()) {
				continue;
			}

			$impurePoints[] = new ImpurePoint(
				$scope,
				$node,
				'functionCall',
				'call to a Closure with by-ref parameter',
				true,
			);
		}

		$throwPointsForClosureType = array_map(static fn (InternalThrowPoint $throwPoint) => $throwPoint->explicit ? SimpleThrowPoint::createExplicit($throwPoint->type, $throwPoint->canContainAnyThrowable) : SimpleThrowPoint::createImplicit(), $throwPoints);
		$impurePointsForClosureType = array_map(static fn (ImpurePoint $impurePoint) => new SimpleImpurePoint($impurePoint->getIdentifier(), $impurePoint->getDescription(), $impurePoint->isCertain()), $impurePoints);

		$mustUseReturnValue = TrinaryLogic::createNo();
		foreach ($node->attrGroups as $attrGroup) {
			foreach ($attrGroup->attrs as $attr) {
				if ($attr->name->toLowerString() === 'nodiscard') {
					$mustUseReturnValue = TrinaryLogic::createYes();
					break;
				}
			}
		}

		return new ClosureType(
			$parameters,
			$returnType,
			$isVariadic,
			TemplateTypeMap::createEmpty(),
			TemplateTypeMap::createEmpty(),
			TemplateTypeVarianceMap::createEmpty(),
			throwPoints: $throwPointsForClosureType,
			usedVariables: $usedVariables,
			impurePoints: $impurePointsForClosureType,
			invalidateExpressions: $invalidateExpressions,
			acceptsNamedArguments: TrinaryLogic::createYes(),
			mustUseReturnValue: $mustUseReturnValue,
		);
	}

	/**
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, ParameterReflection[]|null>
	 */
	public function createCallableParameters(ArrowFunction|Closure $node, GeneratorScope $scope): Generator
	{
		$callableParameters = null;
		$arrayMapArgs = $node->getAttribute(ArrayMapArgVisitor::ATTRIBUTE_NAME);
		$immediatelyInvokedArgs = $node->getAttribute(ImmediatelyInvokedClosureVisitor::ARGS_ATTRIBUTE_NAME);
		if ($arrayMapArgs !== null) {
			$callableParameters = [];
			foreach ($arrayMapArgs as $funcCallArg) {
				$callableParameters[] = new DummyParameter('item', (yield new TypeExprRequest($funcCallArg->value))->type->getIterableValueType(), false, PassedByReference::createNo(), false, null);
			}
		} elseif ($immediatelyInvokedArgs !== null) {
			foreach ($immediatelyInvokedArgs as $immediatelyInvokedArg) {
				$callableParameters[] = new DummyParameter('item', (yield new TypeExprRequest($immediatelyInvokedArg->value))->type, false, PassedByReference::createNo(), false, null);
			}
		} else {
			$inFunctionCallsStack = $scope->getFunctionCallStackWithParameters();
			$inFunctionCallsStackCount = count($inFunctionCallsStack);
			if ($inFunctionCallsStackCount > 0) {
				[, $inParameter] = $inFunctionCallsStack[$inFunctionCallsStackCount - 1];
				if ($inParameter !== null) {
					$callableParameters = $this->fromInParameterType($scope, $inParameter->getType());
				}
			}
		}

		return $callableParameters;
	}

	/**
	 * @return ParameterReflection[]|null
	 */
	private function fromInParameterType(GeneratorScope $scope, Type $passedToType): ?array
	{
		$callableParameters = null;
		if (!$passedToType->isCallable()->no()) {
			if ($passedToType instanceof UnionType) {
				$passedToType = $passedToType->filterTypes(static fn (Type $innerType) => $innerType->isCallable()->yes());

				if ($passedToType->isCallable()->no()) {
					return null;
				}
			}

			$acceptors = $passedToType->getCallableParametersAcceptors($scope);
			if (count($acceptors) > 0) {
				foreach ($acceptors as $acceptor) {
					if ($callableParameters === null) {
						$callableParameters = array_map(static fn (ParameterReflection $callableParameter) => new NativeParameterReflection(
							$callableParameter->getName(),
							$callableParameter->isOptional(),
							$callableParameter->getType(),
							$callableParameter->passedByReference(),
							$callableParameter->isVariadic(),
							$callableParameter->getDefaultValue(),
						), $acceptor->getParameters());
						continue;
					}

					$newParameters = [];
					foreach ($acceptor->getParameters() as $i => $callableParameter) {
						if (!array_key_exists($i, $callableParameters)) {
							$newParameters[] = $callableParameter;
							continue;
						}

						$newParameters[] = $callableParameters[$i]->union(new NativeParameterReflection(
							$callableParameter->getName(),
							$callableParameter->isOptional(),
							$callableParameter->getType(),
							$callableParameter->passedByReference(),
							$callableParameter->isVariadic(),
							$callableParameter->getDefaultValue(),
						));
					}

					$callableParameters = $newParameters;
				}
			}
		}

		return $callableParameters;
	}

}
