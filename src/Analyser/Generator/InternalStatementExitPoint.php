<?php 

namespace PHPStan\Analyser\Generator;
return;

use PhpParser\Node\Stmt;
use PHPStan\Analyser\StatementExitPoint;

final class InternalStatementExitPoint
{

	public function __construct(public readonly Stmt $statement, public readonly GeneratorScope $scope)
	{
	}

	public function toPublic(): StatementExitPoint
	{
		return new StatementExitPoint($this->statement, $this->scope);
	}

}
