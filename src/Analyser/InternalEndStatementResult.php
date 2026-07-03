<?php 

namespace PHPStan\Analyser;
return;

use PhpParser\Node\Stmt;

final class InternalEndStatementResult
{

	public function __construct(
		private Stmt $statement,
		private InternalStatementResult $result,
	)
	{
	}

	public function toPublic(): EndStatementResult
	{
		return new EndStatementResult($this->statement, $this->result->toPublic());
	}

	public function getStatement(): Stmt
	{
		return $this->statement;
	}

	public function getResult(): InternalStatementResult
	{
		return $this->result;
	}

}
