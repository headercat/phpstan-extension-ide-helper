<?php 

namespace PHPStan\Analyser\Generator\NodeHandler;
return;

use Generator;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\Generator\GeneratorNodeScopeResolver;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\NodeCallbackRequest;
use PHPStan\Analyser\Generator\StmtAnalysisRequest;
use PHPStan\Analyser\Generator\StmtAnalysisResult;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\StatementContext;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Node\ExecutionEndNode;
use PHPStan\Node\PropertyHookStatementNode;
use PHPStan\Node\UnreachableStatementNode;
use function array_merge;
use function array_slice;
use function count;

/**
 * @phpstan-import-type GeneratorTValueType from GeneratorNodeScopeResolver
 * @phpstan-import-type GeneratorTSendType from GeneratorNodeScopeResolver
 */
#[AutowiredService]
final class StmtsHandler
{

	/**
	 * @param array<Stmt> $stmts
	 * @param (callable(Node, Scope, callable(Node, Scope): void): void)|null $alternativeNodeCallback
	 *
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, StmtAnalysisResult>
	 */
	public function analyseStmts(Node $parentNode, array $stmts, GeneratorScope $scope, StatementContext $context, ?callable $alternativeNodeCallback): Generator
	{
		$exitPoints = [];
		$throwPoints = [];
		$impurePoints = [];
		$alreadyTerminated = false;
		$hasYield = false;
		$stmtCount = count($stmts);
		$shouldCheckLastStatement = $parentNode instanceof Node\Stmt\Function_
			|| $parentNode instanceof Node\Stmt\ClassMethod
			|| $parentNode instanceof PropertyHookStatementNode
			|| $parentNode instanceof Node\Expr\Closure;
		foreach ($stmts as $i => $stmt) {
			if ($alreadyTerminated && !($stmt instanceof Node\Stmt\Function_ || $stmt instanceof Node\Stmt\ClassLike)) {
				continue;
			}

			$isLast = $i === $stmtCount - 1;
			$statementResult = yield new StmtAnalysisRequest(
				$stmt,
				$scope,
				$context,
				$alternativeNodeCallback,
			);
			$scope = $statementResult->scope;
			$hasYield = $hasYield || $statementResult->hasYield;

			if ($shouldCheckLastStatement && $isLast) {
				$endStatements = $statementResult->endStatements;
				if (count($endStatements) > 0) {
					foreach ($endStatements as $endStatement) {
						$endStatementResult = $endStatement->result;
						yield new NodeCallbackRequest(new ExecutionEndNode(
							$endStatement->statement,
							(new StmtAnalysisResult(
								$endStatementResult->scope,
								$hasYield,
								$endStatementResult->isAlwaysTerminating,
								$endStatementResult->exitPoints,
								$endStatementResult->throwPoints,
								$endStatementResult->impurePoints,
							))->toPublic(),
							$parentNode->getReturnType() !== null,
						), $endStatementResult->scope, $alternativeNodeCallback);
					}
				} else {
					yield new NodeCallbackRequest(new ExecutionEndNode(
						$stmt,
						(new StmtAnalysisResult(
							$scope,
							$hasYield,
							$statementResult->isAlwaysTerminating,
							$statementResult->exitPoints,
							$statementResult->throwPoints,
							$statementResult->impurePoints,
						))->toPublic(),
						$parentNode->getReturnType() !== null,
					), $scope, $alternativeNodeCallback);
				}
			}

			$exitPoints = array_merge($exitPoints, $statementResult->exitPoints);
			$throwPoints = array_merge($throwPoints, $statementResult->throwPoints);
			$impurePoints = array_merge($impurePoints, $statementResult->impurePoints);

			if ($alreadyTerminated || !$statementResult->isAlwaysTerminating) {
				continue;
			}

			$alreadyTerminated = true;
			$nextStmts = $this->getNextUnreachableStatements(array_slice($stmts, $i + 1), $parentNode instanceof Node\Stmt\Namespace_);
			yield from $this->processUnreachableStatement($nextStmts, $scope, $alternativeNodeCallback);
		}

		$statementResult = new StmtAnalysisResult($scope, $hasYield, $alreadyTerminated, $exitPoints, $throwPoints, $impurePoints);
		if ($stmtCount === 0 && $shouldCheckLastStatement) {
			$returnTypeNode = $parentNode->getReturnType();
			if ($parentNode instanceof Node\Expr\Closure) {
				$parentNode = new Node\Stmt\Expression($parentNode, $parentNode->getAttributes());
			}
			yield new NodeCallbackRequest(new ExecutionEndNode(
				$parentNode,
				$statementResult->toPublic(),
				$returnTypeNode !== null,
			), $scope, $alternativeNodeCallback);
		}

		return $statementResult;
	}

