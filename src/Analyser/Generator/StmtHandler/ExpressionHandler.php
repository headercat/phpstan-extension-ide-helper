<?php 

namespace PHPStan\Analyser\Generator\StmtHandler;
return;

use Generator;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Analyser\ExpressionContext;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\GeneratorNodeScopeResolver;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\InternalStatementExitPoint;
use PHPStan\Analyser\Generator\NodeCallbackRequest;
use PHPStan\Analyser\Generator\StmtAnalysisResult;
use PHPStan\Analyser\Generator\StmtHandler;
use PHPStan\Analyser\Generator\TypeExprRequest;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\StatementContext;
use PHPStan\DependencyInjection\AutowiredParameter;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Node\NoopExpressionNode;
use PHPStan\Node\PropertyAssignNode;
use PHPStan\Node\VariableAssignNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\NeverType;
use function array_filter;
use function array_key_exists;
use function array_merge;
use function count;
use function in_array;
use function strtolower;

/**
 * @implements StmtHandler<Expression>
 * @phpstan-import-type GeneratorTValueType from GeneratorNodeScopeResolver
 * @phpstan-import-type GeneratorTSendType from GeneratorNodeScopeResolver
 */
#[AutowiredService]
final class ExpressionHandler implements StmtHandler
{

	/** @var array<string, true> */
	private array $earlyTerminatingMethodNames;

	/**
	 * @param array<string, string[]> $earlyTerminatingMethodCalls className(string) => methods(string[])
	 * @param array<int, string> $earlyTerminatingFunctionCalls
	 */
	public function __construct(
		private readonly ReflectionProvider $reflectionProvider,
		#[AutowiredParameter]
		private readonly array $earlyTerminatingMethodCalls,
		#[AutowiredParameter]
		private readonly array $earlyTerminatingFunctionCalls,
	)
	{
		$earlyTerminatingMethodNames = [];
		foreach ($this->earlyTerminatingMethodCalls as $methodNames) {
			foreach ($methodNames as $methodName) {
				$earlyTerminatingMethodNames[strtolower($methodName)] = true;
			}
		}
		$this->earlyTerminatingMethodNames = $earlyTerminatingMethodNames;
	}

	public function supports(Stmt $stmt): bool
	{
		return $stmt instanceof Expression;
	}

	public function analyseStmt(Stmt $stmt, GeneratorScope $scope, StatementContext $context, ?callable $alternativeNodeCallback): Generator
	{
		$hasAssign = false;
		$currentScope = $scope;
		$result = yield new ExprAnalysisRequest($stmt, $stmt->expr, $scope, ExpressionContext::createTopLevel(), static function (Node $node, Scope $scope, callable $nodeCallback) use ($currentScope, &$hasAssign): void {
			$nodeCallback($node, $scope);
			if ($scope->getAnonymousFunctionReflection() !== $currentScope->getAnonymousFunctionReflection()) {
				return;
			}
			if ($scope->getFunction() !== $currentScope->getFunction()) {
				return;
			}
			if (!$node instanceof VariableAssignNode && !$node instanceof PropertyAssignNode) {
				return;
			}

			$hasAssign = true;
		});
		$throwPoints = array_filter($result->throwPoints, static fn ($throwPoint) => $throwPoint->explicit);
		if (
			count($result->throwPoints) === 0
			&& count($throwPoints) === 0
			&& !$stmt->expr instanceof Expr\PostInc
			&& !$stmt->expr instanceof Expr\PreInc
			&& !$stmt->expr instanceof Expr\PostDec
			&& !$stmt->expr instanceof Expr\PreDec
		) {
			yield new NodeCallbackRequest(new NoopExpressionNode($stmt->expr, $hasAssign), $scope, $alternativeNodeCallback);
		}
		$scope = $result->scope;
		$applyTypesGen = $scope->applySpecifiedTypes($result->specifiedNullTypes);
		yield from $applyTypesGen;
		$scope = $applyTypesGen->getReturn();

		$earlyTerminationExprGen = $this->findEarlyTerminatingExpr($stmt->expr, $scope);
		yield from $earlyTerminationExprGen;
		$earlyTerminationExpr = $earlyTerminationExprGen->getReturn();
		if ($earlyTerminationExpr !== null) {
			return new StmtAnalysisResult(
				$scope,
				hasYield: $result->hasYield,
				isAlwaysTerminating: true,
				exitPoints: [
					new InternalStatementExitPoint($stmt, $scope),
				],
				throwPoints: $result->throwPoints,
				impurePoints: $result->impurePoints,
			);
		}

		return new StmtAnalysisResult(
			$scope,
			hasYield: $result->hasYield,
			isAlwaysTerminating: $result->isAlwaysTerminating,
			exitPoints: [],
			throwPoints: $result->throwPoints,
			impurePoints: $result->impurePoints,
		);
	}

	/**
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, ?Expr>
	 */
	private function findEarlyTerminatingExpr(Expr $expr, Scope $scope): Generator
	{
		if (($expr instanceof MethodCall || $expr instanceof Expr\StaticCall) && $expr->name instanceof Node\Identifier) {
			if (array_key_exists($expr->name->toLowerString(), $this->earlyTerminatingMethodNames)) {
				if ($expr instanceof MethodCall) {
					$methodCalledOnType = (yield new TypeExprRequest($expr->var))->type;
				} else {
					if ($expr->class instanceof Name) {
						$methodCalledOnType = $scope->resolveTypeByName($expr->class);
					} else {
						$methodCalledOnType = (yield new TypeExprRequest($expr->class))->type;
					}
				}

				foreach ($methodCalledOnType->getObjectClassNames() as $referencedClass) {
					if (!$this->reflectionProvider->hasClass($referencedClass)) {
						continue;
					}

					$classReflection = $this->reflectionProvider->getClass($referencedClass);
					foreach (array_merge([$referencedClass], $classReflection->getParentClassesNames(), $classReflection->getNativeReflection()->getInterfaceNames()) as $className) {
						if (!isset($this->earlyTerminatingMethodCalls[$className])) {
							continue;
						}

						if (in_array((string) $expr->name, $this->earlyTerminatingMethodCalls[$className], true)) {
							return $expr;
						}
					}
				}
			}
		}

		if ($expr instanceof FuncCall && $expr->name instanceof Name) {
			if (in_array((string) $expr->name, $this->earlyTerminatingFunctionCalls, true)) {
				return $expr;
			}
		}

		if ($expr instanceof Expr\Exit_ || $expr instanceof Expr\Throw_) {
			return $expr;
		}

		$exprType = (yield new TypeExprRequest($expr))->type;
		if ($exprType instanceof NeverType && $exprType->isExplicit()) {
			return $expr;
		}

		return null;
	}

}
