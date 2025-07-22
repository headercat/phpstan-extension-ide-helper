<?php 

namespace PHPStan\Node\Expr;
return;

use Override;
use PhpParser\Node\Expr;
use PHPStan\Node\VirtualNode;

final class OriginalPropertyTypeExpr extends Expr implements VirtualNode
{

	public function __construct(private Expr\PropertyFetch|Expr\StaticPropertyFetch $propertyFetch)
	{
		parent::__construct([]);
	}

	public function getPropertyFetch(): Expr\PropertyFetch|Expr\StaticPropertyFetch
	{
		return $this->propertyFetch;
	}

	#[Override]
	public function getType(): string
	{
		return 'PHPStan_Node_OriginalPropertyTypeExpr';
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
