<?php 

namespace PHPStan\Rules\DeadCode;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\MethodReturnStatementsNode;
use function count;

/**
 * @implements Collector<MethodReturnStatementsNode, string>
 */
final class ConstructorWithoutImpurePointsCollector implements Collector
{

	public function getNodeType(): string
	{
		return MethodReturnStatementsNode::class;
	}

	public function processNode(Node $node, Scope $scope)
	{
		$method = $node->getMethodReflection();
		if (!$method->isConstructor()) {
			return null;
		}

		if (!$method->isPure()->maybe()) {
			return null;
		}

		if (count($node->getImpurePoints()) !== 0) {
			return null;
		}

		if (count($node->getStatementResult()->getThrowPoints()) !== 0) {
			return null;
		}

		foreach ($method->getParameters() as $parameter) {
			if (!$parameter->passedByReference()->createsNewVariable()) {
				continue;
			}

			return null;
		}

		if (count($method->getAsserts()->getAll()) !== 0) {
			return null;
		}

		return $method->getDeclaringClass()->getName();
	}

}
