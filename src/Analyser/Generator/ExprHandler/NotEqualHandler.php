<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use Generator;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\ExpressionContext;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\ExprAnalysisResult;
use PHPStan\Analyser\Generator\ExprHandler;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\DependencyInjection\AutowiredService;
use function array_merge;

/**
 * @implements ExprHandler<NotEqual>
 */
#[AutowiredService]
final class NotEqualHandler implements ExprHandler
{

	public function supports(Expr $expr): bool
	{
		return $expr instanceof NotEqual;
	}

	public function analyseExpr(Stmt $stmt, Expr $expr, GeneratorScope $scope, ExpressionContext $context, ?callable $alternativeNodeCallback): Generator
	{
		$leftResult = yield new ExprAnalysisRequest($stmt, $expr->left, $scope, $context->enterDeep(), $alternativeNodeCallback);
		$rightResult = yield new ExprAnalysisRequest($stmt, $expr->right, $leftResult->scope, $context->enterDeep(), $alternativeNodeCallback);
		$booleanNotResult = yield ExprAnalysisRequest::createNoopRequest(new BooleanNot(new Equal($expr->left, $expr->right)), $scope);

		return new ExprAnalysisResult(
			$booleanNotResult->type,
			$booleanNotResult->nativeType,
			$rightResult->scope,
			hasYield: $leftResult->hasYield || $rightResult->hasYield,
			isAlwaysTerminating: $leftResult->isAlwaysTerminating || $rightResult->isAlwaysTerminating,
			throwPoints: array_merge($leftResult->throwPoints, $rightResult->throwPoints),
			impurePoints: array_merge($leftResult->impurePoints, $rightResult->impurePoints),
			specifiedTruthyTypes: $booleanNotResult->specifiedTruthyTypes->setRootExpr($expr),
			specifiedFalseyTypes: $booleanNotResult->specifiedFalseyTypes->setRootExpr($expr),
			specifiedNullTypes: new SpecifiedTypes(),
		);
	}

}
