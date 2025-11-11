<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use Generator;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Closure;
use PHPStan\Analyser\Generator\ExprAnalysisResult;
use PHPStan\Analyser\Generator\ExprHandler;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\StmtsAnalysisRequest;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Type\ClosureType;

/**
 * @implements ExprHandler<Closure>
 */
#[AutowiredService]
final class ClosureHandler implements ExprHandler
{

	public function supports(Expr $expr): bool
	{
		return $expr instanceof Closure;
	}

	public function analyseExpr(Expr $expr, GeneratorScope $scope): Generator
	{
		$result = yield new StmtsAnalysisRequest($expr->stmts, $scope); // @phpstan-ignore generator.valueType
		$scope = $result->scope;

		return new ExprAnalysisResult(new ClosureType(), $scope);
	}

}
