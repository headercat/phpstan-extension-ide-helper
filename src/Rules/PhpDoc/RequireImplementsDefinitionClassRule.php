<?php 

namespace PHPStan\Rules\PhpDoc;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\RegisteredRule;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use function count;
use function sprintf;

/**
 * @implements Rule<InClassNode>
 */
#[RegisteredRule(level: 2)]
final class RequireImplementsDefinitionClassRule implements Rule
{

	public function getNodeType(): string
	{
		return InClassNode::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$classReflection = $node->getClassReflection();
		$implementsTags = $classReflection->getRequireImplementsTags();

		if (count($implementsTags) === 0) {
			return [];
		}

		return [
			RuleErrorBuilder::message('PHPDoc tag @phpstan-require-implements is only valid on trait.')
				->identifier(sprintf('requireImplements.on%s', $classReflection->getClassTypeDescription()))
				->build(),
		];
	}

}
