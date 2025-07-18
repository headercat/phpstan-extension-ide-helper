<?php 

namespace PHPStan\Rules\Properties;
return;

use Attribute;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\RegisteredRule;
use PHPStan\Rules\AttributesCheck;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<Node\Stmt\Property>
 */
#[RegisteredRule(level: 0)]
final class PropertyAttributesRule implements Rule
{

	public function __construct(private AttributesCheck $attributesCheck)
	{
	}

	public function getNodeType(): string
	{
		return Node\Stmt\Property::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		return $this->attributesCheck->check(
			$scope,
			$node->attrGroups,
			Attribute::TARGET_PROPERTY,
			'property',
		);
	}

}
