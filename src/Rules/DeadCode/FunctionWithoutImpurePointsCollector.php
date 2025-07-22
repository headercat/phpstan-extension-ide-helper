<?php 

namespace PHPStan\Rules\DeadCode;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\DependencyInjection\RegisteredCollector;
use PHPStan\Node\FunctionReturnStatementsNode;
use function count;

/**
 * @implements Collector<FunctionReturnStatementsNode, string>
 */
#[RegisteredCollector(level: 4)]
final class FunctionWithoutImpurePointsCollector implements Collector
{

	public function getNodeType(): string
	{
		return FunctionReturnStatementsNode::class;
	}

	public function processNode(Node $node, Scope $scope)
	{
		$function = $node->getFunctionReflection();
		if (!$function->isPure()->maybe()) {
			return null;
		}
		if (!$function->hasSideEffects()->maybe()) {
			return null;
		}

		if (count($node->getImpurePoints()) !== 0) {
			return null;
		}

		if (count($node->getStatementResult()->getThrowPoints()) !== 0) {
			return null;
		}

		foreach ($function->getParameters() as $parameter) {
			if (!$parameter->passedByReference()->createsNewVariable()) {
				continue;
			}

			return null;
		}

		if (count($function->getAsserts()->getAll()) !== 0) {
			return null;
		}

		return $function->getName();
	}

}
