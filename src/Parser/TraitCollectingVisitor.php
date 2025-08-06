<?php 

namespace PHPStan\Parser;
return;

use Override;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class TraitCollectingVisitor extends NodeVisitorAbstract
{

	/** @var list<Node\Stmt\Trait_> */
	public array $traits = [];

	#[Override]
	public function enterNode(Node $node): ?Node
	{
		if (!$node instanceof Node\Stmt\Trait_) {
			return null;
		}

		$this->traits[] = $node;

		return null;
	}

}
