<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use Generator;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\ConstantResolver;
use PHPStan\Analyser\ExpressionContext;
use PHPStan\Analyser\Generator\ExprAnalysisResult;
use PHPStan\Analyser\Generator\ExprHandler;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\NodeCallbackRequest;
use PHPStan\Analyser\Generator\SpecifiedTypesHelper;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\ErrorType;
use PHPStan\Type\NullType;
use PHPStan\Type\Type;
use function strtolower;

/**
 * @implements ExprHandler<ConstFetch>
 */
#[AutowiredService]
final class ConstFetchHandler implements ExprHandler
{

	public function __construct(
		private ConstantResolver $constantResolver,
		private SpecifiedTypesHelper $specifiedTypesHelper,
	)
	{
	}

	public function supports(Expr $expr): bool
	{
		return $expr instanceof ConstFetch;
	}

	public function analyseExpr(Stmt $stmt, Expr $expr, GeneratorScope $scope, ExpressionContext $context, ?callable $alternativeNodeCallback): Generator
	{
		yield new NodeCallbackRequest($expr->name, $scope, $alternativeNodeCallback);

		$type = $this->getConstFetchType($expr, $scope);

		return new ExprAnalysisResult(
			$type,
			$type,
			$scope,
			hasYield: false,
			isAlwaysTerminating: false,
			throwPoints: [],
			impurePoints: [],
			specifiedTruthyTypes: $this->specifiedTypesHelper->createDefaultSpecifiedTruthyTypes($expr),
			specifiedFalseyTypes: $this->specifiedTypesHelper->createDefaultSpecifiedFalseyTypes($expr),
			specifiedNullTypes: new SpecifiedTypes(),
		);
	}

	private function getConstFetchType(ConstFetch $expr, GeneratorScope $scope): Type
	{
		$constName = (string) $expr->name;
		$loweredConstName = strtolower($constName);
		if ($loweredConstName === 'true') {
			return new ConstantBooleanType(true);
		} elseif ($loweredConstName === 'false') {
			return new ConstantBooleanType(false);
		} elseif ($loweredConstName === 'null') {
			return new NullType();
		}

		$namespacedName = null;
		if (!$expr->name->isFullyQualified() && $scope->getNamespace() !== null) {
			$namespacedName = new FullyQualified([$scope->getNamespace(), $expr->name->toString()]);
		}
		$globalName = new FullyQualified($expr->name->toString());

		foreach ([$namespacedName, $globalName] as $name) {
			if ($name === null) {
				continue;
			}
			$constFetch = new ConstFetch($name);
			$exprType = $scope->getExpressionType($constFetch);
			if ($exprType !== null) {
				return $this->constantResolver->resolveConstantType(
					$name->toString(),
					$exprType,
				);
			}
		}

		$constantType = $this->constantResolver->resolveConstant($expr->name, $scope);
		if ($constantType !== null) {
			return $constantType;
		}

		return new ErrorType();
	}

}
