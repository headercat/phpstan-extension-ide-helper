<?php 

namespace PHPStan\Rules\Classes;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\RegisteredRule;
use PHPStan\Node\InTraitNode;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<InTraitNode>
 */
#[RegisteredRule(level: 2)]
final class MixinTraitUseRule implements Rule
{

	public function __construct(private MixinCheck $check)
	{
	}

	public function getNodeType(): string
	{
		return InTraitNode::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		return $this->check->checkInTraitUseContext(
			$scope,
			$node->getTraitReflection(),
			$node->getImplementingClassReflection(),
			$node->getOriginalNode(),
		);
	}

}
