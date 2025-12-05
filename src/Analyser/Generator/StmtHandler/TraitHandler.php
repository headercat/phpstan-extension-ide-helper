<?php 

namespace PHPStan\Analyser\Generator\StmtHandler;
return;

use Generator;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Trait_;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\StmtAnalysisResult;
use PHPStan\Analyser\Generator\StmtHandler;
use PHPStan\Analyser\StatementContext;
use PHPStan\DependencyInjection\AutowiredService;

/**
 * @implements StmtHandler<Trait_>
 */
#[AutowiredService]
final class TraitHandler implements StmtHandler
{

	public function supports(Stmt $stmt): bool
	{
		return $stmt instanceof Trait_;
	}

	public function analyseStmt(Stmt $stmt, GeneratorScope $scope, StatementContext $context, ?callable $alternativeNodeCallback): Generator
	{
		// This handler is there as a no-op. Rules looking for Trait_ node
		// will be called but the internals of a trait are analysed
		// in context of each TraitUse, not as a standalone unit.

		yield from [];

		return new StmtAnalysisResult(
			$scope,
			hasYield: false,
			isAlwaysTerminating: false,
			exitPoints: [],
			throwPoints: [],
			impurePoints: [],
		);
	}

}
