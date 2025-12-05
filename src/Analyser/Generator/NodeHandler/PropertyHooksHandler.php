<?php 

namespace PHPStan\Analyser\Generator\NodeHandler;
return;

use Generator;
use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use PHPStan\Analyser\Generator\AttrGroupsAnalysisRequest;
use PHPStan\Analyser\Generator\GeneratorNodeScopeResolver;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\NodeCallbackRequest;
use PHPStan\Analyser\Generator\StmtsAnalysisRequest;
use PHPStan\Analyser\ImpurePoint;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\StatementContext;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Node\ExecutionEndNode;
use PHPStan\Node\InPropertyHookNode;
use PHPStan\Node\PropertyAssignNode;
use PHPStan\Node\PropertyHookReturnStatementsNode;
use PHPStan\Node\PropertyHookStatementNode;
use PHPStan\Node\ReturnStatement;
use PHPStan\Parser\LineAttributesVisitor;
use PHPStan\Reflection\Php\PhpMethodFromParserNodeReflection;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Type;
use function array_merge;

/**
 * @phpstan-import-type GeneratorTValueType from GeneratorNodeScopeResolver
 * @phpstan-import-type GeneratorTSendType from GeneratorNodeScopeResolver
 */
#[AutowiredService]
final class PropertyHooksHandler
{

	public function __construct(
		private StatementPhpDocsHelper $phpDocsHelper,
		private ParamHandler $paramHandler,
		private DeprecatedAttributeHelper $deprecatedAttributeHelper,
	)
	{
	}

	/**
	 * @param Node\PropertyHook[] $hooks
	 * @param (callable(Node, Scope, callable(Node, Scope): void): void)|null $alternativeNodeCallback
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, void>
	 */
	public function processPropertyHooks(
		Node\Stmt $stmt,
		Identifier|Name|ComplexType|null $nativeTypeNode,
		?Type $phpDocType,
		string $propertyName,
		array $hooks,
		GeneratorScope $scope,
		?callable $alternativeNodeCallback,
	): Generator
	{
		if (!$scope->isInClass()) {
			throw new ShouldNotHappenException();
		}

		$classReflection = $scope->getClassReflection();

		foreach ($hooks as $hook) {
			yield new NodeCallbackRequest($hook, $scope, $alternativeNodeCallback);

			yield new AttrGroupsAnalysisRequest($stmt, $hook->attrGroups, $scope, $alternativeNodeCallback);

			[, $phpDocParameterTypes,,,, $phpDocThrowType,,,,,,,, $phpDocComment] = $this->phpDocsHelper->getPhpDocs($scope, $hook);

			foreach ($hook->params as $param) {
				yield from $this->paramHandler->processParam($stmt, $param, $scope, $alternativeNodeCallback);
			}

			[$isDeprecated, $deprecatedDescription] = $this->deprecatedAttributeHelper->getDeprecatedAttribute($scope, $hook);

			$hookScope = $scope->enterPropertyHook(
				$hook,
				$propertyName,
				$nativeTypeNode,
				$phpDocType,
				$phpDocParameterTypes,
				$phpDocThrowType,
				$deprecatedDescription,
				$isDeprecated,
				$phpDocComment,
			);
			$hookReflection = $hookScope->getFunction();
			if (!$hookReflection instanceof PhpMethodFromParserNodeReflection) {
				throw new ShouldNotHappenException();
			}

			if (!$classReflection->hasNativeProperty($propertyName)) {
				throw new ShouldNotHappenException();
			}

			$propertyReflection = $classReflection->getNativeProperty($propertyName);

			yield new NodeCallbackRequest(new InPropertyHookNode(
				$classReflection,
				$hookReflection,
				$propertyReflection,
				$hook,
			), $hookScope, $alternativeNodeCallback);

			$stmts = $hook->getStmts();
			if ($stmts === null) {
				return;
			}

			if ($hook->body instanceof Expr) {
				// enrich attributes of nodes in short hook body statements
				$traverser = new NodeTraverser(
					new LineAttributesVisitor($hook->body->getStartLine(), $hook->body->getEndLine()),
				);
				$traverser->traverse($stmts);
			}

			$gatheredReturnStatements = [];
			$executionEnds = [];
			$methodImpurePoints = [];
			$statementResult = (yield new StmtsAnalysisRequest(new PropertyHookStatementNode($hook), $stmts, $hookScope, StatementContext::createTopLevel(), static function (Node $node, Scope $scope, $nodeCallback) use ($hookScope, &$gatheredReturnStatements, &$executionEnds, &$hookImpurePoints): void {
				$nodeCallback($node, $scope);
				if ($scope->getFunction() !== $hookScope->getFunction()) {
					return;
				}
				if ($scope->isInAnonymousFunction()) {
					return;
				}
				if ($node instanceof PropertyAssignNode) {
					$hookImpurePoints[] = new ImpurePoint(
						$scope,
						$node,
						'propertyAssign',
						'property assignment',
						true,
					);
					return;
				}
				if ($node instanceof ExecutionEndNode) {
					$executionEnds[] = $node;
					return;
				}
				if (!$node instanceof Return_) {
					return;
				}

				$gatheredReturnStatements[] = new ReturnStatement($scope, $node);
			}))->toPublic();

			yield new NodeCallbackRequest(new PropertyHookReturnStatementsNode(
				$hook,
				$gatheredReturnStatements,
				$statementResult,
				$executionEnds,
				array_merge($statementResult->getImpurePoints(), $methodImpurePoints),
				$classReflection,
				$hookReflection,
				$propertyReflection,
			), $hookScope, $alternativeNodeCallback);
		}
	}

}
