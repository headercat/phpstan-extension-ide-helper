<?php 

namespace PHPStan\Node\Expr;
return;

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

	public function getType(): string
	{
		return 'PHPStan_Node_ParameterVariableOriginalValueExpr';
	}

	/**
	 * @return string[]
	 */
	public function getSubNodeNames(): array
	{
		return [];
	}

}
