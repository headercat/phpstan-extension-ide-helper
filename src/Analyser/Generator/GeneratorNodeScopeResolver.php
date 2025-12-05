<?php 

namespace PHPStan\Analyser\Generator;
return;

use Fiber;
use Generator;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Static_;
use PHPStan\Analyser\ExpressionContext;
use PHPStan\Analyser\Generator\NodeHandler\AttrGroupsHandler;
use PHPStan\Analyser\Generator\NodeHandler\StmtsHandler;
use PHPStan\Analyser\Generator\NodeHandler\VarAnnotationHelper;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\StatementContext;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\DependencyInjection\Container;
use PHPStan\NeverException;
use PHPStan\Node\FunctionCallableNode;
use PHPStan\Node\InstantiationCallableNode;
use PHPStan\Node\MethodCallableNode;
use PHPStan\Node\Printer\ExprPrinter;
use PHPStan\Node\StaticMethodCallableNode;
use PHPStan\ShouldNotHappenException;
use Throwable;
use function array_merge;
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
 *
 * @phpstan-type GeneratorTValueType = ExprAnalysisRequest|StmtAnalysisRequest|StmtsAnalysisRequest|NodeCallbackRequest|AttrGroupsAnalysisRequest|TypeExprRequest|PersistStorageRequest|RestoreStorageRequest|RunInFiberRequest<mixed>|GetExprAnalysisResultStorageRequest|StoreExprResultRequest
 * @phpstan-type GeneratorTSendType = ExprAnalysisResult|StmtAnalysisResult|TypeExprResult|ExprAnalysisResultStorage|RunInFiberResult<mixed>|null
 */
#[AutowiredService]
final class GeneratorNodeScopeResolver
{

	/** @var ExprHandler<Expr>[]|null */
	private ?array $exprHandlers = null;

	/** @var StmtHandler<Stmt>[]|null */
	private ?array $stmtHandlers = null;

	public function __construct(
		private ExprPrinter $exprPrinter,
		private VarAnnotationHelper $varAnnotationHelper,
		private Container $container,
	)
	{
	}

	/**
	 * @param string[] $files
	 */
	public function setAnalysedFiles(array $files): void
	{
		// todo
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
			StatementContext::createTopLevel(),
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
		StatementContext $context,
	): void
	{
		$gen = new IdentifiedGeneratorInStack($this->analyseInitialStmts($stmts, $scope, $context, null), $stmts, null, null);
		$gen->generator->current();

		$stack = [];

		try {
			$this->runTrampoline(
				$fibersStorage,
				$exprAnalysisResultStorage,
				$gen,
				$nodeCallback,
				$stack,
			);
		} catch (Throwable $e) {
			$stackTrace = [];
			foreach (array_merge($stack, [$gen]) as $identifiedGenerator) {
				$string = (string) $identifiedGenerator;
				if ($string === '') {
					continue;
				}

				$stackTrace[] = $string;
			}

			throw new TrampolineException(sprintf(
				"Error occurred in GNSR trampoline: %s\n\nAST processor stack trace:\n%s",
				$e->getMessage(),
				implode("\n", $stackTrace),
			), previous: $e);
		}
	}

