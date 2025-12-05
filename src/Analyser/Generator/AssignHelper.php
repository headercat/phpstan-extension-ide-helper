<?php 

namespace PHPStan\Analyser\Generator;
return;

use Closure;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PHPStan\DependencyInjection\AutowiredService;

#[AutowiredService]
final class AssignHelper
{

	public function lookForSetAllowedUndefinedExpressions(GeneratorScope $scope, Expr $expr): GeneratorScope
	{
		return $this->lookForExpressionCallback($scope, $expr, static fn (GeneratorScope $scope, Expr $expr): GeneratorScope => $scope->setAllowedUndefinedExpression($expr));
	}

	public function lookForUnsetAllowedUndefinedExpressions(GeneratorScope $scope, Expr $expr): GeneratorScope
	{
		return $this->lookForExpressionCallback($scope, $expr, static fn (GeneratorScope $scope, Expr $expr): GeneratorScope => $scope->unsetAllowedUndefinedExpression($expr));
	}

	/**
	 * @param Closure(GeneratorScope $scope, Expr $expr): GeneratorScope $callback
	 */
	private function lookForExpressionCallback(GeneratorScope $scope, Expr $expr, Closure $callback): GeneratorScope
	{
		if (!$expr instanceof ArrayDimFetch || $expr->dim !== null) {
			$scope = $callback($scope, $expr);
		}

		if ($expr instanceof ArrayDimFetch) {
			$scope = $this->lookForExpressionCallback($scope, $expr->var, $callback);
		} elseif ($expr instanceof PropertyFetch || $expr instanceof Expr\NullsafePropertyFetch) {
			$scope = $this->lookForExpressionCallback($scope, $expr->var, $callback);
		} elseif ($expr instanceof StaticPropertyFetch && $expr->class instanceof Expr) {
			$scope = $this->lookForExpressionCallback($scope, $expr->class, $callback);
		} elseif ($expr instanceof List_) {
			foreach ($expr->items as $item) {
				if ($item === null) {
					continue;
				}

				$scope = $this->lookForExpressionCallback($scope, $item->value, $callback);
			}
		}

		return $scope;
	}

}
