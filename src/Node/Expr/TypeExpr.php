<?php 

namespace PHPStan\Node\Expr;
return;

use Override;
use PhpParser\Node\Expr;
use PHPStan\Node\VirtualNode;
use PHPStan\Type\Type;

/**
 * @api
 */
final class TypeExpr extends Expr implements VirtualNode
{

	/** @api */
	public function __construct(private Type $exprType)
	{
		parent::__construct();
	}

	public function getExprType(): Type
	{
		return $this->exprType;
	}

	#[Override]
	public function getType(): string
	{
		return 'PHPStan_Node_TypeExpr';
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
