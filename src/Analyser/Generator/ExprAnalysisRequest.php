<?php 

namespace PHPStan\Analyser\Generator;
return;

use PhpParser\Node\Expr;

final class ExprAnalysisRequest
{

	public function __construct(
		public Expr $expr,
		public GeneratorScope $scope,
	)
	{
	}

}
