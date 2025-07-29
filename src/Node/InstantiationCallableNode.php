<?php 

namespace PHPStan\Node;
return;

use Override;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;

/**
 * @api
 */
final class InstantiationCallableNode extends Expr implements VirtualNode
{

	public function __construct(private Name|Expr $class, private Expr\New_ $originalNode)
	{
		parent::__construct($this->originalNode->getAttributes());
	}

	/**
	 * @return Expr|Name
	 */
	public function getClass()
	{
		return $this->class;
	}

	public function getOriginalNode(): Expr\New_
	{
		return $this->originalNode;
	}

	#[Override]
	public function getType(): string
	{
		return 'PHPStan_Node_InstantiationCallableNode';
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
