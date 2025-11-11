<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use Generator;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Generator\ExprAnalysisResult;
use PHPStan\Analyser\Generator\ExprHandler;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Type\Constant\ConstantStringType;

/**
 * @implements ExprHandler<String_>
 */
#[AutowiredService]
final class ScalarStringHandler implements ExprHandler
{

	public function supports(Expr $expr): bool
	{
		return $expr instanceof String_;
	}

	public function analyseExpr(Expr $expr, GeneratorScope $scope): Generator
	{
		yield from [];
		return new ExprAnalysisResult(new ConstantStringType($expr->value), $scope);
	}

}
