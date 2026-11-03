<?php 

namespace PHPStan\Node\Expr;
return;

use Override;
use PhpParser\Node\Expr;
use PHPStan\Node\VirtualNode;
use PHPStan\Turbo\ReferencedByTurboExtension;

#[ReferencedByTurboExtension(key: 'foreachValueByRefExpr')]
final class ForeachValueByRefExpr extends Expr implements VirtualNode
{

	public function __construct(private Expr $expr)
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
		return 'PHPStan_Node_ForeachValueByRefExpr';
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
