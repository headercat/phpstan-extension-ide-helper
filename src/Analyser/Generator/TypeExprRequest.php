<?php 

namespace PHPStan\Analyser\Generator;
return;

use PhpParser\Node\Expr;
use function debug_backtrace;
use const DEBUG_BACKTRACE_IGNORE_ARGS;

final class TypeExprRequest
{

	public ?string $originFile = null;

	public ?int $originLine = null;

	public function __construct(
		public readonly Expr $expr,
	)
	{
		$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
		$this->originFile = $trace[0]['file'] ?? null;
		$this->originLine = $trace[0]['line'] ?? null;
	}

}
