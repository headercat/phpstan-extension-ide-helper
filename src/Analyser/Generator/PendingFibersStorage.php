<?php 

namespace PHPStan\Analyser\Generator;
return;

use Fiber;

final class PendingFibersStorage
{

	/** @var array<array{fiber: Fiber<mixed, ExprAnalysisResult|null, null, ExprAnalysisRequest|NodeCallbackRequest>, request: ExprAnalysisRequest}> */
	public array $pendingFibers = [];

}
