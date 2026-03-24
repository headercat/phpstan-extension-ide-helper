<?php 

namespace PHPStan\Analyser;
return;

use PhpParser\Node\Stmt;

/**
 * @api
 */
final class StatementExitPoint
{

	public function __construct(private Stmt $statement, private Scope $scope)
	{
	}

	public function getStatement(): Stmt
	{
		return $this->statement;
	}

	public function getScope(): Scope
	{
		return $this->scope;
	}

}
