<?php 

namespace PHPStan\Analyser\Generator;
return;

use function debug_backtrace;
use const DEBUG_BACKTRACE_IGNORE_ARGS;

final class PersistStorageRequest
{

	public ?string $originFile = null;

	public ?int $originLine = null;

	public function __construct()
	{
		$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
		$this->originFile = $trace[0]['file'] ?? null;
		$this->originLine = $trace[0]['line'] ?? null;
	}

}
