<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use Generator;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\ExpressionContext;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\ExprAnalysisResult;
use PHPStan\Analyser\Generator\ExprHandler;
use PHPStan\Analyser\Generator\GeneratorNodeScopeResolver;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\NodeCallbackRequest;
use PHPStan\Analyser\Generator\NodeHandler\ParamHandler;
use PHPStan\Analyser\Generator\TypeExprRequest;
use PHPStan\Analyser\ImpurePoint;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Node\InArrowFunctionNode;
use PHPStan\Node\InvalidateExprNode;
use PHPStan\Node\PropertyAssignNode;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\VoidType;
use function array_merge;

/**
 * @implements ExprHandler<ArrowFunction>
 * @phpstan-import-type GeneratorTValueType from GeneratorNodeScopeResolver
 * @phpstan-import-type GeneratorTSendType from GeneratorNodeScopeResolver
 */
#[AutowiredService]
final class ArrowFunctionHandler implements ExprHandler
{

	public function __construct(
		private ClosureHelper $closureHelper,
		private ParamHandler $paramHandler,
	)
	{
	}

	public function supports(Expr $expr): bool
	{
		return $expr instanceof ArrowFunction;
	}

	public function analyseExpr(
		Stmt $stmt,
		Expr $expr,
		GeneratorScope $scope,
		ExpressionContext $context,
		?callable $alternativeNodeCallback,
	): Generator
	{
		$gen = $this->processArrowFunctionNode($stmt, $expr, $scope, $alternativeNodeCallback);
		yield from $gen;
		$result = $gen->getReturn();

		return new ExprAnalysisResult(
			$result->type,
			$result->nativeType,
			$result->scope,
			hasYield: $result->hasYield,
			isAlwaysTerminating: false,
			throwPoints: [],
			impurePoints: [],
			specifiedTruthyTypes: new SpecifiedTypes(),
			specifiedFalseyTypes: new SpecifiedTypes(),
			specifiedNullTypes: new SpecifiedTypes(),
		);
	}

	/**
	 * @param (callable(Node, Scope, callable(Node, Scope): void): void)|null $alternativeNodeCallback
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, ExprAnalysisResult>
	 */
	public function processArrowFunctionNode(
		Stmt $stmt,
		ArrowFunction $expr,
		GeneratorScope $scope,
		?callable $alternativeNodeCallback,
	): Generator
	{
		foreach ($expr->params as $param) {
			yield from $this->paramHandler->processParam($stmt, $param, $scope, $alternativeNodeCallback);
		}
		if ($expr->returnType !== null) {
			yield new NodeCallbackRequest($expr->returnType, $scope, $alternativeNodeCallback);
		}

		$closureTypeGen = $this->getArrowFunctionScope($stmt, $scope, $expr);
		yield from $closureTypeGen;
		[$arrowFunctionScope, $exprResult] = $closureTypeGen->getReturn();

		$arrowFunctionType = $arrowFunctionScope->getAnonymousFunctionReflection();
		if ($arrowFunctionType === null) {
			throw new ShouldNotHappenException();
		}
		yield new NodeCallbackRequest(new InArrowFunctionNode($arrowFunctionType, $expr), $arrowFunctionScope, $alternativeNodeCallback);

		return new ExprAnalysisResult(
			$arrowFunctionType,
			$arrowFunctionType,
			$scope,
			hasYield: false,
			isAlwaysTerminating: $exprResult->isAlwaysTerminating,
			throwPoints: $exprResult->throwPoints,
			impurePoints: $exprResult->impurePoints,
			specifiedTruthyTypes: new SpecifiedTypes(),
			specifiedFalseyTypes: new SpecifiedTypes(),
			specifiedNullTypes: new SpecifiedTypes(),
		);
	}

	/**
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, array{GeneratorScope, ExprAnalysisResult}>
	 */
	private function getArrowFunctionScope(Stmt $stmt, GeneratorScope $scope, ArrowFunction $node): Generator
	{
		$callableParametersGen = $this->closureHelper->createCallableParameters($node, $scope);
		yield from $callableParametersGen;
		$callableParameters = $callableParametersGen->getReturn();

		$arrowScopeGen = $scope->enterArrowFunctionWithoutReflection($node, $callableParameters);

		yield from $arrowScopeGen;
		$arrowScope = $arrowScopeGen->getReturn();
		$arrowFunctionImpurePoints = [];
		$invalidateExpressions = [];
		$arrowFunctionExprResult = yield new ExprAnalysisRequest($stmt, $node->expr, $arrowScope, ExpressionContext::createDeep(), static function (Node $node, Scope $scope, callable $nodeCallback) use ($arrowScope, &$arrowFunctionImpurePoints, &$invalidateExpressions): void {
			$nodeCallback($node, $scope);
			if ($scope->getAnonymousFunctionReflection() !== $arrowScope->getAnonymousFunctionReflection()) {
				return;
			}

			if ($node instanceof InvalidateExprNode) {
				$invalidateExpressions[] = $node;
				return;
			}

			if (!$node instanceof PropertyAssignNode) {
				return;
			}

			$arrowFunctionImpurePoints[] = new ImpurePoint(
				$scope,
				$node,
				'propertyAssign',
				'property assignment',
				true,
			);
		});

		$impurePoints = array_merge($arrowFunctionImpurePoints, $arrowFunctionExprResult->impurePoints);

		if ($node->expr instanceof Expr\Yield_ || $node->expr instanceof Expr\YieldFrom) {
			$yieldNode = $node->expr;

			if ($yieldNode instanceof Expr\Yield_) {
				if ($yieldNode->key === null) {
					$keyType = new IntegerType();
				} else {
					$keyType = (yield new TypeExprRequest($yieldNode->key))->type;
				}

				if ($yieldNode->value === null) {
					$valueType = new NullType();
				} else {
					$valueType = (yield new TypeExprRequest($yieldNode->value))->type;
				}
			} else {
				$yieldFromType = (yield new TypeExprRequest($yieldNode->expr))->type;
				$keyType = $arrowScope->getIterableKeyType($yieldFromType);
				$valueType = $arrowScope->getIterableValueType($yieldFromType);
			}

			$returnType = new GenericObjectType(Generator::class, [
				$keyType,
				$valueType,
				new MixedType(),
				new VoidType(),
			]);
		} else {
			$returnType = $arrowFunctionExprResult->keepVoidType;
			if ($node->returnType !== null) {
				$nativeReturnType = $scope->getFunctionType($node->returnType, false, false);
				$returnType = GeneratorScope::intersectButNotNever($nativeReturnType, $returnType);
			}
		}

		return [
			$arrowScope->enterArrowFunction($this->closureHelper->createClosureType(
				$node,
				$scope,
				$returnType,
				$arrowFunctionExprResult->throwPoints,
				$impurePoints,
				$invalidateExpressions,
				[],
			)),
			$arrowFunctionExprResult,
		];
	}

}
