<?php 

namespace PHPStan\Node;
return;

use PhpParser\Node\Expr;
use PhpParser\NodeAbstract;

/**
 * @api
 */
final class InvalidateExprNode extends NodeAbstract implements VirtualNode
{

	public function __construct(private Expr $expr)
	{
		parent::__construct($expr->getAttributes());
	}

	public function getExpr(): Expr
	{
		return $this->expr;
	}

	public function getType(): string
	{
		return 'PHPStan_Node_InvalidateExpr';
	}

	/**
	 * @return string[]
	 */
	public function getSubNodeNames(): array
	{
		return [];
	}

}
