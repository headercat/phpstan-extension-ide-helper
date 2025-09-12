<?php 

namespace PHPStan\Node;
return;

use Override;
use PhpParser\Node\Expr;
use PhpParser\NodeAbstract;
use PHPStan\PhpDoc\Tag\VarTag;

final class VarTagChangedExpressionTypeNode extends NodeAbstract implements VirtualNode
{

	public function __construct(private VarTag $varTag, private Expr $expr)
	{
		parent::__construct($expr->getAttributes());
	}

	public function getVarTag(): VarTag
	{
		return $this->varTag;
	}

	public function getExpr(): Expr
	{
		return $this->expr;
	}

	#[Override]
	public function getType(): string
	{
		return 'PHPStan_Node_VarTagChangedExpressionType';
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