	/**
	 * @param callable(Node, Scope): void $nodeCallback
	 * @param list<IdentifiedGeneratorInStack> $stack
	 */
	private function runTrampoline(
		PendingFibersStorage $fibersStorage,
		ExprAnalysisResultStorage $exprAnalysisResultStorage,
		IdentifiedGeneratorInStack &$gen,
		callable $nodeCallback,
		array &$stack,
	): void
	{
		while (true) {
			$pendingFibersGen = $this->processPendingFibers($exprAnalysisResultStorage, $fibersStorage);
			if ($pendingFibersGen->valid()) {
				$stack[] = $gen;
				$gen = new IdentifiedGeneratorInStack($pendingFibersGen, new Stmt\Expression(new Node\Scalar\String_('pendingFibers')), null, null);
				$gen->sendResultOnComplete = false;
			}

			if ($gen->generator->valid()) {
				$yielded = $gen->generator->current();

				if ($yielded instanceof NodeCallbackRequest) {
					$alternativeNodeCallback = $yielded->alternativeNodeCallback;
					$stack[] = $gen;
					$gen = new IdentifiedGeneratorInStack(
						$this->invokeNodeCallback(
							$fibersStorage,
							$yielded->node,
							$yielded->scope,
							$alternativeNodeCallback !== null
								? static function (Node $node, Scope $scope) use ($alternativeNodeCallback, $nodeCallback): void {
									$alternativeNodeCallback($node, $scope, $nodeCallback);
								}
								: $nodeCallback,
						),
						$yielded->node,
						$yielded->originFile,
						$yielded->originLine,
					);
					$gen->generator->current();
					continue;
				} elseif ($yielded instanceof ExprAnalysisRequest) {
					$stack[] = $gen;
					$gen = new IdentifiedGeneratorInStack(
						$this->analyseExpr($fibersStorage, $yielded->stmt, $yielded->expr, $yielded->scope, $yielded->context, $yielded->alternativeNodeCallback, $stack, $yielded->originFile, $yielded->originLine),
						$yielded->expr,
						$yielded->originFile,
						$yielded->originLine,
					);
					$gen->generator->current();
					continue;
				} elseif ($yielded instanceof TypeExprRequest) {
					$stack[] = $gen;
					$gen = new IdentifiedGeneratorInStack(
						$this->analyseExprForType($exprAnalysisResultStorage, $yielded->expr),
						$yielded->expr,
						$yielded->originFile,
						$yielded->originLine,
					);
					$gen->generator->current();
					continue;
				} elseif ($yielded instanceof StmtAnalysisRequest) {
					$stack[] = $gen;
					$gen = new IdentifiedGeneratorInStack(
						$this->analyseStmt($yielded->stmt, $yielded->scope, $yielded->context, $yielded->alternativeNodeCallback),
						$yielded->stmt,
						$yielded->originFile,
						$yielded->originLine,
					);
					$gen->generator->current();
					continue;
				} elseif ($yielded instanceof StmtsAnalysisRequest) {
					$stack[] = $gen;
					$gen = new IdentifiedGeneratorInStack(
						$this->analyseStmts($yielded->parentNode, $yielded->stmts, $yielded->scope, $yielded->context, $yielded->alternativeNodeCallback),
						$yielded->stmts,
						$yielded->originFile,
						$yielded->originLine,
					);
					$gen->generator->current();
					continue;
				} elseif ($yielded instanceof AttrGroupsAnalysisRequest) {
					$stack[] = $gen;
					$gen = new IdentifiedGeneratorInStack(
						$this->analyseAttrGroups($yielded->stmt, $yielded->attrGroups, $yielded->scope, $yielded->alternativeNodeCallback),
						$yielded->stmt,
						$yielded->originFile,
						$yielded->originLine,
					);
					$gen->generator->current();
					continue;
				} elseif ($yielded instanceof PersistStorageRequest) {
					$gen->generator->send($exprAnalysisResultStorage->duplicate());
					continue;
				} elseif ($yielded instanceof RestoreStorageRequest) {
					$exprAnalysisResultStorage = $yielded->storage->duplicate();
					$gen->generator->next();
					continue;
				} elseif ($yielded instanceof RunInFiberRequest) {
					$stack[] = $gen;
					$gen = new IdentifiedGeneratorInStack(
						$this->runInFiber($yielded->callback),
						new Stmt\Expression(new Node\Scalar\String_('runInFiber')),
						$yielded->originFile,
						$yielded->originLine,
					);
					$gen->generator->current();
					continue;
				} elseif ($yielded instanceof GetExprAnalysisResultStorageRequest) {
					$gen->generator->send($exprAnalysisResultStorage);
					continue;
				} elseif ($yielded instanceof StoreExprResultRequest) {
					$exprAnalysisResultStorage->storeExprAnalysisResult(
						$yielded->expr,
						$yielded->result,
						$yielded->stack,
						$yielded->file,
						$yielded->line,
					);
					$gen->generator->next();
					continue;
				} else { // phpcs:ignore
					throw new NeverException($yielded);
				}
			}

			$result = $gen->generator->getReturn();
			$sendResult = $gen->sendResultOnComplete;
			if (count($stack) === 0) {
				foreach ($fibersStorage->pendingFibers as $pending) {
					$request = $pending['request'];
					$exprAnalysisResult = $exprAnalysisResultStorage->findExprAnalysisResult($request->expr);

					if ($exprAnalysisResult !== null) {
						throw new ShouldNotHappenException('Pending fibers with an empty stack should be about synthetic nodes');
					}

					$stack[] = $gen;
					$gen = new IdentifiedGeneratorInStack(
						$this->analyseExpr(null, $request->stmt, $request->expr, $request->scope, $request->context, $request->alternativeNodeCallback, $stack, $request->originFile, $request->originLine),
						$request->expr,
						$request->originFile,
						$request->originLine,
					);
					$gen->generator->current();
					continue 2;
				}

				if ($result !== null) {
					throw new ShouldNotHappenException(sprintf('Null result is expected from analyseInitialStmts, given %s', get_debug_type($result)));
				}
				return;
			}

			$gen = array_pop($stack);
			if (!$sendResult) {
				continue;
			}

			$gen->generator->send($result);
		}
	}

