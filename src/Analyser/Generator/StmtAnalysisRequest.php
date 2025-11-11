<?php 

namespace PHPStan\Analyser\Generator;
return;

use PhpParser\Node\Stmt;

final class StmtAnalysisRequest
{

	public function __construct(
		public Stmt $stmt,
		public GeneratorScope $scope,
	)
	{
	}

}
