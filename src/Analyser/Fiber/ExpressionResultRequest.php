<?php 

namespace PHPStan\Analyser\Fiber;
return;

use PhpParser\Node\Expr;

final class ExpressionResultRequest
{

	public function __construct(public readonly Expr $expr, public readonly FiberScope $scope)
	{
	}

}
