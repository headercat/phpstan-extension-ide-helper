<?php 

namespace PHPStan\Analyser\Generator;
return;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\StatementContext;
use function debug_backtrace;
use const DEBUG_BACKTRACE_IGNORE_ARGS;

final class StmtAnalysisRequest
{

	public ?string $originFile = null;

	public ?int $originLine = null;

	/**
	 * @param (callable(Node, Scope, callable(Node, Scope): void): void)|null $alternativeNodeCallback
	 */
	public function __construct(
		public readonly Stmt $stmt,
		public readonly GeneratorScope $scope,
		public readonly StatementContext $context,
		public readonly mixed $alternativeNodeCallback,
	)
	{
		$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
		$this->originFile = $trace[0]['file'] ?? null;
		$this->originLine = $trace[0]['line'] ?? null;
	}

}
