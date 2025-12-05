<?php 

namespace PHPStan\Analyser\Generator\StmtHandler;
return;

use Generator;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\ExpressionContext;
use PHPStan\Analyser\Generator\AttrGroupsAnalysisRequest;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\NodeCallbackRequest;
use PHPStan\Analyser\Generator\NodeHandler\PropertyHooksHandler;
use PHPStan\Analyser\Generator\NodeHandler\StatementPhpDocsHelper;
use PHPStan\Analyser\Generator\StmtAnalysisResult;
use PHPStan\Analyser\Generator\StmtHandler;
use PHPStan\Analyser\StatementContext;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Node\ClassPropertyNode;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\ParserNodeTypeToPHPStanType;
use function count;

/**
 * @implements StmtHandler<Property>
 */
#[AutowiredService]
final class PropertyHandler implements StmtHandler
{

	public function __construct(
		private StatementPhpDocsHelper $statementPhpDocsHelper,
		private PropertyHooksHandler $propertyHooksHandler,
	)
	{
	}

	public function supports(Stmt $stmt): bool
	{
		return $stmt instanceof Property;
	}

	public function analyseStmt(Stmt $stmt, GeneratorScope $scope, StatementContext $context, ?callable $alternativeNodeCallback): Generator
	{
		yield new AttrGroupsAnalysisRequest($stmt, $stmt->attrGroups, $scope, $alternativeNodeCallback);

		$nativePropertyType = $stmt->type !== null ? ParserNodeTypeToPHPStanType::resolve($stmt->type, $scope->getClassReflection()) : null;

		[,,,,,,,,,,,,$isReadOnly, $docComment, ,,,$varTags, $isAllowedPrivateMutation] = $this->statementPhpDocsHelper->getPhpDocs($scope, $stmt);
		$phpDocType = null;
		if (isset($varTags[0]) && count($varTags) === 1) {
			$phpDocType = $varTags[0]->getType();
		}

		foreach ($stmt->props as $prop) {
			yield new NodeCallbackRequest($prop, $scope, $alternativeNodeCallback);
			if ($prop->default !== null) {
				yield new ExprAnalysisRequest($stmt, $prop->default, $scope, ExpressionContext::createDeep(), $alternativeNodeCallback);
			}

			if (!$scope->isInClass()) {
				throw new ShouldNotHappenException();
			}
			$propertyName = $prop->name->toString();

			if ($phpDocType === null) {
				if (isset($varTags[$propertyName])) {
					$phpDocType = $varTags[$propertyName]->getType();
				}
			}

			$propStmt = clone $stmt;
			$propStmt->setAttributes($prop->getAttributes());
			$propStmt->setAttribute('originalPropertyStmt', $stmt);
			yield new NodeCallbackRequest(
				new ClassPropertyNode(
					$propertyName,
					$stmt->flags,
					$nativePropertyType,
					$prop->default,
					$docComment,
					$phpDocType,
					false,
					false,
					$propStmt,
					$isReadOnly,
					$scope->isInTrait(),
					$scope->getClassReflection()->isReadOnly(),
					$isAllowedPrivateMutation,
					$scope->getClassReflection(),
				),
				$scope,
				$alternativeNodeCallback,
			);
		}

		if (count($stmt->hooks) > 0) {
			if (!isset($propertyName)) {
				throw new ShouldNotHappenException('Property name should be known when analysing hooks.');
			}

			yield from $this->propertyHooksHandler->processPropertyHooks(
				$stmt,
				$stmt->type,
				$phpDocType,
				$propertyName,
				$stmt->hooks,
				$scope,
				$alternativeNodeCallback,
			);
		}

		if ($stmt->type !== null) {
			yield new NodeCallbackRequest($stmt->type, $scope, $alternativeNodeCallback);
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
