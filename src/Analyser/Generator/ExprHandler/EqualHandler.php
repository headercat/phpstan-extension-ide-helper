<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use Generator;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\ExpressionContext;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\ExprAnalysisResult;
use PHPStan\Analyser\Generator\ExprHandler;
use PHPStan\Analyser\Generator\GeneratorNodeScopeResolver;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\SpecifiedTypesHelper;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Node\Printer\ExprPrinter;
use PHPStan\Reflection\InitializerExprTypeResolver;
use PHPStan\Type\Accessory\NonEmptyArrayType;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\Constant\ConstantFloatType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\NullType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;
use function array_merge;
use function in_array;
use function is_string;
use function strtolower;

/**
 * @implements ExprHandler<Equal>
 * @phpstan-import-type GeneratorTValueType from GeneratorNodeScopeResolver
 * @phpstan-import-type GeneratorTSendType from GeneratorNodeScopeResolver
 */
#[AutowiredService]
final class EqualHandler implements ExprHandler
{

	public function __construct(
		private InitializerExprTypeResolver $initializerExprTypeResolver,
		private SpecifiedTypesHelper $specifiedTypesHelper,
		private ExprPrinter $exprPrinter,
	)
	{
	}

	public function supports(Expr $expr): bool
	{
		return $expr instanceof Equal;
	}

	public function analyseExpr(Stmt $stmt, Expr $expr, GeneratorScope $scope, ExpressionContext $context, ?callable $alternativeNodeCallback): Generator
	{
		$leftResult = yield new ExprAnalysisRequest($stmt, $expr->left, $scope, $context->enterDeep(), $alternativeNodeCallback);
		$rightResult = yield new ExprAnalysisRequest($stmt, $expr->right, $leftResult->scope, $context->enterDeep(), $alternativeNodeCallback);

		$specifiedTypeGen = $this->resolveEqualSpecifiedTypes($expr, $leftResult, $rightResult, $scope);
		yield from $specifiedTypeGen;
		[$truthyTypes, $falseyTypes] = $specifiedTypeGen->getReturn();

		return new ExprAnalysisResult(
			$this->getEqualType($expr, $leftResult->type, $rightResult->type),
			$this->getEqualType($expr, $leftResult->nativeType, $rightResult->nativeType),
			$rightResult->scope,
			hasYield: $leftResult->hasYield || $rightResult->hasYield,
			isAlwaysTerminating: $leftResult->isAlwaysTerminating || $rightResult->isAlwaysTerminating,
			throwPoints: array_merge($leftResult->throwPoints, $rightResult->throwPoints),
			impurePoints: array_merge($leftResult->impurePoints, $rightResult->impurePoints),
			specifiedTruthyTypes: $truthyTypes,
			specifiedFalseyTypes: $falseyTypes,
			specifiedNullTypes: new SpecifiedTypes(),
		);
	}

	private function getEqualType(Equal $expr, Type $leftType, Type $rightType): Type
	{
		if (
			$expr->left instanceof Variable
			&& is_string($expr->left->name)
			&& $expr->right instanceof Variable
			&& is_string($expr->right->name)
			&& $expr->left->name === $expr->right->name
		) {
			return new ConstantBooleanType(true);
		}

		return $this->initializerExprTypeResolver->resolveEqualType($leftType, $rightType)->type;
	}

