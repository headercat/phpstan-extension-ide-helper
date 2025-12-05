<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use PhpParser\Node\Expr\Variable;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Node\InvalidateExprNode;

#[AutowiredService]
final class ImmediatelyCalledCallableHelper
{

	/**
	 * @param InvalidateExprNode[] $invalidatedExpressions
	 * @param string[] $uses
	 */
	public function processImmediatelyCalledCallable(GeneratorScope $scope, array $invalidatedExpressions, array $uses): GeneratorScope
	{
		if ($scope->isInClass()) {
			$uses[] = 'this';
		}

		$finder = new NodeFinder();
		foreach ($invalidatedExpressions as $invalidateExpression) {
			$found = false;
			foreach ($uses as $use) {
				$result = $finder->findFirst([$invalidateExpression->getExpr()], static fn ($node) => $node instanceof Variable && $node->name === $use);
				if ($result === null) {
					continue;
				}

				$found = true;
				break;
			}

			if (!$found) {
				continue;
			}

			$scope = $scope->invalidateExpression($invalidateExpression->getExpr(), true);
		}

		return $scope;
	}

}
