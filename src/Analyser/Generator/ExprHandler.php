<?php 

namespace PHPStan\Analyser\Generator;
return;

use Generator;
use PhpParser\Node\Expr;

/**
 * @template T of Expr
 */
interface ExprHandler
{

	public const HANDLER_TAG = 'phpstan.gnsr.exprHandler';

	/**
	 * @phpstan-assert-if-true T $expr
	 */
	public function supports(Expr $expr): bool;

	/**
	 * @param T $expr
	 * @return Generator<int, ExprAnalysisRequest|NodeCallbackRequest, ExprAnalysisResult, ExprAnalysisResult>
	 */
	public function analyseExpr(Expr $expr, GeneratorScope $scope): Generator;

}
