<?php 

namespace PHPStan\Analyser\Generator\StmtHandler;
return;

use Generator;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Declare_;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\NodeCallbackRequest;
use PHPStan\Analyser\Generator\StmtAnalysisResult;
use PHPStan\Analyser\Generator\StmtHandler;
use PHPStan\Analyser\Generator\StmtsAnalysisRequest;
use PHPStan\DependencyInjection\AutowiredService;

/**
 * @implements StmtHandler<Declare_>
 */
#[AutowiredService]
final class DeclareHandler implements StmtHandler
{

	public function supports(Stmt $stmt): bool
	{
		return $stmt instanceof Declare_;
	}

	public function analyseStmt(Stmt $stmt, GeneratorScope $scope): Generator
	{
		foreach ($stmt->declares as $declare) {
			yield new NodeCallbackRequest($declare, $scope);
			yield new ExprAnalysisRequest($declare->value, $scope);
			if (
				$declare->key->name !== 'strict_types'
				|| !($declare->value instanceof Int_)
				|| $declare->value->value !== 1
			) {
				continue;
			}

			$scope = $scope->enterDeclareStrictTypes();
		}

		if ($stmt->stmts !== null) {
			/** @var StmtAnalysisResult */
			return yield new StmtsAnalysisRequest($stmt->stmts, $scope);

		}

		return new StmtAnalysisResult($scope);
	}

}
