<?php 

namespace PHPStan\Analyser\Fiber;
return;

use PhpParser\Node\Expr;
use PHPStan\Analyser\MutatingScope;

final class BeforeScopeForExprRequest
{

	public function __construct(public readonly Expr $expr, public readonly MutatingScope $scope)
	{
	}

}
