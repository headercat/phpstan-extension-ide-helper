<?php 

namespace PHPStan\Node;
return;

use PhpParser\Node\Expr;
use PHPStan\Analyser\Scope;

/**
 * @api
 */
final class MatchExpressionArmCondition
{

	public function __construct(private Expr $condition, private Scope $scope, private int $line)
	{
	}

	public function getCondition(): Expr
	{
		return $this->condition;
	}

	public function getScope(): Scope
	{
		return $this->scope;
	}

	public function getLine(): int
	{
		return $this->line;
	}

}