	/**
	 * @param array<Stmt> $stmts
	 * @param (callable(Node, Scope, callable(Node, Scope): void): void)|null $alternativeNodeCallback
	 *
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, void>
	 */
	public function analyseInitialStmts(array $stmts, GeneratorScope $scope, StatementContext $context, ?callable $alternativeNodeCallback): Generator
	{
		$alreadyTerminated = false;
		foreach ($stmts as $i => $stmt) {
			if (
				$alreadyTerminated
				&& !$stmt instanceof Node\Stmt\Function_
				&& !$stmt instanceof Node\Stmt\ClassLike
			) {
				continue;
			}

			$statementResult = yield new StmtAnalysisRequest($stmt, $scope, $context, $alternativeNodeCallback);
			$scope = $statementResult->scope;
			if ($alreadyTerminated || !$statementResult->isAlwaysTerminating) {
				continue;
			}

			$alreadyTerminated = true;
			$nextStmts = $this->getNextUnreachableStatements(array_slice($stmts, $i + 1), true);
			yield from $this->processUnreachableStatement($nextStmts, $scope, $alternativeNodeCallback);
		}
	}

	/**
	 * @param array<Node> $nodes
	 * @return list<Node\Stmt>
	 */
	private function getNextUnreachableStatements(array $nodes, bool $earlyBinding): array
	{
		$stmts = [];
		$isPassedUnreachableStatement = false;
		foreach ($nodes as $node) {
			if ($earlyBinding && ($node instanceof Node\Stmt\Function_ || $node instanceof Node\Stmt\ClassLike || $node instanceof Node\Stmt\HaltCompiler)) {
				continue;
			}
			if ($isPassedUnreachableStatement && $node instanceof Node\Stmt) {
				$stmts[] = $node;
				continue;
			}
			if ($node instanceof Node\Stmt\Nop || $node instanceof Node\Stmt\InlineHTML) {
				continue;
			}
			if (!$node instanceof Node\Stmt) {
				continue;
			}
			$stmts[] = $node;
			$isPassedUnreachableStatement = true;
		}
		return $stmts;
	}

	/**
	 * @param Node\Stmt[] $nextStmts
	 * @param (callable(Node, Scope, callable(Node, Scope): void): void)|null $alternativeNodeCallback
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, void>
	 */
	private function processUnreachableStatement(array $nextStmts, GeneratorScope $scope, ?callable $alternativeNodeCallback): Generator
	{
		if ($nextStmts === []) {
			return;
		}

		$unreachableStatement = null;
		$nextStatements = [];

		foreach ($nextStmts as $key => $nextStmt) {
			if ($key === 0) {
				$unreachableStatement = $nextStmt;
				continue;
			}

			$nextStatements[] = $nextStmt;
		}

		if (!$unreachableStatement instanceof Node\Stmt) {
			return;
		}

		yield new NodeCallbackRequest(new UnreachableStatementNode($unreachableStatement, $nextStatements), $scope, $alternativeNodeCallback);
	}

}
