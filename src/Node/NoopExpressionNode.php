<?php 

namespace PHPStan\Node;
return;

use Override;
use PhpParser\Node\Expr;
use PhpParser\NodeAbstract;

final class NoopExpressionNode extends NodeAbstract implements VirtualNode
{

	public function __construct(private Expr $originalExpr, private bool $hasAssign)
	{
		parent::__construct($this->originalExpr->getAttributes());
	}

	public function getOriginalExpr(): Expr
	{
		return $this->originalExpr;
	}

	public function hasAssign(): bool
	{
		return $this->hasAssign;
	}

	#[Override]
	public function getType(): string
	{
		return 'PHPStan_Node_NoopExpressionNode';
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
