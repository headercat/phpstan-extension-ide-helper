<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use Generator;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\ExpressionContext;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\ExprAnalysisResult;
use PHPStan\Analyser\Generator\ExprHandler;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\NodeCallbackRequest;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Php\PhpVersion;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\IntegerType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use function array_merge;
use function count;

/**
 * @implements ExprHandler<Array_>
 */
#[AutowiredService]
final class LiteralArrayHandler implements ExprHandler
{

	public function __construct(private PhpVersion $phpVersion)
	{
	}

	public function supports(Expr $expr): bool
	{
		return $expr instanceof Array_;
	}

	public function analyseExpr(
		Stmt $stmt,
		Expr $expr,
		GeneratorScope $scope,
		ExpressionContext $context,
		?callable $alternativeNodeCallback,
	): Generator
	{
		// todo oversizedArrayBuilder
		$arrayBuilder = ConstantArrayTypeBuilder::createEmpty();
		$nativeArrayBuilder = ConstantArrayTypeBuilder::createEmpty();
		$isList = null;

		$hasYield = false;
		$throwPoints = [];
		$impurePoints = [];
		$isAlwaysTerminating = false;

		foreach ($expr->items as $arrayItem) {
			yield new NodeCallbackRequest($arrayItem, $scope, $alternativeNodeCallback);
			$keyResult = null;
			if ($arrayItem->key !== null) {
				$keyResult = yield new ExprAnalysisRequest($stmt, $arrayItem->key, $scope, $context->enterDeep(), $alternativeNodeCallback);
				$hasYield = $hasYield || $keyResult->hasYield;
				$throwPoints = array_merge($throwPoints, $keyResult->throwPoints);
				$impurePoints = array_merge($impurePoints, $keyResult->impurePoints);
				$isAlwaysTerminating = $isAlwaysTerminating || $keyResult->isAlwaysTerminating;
				$scope = $keyResult->scope;
			}

			$valueResult = yield new ExprAnalysisRequest($stmt, $arrayItem->value, $scope, $context->enterDeep(), $alternativeNodeCallback);
			$hasYield = $hasYield || $valueResult->hasYield;
			$throwPoints = array_merge($throwPoints, $valueResult->throwPoints);
			$impurePoints = array_merge($impurePoints, $valueResult->impurePoints);
			$isAlwaysTerminating = $isAlwaysTerminating || $valueResult->isAlwaysTerminating;
			$scope = $valueResult->scope;

			if ($arrayItem->unpack) {
				$this->processUnpackedConstantArray($arrayBuilder, $valueResult->type, $isList);
				$this->processUnpackedConstantArray($nativeArrayBuilder, $valueResult->nativeType, $isList);
			} else {
				$arrayBuilder->setOffsetValueType(
					$keyResult !== null ? $keyResult->type : null,
					$valueResult->type,
				);
				$nativeArrayBuilder->setOffsetValueType(
					$keyResult !== null ? $keyResult->nativeType : null,
					$valueResult->nativeType,
				);
			}
		}

		$arrayType = $arrayBuilder->getArray();
		$nativeArrayType = $nativeArrayBuilder->getArray();

		if ($isList === true) {
			$arrayType = TypeCombinator::intersect($arrayType, new AccessoryArrayListType());
			$nativeArrayType = TypeCombinator::intersect($nativeArrayType, new AccessoryArrayListType());
		}

		return new ExprAnalysisResult(
			$arrayType,
			$nativeArrayType,
			$scope,
			hasYield: $hasYield,
			isAlwaysTerminating: $isAlwaysTerminating,
			throwPoints: $throwPoints,
			impurePoints: $impurePoints,
			specifiedTruthyTypes: new SpecifiedTypes(),
			specifiedFalseyTypes: new SpecifiedTypes(),
			specifiedNullTypes: new SpecifiedTypes(),
		);
	}

	private function processUnpackedConstantArray(ConstantArrayTypeBuilder $arrayBuilder, Type $valueType, ?bool &$isList): void
	{
		$constantArrays = $valueType->getConstantArrays();
		if (count($constantArrays) === 1) {
			$constantArrayType = $constantArrays[0];
			$hasStringKey = false;
			foreach ($constantArrayType->getKeyTypes() as $keyType) {
				if ($keyType->isString()->yes()) {
					$hasStringKey = true;
					break;
				}
			}

			foreach ($constantArrayType->getValueTypes() as $i => $innerValueType) {
				if ($hasStringKey && $this->phpVersion->supportsArrayUnpackingWithStringKeys()) {
					$arrayBuilder->setOffsetValueType($constantArrayType->getKeyTypes()[$i], $innerValueType, $constantArrayType->isOptionalKey($i));
				} else {
					$arrayBuilder->setOffsetValueType(null, $innerValueType, $constantArrayType->isOptionalKey($i));
				}
			}
		} else {
			$arrayBuilder->degradeToGeneralArray();

			if ($this->phpVersion->supportsArrayUnpackingWithStringKeys() && !$valueType->getIterableKeyType()->isString()->no()) {
				$isList = false;
				$offsetType = $valueType->getIterableKeyType();
			} else {
				$isList ??= $arrayBuilder->isList();
				$offsetType = new IntegerType();
			}

			$arrayBuilder->setOffsetValueType($offsetType, $valueType->getIterableValueType(), !$valueType->isIterableAtLeastOnce()->yes());
		}
	}

}
