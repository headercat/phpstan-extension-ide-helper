<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use Countable;
use Generator;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
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
use PHPStan\Reflection\InitializerExprTypeResolver;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Accessory\AccessoryNonEmptyStringType;
use PHPStan\Type\Accessory\AccessoryNonFalsyStringType;
use PHPStan\Type\Accessory\NonEmptyArrayType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\FloatType;
use PHPStan\Type\Generic\GenericClassStringType;
use PHPStan\Type\IntegerRangeType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ObjectWithoutClassType;
use PHPStan\Type\ResourceType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use function array_merge;
use function count;
use function in_array;
use function is_string;
use function strtolower;

/**
 * @implements ExprHandler<Identical>
 * @phpstan-import-type GeneratorTValueType from GeneratorNodeScopeResolver
 * @phpstan-import-type GeneratorTSendType from GeneratorNodeScopeResolver
 */
#[AutowiredService]
final class IdenticalHandler implements ExprHandler
{

	public function __construct(
		private InitializerExprTypeResolver $initializerExprTypeResolver,
		private SpecifiedTypesHelper $specifiedTypesHelper,
		private ReflectionProvider $reflectionProvider,
	)
	{
	}

	public function supports(Expr $expr): bool
	{
		return $expr instanceof Identical;
	}

	public function analyseExpr(Stmt $stmt, Expr $expr, GeneratorScope $scope, ExpressionContext $context, ?callable $alternativeNodeCallback): Generator
	{
		$leftResult = yield new ExprAnalysisRequest($stmt, $expr->left, $scope, $context->enterDeep(), $alternativeNodeCallback);
		$rightResult = yield new ExprAnalysisRequest($stmt, $expr->right, $leftResult->scope, $context->enterDeep(), $alternativeNodeCallback);

		$specifiedTypeGen = $this->resolveIdenticalSpecifiedTypes($expr, $leftResult, $rightResult, $scope);
		yield from $specifiedTypeGen;
		[$truthyTypes, $falseyTypes] = $specifiedTypeGen->getReturn();

		return new ExprAnalysisResult(
			$this->getIdenticalType($expr, $leftResult->type, $rightResult->type),
			$this->getIdenticalType($expr, $leftResult->nativeType, $rightResult->nativeType),
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

	private function getIdenticalType(Identical $expr, Type $leftType, Type $rightType): Type
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

		// todo RicherScopeGetTypeHelper

		return $this->initializerExprTypeResolver->resolveIdenticalType($leftType, $rightType)->type;
	}

	/**
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, array{SpecifiedTypes, SpecifiedTypes}>
	 */
	private function resolveIdenticalSpecifiedTypes(
		Identical $identicalExpr,
		ExprAnalysisResult $leftResult,
		ExprAnalysisResult $rightResult,
		GeneratorScope $scope,
	): Generator
	{
		// Normalize to: fn() === expr
		$leftExpr = $identicalExpr->left;
		$rightExpr = $identicalExpr->right;
		if ($rightExpr instanceof FuncCall && !$leftExpr instanceof FuncCall) {
			[$leftExpr, $rightExpr] = [$rightExpr, $leftExpr];
		}

		$unwrappedLeftExpr = $leftExpr;
		/*$unwrappedRightExpr = $rightExpr;
		if ($leftExpr instanceof AlwaysRememberedExpr) {
			$unwrappedLeftExpr = $leftExpr->getExpr();
		}

		if ($rightExpr instanceof AlwaysRememberedExpr) {
			$unwrappedRightExpr = $rightExpr->getExpr();
		}*/

		$rightType = $rightResult->type;

		// (count($a) === $b)
		if (
			$unwrappedLeftExpr instanceof FuncCall
			&& count($unwrappedLeftExpr->getArgs()) >= 1
			&& $unwrappedLeftExpr->name instanceof Name
			&& in_array(strtolower((string) $unwrappedLeftExpr->name), ['count', 'sizeof'], true)
			&& $rightType->isInteger()->yes()
		) {
			if (IntegerRangeType::fromInterval(null, -1)->isSuperTypeOf($rightType)->yes()) {
				return [
					$this->specifiedTypesHelper->create(
						$unwrappedLeftExpr->getArgs()[0]->value,
						new NeverType(),
						TypeSpecifierContext::createTruthy(),
					)->setRootExpr($identicalExpr),
					$this->specifiedTypesHelper->create(
						$unwrappedLeftExpr->getArgs()[0]->value,
						new NeverType(),
						TypeSpecifierContext::createFalsey(),
					)->setRootExpr($identicalExpr),
				];
			}

			$argType = (yield ExprAnalysisRequest::createNoopRequest($unwrappedLeftExpr->getArgs()[0]->value, $scope))->type;
			$isZero = (new ConstantIntegerType(0))->isSuperTypeOf($rightType);
			if ($isZero->yes()) {
				$falseyNewArgType = new ConstantArrayType([], []);
				if (!$argType->isArray()->yes()) {
					$truthyNewArgType = new UnionType([
						new ObjectType(Countable::class),
						new ConstantArrayType([], []),
					]);
				} else {
					$truthyNewArgType = $falseyNewArgType;
				}

				return [
					$this->specifiedTypesHelper->create($unwrappedLeftExpr, $rightType, TypeSpecifierContext::createTruthy())->setRootExpr($identicalExpr)->unionWith(
						$this->specifiedTypesHelper->create($unwrappedLeftExpr->getArgs()[0]->value, $truthyNewArgType, TypeSpecifierContext::createTruthy())->setRootExpr($identicalExpr),
					),
					$this->specifiedTypesHelper->create($unwrappedLeftExpr, $rightType, TypeSpecifierContext::createFalsey())->setRootExpr($identicalExpr)->unionWith(
						$this->specifiedTypesHelper->create($unwrappedLeftExpr->getArgs()[0]->value, $falseyNewArgType, TypeSpecifierContext::createFalsey())->setRootExpr($identicalExpr),
					),
				];
			}

			$modeArgType = null;
			if (count($unwrappedLeftExpr->getArgs()) > 1) {
				$modeArgType = (yield ExprAnalysisRequest::createNoopRequest($unwrappedLeftExpr->getArgs()[1]->value, $scope))->type;
			}

			$specifiedTypes = $this->specifiedTypesHelper->specifyTypesForCountFuncCall(
				$unwrappedLeftExpr,
				$argType,
				$rightType,
				$modeArgType,
				$identicalExpr,
			);
			if ($specifiedTypes !== null) {
				return $specifiedTypes;
			}

			if ($argType->isArray()->yes()) {
				$funcTypes = $this->specifiedTypesHelper->create($unwrappedLeftExpr, $rightType, TypeSpecifierContext::createTruthy())->setRootExpr($identicalExpr);
				if (IntegerRangeType::fromInterval(1, null)->isSuperTypeOf($rightType)->yes()) {
					return [
						$funcTypes->unionWith(
							$this->specifiedTypesHelper->create($unwrappedLeftExpr->getArgs()[0]->value, new NonEmptyArrayType(), TypeSpecifierContext::createTruthy())->setRootExpr($identicalExpr),
						),
						new SpecifiedTypes(),
					];
				}

				return [
					$funcTypes,
					new SpecifiedTypes(),
				];
			}
		}

		// strlen($a) === $b
		if (
			$unwrappedLeftExpr instanceof FuncCall
			&& count($unwrappedLeftExpr->getArgs()) === 1
			&& $unwrappedLeftExpr->name instanceof Name
			&& in_array(strtolower((string) $unwrappedLeftExpr->name), ['strlen', 'mb_strlen'], true)
			&& $rightType->isInteger()->yes()
		) {
			if (IntegerRangeType::fromInterval(null, -1)->isSuperTypeOf($rightType)->yes()) {
				return [
					$this->specifiedTypesHelper->create($unwrappedLeftExpr->getArgs()[0]->value, new NeverType(), TypeSpecifierContext::createTruthy())->setRootExpr($identicalExpr),
					$this->specifiedTypesHelper->create($unwrappedLeftExpr->getArgs()[0]->value, new NeverType(), TypeSpecifierContext::createFalsey())->setRootExpr($identicalExpr),
				];
			}

			$isZero = (new ConstantIntegerType(0))->isSuperTypeOf($rightType);
			if ($isZero->yes()) {
				return [
					$this->specifiedTypesHelper->create($unwrappedLeftExpr, $rightType, TypeSpecifierContext::createTruthy())->setRootExpr($identicalExpr)->unionWith(
						$this->specifiedTypesHelper->create($unwrappedLeftExpr->getArgs()[0]->value, new ConstantStringType(''), TypeSpecifierContext::createTruthy())->setRootExpr($identicalExpr),
					),
					$this->specifiedTypesHelper->create($unwrappedLeftExpr, $rightType, TypeSpecifierContext::createFalsey())->setRootExpr($identicalExpr)->unionWith(
						$this->specifiedTypesHelper->create($unwrappedLeftExpr->getArgs()[0]->value, new ConstantStringType(''), TypeSpecifierContext::createFalsey())->setRootExpr($identicalExpr),
					),
				];
			}

			if (IntegerRangeType::fromInterval(1, null)->isSuperTypeOf($rightType)->yes()) {
				$argType = (yield ExprAnalysisRequest::createNoopRequest($unwrappedLeftExpr->getArgs()[0]->value, $scope))->type;
				if ($argType->isString()->yes()) {
					$funcTypes = $this->specifiedTypesHelper->create($unwrappedLeftExpr, $rightType, TypeSpecifierContext::createTruthy())->setRootExpr($identicalExpr);

					$accessory = new AccessoryNonEmptyStringType();
					if (IntegerRangeType::fromInterval(2, null)->isSuperTypeOf($rightType)->yes()) {
						$accessory = new AccessoryNonFalsyStringType();
					}
					$valueTypes = $this->specifiedTypesHelper->create($unwrappedLeftExpr->getArgs()[0]->value, $accessory, TypeSpecifierContext::createTruthy())->setRootExpr($identicalExpr);

					return [
						$funcTypes->unionWith($valueTypes),
						new SpecifiedTypes(),
					];
				}
			}
		}

		// preg_match($a) === $b
		if (
			$unwrappedLeftExpr instanceof FuncCall
			&& $unwrappedLeftExpr->name instanceof Name
			&& $unwrappedLeftExpr->name->toLowerString() === 'preg_match'
			&& (new ConstantIntegerType(1))->isSuperTypeOf($rightType)->yes()
		) {
			return [
				$leftResult->specifiedTruthyTypes->setRootExpr($identicalExpr),
				new SpecifiedTypes(),
			];
		}

		// get_class($a) === 'Foo'
		if (
			$unwrappedLeftExpr instanceof FuncCall
			&& $unwrappedLeftExpr->name instanceof Name
			&& in_array(strtolower($unwrappedLeftExpr->name->toString()), ['get_class', 'get_debug_type'], true)
			&& isset($unwrappedLeftExpr->getArgs()[0])
		) {
			if ($rightType instanceof ConstantStringType && $this->reflectionProvider->hasClass($rightType->getValue())) {
				return [
					$this->specifiedTypesHelper->create(
						$unwrappedLeftExpr->getArgs()[0]->value,
						new ObjectType($rightType->getValue(), classReflection: $this->reflectionProvider->getClass($rightType->getValue())->asFinal()),
						TypeSpecifierContext::createTruthy(),
					)->unionWith($this->specifiedTypesHelper->create($leftExpr, $rightType, TypeSpecifierContext::createTruthy()))->setRootExpr($identicalExpr),
					new SpecifiedTypes(),
				];
			}
			if ($rightType->getClassStringObjectType()->isObject()->yes()) {
				return [
					$this->specifiedTypesHelper->create(
						$unwrappedLeftExpr->getArgs()[0]->value,
						$rightType->getClassStringObjectType(),
						TypeSpecifierContext::createTruthy(),
					)->unionWith($this->specifiedTypesHelper->create($leftExpr, $rightType, TypeSpecifierContext::createTruthy()))->setRootExpr($identicalExpr),
					new SpecifiedTypes(),
				];
			}
		}

		if (
			$unwrappedLeftExpr instanceof FuncCall
			&& $unwrappedLeftExpr->name instanceof Name
			&& in_array(strtolower($unwrappedLeftExpr->name->toString()), [
				'substr', 'strstr', 'stristr', 'strchr', 'strrchr', 'strtolower', 'strtoupper', 'ucfirst', 'lcfirst',
				'mb_substr', 'mb_strstr', 'mb_stristr', 'mb_strchr', 'mb_strrchr', 'mb_strtolower', 'mb_strtoupper', 'mb_ucfirst', 'mb_lcfirst',
				'ucwords', 'mb_convert_case', 'mb_convert_kana',
			], true)
			&& isset($unwrappedLeftExpr->getArgs()[0])
			&& $rightType->isNonEmptyString()->yes()
		) {
			$argType = (yield ExprAnalysisRequest::createNoopRequest($unwrappedLeftExpr->getArgs()[0]->value, $scope))->type;

			if ($argType->isString()->yes()) {
				if ($rightType->isNonFalsyString()->yes()) {
					return [
						$this->specifiedTypesHelper->create(
							$unwrappedLeftExpr->getArgs()[0]->value,
							TypeCombinator::intersect($argType, new AccessoryNonFalsyStringType()),
							TypeSpecifierContext::createTruthy(),
						)->setRootExpr($identicalExpr),
						new SpecifiedTypes(),
					];
				}

				return [
					$this->specifiedTypesHelper->create(
						$unwrappedLeftExpr->getArgs()[0]->value,
						TypeCombinator::intersect($argType, new AccessoryNonEmptyStringType()),
						TypeSpecifierContext::createTruthy(),
					)->setRootExpr($identicalExpr),
					new SpecifiedTypes(),
				];
			}
		}

		if ($rightType->isString()->yes()) {
			$truthyTypes = null;
			$falseyTypes = null;
			foreach ($rightType->getConstantStrings() as $constantString) {
				$specifiedTypesGen = $this->specifyTypesForConstantStringBinaryExpression($unwrappedLeftExpr, $constantString, $identicalExpr, $scope);
				yield from $specifiedTypesGen;
				$specifiedTypes = $specifiedTypesGen->getReturn();

				if ($specifiedTypes === null) {
					continue;
				}
				if ($truthyTypes === null) {
					$truthyTypes = $specifiedTypes[0];
					continue;
				}
				if ($falseyTypes === null) {
					$falseyTypes = $specifiedTypes[1];
					continue;
				}

				$truthyTypes = $truthyTypes->intersectWith($specifiedTypes[0]);
				$falseyTypes = $falseyTypes->intersectWith($specifiedTypes[1]);
			}

			if ($truthyTypes !== null && $falseyTypes !== null) {
				if ($leftExpr !== $unwrappedLeftExpr) {
					$truthyTypes = $truthyTypes->unionWith($this->specifiedTypesHelper->create($leftExpr, $rightType, TypeSpecifierContext::createTruthy())->setRootExpr($identicalExpr));
					$falseyTypes = $falseyTypes->unionWith($this->specifiedTypesHelper->create($leftExpr, $rightType, TypeSpecifierContext::createFalsey())->setRootExpr($identicalExpr));
				}
				return [
					$truthyTypes,
					$falseyTypes,
				];
			}
		}

		$expressions = $this->specifiedTypesHelper->findTypeExpressionsFromBinaryOperation($leftResult->type, $rightResult->type, $identicalExpr);
		if ($expressions !== null) {
			$exprNode = $expressions[0];
			$constantType = $expressions[1];

			$unwrappedExprNode = $exprNode;
			/*if ($exprNode instanceof AlwaysRememberedExpr) {
				$unwrappedExprNode = $exprNode->getExpr();
			}*/

			$specifiedTypesGen = $this->specifyTypesForConstantBinaryExpression($unwrappedExprNode, $constantType, $identicalExpr, $scope);
			yield from $specifiedTypesGen;
			$specifiedTypes = $specifiedTypesGen->getReturn();
			if ($specifiedTypes !== null) {
				if ($exprNode !== $unwrappedExprNode) {
					return [
						$specifiedTypes[0]->unionWith(
							$this->specifiedTypesHelper->create($exprNode, $constantType, TypeSpecifierContext::createTruthy())->setRootExpr($identicalExpr),
						),
						$specifiedTypes[1]->unionWith(
							$this->specifiedTypesHelper->create($exprNode, $constantType, TypeSpecifierContext::createFalsey())->setRootExpr($identicalExpr),
						),
					];
				}

				return $specifiedTypes;
			}
		}

		// todo
		// $a::class === 'Foo'

		return [
			new SpecifiedTypes(),
			new SpecifiedTypes(),
		];
	}

	/**
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, array{SpecifiedTypes, SpecifiedTypes}|null>
	 */
	private function specifyTypesForConstantBinaryExpression(
		Expr $exprNode,
		Type $constantType,
		Identical $identicalExpr,
		GeneratorScope $scope,
	): Generator
	{
		if ($constantType->isFalse()->yes()) {
			$truthyTypes = $this->specifiedTypesHelper->create($exprNode, $constantType, TypeSpecifierContext::createTruthy())->setRootExpr($identicalExpr);
			$falseyTypes = $this->specifiedTypesHelper->create($exprNode, $constantType, TypeSpecifierContext::createFalsey())->setRootExpr($identicalExpr);

			$exprNodeResult = yield ExprAnalysisRequest::createNoopRequest($exprNode, $scope);
			if (!$exprNode instanceof Expr\NullsafeMethodCall && !$exprNode instanceof Expr\NullsafePropertyFetch) {
				$falseyTypes = $falseyTypes->unionWith($exprNodeResult->specifiedTruthyTypes);
			}

			return [
				$truthyTypes->unionWith($exprNodeResult->specifiedFalseyTypes)->setRootExpr($identicalExpr),
				$falseyTypes->setRootExpr($identicalExpr),
			];
		}

		if ($constantType->isTrue()->yes()) {
			$truthyTypes = $this->specifiedTypesHelper->create($exprNode, $constantType, TypeSpecifierContext::createTruthy())->setRootExpr($identicalExpr);
			$falseyTypes = $this->specifiedTypesHelper->create($exprNode, $constantType, TypeSpecifierContext::createFalsey())->setRootExpr($identicalExpr);

			$exprNodeResult = yield ExprAnalysisRequest::createNoopRequest($exprNode, $scope);
			if (!$exprNode instanceof Expr\NullsafeMethodCall && !$exprNode instanceof Expr\NullsafePropertyFetch) {
				$falseyTypes = $falseyTypes->unionWith($exprNodeResult->specifiedFalseyTypes);
			}

			return [
				$truthyTypes->unionWith($exprNodeResult->specifiedTruthyTypes)->setRootExpr($identicalExpr),
				$falseyTypes->setRootExpr($identicalExpr),
			];
		}

		return null;
	}

	/**
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, ?array{SpecifiedTypes, SpecifiedTypes}>
	 */
	private function specifyTypesForConstantStringBinaryExpression(
		Expr $exprNode,
		Type $constantType,
		Expr $identicalExpr,
		GeneratorScope $scope,
	): Generator
	{
		$scalarValues = $constantType->getConstantScalarValues();
		if (count($scalarValues) !== 1 || !is_string($scalarValues[0])) {
			return null;
		}
		$constantStringValue = $scalarValues[0];

		if (
			$exprNode instanceof FuncCall
			&& $exprNode->name instanceof Name
			&& strtolower($exprNode->name->toString()) === 'gettype'
			&& isset($exprNode->getArgs()[0])
		) {
			$type = null;
			if ($constantStringValue === 'string') {
				$type = new StringType();
			}
			if ($constantStringValue === 'array') {
				$type = new ArrayType(new MixedType(), new MixedType());
			}
			if ($constantStringValue === 'boolean') {
				$type = new BooleanType();
			}
			if (in_array($constantStringValue, ['resource', 'resource (closed)'], true)) {
				$type = new ResourceType();
			}
			if ($constantStringValue === 'integer') {
				$type = new IntegerType();
			}
			if ($constantStringValue === 'double') {
				$type = new FloatType();
			}
			if ($constantStringValue === 'NULL') {
				$type = new NullType();
			}
			if ($constantStringValue === 'object') {
				$type = new ObjectWithoutClassType();
			}

			if ($type !== null) {
				return [
					$this->specifiedTypesHelper->create($exprNode, $constantType, TypeSpecifierContext::createTruthy())->setRootExpr($identicalExpr)
						->unionWith($this->specifiedTypesHelper->create($exprNode->getArgs()[0]->value, $type, TypeSpecifierContext::createTruthy())->setRootExpr($identicalExpr)),
					$this->specifiedTypesHelper->create($exprNode, $constantType, TypeSpecifierContext::createFalsey())->setRootExpr($identicalExpr)
						->unionWith($this->specifiedTypesHelper->create($exprNode->getArgs()[0]->value, $type, TypeSpecifierContext::createFalsey())->setRootExpr($identicalExpr)),
				];
			}
		}

		if (
			$exprNode instanceof FuncCall
			&& $exprNode->name instanceof Name
			&& strtolower((string) $exprNode->name) === 'get_parent_class'
			&& isset($exprNode->getArgs()[0])
		) {
			$argType = (yield ExprAnalysisRequest::createNoopRequest($exprNode->getArgs()[0]->value, $scope))->type;
			$objectType = new ObjectType($constantStringValue);
			$classStringType = new GenericClassStringType($objectType);

			if ($argType->isString()->yes()) {
				return [
					$this->specifiedTypesHelper->create(
						$exprNode->getArgs()[0]->value,
						$classStringType,
						TypeSpecifierContext::createTruthy(),
					)->setRootExpr($identicalExpr),
					new SpecifiedTypes(),
				];
			}

			if ($argType->isObject()->yes()) {
				return [
					$this->specifiedTypesHelper->create(
						$exprNode->getArgs()[0]->value,
						$objectType,
						TypeSpecifierContext::createTruthy(),
					)->setRootExpr($identicalExpr),
					new SpecifiedTypes(),
				];
			}

			return [
				$this->specifiedTypesHelper->create(
					$exprNode->getArgs()[0]->value,
					TypeCombinator::union($objectType, $classStringType),
					TypeSpecifierContext::createTruthy(),
				)->setRootExpr($identicalExpr),
				new SpecifiedTypes(),
			];
		}

		if (
			$exprNode instanceof FuncCall
			&& $exprNode->name instanceof Name
			&& in_array(strtolower((string) $exprNode->name), [
				'trim', 'ltrim', 'rtrim',
				'mb_trim', 'mb_ltrim', 'mb_rtrim',
			], true)
			&& isset($exprNode->getArgs()[0])
			&& $constantStringValue === ''
		) {
			$argValue = $exprNode->getArgs()[0]->value;
			$argType = (yield ExprAnalysisRequest::createNoopRequest($argValue, $scope))->type;
			if ($argType->isString()->yes()) {
				return [
					new SpecifiedTypes(),
					$this->specifiedTypesHelper->create(
						$argValue,
						TypeCombinator::intersect(
							new StringType(),
							new AccessoryNonEmptyStringType(),
						),
						TypeSpecifierContext::createTruthy(),
					)->setRootExpr($identicalExpr),
				];
			}
		}

		return null;
	}

}
