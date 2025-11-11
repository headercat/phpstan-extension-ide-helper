<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use Generator;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Generator\ExprAnalysisResult;
use PHPStan\Analyser\Generator\ExprHandler;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Constant\ConstantStringType;

/**
 * @implements ExprHandler<ClassConstFetch>
 */
#[AutowiredService]
final class ClassConstFetchHandler implements ExprHandler
{

	public function supports(Expr $expr): bool
	{
		return $expr instanceof ClassConstFetch;
	}

	public function analyseExpr(Expr $expr, GeneratorScope $scope): Generator
	{
		if (
			$expr->class instanceof Name
			&& $expr->name instanceof Identifier
			&& $expr->name->toLowerString() === 'class'
		) {
			yield from [];
			return new ExprAnalysisResult(new ConstantStringType($expr->class->toString()), $scope);
		}

		throw new ShouldNotHappenException('Not implemented');
	}

}
