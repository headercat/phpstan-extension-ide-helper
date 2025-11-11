<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use Generator;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\ExprAnalysisResult;
use PHPStan\Analyser\Generator\ExprHandler;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\ShouldNotHappenException;
use function is_string;

/**
 * @implements ExprHandler<Assign>
 */
#[AutowiredService]
final class AssignHandler implements ExprHandler
{

	public function supports(Expr $expr): bool
	{
		return $expr instanceof Assign;
	}

	public function analyseExpr(Expr $expr, GeneratorScope $scope): Generator
	{
		if (!$expr->var instanceof Expr\Variable || !is_string($expr->var->name)) {
			throw new ShouldNotHappenException('Not implemented');
		}
		$variableName = $expr->var->name;
		$exprResult = yield new ExprAnalysisRequest($expr->expr, $scope);
		$varResult = yield new ExprAnalysisRequest($expr->var, $scope);

		return new ExprAnalysisResult($exprResult->type, $varResult->scope->assignVariable($variableName, $exprResult->type));
	}

}
