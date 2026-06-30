<?php 

namespace PHPStan\Node;
return;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PHPStan\DependencyInjection\AutowiredService;

#[AutowiredService]
final class DeepNodeCloner
{

	/**
	 * @template T of Node
	 * @param T $node
	 * @return T
	 */
	public function cloneNode(Node $node): Node
	{
		$traverser = new NodeTraverser(new CloningVisitor());

		[$clonedNode] = $traverser->traverse([$node]);

		/** @var T */
		return $clonedNode;
	}

}