	/**
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, array{SpecifiedTypes, SpecifiedTypes}>
	 */
	private function resolveEqualSpecifiedTypes(
		Expr\BinaryOp\Equal $equalExpr,
		ExprAnalysisResult $leftResult,
		ExprAnalysisResult $rightResult,
		GeneratorScope $scope,
	): Generator
	{
		$expressions = $this->specifiedTypesHelper->findTypeExpressionsFromBinaryOperation($leftResult->type, $rightResult->type, $equalExpr);
		if ($expressions !== null) {
			$exprNode = $expressions[0];
			$constantType = $expressions[1];
			$otherType = $expressions[2];

			if ($constantType->getValue() === null) {
				$trueTypes = new UnionType([
					new NullType(),
					new ConstantBooleanType(false),
					new ConstantIntegerType(0),
					new ConstantFloatType(0.0),
					new ConstantStringType(''),
					new ConstantArrayType([], []),
				]);

				return [
					$this->specifiedTypesHelper->create($exprNode, $trueTypes, TypeSpecifierContext::createTruthy())->setRootExpr($equalExpr),
					$this->specifiedTypesHelper->create($exprNode, $trueTypes, TypeSpecifierContext::createFalsey())->setRootExpr($equalExpr),
				];
			}

			if ($constantType->getValue() === false) {
				if ($exprNode === $equalExpr->left) {
					$result = $leftResult;
				} else {
					$result = $rightResult;
				}

				return [
					$result->specifiedFalseyTypes->setRootExpr($equalExpr),
					$result->specifiedTruthyTypes->setRootExpr($equalExpr),
				];
			}

			if ($constantType->getValue() === true) {
				if ($exprNode === $equalExpr->left) {
					$result = $leftResult;
				} else {
					$result = $rightResult;
				}

				return [
					$result->specifiedTruthyTypes->setRootExpr($equalExpr),
					$result->specifiedFalseyTypes->setRootExpr($equalExpr),
				];
			}

			if ($constantType->getValue() === 0 && !$otherType->isInteger()->yes() && !$otherType->isBoolean()->yes()) {
				/* There is a difference between php 7.x and 8.x on the equality
				 * behavior between zero and the empty string, so to be conservative
				 * we leave it untouched regardless of the language version */
				return [
					$this->specifiedTypesHelper->create($exprNode, new UnionType([
						new NullType(),
						new ConstantBooleanType(false),
						new ConstantIntegerType(0),
						new ConstantFloatType(0.0),
						new StringType(),
					]), TypeSpecifierContext::createTruthy())->setRootExpr($equalExpr),
					$this->specifiedTypesHelper->create($exprNode, new UnionType([
						new NullType(),
						new ConstantBooleanType(false),
						new ConstantIntegerType(0),
						new ConstantFloatType(0.0),
						new ConstantStringType('0'),
					]), TypeSpecifierContext::createFalsey())->setRootExpr($equalExpr),
				];
			}

			if ($constantType->getValue() === '') {
				/* There is a difference between php 7.x and 8.x on the equality
				 * behavior between zero and the empty string, so to be conservative
				 * we leave it untouched regardless of the language version */
				return [
					$this->specifiedTypesHelper->create($exprNode, new UnionType([
						new NullType(),
						new ConstantBooleanType(false),
						new ConstantIntegerType(0),
						new ConstantFloatType(0.0),
						new ConstantStringType(''),
					]), TypeSpecifierContext::createTruthy())->setRootExpr($equalExpr),
					$this->specifiedTypesHelper->create($exprNode, new UnionType([
						new NullType(),
						new ConstantBooleanType(false),
						new ConstantStringType(''),
					]), TypeSpecifierContext::createFalsey())->setRootExpr($equalExpr),
				];
			}

			if (
				$exprNode instanceof FuncCall
				&& $exprNode->name instanceof Name
				&& in_array(strtolower($exprNode->name->toString()), ['gettype', 'get_class', 'get_debug_type'], true)
				&& isset($exprNode->getArgs()[0])
				&& $constantType->isString()->yes()
			) {
				$identicalResult = yield ExprAnalysisRequest::createNoopRequest(
					new Expr\BinaryOp\Identical($equalExpr->left, $equalExpr->right),
					$scope,
				);
				return [
					$identicalResult->specifiedTruthyTypes->setRootExpr($equalExpr),
					$identicalResult->specifiedFalseyTypes->setRootExpr($equalExpr),
				];
			}

			if (
				$exprNode instanceof FuncCall
				&& $exprNode->name instanceof Name
				&& $exprNode->name->toLowerString() === 'preg_match'
				&& (new ConstantIntegerType(1))->isSuperTypeOf($constantType)->yes()
			) {
				$identicalResult = yield ExprAnalysisRequest::createNoopRequest(
					new Expr\BinaryOp\Identical($equalExpr->left, $equalExpr->right),
					$scope,
				);
				return [
					$identicalResult->specifiedTruthyTypes->setRootExpr($equalExpr),
					(new SpecifiedTypes())->setRootExpr($equalExpr),
				];
			}

			if (
				$exprNode instanceof ClassConstFetch
				&& $exprNode->name instanceof Identifier
				&& strtolower($exprNode->name->toString()) === 'class'
				&& $constantType->isString()->yes()
			) {
				$identicalResult = yield ExprAnalysisRequest::createNoopRequest(
					new Expr\BinaryOp\Identical($equalExpr->left, $equalExpr->right),
					$scope,
				);
				return [
					$identicalResult->specifiedTruthyTypes->setRootExpr($equalExpr),
					(new SpecifiedTypes())->setRootExpr($equalExpr),
				];
			}
		}

		$leftType = $leftResult->type;
		$rightType = $rightResult->type;

		$leftBooleanType = $leftType->toBoolean();
		if ($leftBooleanType instanceof ConstantBooleanType && $rightType->isBoolean()->yes()) {
			$identicalResult = yield ExprAnalysisRequest::createNoopRequest(
				new Expr\BinaryOp\Identical(
					new ConstFetch(new Name($leftBooleanType->getValue() ? 'true' : 'false')),
					$equalExpr->right,
				),
				$scope,
			);
			return [
				$identicalResult->specifiedTruthyTypes->setRootExpr($equalExpr),
				$identicalResult->specifiedFalseyTypes->setRootExpr($equalExpr),
			];
		}

		$rightBooleanType = $rightType->toBoolean();
		if ($rightBooleanType instanceof ConstantBooleanType && $leftType->isBoolean()->yes()) {
			$identicalResult = yield ExprAnalysisRequest::createNoopRequest(
				new Expr\BinaryOp\Identical(
					$equalExpr->left,
					new ConstFetch(new Name($rightBooleanType->getValue() ? 'true' : 'false')),
				),
				$scope,
			);
			return [
				$identicalResult->specifiedTruthyTypes->setRootExpr($equalExpr),
				$identicalResult->specifiedFalseyTypes->setRootExpr($equalExpr),
			];
		}

		if (
			$rightType->isArray()->yes()
			&& $leftType->isConstantArray()->yes() && $leftType->isIterableAtLeastOnce()->no()
		) {
			return [
				$this->specifiedTypesHelper->create(
					$equalExpr->right,
					new NonEmptyArrayType(),
					TypeSpecifierContext::createFalsey(),
				)->setRootExpr($equalExpr),
				$this->specifiedTypesHelper->create(
					$equalExpr->right,
					new NonEmptyArrayType(),
					TypeSpecifierContext::createTruthy(),
				)->setRootExpr($equalExpr),
			];
		}

		if (
			$leftType->isArray()->yes()
			&& $rightType->isConstantArray()->yes() && $rightType->isIterableAtLeastOnce()->no()
		) {
			return [
				$this->specifiedTypesHelper->create(
					$equalExpr->left,
					new NonEmptyArrayType(),
					TypeSpecifierContext::createFalsey(),
				)->setRootExpr($equalExpr),
				$this->specifiedTypesHelper->create(
					$equalExpr->left,
					new NonEmptyArrayType(),
					TypeSpecifierContext::createTruthy(),
				)->setRootExpr($equalExpr),
			];
		}

		if (
			($leftType->isString()->yes() && $rightType->isString()->yes())
			|| ($leftType->isInteger()->yes() && $rightType->isInteger()->yes())
			|| ($leftType->isFloat()->yes() && $rightType->isFloat()->yes())
			|| ($leftType->isEnum()->yes() && $rightType->isEnum()->yes())
		) {
			$identicalResult = yield ExprAnalysisRequest::createNoopRequest(
				new Expr\BinaryOp\Identical(
					$equalExpr->left,
					$equalExpr->right,
				),
				$scope,
			);
			return [
				$identicalResult->specifiedTruthyTypes->setRootExpr($equalExpr),
				$identicalResult->specifiedFalseyTypes->setRootExpr($equalExpr),
			];
		}

		$leftExprString = $this->exprPrinter->printExpr($equalExpr->left);
		$rightExprString = $this->exprPrinter->printExpr($equalExpr->right);
		if ($leftExprString === $rightExprString) {
			if (!$equalExpr->left instanceof Expr\Variable || !$equalExpr->right instanceof Expr\Variable) {
				return (new SpecifiedTypes([], []))->setRootExpr($equalExpr);
			}
		}

		// todo normalize?

		return [
			$this->specifiedTypesHelper->create($equalExpr->left, $leftType, TypeSpecifierContext::createTruthy())->setRootExpr($equalExpr)->unionWith(
				$this->specifiedTypesHelper->create($equalExpr->right, $rightType, TypeSpecifierContext::createTruthy())->setRootExpr($equalExpr),
			),
			$this->specifiedTypesHelper->create($equalExpr->left, $leftType, TypeSpecifierContext::createFalsey())->setRootExpr($equalExpr)/*->normalize($scope)*/->intersectWith(
				$this->specifiedTypesHelper->create($equalExpr->right, $rightType, TypeSpecifierContext::createFalsey())->setRootExpr($equalExpr)/*->normalize($scope)*/,
			),
		];
	}

}
