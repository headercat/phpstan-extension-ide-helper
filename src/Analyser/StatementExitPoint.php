<?php 

namespace PHPStan\Analyser;
return;

use PhpParser\Node\Stmt;

/**
 * @api
 */
final class StatementExitPoint
{

	public function __construct(private Stmt $statement, private MutatingScope $scope)
	{
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
