<?php 

namespace PHPStan\Rules\Classes;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\RegisteredRule;
use PHPStan\Node\InstantiationCallableNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InstantiationCallableNode>
 */
#[RegisteredRule(level: 0)]
final class InstantiationCallableRule implements Rule
{

	public function getNodeType(): string
	{
		return InstantiationCallableNode::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		return [
			RuleErrorBuilder::message('Cannot create callable from the new operator.')
				->identifier('callable.notSupported')
				->nonIgnorable()
				->build(),
		];
	}

}
