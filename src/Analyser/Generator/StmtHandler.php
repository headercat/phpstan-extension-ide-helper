<?php 

namespace PHPStan\Analyser\Generator;
return;

use Generator;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\StatementContext;

/**
 * @template T of Stmt
 * @phpstan-import-type GeneratorTValueType from GeneratorNodeScopeResolver
 * @phpstan-import-type GeneratorTSendType from GeneratorNodeScopeResolver
 */
interface StmtHandler
{

	public const HANDLER_TAG = 'phpstan.gnsr.stmtHandler';

	/**
	 * @phpstan-assert-if-true T $stmt
	 */
	public function supports(Stmt $stmt): bool;

	/**
	 * @param T $stmt
	 * @param (callable(Node, Scope, callable(Node, Scope): void): void)|null $alternativeNodeCallback
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, StmtAnalysisResult>
	 */
	public function analyseStmt(
		Stmt $stmt,
		GeneratorScope $scope,
		StatementContext $context,
		?callable $alternativeNodeCallback,
	): Generator;

}
