<?php 

namespace PHPStan\Analyser;
return;

use PhpParser\Node;
use PHPStan\Node\PropertyAssignNode;
use PHPStan\Node\VariableAssignNode;

final class VirtualAssignNodeCallback implements ShallowNodeCallback
{

	/**
	 * @param callable(Node $node, Scope $scope): void $originalNodeCallback
	 */
	public function __construct(private mixed $originalNodeCallback)
	{
	}

	public function __invoke(Node $node, Scope $scope): void
	{
		if (!$node instanceof PropertyAssignNode && !$node instanceof VariableAssignNode) {
			return;
		}

		($this->originalNodeCallback)($node, $scope);
	}

}
