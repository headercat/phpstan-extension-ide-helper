<?php 

namespace PHPStan\Analyser\Generator;
return;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Node\Expr\AlwaysRememberedExpr;
use PHPStan\Node\Printer\ExprPrinter;
use PHPStan\ShouldNotHappenException;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Accessory\NonEmptyArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\ConstantScalarType;
use PHPStan\Type\IntegerRangeType;
use PHPStan\Type\StaticTypeFactory;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use function count;
use const COUNT_NORMAL;

#[AutowiredService]
final class SpecifiedTypesHelper
{

	public function __construct(
		private ExprPrinter $exprPrinter,
	)
	{
	}

	public function createDefaultSpecifiedTruthyTypes(Expr $expr): SpecifiedTypes
	{
		$type = StaticTypeFactory::falsey();
		return $this->create($expr, $type, TypeSpecifierContext::createFalse())->setRootExpr($expr);
	}

	public function createDefaultSpecifiedFalseyTypes(Expr $expr): SpecifiedTypes
	{
		$type = StaticTypeFactory::truthy();
		return $this->create($expr, $type, TypeSpecifierContext::createFalse())->setRootExpr($expr);
	}

	public function create(
		Expr $expr,
		Type $type,
		TypeSpecifierContext $context,
	): SpecifiedTypes
	{
		if ($expr instanceof Instanceof_ || $expr instanceof Expr\List_) {
			return (new SpecifiedTypes([], []))->setRootExpr($expr);
		}

		$specifiedExprs = [$expr];
		if ($expr instanceof AlwaysRememberedExpr) {
			$specifiedExprs[] = $expr->expr;
		}

		$types = null;

		foreach ($specifiedExprs as $specifiedExpr) {
			$newTypes = $this->createForExpr($specifiedExpr, $type, $context);

			if ($types === null) {
				$types = $newTypes;
			} else {
				$types = $types->unionWith($newTypes);
			}
		}

		return $types;
	}

	private function createForExpr(
		Expr $expr,
		Type $type,
		TypeSpecifierContext $context,
	): SpecifiedTypes
	{
		$sureTypes = [];
		$sureNotTypes = [];
		$exprString = $this->exprPrinter->printExpr($expr);
		if ($context->false()) {
			$sureNotTypes[$exprString] = [$expr, $type];
		} elseif ($context->true()) {
			$sureTypes[$exprString] = [$expr, $type];
		}

		return new SpecifiedTypes($sureTypes, $sureNotTypes);
	}

	/**
	 * @return array{Expr, ConstantScalarType, Type}|null
	 */
	public function findTypeExpressionsFromBinaryOperation(Type $leftType, Type $rightType, Expr\BinaryOp $binaryOperation): ?array
	{
		$rightExpr = $binaryOperation->right;
		if ($rightExpr instanceof AlwaysRememberedExpr) {
			$rightExpr = $rightExpr->getExpr();
		}

		$leftExpr = $binaryOperation->left;
		if ($leftExpr instanceof AlwaysRememberedExpr) {
			$leftExpr = $leftExpr->getExpr();
		}

		if (
			$leftType instanceof ConstantScalarType
			&& !$rightExpr instanceof ConstFetch
		) {
			return [$binaryOperation->right, $leftType, $rightType];
		} elseif (
			$rightType instanceof ConstantScalarType
			&& !$leftExpr instanceof ConstFetch
		) {
			return [$binaryOperation->left, $rightType, $leftType];
		}

		return null;
	}

