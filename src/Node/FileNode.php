<?php 

namespace PHPStan\Node;
return;

use Override;
use PhpParser\Node;
use PhpParser\NodeAbstract;

/**
 * @api
 */
final class FileNode extends NodeAbstract implements VirtualNode
{

	/**
	 * @param Node[] $nodes
	 */
	public function __construct(private array $nodes)
	{
		$firstNode = $nodes[0] ?? null;
		parent::__construct($firstNode !== null ? $firstNode->getAttributes() : []);
	}

	/**
	 * @return Node[]
	 */
	public function getNodes(): array
	{
		return $this->nodes;
	}

	#[Override]
	public function getType(): string
	{
		return 'PHPStan_Node_FileNode';
	}

	/**
	 * @return string[]
	 */
	#[Override]
	public function getSubNodeNames(): array
	{
		return [];
	}

}
