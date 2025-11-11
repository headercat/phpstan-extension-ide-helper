<?php 

namespace PHPStan\Analyser\Generator\StmtHandler;
return;

use Generator;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\StmtAnalysisResult;
use PHPStan\Analyser\Generator\StmtHandler;
use PHPStan\Analyser\Generator\StmtsAnalysisRequest;
use PHPStan\DependencyInjection\AutowiredService;

/**
 * @implements StmtHandler<Class_|Interface_|Enum_>
 */
#[AutowiredService]
final class ClassLikeHandler implements StmtHandler
{

	public function supports(Stmt $stmt): bool
	{
		return $stmt instanceof Class_ || $stmt instanceof Interface_ || $stmt instanceof Enum_;
	}

	public function analyseStmt(Stmt $stmt, GeneratorScope $scope): Generator
	{
		//$scope = $scope->enterClass();
		$result = yield new StmtsAnalysisRequest($stmt->stmts, $scope);
		$scope = $result->scope;

		return new StmtAnalysisResult($scope);
	}

}