	/**
	 * @return array{SpecifiedTypes, SpecifiedTypes}|null
	 */
	public function specifyTypesForCountFuncCall(
		FuncCall $countFuncCall,
		Type $type,
		Type $sizeType,
		?Type $mode,
		Expr $rootExpr,
	): ?array
	{
		if (count($countFuncCall->getArgs()) === 1) {
			$isNormalCount = TrinaryLogic::createYes();
		} elseif ($mode !== null) {
			$isNormalCount = (new ConstantIntegerType(COUNT_NORMAL))->isSuperTypeOf($mode)->result->or($type->getIterableValueType()->isArray()->negate());
		} else {
			throw new ShouldNotHappenException();
		}

		$isConstantArray = $type->isConstantArray();
		$isList = $type->isList();
		$oneOrMore = IntegerRangeType::fromInterval(1, null);
		if (
			!$isNormalCount->yes()
			|| (!$isConstantArray->yes() && !$isList->yes())
			|| !$oneOrMore->isSuperTypeOf($sizeType)->yes()
			|| $sizeType->isSuperTypeOf($type->getArraySize())->yes()
		) {
			return null;
		}

		$truthyResultTypes = [];
		$falseyResultTypes = [];
		foreach ($type->getArrays() as $arrayType) {
			$isSizeSuperTypeOfArraySize = $sizeType->isSuperTypeOf($arrayType->getArraySize());
			if ($isSizeSuperTypeOfArraySize->no()) {
				continue;
			}

			if (
				$sizeType instanceof ConstantIntegerType
				&& $sizeType->getValue() < ConstantArrayTypeBuilder::ARRAY_COUNT_LIMIT
				&& $isList->yes()
				&& $arrayType->getKeyType()->isSuperTypeOf(IntegerRangeType::fromInterval(0, $sizeType->getValue() - 1))->yes()
			) {
				// turn optional offsets non-optional
				$valueTypesBuilder = ConstantArrayTypeBuilder::createEmpty();
				for ($i = 0; $i < $sizeType->getValue(); $i++) {
					$offsetType = new ConstantIntegerType($i);
					$valueTypesBuilder->setOffsetValueType($offsetType, $arrayType->getOffsetValueType($offsetType));
				}
				$array = $valueTypesBuilder->getArray();
				$truthyResultTypes[] = $array;
				if (!$isSizeSuperTypeOfArraySize->maybe()) {
					$falseyResultTypes[] = $array;
				}
				continue;
			}

			if (
				$sizeType instanceof IntegerRangeType
				&& $sizeType->getMin() !== null
				&& $sizeType->getMin() < ConstantArrayTypeBuilder::ARRAY_COUNT_LIMIT
				&& $isList->yes()
				&& $arrayType->getKeyType()->isSuperTypeOf(IntegerRangeType::fromInterval(0, ($sizeType->getMax() ?? $sizeType->getMin()) - 1))->yes()
			) {
				$builderData = [];
				// turn optional offsets non-optional
				for ($i = 0; $i < $sizeType->getMin(); $i++) {
					$offsetType = new ConstantIntegerType($i);
					$builderData[] = [$offsetType, $arrayType->getOffsetValueType($offsetType), false];
				}
				if ($sizeType->getMax() !== null) {
					if ($sizeType->getMax() - $sizeType->getMin() > ConstantArrayTypeBuilder::ARRAY_COUNT_LIMIT) {
						$truthyResultTypes[] = $arrayType;
						if (!$isSizeSuperTypeOfArraySize->maybe()) {
							$falseyResultTypes[] = $arrayType;
						}
						continue;
					}
					for ($i = $sizeType->getMin(); $i < $sizeType->getMax(); $i++) {
						$offsetType = new ConstantIntegerType($i);
						$builderData[] = [$offsetType, $arrayType->getOffsetValueType($offsetType), true];
					}
				} elseif ($arrayType->isConstantArray()->yes()) {
					for ($i = $sizeType->getMin();; $i++) {
						$offsetType = new ConstantIntegerType($i);
						$hasOffset = $arrayType->hasOffsetValueType($offsetType);
						if ($hasOffset->no()) {
							break;
						}
						$builderData[] = [$offsetType, $arrayType->getOffsetValueType($offsetType), !$hasOffset->yes()];
					}
				} else {
					$array = TypeCombinator::intersect($arrayType, new NonEmptyArrayType());
					$truthyResultTypes[] = $array;
					if (!$isSizeSuperTypeOfArraySize->maybe()) {
						$falseyResultTypes[] = $array;
					}
					continue;
				}

				if (count($builderData) > ConstantArrayTypeBuilder::ARRAY_COUNT_LIMIT) {
					$truthyResultTypes[] = $arrayType;
					if (!$isSizeSuperTypeOfArraySize->maybe()) {
						$falseyResultTypes[] = $arrayType;
					}
					continue;
				}

				$builder = ConstantArrayTypeBuilder::createEmpty();
				foreach ($builderData as [$offsetType, $valueType, $optional]) {
					$builder->setOffsetValueType($offsetType, $valueType, $optional);
				}

				$array = $builder->getArray();
				$truthyResultTypes[] = $array;
				if (!$isSizeSuperTypeOfArraySize->maybe()) {
					$falseyResultTypes[] = $array;
				}
				continue;
			}

			$array = TypeCombinator::intersect($arrayType, new NonEmptyArrayType());
			$truthyResultTypes[] = $array;
			if ($isSizeSuperTypeOfArraySize->maybe()) {
				continue;
			}

			$falseyResultTypes[] = $array;
		}

		return [
			$this->create($countFuncCall->getArgs()[0]->value, TypeCombinator::union(...$truthyResultTypes), TypeSpecifierContext::createTruthy())->setRootExpr($rootExpr),
			$this->create($countFuncCall->getArgs()[0]->value, TypeCombinator::union(...$falseyResultTypes), TypeSpecifierContext::createFalsey())->setRootExpr($rootExpr),
		];
	}

}
