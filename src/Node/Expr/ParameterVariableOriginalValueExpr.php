<?php 

namespace PHPStan\Node\Expr;
return;

use Override;
use PhpParser\Node\Expr;
use PHPStan\Node\VirtualNode;

final class ParameterVariableOriginalValueExpr extends Expr implements VirtualNode
{

	public function __construct(private string $variableName)
	{
		parent::__construct([]);
	}

	public function getVariableName(): string
	{
		return $this->variableName;
	}

	#[Override]
	public function getType(): string
	{
		return 'PHPStan_Node_ParameterVariableOriginalValueExpr';
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
