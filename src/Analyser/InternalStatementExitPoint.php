<?php 

namespace PHPStan\Analyser;
return;

use PhpParser\Node\Stmt;

final class InternalStatementExitPoint
{

	public function __construct(private Stmt $statement, private MutatingScope $scope)
	{
	}

	public function toPublic(): StatementExitPoint
	{
		return new StatementExitPoint($this->statement, $this->scope);
	}

	public function getStatement(): Stmt
	{
		return $this->statement;
	}

	public function getScope(): MutatingScope
	{
		return $this->scope;
	}

}
