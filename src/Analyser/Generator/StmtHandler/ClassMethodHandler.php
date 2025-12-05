<?php 

namespace PHPStan\Analyser\Generator\StmtHandler;
return;

use Generator;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Generator\AttrGroupsAnalysisRequest;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\NodeCallbackRequest;
use PHPStan\Analyser\Generator\NodeHandler\DeprecatedAttributeHelper;
use PHPStan\Analyser\Generator\NodeHandler\ParamHandler;
use PHPStan\Analyser\Generator\NodeHandler\PropertyHooksHandler;
use PHPStan\Analyser\Generator\NodeHandler\StatementPhpDocsHelper;
use PHPStan\Analyser\Generator\StmtAnalysisResult;
use PHPStan\Analyser\Generator\StmtHandler;
use PHPStan\Analyser\Generator\StmtsAnalysisRequest;
use PHPStan\Analyser\ImpurePoint;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\StatementContext;
use PHPStan\DependencyInjection\AutowiredParameter;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Node\ClassPropertyNode;
use PHPStan\Node\ExecutionEndNode;
use PHPStan\Node\Expr\PropertyInitializationExpr;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Node\MethodReturnStatementsNode;
use PHPStan\Node\PropertyAssignNode;
use PHPStan\Node\ReturnStatement;
use PHPStan\Reflection\Php\PhpMethodFromParserNodeReflection;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\MixedType;
use PHPStan\Type\ParserNodeTypeToPHPStanType;
use PHPStan\Type\TypeUtils;
use function array_merge;
use function is_string;

/**
 * @implements StmtHandler<ClassMethod>
 */
#[AutowiredService]
final class ClassMethodHandler implements StmtHandler
{

	public function __construct(
		private StatementPhpDocsHelper $phpDocsHelper,
		private ParamHandler $paramHandler,
		private DeprecatedAttributeHelper $deprecatedAttributeHelper,
		private PropertyHooksHandler $propertyHooksHandler,
		#[AutowiredParameter]
		private readonly bool $narrowMethodScopeFromConstructor,
	)
	{
	}

	public function supports(Stmt $stmt): bool
	{
		return $stmt instanceof ClassMethod;
	}

