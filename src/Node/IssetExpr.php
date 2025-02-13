<?php 

namespace PHPStan\Node;
return;

use PhpParser\Node\Expr;

/**
 * @api
 */
final class IssetExpr extends Expr implements VirtualNode
{

	/**
	 * @api
	 */
	public function __construct(
		private Expr $expr,
	)
	{
		parent::__construct([]);
	}

	public function getExpr(): Expr
	{
		return $this->expr;
	}

	public function getType(): string
	{
		return 'PHPStan_Node_IssetExpr';
	}

	/**
	 * @return string[]
	 */
	public function getSubNodeNames(): array
	{
		return [];
	}

}
