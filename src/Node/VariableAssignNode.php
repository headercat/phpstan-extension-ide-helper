<?php 

namespace PHPStan\Node;
return;

use PhpParser\Node\Expr;
use PhpParser\NodeAbstract;

final class VariableAssignNode extends NodeAbstract implements VirtualNode
{

	public function __construct(
		private Expr\Variable $variable,
		private Expr $assignedExpr,
	)
	{
		parent::__construct($variable->getAttributes());
	}

	public function getVariable(): Expr\Variable
	{
		return $this->variable;
	}

	public function getAssignedExpr(): Expr
	{
		return $this->assignedExpr;
	}

	public function getType(): string
	{
		return 'PHPStan_Node_VariableAssignNodeNode';
	}

	/**
	 * @return string[]
	 */
	public function getSubNodeNames(): array
	{
		return [];
	}

}
