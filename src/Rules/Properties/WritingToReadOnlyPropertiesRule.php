<?php 

namespace PHPStan\Rules\Properties;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredParameter;
use PHPStan\DependencyInjection\RegisteredRule;
use PHPStan\Node\PropertyAssignNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Rules\RuleLevelHelper;
use function sprintf;

/**
 * @implements Rule<PropertyAssignNode>
 */
#[RegisteredRule(level: 0)]
final class WritingToReadOnlyPropertiesRule implements Rule
{

	public function __construct(
		private RuleLevelHelper $ruleLevelHelper,
		private PropertyDescriptor $propertyDescriptor,
		private PropertyReflectionFinder $propertyReflectionFinder,
		#[AutowiredParameter]
		private bool $checkThisOnly,
	)
	{
	}

	public function getNodeType(): string
	{
		return PropertyAssignNode::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$propertyFetch = $node->getPropertyFetch();
		if (
			$propertyFetch instanceof Node\Expr\PropertyFetch
			&& $this->checkThisOnly
			&& !$this->ruleLevelHelper->isThis($propertyFetch->var)
		) {
			return [];
		}

		$propertyReflection = $this->propertyReflectionFinder->findPropertyReflectionFromNode($propertyFetch, $scope);
		if ($propertyReflection === null) {
			return [];
		}

		if (!$scope->canWriteProperty($propertyReflection)) {
			return [];
		}

		if (!$propertyReflection->isWritable()) {
			$propertyDescription = $this->propertyDescriptor->describeProperty($propertyReflection, $scope, $propertyFetch);

			return [
				RuleErrorBuilder::message(sprintf(
					'%s is not writable.',
					$propertyDescription,
				))->identifier('assign.propertyReadOnly')->build(),
			];
		}

		return [];
	}

}
