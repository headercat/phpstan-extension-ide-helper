<?php 

namespace PHPStan\Node\Expr;
return;

use PhpParser\Node\Expr;
use PHPStan\Node\VirtualNode;

final class GetIterableKeyTypeExpr extends Expr implements VirtualNode
{

	public function __construct(private Expr $expr)
	{
		parent::__construct([]);
	}

	public function getExpr(): Expr
	{
		return $this->expr;
	}

	public function getType(): string
	{
		return 'PHPStan_Node_GetIterableKeyTypeExpr';
	}

	/**
	 * @return string[]
	 */
	public function getSubNodeNames(): array
	{
		return [];
	}

}
