<?php 

namespace PHPStan\Analyser\Generator;
return;

use PhpParser\Node\Stmt;
use PHPStan\Analyser\EndStatementResult;

final class InternalEndStatementResult
{

	public function __construct(
		public readonly Stmt $statement,
		public readonly StmtAnalysisResult $result,
	)
	{
	}

	public function toPublic(): EndStatementResult
	{
		return new EndStatementResult($this->statement, $this->result->toPublic());
	}

}