	public function analyseStmt(Stmt $stmt, GeneratorScope $scope, StatementContext $context, ?callable $alternativeNodeCallback): Generator
	{
		yield new AttrGroupsAnalysisRequest($stmt, $stmt->attrGroups, $scope, $alternativeNodeCallback);
		[$templateTypeMap, $phpDocParameterTypes, $phpDocImmediatelyInvokedCallableParameters, $phpDocClosureThisTypeParameters, $phpDocReturnType, $phpDocThrowType, $deprecatedDescription, $isDeprecated, $isInternal, $isFinal, $isPure, $acceptsNamedArguments, $isReadOnly, $phpDocComment, $asserts, $selfOutType, $phpDocParameterOutTypes] = $this->phpDocsHelper->getPhpDocs($scope, $stmt);

		foreach ($stmt->params as $param) {
			yield from $this->paramHandler->processParam($stmt, $param, $scope, $alternativeNodeCallback);
		}

		if ($stmt->returnType !== null) {
			yield new NodeCallbackRequest($stmt->returnType, $scope, $alternativeNodeCallback);
		}

		if (!$isDeprecated) {
			[$isDeprecated, $deprecatedDescription] = $this->deprecatedAttributeHelper->getDeprecatedAttribute($scope, $stmt);
		}

		$isFromTrait = $stmt->getAttribute('originalTraitMethodName') === '__construct';
		$isConstructor = $isFromTrait || $stmt->name->toLowerString() === '__construct';

		$methodScope = $scope->enterClassMethod(
			$stmt,
			$templateTypeMap,
			$phpDocParameterTypes,
			$phpDocReturnType,
			$phpDocThrowType,
			$deprecatedDescription,
			$isDeprecated,
			$isInternal,
			$isFinal,
			$isPure,
			$acceptsNamedArguments,
			$asserts,
			$selfOutType,
			$phpDocComment,
			$phpDocParameterOutTypes,
			$phpDocImmediatelyInvokedCallableParameters,
			$phpDocClosureThisTypeParameters,
			$isConstructor,
		);

		if (!$scope->isInClass()) {
			throw new ShouldNotHappenException();
		}

		$classReflection = $scope->getClassReflection();

		if ($isConstructor) {
			foreach ($stmt->params as $param) {
				if ($param->flags === 0 && $param->hooks === []) {
					continue;
				}

				if (!$param->var instanceof Variable || !is_string($param->var->name) || $param->var->name === '') {
					throw new ShouldNotHappenException();
				}
				$phpDoc = null;
				if ($param->getDocComment() !== null) {
					$phpDoc = $param->getDocComment()->getText();
				}
				yield new NodeCallbackRequest(new ClassPropertyNode(
					$param->var->name,
					$param->flags,
					$param->type !== null ? ParserNodeTypeToPHPStanType::resolve($param->type, $classReflection) : null,
					null,
					$phpDoc,
					$phpDocParameterTypes[$param->var->name] ?? null,
					true,
					$isFromTrait,
					$param,
					$isReadOnly,
					$scope->isInTrait(),
					$classReflection->isReadOnly(),
					false,
					$classReflection,
				), $methodScope, $alternativeNodeCallback);
				yield from $this->propertyHooksHandler->processPropertyHooks(
					$stmt,
					$param->type,
					$phpDocParameterTypes[$param->var->name] ?? null,
					$param->var->name,
					$param->hooks,
					$scope,
					$alternativeNodeCallback,
				);
				$assignExprGen = $methodScope->assignExpression(new PropertyInitializationExpr($param->var->name), new MixedType(), new MixedType());
				yield from $assignExprGen;
				$methodScope = $assignExprGen->getReturn();
			}
		}

		if ($stmt->getAttribute('virtual', false) === false) {
			$methodReflection = $methodScope->getFunction();
			if (!$methodReflection instanceof PhpMethodFromParserNodeReflection) {
				throw new ShouldNotHappenException();
			}
			yield new NodeCallbackRequest(new InClassMethodNode($classReflection, $methodReflection, $stmt), $methodScope, $alternativeNodeCallback);
		}

		if ($stmt->stmts !== null) {
			$gatheredReturnStatements = [];
			$gatheredYieldStatements = [];
			$executionEnds = [];
			$methodImpurePoints = [];
			$statementResult = (yield new StmtsAnalysisRequest($stmt, $stmt->stmts, $methodScope, StatementContext::createTopLevel(), static function (Node $node, Scope $scope, callable $nodeCallback) use ($methodScope, &$gatheredReturnStatements, &$gatheredYieldStatements, &$executionEnds, &$methodImpurePoints): void {
				$nodeCallback($node, $scope);
				if ($scope->getFunction() !== $methodScope->getFunction()) {
					return;
				}
				if ($scope->isInAnonymousFunction()) {
					return;
				}
				if ($node instanceof PropertyAssignNode) {
					if (
						$node->getPropertyFetch() instanceof Node\Expr\PropertyFetch
						&& $scope->getFunction() instanceof PhpMethodFromParserNodeReflection
						&& $scope->getFunction()->getDeclaringClass()->hasConstructor()
						&& $scope->getFunction()->getDeclaringClass()->getConstructor()->getName() === $scope->getFunction()->getName()
						// @phpstan-ignore phpstan.scopeGetType (this is okay because we are in nodeCallback)
						&& TypeUtils::findThisType($scope->getType($node->getPropertyFetch()->var)) !== null
					) {
						return;
					}
					$methodImpurePoints[] = new ImpurePoint(
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
			}))->toPublic();

			$methodReflection = $methodScope->getFunction();
			if (!$methodReflection instanceof PhpMethodFromParserNodeReflection) {
				throw new ShouldNotHappenException();
			}

			yield new NodeCallbackRequest(new MethodReturnStatementsNode(
				$stmt,
				$gatheredReturnStatements,
				$gatheredYieldStatements,
				$statementResult,
				$executionEnds,
				array_merge($statementResult->getImpurePoints(), $methodImpurePoints),
				$classReflection,
				$methodReflection,
			), $methodScope, $alternativeNodeCallback);

			if ($isConstructor && $this->narrowMethodScopeFromConstructor) {
				$finalScope = null;

				foreach ($executionEnds as $executionEnd) {
					if ($executionEnd->getStatementResult()->isAlwaysTerminating()) {
						continue;
					}

					$endScope = $executionEnd->getStatementResult()->getScope();
					if ($finalScope === null) {
						$finalScope = $endScope;
						continue;
					}

					$finalScope = $finalScope->mergeWith($endScope);
				}

				foreach ($gatheredReturnStatements as $statement) {
					if ($finalScope === null) {
						$finalScope = $statement->getScope();
						continue;
					}

					$finalScope = $finalScope->mergeWith($statement->getScope());
				}

				if ($finalScope !== null) {
					$scope = $finalScope->rememberConstructorScope();
				}

			}
		}

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
