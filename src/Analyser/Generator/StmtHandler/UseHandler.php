<?php 

namespace PHPStan\Analyser\Generator\StmtHandler;
return;

use Generator;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Use_;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\NodeCallbackRequest;
use PHPStan\Analyser\Generator\StmtAnalysisResult;
use PHPStan\Analyser\Generator\StmtHandler;
use PHPStan\DependencyInjection\AutowiredService;

/**
 * @implements StmtHandler<Use_>
 */
#[AutowiredService]
final class UseHandler implements StmtHandler
{

	public function supports(Stmt $stmt): bool
	{
		return $stmt instanceof Use_;
	}

	public function analyseStmt(Stmt $stmt, GeneratorScope $scope): Generator
	{
		foreach ($stmt->uses as $useItem) {
			yield new NodeCallbackRequest($useItem, $scope);
		}

		return new StmtAnalysisResult($scope);
	}

}
