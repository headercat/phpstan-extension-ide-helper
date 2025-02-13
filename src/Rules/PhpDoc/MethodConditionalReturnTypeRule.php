<?php 

namespace PHPStan\Rules\PhpDoc;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\Rule;
use function count;

/**
 * @implements Rule<InClassMethodNode>
 */
final class MethodConditionalReturnTypeRule implements Rule
{

	public function __construct(private ConditionalReturnTypeRuleHelper $helper)
	{
	}

	public function getNodeType(): string
	{
		return InClassMethodNode::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$method = $node->getMethodReflection();
		$variants = $method->getVariants();
		if (count($variants) !== 1) {
			return [];
		}

		return $this->helper->check($variants[0]);
	}

}
