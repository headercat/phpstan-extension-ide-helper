<?php 

namespace PHPStan\Rules\Classes;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\RegisteredRule;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use function strtolower;

/**
 * @implements Rule<InClassNode>
 */
#[RegisteredRule(level: 0)]
final class DuplicateDeclarationRule implements Rule
{

	public function __construct(private DuplicateDeclarationHelper $helper)
	{
	}

	public function getNodeType(): string
	{
		return InClassNode::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$classReflection = $node->getClassReflection();

		return $this->helper->checkClassLike(
			$node->getOriginalNode(),
			$classReflection->getDisplayName(),
			strtolower($classReflection->getClassTypeDescription()),
		);
	}

}