	/**
	 * @param array<Stmt> $stmts
	 * @param (callable(Node, Scope, callable(Node, Scope): void): void)|null $alternativeNodeCallback
	 *
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, void>
	 */
	private function analyseInitialStmts(array $stmts, GeneratorScope $scope, StatementContext $context, ?callable $alternativeNodeCallback): Generator
	{
		$handler = $this->container->getByType(StmtsHandler::class);
		$gen = $handler->analyseInitialStmts($stmts, $scope, $context, $alternativeNodeCallback);
		yield from $gen;
	}

	/**
	 * @param array<Stmt> $stmts
	 * @param (callable(Node, Scope, callable(Node, Scope): void): void)|null $alternativeNodeCallback
	 *
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, StmtAnalysisResult>
	 */
	private function analyseStmts(Node $parentNode, array $stmts, GeneratorScope $scope, StatementContext $context, ?callable $alternativeNodeCallback): Generator
	{
		$handler = $this->container->getByType(StmtsHandler::class);
		$gen = $handler->analyseStmts($parentNode, $stmts, $scope, $context, $alternativeNodeCallback);
		yield from $gen;

		return $gen->getReturn();
	}

	/**
	 * @param (callable(Node, Scope, callable(Node, Scope): void): void)|null $alternativeNodeCallback
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, StmtAnalysisResult>
	 */
	private function analyseStmt(Stmt $stmt, GeneratorScope $scope, StatementContext $context, ?callable $alternativeNodeCallback): Generator
	{
		if (
			!$stmt instanceof Static_
			&& !$stmt instanceof Foreach_
			&& !$stmt instanceof Node\Stmt\Global_
			&& !$stmt instanceof Node\Stmt\Property
			&& !$stmt instanceof Node\Stmt\ClassConst
			&& !$stmt instanceof Node\Stmt\Const_
		) {
			$stmtVarGen = $this->varAnnotationHelper->processStmtVarAnnotation($scope, $stmt, null, $alternativeNodeCallback);
			yield from $stmtVarGen;
			$scope = $stmtVarGen->getReturn();
		}

		$stmtScope = $scope;
		if ($stmt instanceof Node\Stmt\Expression && $stmt->expr instanceof Expr\Throw_) {
			$stmtScopeGen = $this->varAnnotationHelper->processStmtVarAnnotation($scope, $stmt, $stmt->expr->expr, $alternativeNodeCallback);
			yield from $stmtScopeGen;
			$stmtScope = $stmtScopeGen->getReturn();
		}
		if ($stmt instanceof Return_) {
			$stmtScopeGen = $this->varAnnotationHelper->processStmtVarAnnotation($scope, $stmt, $stmt->expr, $alternativeNodeCallback);
			yield from $stmtScopeGen;
			$stmtScope = $stmtScopeGen->getReturn();
		}

		yield new NodeCallbackRequest($stmt, $stmtScope, $alternativeNodeCallback);

		$overridingThrowPoints = $this->varAnnotationHelper->getOverridingThrowPoints($stmt, $scope);

		if ($stmt instanceof Stmt\Expression) {
			if ($stmt->expr instanceof Expr\Throw_) {
				$scope = $stmtScope;
			}
		}

		foreach ($this->getStmtHandlers() as $stmtHandler) {
			if (!$stmtHandler->supports($stmt)) {
				continue;
			}

			$gen = $stmtHandler->analyseStmt($stmt, $scope, $context, $alternativeNodeCallback);
			yield from $gen;

			$stmtResult = $gen->getReturn();
			if ($overridingThrowPoints !== null) {
				$stmtResult = $stmtResult->withThrowPoints($overridingThrowPoints);
			}

			return $stmtResult;
		}

		throw new ShouldNotHappenException('Unhandled stmt: ' . get_class($stmt));
	}

