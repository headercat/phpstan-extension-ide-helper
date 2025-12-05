<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use Generator;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\Cast\Int_;
use PhpParser\Node\Scalar\Int_ as ScalarInt;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\ExpressionContext;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\ExprAnalysisResult;
use PHPStan\Analyser\Generator\ExprHandler;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\DependencyInjection\AutowiredService;

/**
 * @implements ExprHandler<Int_>
 */
#[AutowiredService]
final class CastIntHandler implements ExprHandler
{

	public function supports(Expr $expr): bool
	{
		return $expr instanceof Int_;
	}

	public function analyseExpr(
		Stmt $stmt,
		Expr $expr,
		GeneratorScope $scope,
		ExpressionContext $context,
		?callable $alternativeNodeCallback,
	): Generator
	{
		$exprResult = yield new ExprAnalysisRequest($stmt, $expr->expr, $scope, $context->enterDeep(), $alternativeNodeCallback);

		$notEqualExprResult = yield ExprAnalysisRequest::createNoopRequest(new NotEqual($expr->expr, new ScalarInt(0)), $scope);

		return new ExprAnalysisResult(
			$exprResult->type->toInteger(),
			$exprResult->nativeType->toInteger(),
			$scope,
			hasYield: false,
			isAlwaysTerminating: false,
			throwPoints: [],
			impurePoints: [],
			specifiedTruthyTypes: $notEqualExprResult->specifiedTruthyTypes->setRootExpr($expr),
			specifiedFalseyTypes: $notEqualExprResult->specifiedFalseyTypes->setRootExpr($expr),
			specifiedNullTypes: new SpecifiedTypes(),
		);
	}

}
