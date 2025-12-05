<?php 

namespace PHPStan\Analyser\Generator\StmtHandler;
return;

use Generator;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Echo_;
use PHPStan\Analyser\ExpressionContext;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\StmtAnalysisResult;
use PHPStan\Analyser\Generator\StmtHandler;
use PHPStan\Analyser\ImpurePoint;
use PHPStan\Analyser\StatementContext;
use PHPStan\DependencyInjection\AutowiredService;
use function array_merge;

/**
 * @implements StmtHandler<Echo_>
 */
#[AutowiredService]
final class EchoHandler implements StmtHandler
{

	public function supports(Stmt $stmt): bool
	{
		return $stmt instanceof Echo_;
	}

	public function analyseStmt(Stmt $stmt, GeneratorScope $scope, StatementContext $context, ?callable $alternativeNodeCallback): Generator
	{
		$hasYield = false;
		$throwPoints = [];
		$isAlwaysTerminating = false;
		foreach ($stmt->exprs as $echoExpr) {
			$result = yield new ExprAnalysisRequest($stmt, $echoExpr, $scope, ExpressionContext::createDeep(), $alternativeNodeCallback);
			$throwPoints = array_merge($throwPoints, $result->throwPoints);
			$scope = $result->scope;
			$hasYield = $hasYield || $result->hasYield;
			$isAlwaysTerminating = $isAlwaysTerminating || $result->isAlwaysTerminating;
		}

		return new StmtAnalysisResult(
			$scope,
			hasYield: $hasYield,
			isAlwaysTerminating: $isAlwaysTerminating,
			exitPoints: [],
			throwPoints: $throwPoints,
			impurePoints: [
				new ImpurePoint($scope, $stmt, 'echo', 'echo', true),
			],
		);
	}

}