	/**
	 * @return StmtHandler<Stmt>[]
	 */
	private function getStmtHandlers(): array
	{
		return $this->stmtHandlers ??= $this->container->getServicesByTag(StmtHandler::HANDLER_TAG);
	}

	/**
	 * @param Node\AttributeGroup[] $attrGroups
	 * @param (callable(Node, Scope, callable(Node, Scope): void): void)|null $alternativeNodeCallback
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, void>
	 */
	private function analyseAttrGroups(Stmt $stmt, array $attrGroups, GeneratorScope $scope, ?callable $alternativeNodeCallback): Generator
	{
		/** @var AttrGroupsHandler $handler */
		$handler = $this->container->getByType(AttrGroupsHandler::class);
		yield from $handler->processAttributeGroups($stmt, $attrGroups, $scope, $alternativeNodeCallback);
	}

	/**
	 * @template T
	 * @param callable(): T $callback
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, RunInFiberResult<T>>
	 */
	private function runInFiber(callable $callback): Generator
	{
		$fiber = new Fiber($callback);
		$request = $fiber->start();

		while (!$fiber->isTerminated()) {
			if ($request instanceof ExprAnalysisRequest) {
				$request = $fiber->resume(yield $request);
				continue;
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

		return new RunInFiberResult($fiber->getReturn());
	}

	/**
	 * @param (callable(Node, Scope, callable(Node, Scope): void): void)|null $alternativeNodeCallback
	 * @param list<IdentifiedGeneratorInStack> $stack
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, ExprAnalysisResult>
	 */
	private function analyseExpr(?PendingFibersStorage $fibersStorage, Stmt $stmt, Expr $expr, GeneratorScope $scope, ExpressionContext $context, ?callable $alternativeNodeCallback, array $stack, ?string $file, ?int $line): Generator
	{
		$storage = yield new GetExprAnalysisResultStorageRequest();
		$foundExprAnalysisResult = $storage->findExprAnalysisResult($expr);
		if ($foundExprAnalysisResult !== null) {
			if ($alternativeNodeCallback instanceof NoopNodeCallback) {
				return $foundExprAnalysisResult;
			}

			$where = "\n";
			$origin = $storage->findExprAnalysisResultOrigin($expr);
			if ($origin !== null) {
				[$originStack, $originFile, $originLine] = $origin;
				foreach ($originStack as $originStackItem) {
					$where .= (string) $originStackItem . "\n";
				}

				if ($originFile !== null && $originLine !== null) {
					$where .= $originFile . ':' . $originLine;
					$where .= "\n";
				}
			}

			throw new ShouldNotHappenException(sprintf('Expr %s on line %d has already been analysed in:%s', $this->exprPrinter->printExpr($expr), $expr->getStartLine(), $where));
		}

		if ($expr instanceof Expr\CallLike && $expr->isFirstClassCallable()) {
			if ($expr instanceof FuncCall) {
				$newExpr = new FunctionCallableNode($expr->name, $expr);
			} elseif ($expr instanceof MethodCall) {
				$newExpr = new MethodCallableNode($expr->var, $expr->name, $expr);
			} elseif ($expr instanceof StaticCall) {
				$newExpr = new StaticMethodCallableNode($expr->class, $expr->name, $expr);
			} elseif ($expr instanceof New_ && !$expr->class instanceof Class_) {
				$newExpr = new InstantiationCallableNode($expr->class, $expr);
			} else {
				throw new ShouldNotHappenException();
			}

			$exprGen = $this->analyseExpr($fibersStorage, $stmt, $newExpr, $scope, $context, $alternativeNodeCallback, $stack, $file, $line);
			yield from $exprGen;

			return $exprGen->getReturn();
		}

		yield new NodeCallbackRequest($expr, $context->isDeep() ? $scope->exitFirstLevelStatements() : $scope, $alternativeNodeCallback);

		foreach ($this->getExprHandlers() as $exprHandler) {
			if (!$exprHandler->supports($expr)) {
				continue;
			}

			$gen = $exprHandler->analyseExpr($stmt, $expr, $scope, $context, $alternativeNodeCallback);
			yield from $gen;

			$exprAnalysisResult = $gen->getReturn();
			yield new StoreExprResultRequest($expr, $exprAnalysisResult, $stack, $file, $line);

			if ($fibersStorage !== null) {
				yield from $this->processPendingFibersForRequestedExpr($fibersStorage, $expr, $exprAnalysisResult);
			}

			return $exprAnalysisResult;
		}

		throw new ShouldNotHappenException('Unhandled expr: ' . get_class($expr));
	}

	/**
	 * @return ExprHandler<Expr>[]
	 */
	private function getExprHandlers(): array
	{
		return $this->exprHandlers ??= $this->container->getServicesByTag(ExprHandler::HANDLER_TAG);
	}

	/**
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, TypeExprResult>
	 */
	private function analyseExprForType(ExprAnalysisResultStorage $storage, Expr $expr): Generator
	{
		$result = $storage->findExprAnalysisResult($expr);
		if ($result !== null) {
			yield from [];
			return new TypeExprResult($result->type, $result->nativeType, $result->keepVoidType);
		}

		throw new ShouldNotHappenException('Not yet implemented - park expr request for type until later when it has been analysed');
	}

	/**
	 * @param callable(Node, Scope): void $nodeCallback
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, null>
	 */
	private function invokeNodeCallback(
		PendingFibersStorage $fibersStorage,
		Node $node,
		Scope $scope,
		callable $nodeCallback,
	): Generator
	{
		$fiber = new Fiber(static function () use ($node, $scope, $nodeCallback) {
			$nodeCallback($node, $scope);
		});
		$request = $fiber->start();
		yield from $this->runFiberForNodeCallback($fibersStorage, $fiber, $request);

		return null;
	}

	/**
	 * @param Fiber<mixed, ExprAnalysisResult|null, null, ExprAnalysisRequest|NodeCallbackRequest> $fiber
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, void>
	 */
	private function runFiberForNodeCallback(
		PendingFibersStorage $fibersStorage,
		Fiber $fiber,
		ExprAnalysisRequest|NodeCallbackRequest|null $request,
	): Generator
	{
		while (!$fiber->isTerminated()) {
			if ($request instanceof ExprAnalysisRequest) {
				$exprAnalysisResultStorage = yield new GetExprAnalysisResultStorageRequest();
				$result = $exprAnalysisResultStorage->findExprAnalysisResult($request->expr);

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
			if ($request instanceof NodeCallbackRequest) {
				$request = $fiber->resume(yield $request);
				continue;
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

	/**
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, void>
	 */
	private function processPendingFibers(ExprAnalysisResultStorage $exprAnalysisResultStorage, PendingFibersStorage $fibersStorage): Generator
	{
		$restartLoop = true;

		while ($restartLoop) {
			$restartLoop = false;

			foreach ($fibersStorage->pendingFibers as $key => $pending) {
				$request = $pending['request'];
				$exprAnalysisResult = $exprAnalysisResultStorage->findExprAnalysisResult($request->expr);

				if ($exprAnalysisResult === null) {
					continue;
				}

				unset($fibersStorage->pendingFibers[$key]);
				$restartLoop = true;

				$fiber = $pending['fiber'];
				$request = $fiber->resume($exprAnalysisResult);
				yield from $this->runFiberForNodeCallback($fibersStorage, $fiber, $request);

				// Break and restart the loop since the array may have been modified
				break;
			}
		}
	}

	/**
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, void>
	 */
	private function processPendingFibersForRequestedExpr(PendingFibersStorage $fibersStorage, Expr $expr, ExprAnalysisResult $result): Generator
	{
		$restartLoop = true;

		while ($restartLoop) {
			$restartLoop = false;

			foreach ($fibersStorage->pendingFibers as $key => $pending) {
				$request = $pending['request'];
				if ($request->expr !== $expr) {
					continue;
				}

				unset($fibersStorage->pendingFibers[$key]);
				$restartLoop = true;

				$fiber = $pending['fiber'];
				$request = $fiber->resume($result);
				yield from $this->runFiberForNodeCallback($fibersStorage, $fiber, $request);

				// Break and restart the loop since the array may have been modified
				break;
			}
		}
	}

}
