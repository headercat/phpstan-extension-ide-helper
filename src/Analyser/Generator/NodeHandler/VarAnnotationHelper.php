<?php 

namespace PHPStan\Analyser\Generator\NodeHandler;
return;

use Generator;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\Variable;
use PHPStan\Analyser\ExpressionContext;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\GeneratorNodeScopeResolver;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\InternalThrowPoint;
use PHPStan\Analyser\Generator\NodeCallbackRequest;
use PHPStan\Analyser\Generator\NoopNodeCallback;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Node\VarTagChangedExpressionTypeNode;
use PHPStan\TrinaryLogic;
use PHPStan\Type\FileTypeMapper;
use PHPStan\Type\MixedType;
use function count;
use function is_int;
use function is_string;

/**
 * @phpstan-import-type GeneratorTValueType from GeneratorNodeScopeResolver
 * @phpstan-import-type GeneratorTSendType from GeneratorNodeScopeResolver
 */
#[AutowiredService]
final class VarAnnotationHelper
{

	public function __construct(
		private FileTypeMapper $fileTypeMapper,
	)
	{
	}

	/**
	 * @param (callable(Node, Scope, callable(Node, Scope): void): void)|null $alternativeNodeCallback
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, GeneratorScope>
	 */
	public function processStmtVarAnnotation(GeneratorScope $scope, Node\Stmt $stmt, ?Expr $defaultExpr, ?callable $alternativeNodeCallback): Generator
	{
		$function = $scope->getFunction();
		$variableLessTags = [];

		foreach ($stmt->getComments() as $comment) {
			if (!$comment instanceof Doc) {
				continue;
			}

			$resolvedPhpDoc = $this->fileTypeMapper->getResolvedPhpDoc(
				$scope->getFile(),
				$scope->isInClass() ? $scope->getClassReflection()->getName() : null,
				$scope->isInTrait() ? $scope->getTraitReflection()->getName() : null,
				$function !== null ? $function->getName() : null,
				$comment->getText(),
			);

			$assignedVariable = null;
			if (
				$stmt instanceof Node\Stmt\Expression
				&& ($stmt->expr instanceof Assign || $stmt->expr instanceof AssignRef)
				&& $stmt->expr->var instanceof Variable
				&& is_string($stmt->expr->var->name)
			) {
				$assignedVariable = $stmt->expr->var->name;
			}

			foreach ($resolvedPhpDoc->getVarTags() as $name => $varTag) {
				if (is_int($name)) {
					$variableLessTags[] = $varTag;
					continue;
				}

				if ($name === $assignedVariable) {
					continue;
				}

				$certainty = $scope->hasVariableType($name);
				if ($certainty->no()) {
					continue;
				}

				if ($scope->isInClass() && $scope->getFunction() === null) {
					continue;
				}

				if ($scope->canAnyVariableExist()) {
					$certainty = TrinaryLogic::createYes();
				}

				$variableNode = new Variable($name, $stmt->getAttributes());
				$originalType = $scope->getVariableType($name);
				if (!$originalType->equals($varTag->getType())) {
					yield new NodeCallbackRequest(new VarTagChangedExpressionTypeNode($varTag, $variableNode), $scope, $alternativeNodeCallback);
				}

				$variableNodeResult = yield new ExprAnalysisRequest($stmt, $variableNode, $scope, ExpressionContext::createDeep(), new NoopNodeCallback());

				$assignVarGen = $scope->assignVariable(
					$name,
					$varTag->getType(),
					$variableNodeResult->nativeType,
					$certainty,
				);
				yield from $assignVarGen;
				$scope = $assignVarGen->getReturn();
			}
		}

		if (count($variableLessTags) === 1 && $defaultExpr !== null) {
			//$originalType = $scope->getType($defaultExpr);
			$varTag = $variableLessTags[0];
			/*if (!$originalType->equals($varTag->getType())) {
				yield new NodeCallbackRequest(new VarTagChangedExpressionTypeNode($varTag, $defaultExpr), $scope);
			}*/
			$assignExprGen = $scope->assignExpression($defaultExpr, $varTag->getType(), new MixedType());
			yield from $assignExprGen;
			$scope = $assignExprGen->getReturn();
		}

		return $scope;
	}

	/**
	 * @param array<int, string> $variableNames
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, GeneratorScope>
	 */
	public function processVarAnnotation(GeneratorScope $scope, array $variableNames, Node\Stmt $node, bool &$changed = false): Generator
	{
		$function = $scope->getFunction();
		$varTags = [];
		foreach ($node->getComments() as $comment) {
			if (!$comment instanceof Doc) {
				continue;
			}

			$resolvedPhpDoc = $this->fileTypeMapper->getResolvedPhpDoc(
				$scope->getFile(),
				$scope->isInClass() ? $scope->getClassReflection()->getName() : null,
				$scope->isInTrait() ? $scope->getTraitReflection()->getName() : null,
				$function !== null ? $function->getName() : null,
				$comment->getText(),
			);
			foreach ($resolvedPhpDoc->getVarTags() as $key => $varTag) {
				$varTags[$key] = $varTag;
			}
		}

		if (count($varTags) === 0) {
			return $scope;
		}

		foreach ($variableNames as $variableName) {
			if (!isset($varTags[$variableName])) {
				continue;
			}

			$variableType = $varTags[$variableName]->getType();
			$changed = true;
			$assignVarGen = $scope->assignVariable($variableName, $variableType, new MixedType(), TrinaryLogic::createYes());
			yield from $assignVarGen;
			$scope = $assignVarGen->getReturn();
		}

		if (count($variableNames) === 1 && count($varTags) === 1 && isset($varTags[0])) {
			$variableType = $varTags[0]->getType();
			$changed = true;
			$assignVarGen = $scope->assignVariable($variableNames[0], $variableType, new MixedType(), TrinaryLogic::createYes());
			yield from $assignVarGen;
			$scope = $assignVarGen->getReturn();
		}

		return $scope;
	}

	/**
	 * @return InternalThrowPoint[]|null
	 */
	public function getOverridingThrowPoints(Node\Stmt $statement, GeneratorScope $scope): ?array
	{
		foreach ($statement->getComments() as $comment) {
			if (!$comment instanceof Doc) {
				continue;
			}

			$function = $scope->getFunction();
			$resolvedPhpDoc = $this->fileTypeMapper->getResolvedPhpDoc(
				$scope->getFile(),
				$scope->isInClass() ? $scope->getClassReflection()->getName() : null,
				$scope->isInTrait() ? $scope->getTraitReflection()->getName() : null,
				$function !== null ? $function->getName() : null,
				$comment->getText(),
			);

			$throwsTag = $resolvedPhpDoc->getThrowsTag();
			if ($throwsTag !== null) {
				$throwsType = $throwsTag->getType();
				if ($throwsType->isVoid()->yes()) {
					return [];
				}

				return [InternalThrowPoint::createExplicit($scope, $throwsType, $statement, false)];
			}
		}

		return null;
	}

}
