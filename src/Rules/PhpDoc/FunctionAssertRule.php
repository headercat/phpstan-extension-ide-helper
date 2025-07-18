<?php 

namespace PHPStan\Rules\PhpDoc;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\RegisteredRule;
use PHPStan\Node\InFunctionNode;
use PHPStan\Rules\Rule;
use function count;

/**
 * @implements Rule<InFunctionNode>
 */
#[RegisteredRule(level: 2)]
final class FunctionAssertRule implements Rule
{

	public function __construct(private AssertRuleHelper $helper)
	{
	}

	public function getNodeType(): string
	{
		return InFunctionNode::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$function = $node->getFunctionReflection();
		$variants = $function->getVariants();
		if (count($variants) !== 1) {
			return [];
		}

		return $this->helper->check($scope, $node->getOriginalNode(), $function, $variants[0]);
	}

}
