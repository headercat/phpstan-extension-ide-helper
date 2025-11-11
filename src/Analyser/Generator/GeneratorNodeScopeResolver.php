<?php 

namespace PHPStan\Analyser\Generator;
return;

use Fiber;
use Generator;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\Container;
use PHPStan\NeverException;
use PHPStan\ShouldNotHappenException;
use function array_map;
use function array_pop;
use function count;
use function get_class;
use function get_debug_type;
use function implode;
use function sprintf;

/**
 * Work In Progress.
 *
 * This is next-get NodeScopeResolver. It aims to solve several problems:
 *
 * 1) Many expressions are processed multiple times. For example, resolving type
 *    of BooleanAnd has to process left side in order to accurately get the type
 *    of the right side. For things like MethodCall, even dynamic return type extensions
 *    and ParametersAcceptorSelector are called multiple times.
 * 2) For complicated scope-changing expressions like assigns happening inside other expressions,
 *    the current type inference is imprecise. Take $foo->doFoo($a = 1, $a);
 *    When a rule hooks onto MethodCall and iterates over its args, the type of `$a`
 *    in the second argument should be `int`, but currently it's often `mixed` because
 *    the assignment in the first argument hasn't been processed yet in the context
 *    where the rule is checking.
 *
 * This class (I will refer to it as GNSR from now on) aims to merge the tasks currently handled
 * by NodeScopeResolver, MutatingScope and TypeSpecifier all into one, because they are very similar
 * and their work should happen all at once without duplicit processing.
 *
 * This rewrite should fix 1) to improve performance and 2) to improve type inference precision.
 *
 * Architecture:
 * - Uses generators (with `yield`) for internal analysis code to handle recursive traversal
 *   without deep call stacks and to explicitly manage when sub-expressions are analyzed
 * - Uses Fibers when calling custom extension code (rules, dynamic return type extensions, etc.)
 *   to preserve backward compatibility
 * - Calls to `$scope->getType()` in custom extensions do not need to be preceded with `yield`.
 *   Instead, if the type is not yet available, the call will transparently suspend the current
 *   Fiber and resume once the type has been computed
 * - All computed types and analysis results are stored during traversal so that subsequent
 *   lookups (from rules or other extensions) hit cached values with no duplicate work
 * - Synthetic/virtual expressions (e.g., a rule constructing `new MethodCall(...)` to query
 *   a hypothetical method call) are analyzed on-demand when requested, with the Fiber
 *   suspending until analysis completes
 */
final class GeneratorNodeScopeResolver
{

	public function __construct(
		private Container $container,
	)
	{
	}

	/**
	 * @param string[] $files
	 */
	public function setAnalysedFiles(array $files): void
	{
	}

	/**
	 * @param array<Node\Stmt> $stmts
	 * @param callable(Node, Scope): void $nodeCallback
	 */
	public function processNodes(
		array $stmts,
		GeneratorScope $scope,
		callable $nodeCallback,
	): void
	{
		$pendingFibersStorage = new PendingFibersStorage();
		$exprAnalysisResultStorage = new ExprAnalysisResultStorage();
		$this->processStmtNodes(
			$pendingFibersStorage,
			$exprAnalysisResultStorage,
			$stmts,
			$scope,
			$nodeCallback,
		);
	}

	/**
	 * @param array<Node\Stmt> $stmts
	 * @param callable(Node, Scope): void $nodeCallback
	 */
	private function processStmtNodes(
		PendingFibersStorage $fibersStorage,
		ExprAnalysisResultStorage $exprAnalysisResultStorage,
		array $stmts,
		GeneratorScope $scope,
		callable $nodeCallback,
	): StmtAnalysisResult
	{
		$stack = [];

		$gen = $this->analyseStmts($stmts, $scope);
		$gen->current();

		// Trampoline loop
		while (true) {
			$this->processPendingFibers($fibersStorage, $exprAnalysisResultStorage);

			if ($gen->valid()) {
				$yielded = $gen->current();

				if ($yielded instanceof NodeCallbackRequest) {
					$this->invokeNodeCallback(
						$fibersStorage,
						$exprAnalysisResultStorage,
						$yielded->node,
						$yielded->scope,
						$nodeCallback,
					);

					$gen->next();
					continue;
				} elseif ($yielded instanceof ExprAnalysisRequest) {
					$stack[] = $gen;
					$gen = $this->analyseExpr($exprAnalysisResultStorage, $yielded->expr, $yielded->scope);
					$gen->current();
					continue;
				} elseif ($yielded instanceof StmtAnalysisRequest) {
					$stack[] = $gen;
					$gen = $this->analyseStmt($yielded->stmt, $yielded->scope);
					$gen->current();
					continue;
				} elseif ($yielded instanceof StmtsAnalysisRequest) {
					$stack[] = $gen;
					$gen = $this->analyseStmts($yielded->stmts, $yielded->scope);
					$gen->current();
					continue;
				} else { // phpcs:ignore
					throw new NeverException($yielded);
				}
			}

			$result = $gen->getReturn();
			if (count($stack) === 0) {
				foreach ($fibersStorage->pendingFibers as $pending) {
					$request = $pending['request'];
					$exprAnalysisResult = $exprAnalysisResultStorage->lookupExprAnalysisResult($request->expr);

					if ($exprAnalysisResult !== null) {
						throw new ShouldNotHappenException('Pending fibers with an empty stack should be about synthetic nodes');
					}

					$this->processStmtNodes(
						$fibersStorage,
						$exprAnalysisResultStorage,
						[new Stmt\Expression($request->expr)],
						$request->scope,
						static function () {
						},
					);
				}

				if (count($fibersStorage->pendingFibers) === 0) {
					if (!$result instanceof StmtAnalysisResult) {
						throw new ShouldNotHappenException('Top node should be Stmt');
					}
					return $result;
				}

				throw new ShouldNotHappenException(sprintf('Cannot finish analysis, pending fibers about: %s', implode(', ', array_map(static fn (array $fiber) => get_class($fiber['request']->expr), $fibersStorage->pendingFibers))));
			}

			$gen = array_pop($stack);
			$gen->send($result);
		}
	}

