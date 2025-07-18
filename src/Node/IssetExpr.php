<?php 

namespace PHPStan\Node;
return;

use Override;
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

	#[Override]
	public function getType(): string
	{
		return 'PHPStan_Node_IssetExpr';
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
