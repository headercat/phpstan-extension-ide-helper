<?php 

namespace PHPStan\Analyser\Generator\ExprHandler\Virtual;
return;

use Generator;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\ExpressionContext;
use PHPStan\Analyser\Generator\ExprAnalysisResult;
use PHPStan\Analyser\Generator\ExprHandler;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Node\Expr\TypeExpr;

/**
 * @implements ExprHandler<TypeExpr>
 */
#[AutowiredService]
final class TypeExprHandler implements ExprHandler
{

	public function supports(Expr $expr): bool
	{
		return $expr instanceof TypeExpr;
	}

	public function analyseExpr(Stmt $stmt, Expr $expr, GeneratorScope $scope, ExpressionContext $context, ?callable $alternativeNodeCallback): Generator
	{
		yield from [];
		return new ExprAnalysisResult(
			type: $expr->getExprType(),
			nativeType: $expr->getExprType(),
			scope: $scope,
			hasYield: false,
			isAlwaysTerminating: false,
			throwPoints: [],
			impurePoints: [],
			specifiedTruthyTypes: new SpecifiedTypes(),
			specifiedFalseyTypes: new SpecifiedTypes(),
			specifiedNullTypes: new SpecifiedTypes(),
		);
	}

}
