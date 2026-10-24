<?php 

namespace PHPStan\Node\Expr;
return;

use Override;
use PhpParser\Node\Expr;
use PHPStan\Node\VirtualNode;
use PHPStan\Turbo\ReferencedByTurboExtension;

#[ReferencedByTurboExtension(key: 'parameterVariableOriginalValueExpr')]
final class ParameterVariableOriginalValueExpr extends Expr implements VirtualNode
{

	public Expr\Variable $var;

	public function __construct(private string $variableName)
	{
		parent::__construct([]);
		$this->var = new Expr\Variable($this->variableName);
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
		return ['var'];
	}

}
