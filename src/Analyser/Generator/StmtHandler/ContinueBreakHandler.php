<?php 

namespace PHPStan\Analyser\Generator\StmtHandler;
return;

use Generator;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\Continue_;
use PHPStan\Analyser\ExpressionContext;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\InternalStatementExitPoint;
use PHPStan\Analyser\Generator\StmtAnalysisResult;
use PHPStan\Analyser\Generator\StmtHandler;
use PHPStan\Analyser\StatementContext;
use PHPStan\DependencyInjection\AutowiredService;

/**
 * @implements StmtHandler<Continue_|Break_>
 */
#[AutowiredService]
final class ContinueBreakHandler implements StmtHandler
{

	public function supports(Stmt $stmt): bool
	{
		return $stmt instanceof Continue_ || $stmt instanceof Break_;
	}

	public function analyseStmt(Stmt $stmt, GeneratorScope $scope, StatementContext $context, ?callable $alternativeNodeCallback): Generator
	{
		if ($stmt->num !== null) {
			$result = yield new ExprAnalysisRequest($stmt, $stmt->num, $scope, ExpressionContext::createDeep(), $alternativeNodeCallback);

			return new StmtAnalysisResult(
				$result->scope,
				hasYield: $result->hasYield,
				isAlwaysTerminating: true,
				exitPoints: [new InternalStatementExitPoint($stmt, $result->scope)],
				throwPoints: $result->throwPoints,
				impurePoints: $result->impurePoints,
			);
		}

		return new StmtAnalysisResult(
			$scope,
			hasYield: false,
			isAlwaysTerminating: true,
			exitPoints: [new InternalStatementExitPoint($stmt, $scope)],
			throwPoints: [],
			impurePoints: [],
		);
	}

}
