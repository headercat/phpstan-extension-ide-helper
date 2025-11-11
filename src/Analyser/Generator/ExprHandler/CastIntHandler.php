<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use Generator;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Cast\Int_;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\ExprAnalysisResult;
use PHPStan\Analyser\Generator\ExprHandler;
use PHPStan\Analyser\Generator\GeneratorScope;
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

	public function analyseExpr(Expr $expr, GeneratorScope $scope): Generator
	{
		$exprResult = yield new ExprAnalysisRequest($expr->expr, $scope);

		return new ExprAnalysisResult($exprResult->type->toInteger(), $scope);
	}

}
