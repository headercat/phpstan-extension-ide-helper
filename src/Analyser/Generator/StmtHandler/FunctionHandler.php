<?php 

namespace PHPStan\Analyser\Generator\StmtHandler;
return;

use Generator;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Generator\AttrGroupsAnalysisRequest;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\NodeCallbackRequest;
use PHPStan\Analyser\Generator\NodeHandler\DeprecatedAttributeHelper;
use PHPStan\Analyser\Generator\NodeHandler\ParamHandler;
use PHPStan\Analyser\Generator\NodeHandler\StatementPhpDocsHelper;
use PHPStan\Analyser\Generator\StmtAnalysisResult;
use PHPStan\Analyser\Generator\StmtHandler;
use PHPStan\Analyser\Generator\StmtsAnalysisRequest;
use PHPStan\Analyser\ImpurePoint;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\StatementContext;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Node\ExecutionEndNode;
use PHPStan\Node\FunctionReturnStatementsNode;
use PHPStan\Node\InFunctionNode;
use PHPStan\Node\PropertyAssignNode;
use PHPStan\Node\ReturnStatement;
use PHPStan\Reflection\Php\PhpFunctionFromParserNodeReflection;
use PHPStan\ShouldNotHappenException;
use function array_merge;

/**
 * @implements StmtHandler<Function_>
 */
#[AutowiredService]
final class FunctionHandler implements StmtHandler
{

	public function __construct(
		private StatementPhpDocsHelper $phpDocsHelper,
		private ParamHandler $paramHandler,
		private DeprecatedAttributeHelper $deprecatedAttributeHelper,
	)
	{
	}

	public function supports(Stmt $stmt): bool
	{
		return $stmt instanceof Function_;
	}

	public function analyseStmt(
		Stmt $stmt,
		GeneratorScope $scope,
		StatementContext $context,
		?callable $alternativeNodeCallback,
	): Generator
	{
		yield new AttrGroupsAnalysisRequest($stmt, $stmt->attrGroups, $scope, $alternativeNodeCallback);
		[$templateTypeMap, $phpDocParameterTypes, $phpDocImmediatelyInvokedCallableParameters, $phpDocClosureThisTypeParameters, $phpDocReturnType, $phpDocThrowType, $deprecatedDescription, $isDeprecated, $isInternal, , $isPure, $acceptsNamedArguments, , $phpDocComment, $asserts,, $phpDocParameterOutTypes] = $this->phpDocsHelper->getPhpDocs($scope, $stmt);

		foreach ($stmt->params as $param) {
			yield from $this->paramHandler->processParam($stmt, $param, $scope, $alternativeNodeCallback);
		}

		if ($stmt->returnType !== null) {
			yield new NodeCallbackRequest($stmt->returnType, $scope, $alternativeNodeCallback);
		}

		if (!$isDeprecated) {
			[$isDeprecated, $deprecatedDescription] = $this->deprecatedAttributeHelper->getDeprecatedAttribute($scope, $stmt);
		}

		$functionScope = $scope->enterFunction(
			$stmt,
			$templateTypeMap,
			$phpDocParameterTypes,
			$phpDocReturnType,
			$phpDocThrowType,
			$deprecatedDescription,
			$isDeprecated,
			$isInternal,
			$isPure,
			$acceptsNamedArguments,
			$asserts,
			$phpDocComment,
			$phpDocParameterOutTypes,
			$phpDocImmediatelyInvokedCallableParameters,
			$phpDocClosureThisTypeParameters,
		);
		$functionReflection = $functionScope->getFunction();
		if (!$functionReflection instanceof PhpFunctionFromParserNodeReflection) {
			throw new ShouldNotHappenException();
		}

		yield new NodeCallbackRequest(new InFunctionNode($functionReflection, $stmt), $functionScope, $alternativeNodeCallback);

		$gatheredReturnStatements = [];
		$gatheredYieldStatements = [];
		$executionEnds = [];
		$functionImpurePoints = [];

		$statementResult = (yield new StmtsAnalysisRequest(
			$stmt,
			$stmt->stmts,
			$functionScope,
			StatementContext::createTopLevel(),
			static function (Node $node, Scope $scope, callable $nodeCallback) use ($functionScope, &$gatheredReturnStatements, &$gatheredYieldStatements, &$executionEnds, &$functionImpurePoints): void {
				$nodeCallback($node, $scope);
				if ($scope->getFunction() !== $functionScope->getFunction()) {
					return;
				}
				if ($scope->isInAnonymousFunction()) {
					return;
				}
				if ($node instanceof PropertyAssignNode) {
					$functionImpurePoints[] = new ImpurePoint(
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
				if ($node instanceof Node\Expr\Yield_ || $node instanceof Node\Expr\YieldFrom) {
					$gatheredYieldStatements[] = $node;
				}
				if (!$node instanceof Return_) {
					return;
				}

				$gatheredReturnStatements[] = new ReturnStatement($scope, $node);
			},
		))->toPublic();

		yield new NodeCallbackRequest(new FunctionReturnStatementsNode(
			$stmt,
			$gatheredReturnStatements,
			$gatheredYieldStatements,
			$statementResult,
			$executionEnds,
			array_merge($statementResult->getImpurePoints(), $functionImpurePoints),
			$functionReflection,
		), $functionScope, $alternativeNodeCallback);

		return new StmtAnalysisResult($scope, hasYield: false, isAlwaysTerminating: false, exitPoints: [], throwPoints: [], impurePoints: []);
	}

}
