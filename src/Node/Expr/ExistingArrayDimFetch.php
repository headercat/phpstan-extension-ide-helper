<?php 

namespace PHPStan\Node\Expr;
return;

use Override;
use PhpParser\Node\Expr;
use PHPStan\Node\VirtualNode;

final class ExistingArrayDimFetch extends Expr implements VirtualNode
{

	public function __construct(private Expr $var, private Expr $dim)
	{
		parent::__construct([]);
	}

	public function getVar(): Expr
	{
		return $this->var;
	}

	public function getDim(): Expr
	{
		return $this->dim;
	}

	#[Override]
	public function getType(): string
	{
		return 'PHPStan_Node_ExistingArrayDimFetch';
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
