<?php 

namespace PHPStan\Rules\Traits;
return;

use Attribute;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\RegisteredRule;
use PHPStan\Node\InTraitNode;
use PHPStan\Php\PhpVersion;
use PHPStan\Rules\AttributesCheck;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use function count;

/**
 * @implements Rule<InTraitNode>
 */
#[RegisteredRule(level: 0)]
final class TraitAttributesRule implements Rule
{

	public function __construct(
		private AttributesCheck $attributesCheck,
		private PhpVersion $phpVersion,
	)
	{
	}

	public function getNodeType(): string
	{
		return InTraitNode::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		if (!$this->phpVersion->supportsDeprecatedTraits()) {
			if (count($node->getTraitReflection()->getNativeReflection()->getAttributes('Deprecated')) > 0) {
				return [
					RuleErrorBuilder::message('Attribute class Deprecated can be used with traits only on PHP 8.5 and later.')
						->identifier('trait.deprecatedAttribute')
						->nonIgnorable()
						->build(),
				];
			}
		}

		$originalNode = $node->getOriginalNode();
		$errors = $this->attributesCheck->check(
			$scope,
			$originalNode->attrGroups,
			Attribute::TARGET_CLASS,
			'class',
		);

		if (count($node->getTraitReflection()->getNativeReflection()->getAttributes('AllowDynamicProperties')) > 0) {
			$errors[] = RuleErrorBuilder::message('Attribute class AllowDynamicProperties cannot be used with trait.')
				->identifier('trait.allowDynamicProperties')
				->nonIgnorable()
				->build();
		}

		return $errors;
	}

}
