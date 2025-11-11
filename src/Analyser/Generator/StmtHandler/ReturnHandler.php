<?php 

namespace PHPStan\Analyser\Generator\StmtHandler;
return;

use Generator;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\StmtAnalysisResult;
use PHPStan\Analyser\Generator\StmtHandler;
use PHPStan\DependencyInjection\AutowiredService;

/**
 * @implements StmtHandler<Return_>
 */
#[AutowiredService]
final class ReturnHandler implements StmtHandler
{

	public function supports(Stmt $stmt): bool
	{
		return $stmt instanceof Return_;
	}

	public function analyseStmt(Stmt $stmt, GeneratorScope $scope): Generator
	{
		if ($stmt->expr !== null) {
			$result = yield new ExprAnalysisRequest($stmt->expr, $scope);

			return new StmtAnalysisResult($result->scope);
		}

		return new StmtAnalysisResult($scope);
	}

}
