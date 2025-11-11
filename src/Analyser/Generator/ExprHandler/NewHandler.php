<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use Generator;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Generator\ExprAnalysisResult;
use PHPStan\Analyser\Generator\ExprHandler;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\ObjectType;

/**
 * @implements ExprHandler<New_>
 */
#[AutowiredService]
final class NewHandler implements ExprHandler
{

	public function supports(Expr $expr): bool
	{
		return $expr instanceof New_;
	}

	public function analyseExpr(Expr $expr, GeneratorScope $scope): Generator
	{
		if (!$expr->class instanceof Name) {
			throw new ShouldNotHappenException('Not implemented');
		}

		yield from [];
		return new ExprAnalysisResult(new ObjectType($expr->class->toString()), $scope);
	}

}
