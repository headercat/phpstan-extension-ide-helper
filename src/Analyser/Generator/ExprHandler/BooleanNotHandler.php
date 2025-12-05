<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use Generator;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\ExpressionContext;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\ExprAnalysisResult;
use PHPStan\Analyser\Generator\ExprHandler;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Type\BooleanType;
use PHPStan\Type\Constant\ConstantBooleanType;

/**
 * @implements ExprHandler<BooleanNot>
 */
#[AutowiredService]
final class BooleanNotHandler implements ExprHandler
{

	public function supports(Expr $expr): bool
	{
		return $expr instanceof BooleanNot;
	}

	public function analyseExpr(Stmt $stmt, Expr $expr, GeneratorScope $scope, ExpressionContext $context, ?callable $alternativeNodeCallback): Generator
	{
		$result = yield new ExprAnalysisRequest($stmt, $expr->expr, $scope, $context->enterDeep(), $alternativeNodeCallback);
		$exprBooleanType = $result->type->toBoolean();
		$exprBooleanNativeType = $result->nativeType->toBoolean();

		return new ExprAnalysisResult(
			$exprBooleanType instanceof ConstantBooleanType ? new ConstantBooleanType(!$exprBooleanType->getValue()) : new BooleanType(),
			$exprBooleanNativeType instanceof ConstantBooleanType ? new ConstantBooleanType(!$exprBooleanNativeType->getValue()) : new BooleanType(),
			$result->scope,
			hasYield: $result->hasYield,
			isAlwaysTerminating: $result->isAlwaysTerminating,
			throwPoints: $result->throwPoints,
			impurePoints: $result->impurePoints,
			specifiedTruthyTypes: $result->specifiedFalseyTypes,
			specifiedFalseyTypes: $result->specifiedTruthyTypes,
			specifiedNullTypes: new SpecifiedTypes(),
		);
	}

}
