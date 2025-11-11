<?php 

namespace PHPStan\Analyser\Generator;
return;

use PhpParser\Node\Stmt;

final class StmtsAnalysisRequest
{

	/**
	 * @param Stmt[] $stmts
	 */
	public function __construct(
		public array $stmts,
		public GeneratorScope $scope,
	)
	{
	}

}