	/**
	 * @param array<Stmt> $stmts
	 * @return Generator<int, StmtAnalysisRequest, StmtAnalysisResult, StmtAnalysisResult>
	 */
	private function analyseStmts(array $stmts, GeneratorScope $scope): Generator
	{
		foreach ($stmts as $stmt) {
			$result = yield new StmtAnalysisRequest($stmt, $scope);
			$scope = $result->scope;
		}

		return new StmtAnalysisResult($scope);
	}

	/**
	 * @return Generator<int, ExprAnalysisRequest|StmtAnalysisRequest|StmtsAnalysisRequest|NodeCallbackRequest, ExprAnalysisResult|StmtAnalysisResult, StmtAnalysisResult>
	 */
	private function analyseStmt(Stmt $stmt, GeneratorScope $scope): Generator
	{
		yield new NodeCallbackRequest($stmt, $scope);

		/**
		 * @var StmtHandler<Stmt> $stmtHandler
		 */
		foreach ($this->container->getServicesByTag(StmtHandler::HANDLER_TAG) as $stmtHandler) {
			if (!$stmtHandler->supports($stmt)) {
				continue;
			}

			$gen = $stmtHandler->analyseStmt($stmt, $scope);
			yield from $gen;

			return $gen->getReturn();
		}

		throw new ShouldNotHappenException('Unhandled stmt: ' . get_class($stmt));
	}

	/**
	 * @return Generator<int, ExprAnalysisRequest|NodeCallbackRequest, ExprAnalysisResult, ExprAnalysisResult>
	 */
	private function analyseExpr(ExprAnalysisResultStorage $storage, Expr $expr, GeneratorScope $scope): Generator
	{
		yield new NodeCallbackRequest($expr, $scope);

		/**
		 * @var ExprHandler<Expr> $exprHandler
		 */
		foreach ($this->container->getServicesByTag(ExprHandler::HANDLER_TAG) as $exprHandler) {
			if (!$exprHandler->supports($expr)) {
				continue;
			}

			$gen = $exprHandler->analyseExpr($expr, $scope);
			yield from $gen;

			$exprAnalysisResult = $gen->getReturn();
			$storage->storeExprAnalysisResult($expr, $exprAnalysisResult);

			return $exprAnalysisResult;
		}

		throw new ShouldNotHappenException('Unhandled expr: ' . get_class($expr));
	}

	/**
	 * @param callable(Node, Scope): void $nodeCallback
	 */
	private function invokeNodeCallback(
		PendingFibersStorage $fibersStorage,
		ExprAnalysisResultStorage $exprAnalysisResultStorage,
		Node $node,
		Scope $scope,
		callable $nodeCallback,
	): void
	{
		$fiber = new Fiber(static function () use ($node, $scope, $nodeCallback) {
			$nodeCallback($node, $scope);
		});
		$request = $fiber->start();
		$this->runFiber($fibersStorage, $exprAnalysisResultStorage, $fiber, $request);
	}

	/**
	 * @param Fiber<mixed, ExprAnalysisResult, null, ExprAnalysisRequest> $fiber
	 */
	private function runFiber(
		PendingFibersStorage $fibersStorage,
		ExprAnalysisResultStorage $exprAnalysisResultStorage,
		Fiber $fiber,
		?ExprAnalysisRequest $request,
	): void
	{
		while (!$fiber->isTerminated()) {
			if ($request instanceof ExprAnalysisRequest) {
				$result = $exprAnalysisResultStorage->lookupExprAnalysisResult($request->expr);

				if ($result !== null) {
					// Result ready - continue the loop to resume
					$request = $fiber->resume($result);
					continue;
				}

				// Park the fiber - can't make progress yet
				$fibersStorage->pendingFibers[] = [
					'fiber' => $fiber,
					'request' => $request,
				];
				return;
			}

			throw new ShouldNotHappenException(
				'Unknown fiber suspension: ' . get_debug_type($request),
			);
		}

		if ($request !== null) {
			throw new ShouldNotHappenException(
				'Fiber terminated but we did not handle its request ' . get_debug_type($request),
			);
		}
	}

	private function processPendingFibers(PendingFibersStorage $fibersStorage, ExprAnalysisResultStorage $exprAnalysisResultStorage): void
	{
		foreach ($fibersStorage->pendingFibers as $key => $pending) {
			$request = $pending['request'];
			$exprAnalysisResult = $exprAnalysisResultStorage->lookupExprAnalysisResult($request->expr);

			if ($exprAnalysisResult === null) {
				continue;
			}

			unset($fibersStorage->pendingFibers[$key]);

			$fiber = $pending['fiber'];
			$request = $fiber->resume($exprAnalysisResult);
			$this->runFiber($fibersStorage, $exprAnalysisResultStorage, $fiber, $request);
		}
	}

}
