<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use Generator;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\Int_;
use PHPStan\Analyser\Generator\ExprAnalysisResult;
use PHPStan\Analyser\Generator\ExprHandler;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Type\Constant\ConstantIntegerType;

/**
 * @implements ExprHandler<Int_>
 */
#[AutowiredService]
final class ScalarIntHandler implements ExprHandler
{

	public function supports(Expr $expr): bool
	{
		return $expr instanceof Int_;
	}

	public function analyseExpr(Expr $expr, GeneratorScope $scope): Generator
	{
		yield from [];
		return new ExprAnalysisResult(new ConstantIntegerType($expr->value), $scope);
	}

}
