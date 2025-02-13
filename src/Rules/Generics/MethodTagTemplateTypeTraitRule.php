<?php 

namespace PHPStan\Rules\Generics;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<Node\Stmt\Trait_>
 */
final class MethodTagTemplateTypeTraitRule implements Rule
{

	public function __construct(
		private MethodTagTemplateTypeCheck $check,
		private ReflectionProvider $reflectionProvider,
	)
	{
	}

	public function getNodeType(): string
	{
		return Node\Stmt\Trait_::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$docComment = $node->getDocComment();
		if ($docComment === null) {
			return [];
		}

		$traitName = $node->namespacedName;
		if ($traitName === null) {
			return [];
		}

		if (!$this->reflectionProvider->hasClass($traitName->toString())) {
			return [];
		}

		return $this->check->check(
			$this->reflectionProvider->getClass($traitName->toString()),
			$scope,
			$node,
			$docComment->getText(),
		);
	}

}
