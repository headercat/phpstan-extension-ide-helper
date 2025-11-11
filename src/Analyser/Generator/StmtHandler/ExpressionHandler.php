<?php 

namespace PHPStan\Analyser\Generator\StmtHandler;
return;

use Generator;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\StmtAnalysisResult;
use PHPStan\Analyser\Generator\StmtHandler;
use PHPStan\DependencyInjection\AutowiredService;

/**
 * @implements StmtHandler<Expression>
 */
#[AutowiredService]
final class ExpressionHandler implements StmtHandler
{

	public function supports(Stmt $stmt): bool
	{
		return $stmt instanceof Expression;
	}

	public function analyseStmt(Stmt $stmt, GeneratorScope $scope): Generator
	{
		$result = yield new ExprAnalysisRequest($stmt->expr, $scope);

		return new StmtAnalysisResult($result->scope);
	}

}
