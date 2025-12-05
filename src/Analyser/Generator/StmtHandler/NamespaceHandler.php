<?php 

namespace PHPStan\Analyser\Generator\StmtHandler;
return;

use Generator;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Namespace_;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\StmtAnalysisResult;
use PHPStan\Analyser\Generator\StmtHandler;
use PHPStan\Analyser\Generator\StmtsAnalysisRequest;
use PHPStan\Analyser\StatementContext;
use PHPStan\DependencyInjection\AutowiredService;

/**
 * @implements StmtHandler<Namespace_>
 */
#[AutowiredService]
final class NamespaceHandler implements StmtHandler
{

	public function supports(Stmt $stmt): bool
	{
		return $stmt instanceof Namespace_;
	}

	public function analyseStmt(Stmt $stmt, GeneratorScope $scope, StatementContext $context, ?callable $alternativeNodeCallback): Generator
	{
		if ($stmt->name !== null) {
			$scope = $scope->enterNamespace($stmt->name->toString());
		}

		$result = yield new StmtsAnalysisRequest($stmt, $stmt->stmts, $scope, $context, $alternativeNodeCallback);

		return new StmtAnalysisResult(
			$result->scope,
			hasYield: false,
			isAlwaysTerminating: false,
			exitPoints: [],
			throwPoints: [],
			impurePoints: [],
		);
	}

}
