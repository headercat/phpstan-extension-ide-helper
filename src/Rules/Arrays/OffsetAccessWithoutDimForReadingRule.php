<?php 

namespace PHPStan\Rules\Arrays;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node\Expr\ArrayDimFetch>
 */
final class OffsetAccessWithoutDimForReadingRule implements Rule
{

	public function getNodeType(): string
	{
		return Node\Expr\ArrayDimFetch::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		if ($scope->isInExpressionAssign($node)) {
			return [];
		}

		if ($node->dim !== null) {
			return [];
		}

		return [
			RuleErrorBuilder::message('Cannot use [] for reading.')
				->identifier('offsetAccess.noDim')
				->nonIgnorable()
				->build(),
		];
	}

}
