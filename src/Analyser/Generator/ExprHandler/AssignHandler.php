<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use ArrayAccess;
use Closure;
use Generator;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\ConditionalExpressionHolder;
use PHPStan\Analyser\ExpressionContext;
use PHPStan\Analyser\ExpressionTypeHolder;
use PHPStan\Analyser\Generator\AssignHelper;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\ExprAnalysisResult;
use PHPStan\Analyser\Generator\ExprHandler;
use PHPStan\Analyser\Generator\GeneratorNodeScopeResolver;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\InternalThrowPoint;
use PHPStan\Analyser\Generator\NodeCallbackRequest;
use PHPStan\Analyser\Generator\NodeHandler\VarAnnotationHelper;
use PHPStan\Analyser\Generator\NoopNodeCallback;
use PHPStan\Analyser\Generator\TypeExprRequest;
use PHPStan\Analyser\ImpurePoint;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\DependencyInjection\AutowiredParameter;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Node\Expr\ExistingArrayDimFetch;
use PHPStan\Node\Expr\GetOffsetValueTypeExpr;
use PHPStan\Node\Expr\OriginalPropertyTypeExpr;
use PHPStan\Node\Expr\SetExistingOffsetValueTypeExpr;
use PHPStan\Node\Expr\SetOffsetValueTypeExpr;
use PHPStan\Node\PropertyAssignNode;
use PHPStan\Node\VariableAssignNode;
use PHPStan\Php\PhpVersion;
use PHPStan\Reflection\Php\PhpMethodFromParserNodeReflection;
use PHPStan\Reflection\Php\PhpPropertyReflection;
use PHPStan\ShouldNotHappenException;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\Accessory\HasOffsetValueType;
use PHPStan\Type\Accessory\NonEmptyArrayType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StaticTypeFactory;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use TypeError;
use function array_key_last;
use function array_merge;
use function array_pop;
use function array_reverse;
use function array_slice;
use function count;
use function in_array;
use function is_string;

/**
 * @implements ExprHandler<Assign|Expr\AssignRef>
 * @phpstan-import-type GeneratorTValueType from GeneratorNodeScopeResolver
 * @phpstan-import-type GeneratorTSendType from GeneratorNodeScopeResolver
 */
#[AutowiredService]
final class AssignHandler implements ExprHandler
{

	public function __construct(
		private PhpVersion $phpVersion,
		private AssignHelper $assignHelper,
		private VarAnnotationHelper $varAnnotationHelper,
		#[AutowiredParameter(ref: '%exceptions.implicitThrows%')]
		private readonly bool $implicitThrows,
	)
	{
	}

	public function supports(Expr $expr): bool
	{
		return $expr instanceof Assign || $expr instanceof Expr\AssignRef;
	}

	public function analyseExpr(
		Stmt $stmt,
		Expr $expr,
		GeneratorScope $scope,
		ExpressionContext $context,
		?callable $alternativeNodeCallback,
	): Generator
	{
		$gen = $this->processAssignVar(
			$scope,
			$stmt,
			$expr->var,
			$expr->expr,
			$context,
			$alternativeNodeCallback,
			static function (GeneratorScope $scope) use ($stmt, $expr, $context, $alternativeNodeCallback): Generator {
				$impurePoints = [];
				if ($expr instanceof AssignRef) {
					$referencedExpr = $expr->expr;
					while ($referencedExpr instanceof ArrayDimFetch) {
						$referencedExpr = $referencedExpr->var;
					}

					if ($referencedExpr instanceof PropertyFetch || $referencedExpr instanceof StaticPropertyFetch) {
						$impurePoints[] = new ImpurePoint(
							$scope,
							$expr,
							'propertyAssignByRef',
							'property assignment by reference',
							false,
						);
					}

					$scope = $scope->enterExpressionAssign($expr->expr);
				}

				if ($expr->var instanceof Variable && is_string($expr->var->name)) {
					$context = $context->enterRightSideAssign(
						$expr->var->name,
						$expr->expr,
					);
				}

				$result = yield new ExprAnalysisRequest($stmt, $expr->expr, $scope, $context->enterDeep(), $alternativeNodeCallback);
				$scope = $result->scope;

				if ($expr instanceof AssignRef) {
					$scope = $scope->exitExpressionAssign($expr->expr);
				}

				return new ExprAnalysisResult(
					$result->type,
					$result->nativeType,
					$scope,
					hasYield: $result->hasYield,
					isAlwaysTerminating: $result->isAlwaysTerminating,
					throwPoints: $result->throwPoints,
					impurePoints: array_merge($impurePoints, $result->impurePoints),
					specifiedTruthyTypes: new SpecifiedTypes(),
					specifiedFalseyTypes: new SpecifiedTypes(),
					specifiedNullTypes: new SpecifiedTypes(),
				);
			},
			true,
		);
		yield from $gen;

		$result = $gen->getReturn();
		$scope = $result->scope;
		$vars = $this->getAssignedVariables($expr->var);
		if (count($vars) > 0) {
			$varChangedScope = false;
			$processVarGen = $this->varAnnotationHelper->processVarAnnotation($scope, $vars, $stmt, $varChangedScope);
			yield from $processVarGen;
			$scope = $processVarGen->getReturn();
			if (!$varChangedScope) {
				$stmtVarGen = $this->varAnnotationHelper->processStmtVarAnnotation($scope, $stmt, null, $alternativeNodeCallback);
				yield from $stmtVarGen;
				$scope = $stmtVarGen->getReturn();
			}
		}

		return new ExprAnalysisResult(
			$result->type,
			$result->nativeType,
			$scope,
			hasYield: $result->hasYield,
			isAlwaysTerminating: $result->isAlwaysTerminating,
			throwPoints: $result->throwPoints,
			impurePoints: $result->impurePoints,
			specifiedTruthyTypes: new SpecifiedTypes(),
			specifiedFalseyTypes: new SpecifiedTypes(),
			specifiedNullTypes: new SpecifiedTypes(),
		);
	}

	/**
	 * @return string[]
	 */
	private function getAssignedVariables(Expr $expr): array
	{
		if ($expr instanceof Expr\Variable) {
			if (is_string($expr->name)) {
				return [$expr->name];
			}

			return [];
		}

		if ($expr instanceof Expr\List_) {
			$names = [];
			foreach ($expr->items as $item) {
				if ($item === null) {
					continue;
				}

				$names = array_merge($names, $this->getAssignedVariables($item->value));
			}

			return $names;
		}

		if ($expr instanceof ArrayDimFetch) {
			return $this->getAssignedVariables($expr->var);
		}

		return [];
	}

	/**
	 * @param (callable(Node, Scope, callable(Node, Scope): void): void)|null $alternativeNodeCallback
	 * @param Closure(GeneratorScope $scope): Generator<int, GeneratorTValueType, GeneratorTSendType, ExprAnalysisResult> $processExprCallback
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, ExprAnalysisResult>
	 */
	public function processAssignVar(
		GeneratorScope $scope,
		Node\Stmt $stmt,
		Expr $var,
		Expr $assignedExpr,
		ExpressionContext $context,
		?callable $alternativeNodeCallback,
		Closure $processExprCallback,
		bool $enterExpressionAssign,
	): Generator
	{
		yield new NodeCallbackRequest($var, $enterExpressionAssign ? $scope->enterExpressionAssign($var) : $scope, $alternativeNodeCallback);

		$isAssignOp = $assignedExpr instanceof Expr\AssignOp && !$enterExpressionAssign;
		if ($var instanceof Variable && is_string($var->name)) {
			$exprGen = $processExprCallback($scope);
			yield from $exprGen;
			$result = $exprGen->getReturn();
			$impurePoints = $result->impurePoints;
			if (in_array($var->name, Scope::SUPERGLOBAL_VARIABLES, true)) {
				$impurePoints[] = new ImpurePoint($scope, $var, 'superglobal', 'assign to superglobal variable', true);
			}
			$assignedExpr = $this->unwrapAssign($assignedExpr);
			$type = $result->type;

			$conditionalExpressions = [];
			if ($assignedExpr instanceof Ternary) {
				$if = $assignedExpr->if;
				if ($if === null) {
					$if = $assignedExpr->cond;
				}
				$truthyResult = yield new ExprAnalysisRequest($stmt, $if, $scope, $context->enterDeep(), $alternativeNodeCallback);
				if ($assignedExpr->if === null) {
					$condResult = $truthyResult;
				} else {
					$condResult = yield new ExprAnalysisRequest($stmt, $assignedExpr->cond, $scope, $context->enterDeep(), $alternativeNodeCallback);
				}
				$falseyResult = yield new ExprAnalysisRequest($stmt, $assignedExpr->else, $scope, $context->enterDeep(), $alternativeNodeCallback);

				if (
					$truthyResult->type->isSuperTypeOf($falseyResult->type)->no()
					&& $falseyResult->type->isSuperTypeOf($truthyResult->type)->no()
				) {
					$sureTypesGen = $this->processSureTypesForConditionalExpressionsAfterAssign($var->name, $conditionalExpressions, $condResult->specifiedTruthyTypes, $truthyResult->type);
					yield from $sureTypesGen;
					$conditionalExpressions = $sureTypesGen->getReturn();

					$sureTypesGen = $this->processSureNotTypesForConditionalExpressionsAfterAssign($var->name, $conditionalExpressions, $condResult->specifiedTruthyTypes, $truthyResult->type);
					yield from $sureTypesGen;
					$conditionalExpressions = $sureTypesGen->getReturn();

					$sureTypesGen = $this->processSureTypesForConditionalExpressionsAfterAssign($var->name, $conditionalExpressions, $condResult->specifiedFalseyTypes, $falseyResult->type);
					yield from $sureTypesGen;
					$conditionalExpressions = $sureTypesGen->getReturn();

					$sureTypesGen = $this->processSureNotTypesForConditionalExpressionsAfterAssign($var->name, $conditionalExpressions, $condResult->specifiedFalseyTypes, $falseyResult->type);
					yield from $sureTypesGen;
					$conditionalExpressions = $sureTypesGen->getReturn();
				}
			}

			$scopeBeforeAssignEval = $scope;
			$scope = $result->scope;

			$truthyType = TypeCombinator::removeFalsey($type);
			$falseyType = TypeCombinator::intersect($type, StaticTypeFactory::falsey());

			$sureTypesGen = $this->processSureTypesForConditionalExpressionsAfterAssign($var->name, $conditionalExpressions, $result->specifiedTruthyTypes, $truthyType);
			yield from $sureTypesGen;
			$conditionalExpressions = $sureTypesGen->getReturn();

			$sureTypesGen = $this->processSureNotTypesForConditionalExpressionsAfterAssign($var->name, $conditionalExpressions, $result->specifiedTruthyTypes, $truthyType);
			yield from $sureTypesGen;
			$conditionalExpressions = $sureTypesGen->getReturn();

			$sureTypesGen = $this->processSureTypesForConditionalExpressionsAfterAssign($var->name, $conditionalExpressions, $result->specifiedFalseyTypes, $falseyType);
			yield from $sureTypesGen;
			$conditionalExpressions = $sureTypesGen->getReturn();

			$sureTypesGen = $this->processSureNotTypesForConditionalExpressionsAfterAssign($var->name, $conditionalExpressions, $result->specifiedFalseyTypes, $falseyType);
			yield from $sureTypesGen;
			$conditionalExpressions = $sureTypesGen->getReturn();

			yield new NodeCallbackRequest(new VariableAssignNode($var, $assignedExpr), $scopeBeforeAssignEval, $alternativeNodeCallback);

			$assignVarGen = $scope->assignVariable($var->name, $type, $result->nativeType, TrinaryLogic::createYes());
			yield from $assignVarGen;
			$scope = $assignVarGen->getReturn();
			foreach ($conditionalExpressions as $exprString => $holders) {
				$scope = $scope->addConditionalExpressions($exprString, $holders);
			}

			return new ExprAnalysisResult(
				$type,
				$result->nativeType,
				$scope,
				hasYield: $result->hasYield,
				isAlwaysTerminating: $result->isAlwaysTerminating,
				throwPoints: $result->throwPoints,
				impurePoints: $impurePoints,
				specifiedTruthyTypes: new SpecifiedTypes(),
				specifiedFalseyTypes: new SpecifiedTypes(),
				specifiedNullTypes: new SpecifiedTypes(),
			);
		} elseif ($var instanceof ArrayDimFetch) {
			$dimFetchStack = [];
			$originalVar = $var;
			$assignedPropertyExpr = $assignedExpr;
			while ($var instanceof ArrayDimFetch) {
				$varForSetOffsetValue = $var->var;
				if ($varForSetOffsetValue instanceof PropertyFetch || $varForSetOffsetValue instanceof StaticPropertyFetch) {
					$varForSetOffsetValue = new OriginalPropertyTypeExpr($varForSetOffsetValue);
				}

				if (
					$var === $originalVar
					&& $var->dim !== null
					&& $scope->hasExpressionType($var)->yes()
				) {
					$assignedPropertyExpr = new SetExistingOffsetValueTypeExpr(
						$varForSetOffsetValue,
						$var->dim,
						$assignedPropertyExpr,
					);
				} else {
					$assignedPropertyExpr = new SetOffsetValueTypeExpr(
						$varForSetOffsetValue,
						$var->dim,
						$assignedPropertyExpr,
					);
				}
				$dimFetchStack[] = $var;
				$var = $var->var;
			}

			// 1. eval root expr
			if ($enterExpressionAssign) {
				$scope = $scope->enterExpressionAssign($var);
			}
			$varResult = yield new ExprAnalysisRequest($stmt, $var, $scope, $context->enterDeep(), $alternativeNodeCallback);
			$hasYield = $varResult->hasYield;
			$throwPoints = $varResult->throwPoints;
			$impurePoints = $varResult->impurePoints;
			$isAlwaysTerminating = $varResult->isAlwaysTerminating;
			$scope = $varResult->scope;
			if ($enterExpressionAssign) {
				$scope = $scope->exitExpressionAssign($var);
			}

			// 2. eval dimensions
			$offsetTypes = [];
			$offsetNativeTypes = [];
			$dimFetchStack = array_reverse($dimFetchStack);
			$lastDimKey = array_key_last($dimFetchStack);
			foreach ($dimFetchStack as $key => $dimFetch) {
				$dimExpr = $dimFetch->dim;

				// Callback was already called for last dim at the beginning of the method.
				if ($key !== $lastDimKey) {
					yield new NodeCallbackRequest($dimFetch, $enterExpressionAssign ? $scope->enterExpressionAssign($dimFetch) : $scope, $alternativeNodeCallback);
				}

				if ($dimExpr === null) {
					$offsetTypes[] = [null, $dimFetch];
					$offsetNativeTypes[] = [null, $dimFetch];

				} else {
					$dimExprResult = yield new ExprAnalysisRequest($stmt, $dimExpr, $scope, $context->enterDeep(), $alternativeNodeCallback);
					$offsetTypes[] = [$dimExprResult->type, $dimFetch];
					$offsetNativeTypes[] = [$dimExprResult->nativeType, $dimFetch];

					if ($enterExpressionAssign) {
						$scope->enterExpressionAssign($dimExpr);
					}

					$hasYield = $hasYield || $dimExprResult->hasYield;
					$throwPoints = array_merge($throwPoints, $dimExprResult->throwPoints);
					$impurePoints = array_merge($impurePoints, $dimExprResult->impurePoints);
					$isAlwaysTerminating = $isAlwaysTerminating || $dimExprResult->isAlwaysTerminating;
					$scope = $dimExprResult->scope;

					if ($enterExpressionAssign) {
						$scope = $scope->exitExpressionAssign($dimExpr);
					}
				}
			}

			$varType = $varResult->type;
			$varNativeType = $varResult->nativeType;
			$scopeBeforeAssignEval = $scope;

			// 3. eval assigned expr
			$exprGen = $processExprCallback($scope);
			yield from $exprGen;
			$exprResult = $exprGen->getReturn();
			$hasYield = $hasYield || $exprResult->hasYield;
			$throwPoints = array_merge($throwPoints, $exprResult->throwPoints);
			$impurePoints = array_merge($impurePoints, $exprResult->impurePoints);
			$isAlwaysTerminating = $isAlwaysTerminating || $exprResult->isAlwaysTerminating;
			$scope = $exprResult->scope;

			$valueToWrite = $exprResult->type;
			$nativeValueToWrite = $exprResult->nativeType;

			// 4. compose types
			$isImplicitArrayCreation = $this->isImplicitArrayCreation($dimFetchStack, $scope);
			if ($isImplicitArrayCreation->yes()) {
				$varType = new ConstantArrayType([], []);
				$varNativeType = new ConstantArrayType([], []);
			}
			$offsetValueType = $varType;
			$offsetNativeValueType = $varNativeType;

			$produceArrayDimFetchAssignValueToWriteGen = $this->produceArrayDimFetchAssignValueToWrite($dimFetchStack, $offsetTypes, $offsetValueType, $valueToWrite, $scope, false);
			yield from $produceArrayDimFetchAssignValueToWriteGen;
			[$valueToWrite, $additionalExpressions] = $produceArrayDimFetchAssignValueToWriteGen->getReturn();

			if (!$offsetValueType->equals($offsetNativeValueType) || !$valueToWrite->equals($nativeValueToWrite)) {
				$produceArrayDimFetchAssignNativeValueToWriteGen = $this->produceArrayDimFetchAssignValueToWrite($dimFetchStack, $offsetNativeTypes, $offsetNativeValueType, $nativeValueToWrite, $scope, true);
				yield from $produceArrayDimFetchAssignNativeValueToWriteGen;
				[$nativeValueToWrite, $additionalNativeExpressions] = $produceArrayDimFetchAssignNativeValueToWriteGen->getReturn();
			} else {
				$rewritten = false;
				foreach ($offsetTypes as $i => [$offsetType]) {
					[$offsetNativeType] = $offsetNativeTypes[$i];

					if ($offsetType === null) {
						if ($offsetNativeType !== null) {
							throw new ShouldNotHappenException();
						}

						continue;
					} elseif ($offsetNativeType === null) {
						throw new ShouldNotHappenException();
					}
					if ($offsetType->equals($offsetNativeType)) {
						continue;
					}

					$produceArrayDimFetchAssignNativeValueToWriteGen = $this->produceArrayDimFetchAssignValueToWrite($dimFetchStack, $offsetNativeTypes, $offsetNativeValueType, $nativeValueToWrite, $scope, true);
					yield from $produceArrayDimFetchAssignNativeValueToWriteGen;
					[$nativeValueToWrite] = $produceArrayDimFetchAssignNativeValueToWriteGen->getReturn();
					$rewritten = true;
					break;
				}

				if (!$rewritten) {
					$nativeValueToWrite = $valueToWrite;
				}
			}

			if ($varType->isArray()->yes() || !(new ObjectType(ArrayAccess::class))->isSuperTypeOf($varType)->yes()) {
				if ($var instanceof Variable && is_string($var->name)) {
					yield new NodeCallbackRequest(new VariableAssignNode($var, $assignedPropertyExpr), $scopeBeforeAssignEval, $alternativeNodeCallback);
					$assignVarGen = $scope->assignVariable($var->name, $valueToWrite, $nativeValueToWrite, TrinaryLogic::createYes());
					yield from $assignVarGen;
					$scope = $assignVarGen->getReturn();
				} else {
					if ($var instanceof PropertyFetch || $var instanceof StaticPropertyFetch) {
						yield new NodeCallbackRequest(new PropertyAssignNode($var, $assignedPropertyExpr, $isAssignOp), $scopeBeforeAssignEval, $alternativeNodeCallback);
						if ($var instanceof PropertyFetch && $var->name instanceof Node\Identifier && !$isAssignOp) {
							$scope = $scope->assignInitializedProperty((yield new TypeExprRequest($var->var))->type, $var->name->toString());
						}
					}
					$assignExprGen = $scope->assignExpression(
						$var,
						$valueToWrite,
						$nativeValueToWrite,
					);
					yield from $assignExprGen;
					$scope = $assignExprGen->getReturn();
				}
			} else {
				if ($var instanceof Variable) {
					yield new NodeCallbackRequest(new VariableAssignNode($var, $assignedPropertyExpr), $scopeBeforeAssignEval, $alternativeNodeCallback);
				} elseif ($var instanceof PropertyFetch || $var instanceof StaticPropertyFetch) {
					yield new NodeCallbackRequest(new PropertyAssignNode($var, $assignedPropertyExpr, $isAssignOp), $scopeBeforeAssignEval, $alternativeNodeCallback);
					if ($var instanceof PropertyFetch && $var->name instanceof Node\Identifier && !$isAssignOp) {
						$scope = $scope->assignInitializedProperty((yield new TypeExprRequest($var->var))->type, $var->name->toString());
					}
				}
			}

			foreach ($additionalExpressions as $k => $additionalExpression) {
				[$expr, $type] = $additionalExpression;
				$nativeType = $type;
				if (isset($additionalNativeExpressions[$k])) {
					[, $nativeType] = $additionalNativeExpressions[$k];
				}

				$assignExprGen = $scope->assignExpression($expr, $type, $nativeType);
				yield from $assignExprGen;
				$scope = $assignExprGen->getReturn();
			}

			if (!$varType->isArray()->yes() && !(new ObjectType(ArrayAccess::class))->isSuperTypeOf($varType)->no()) {
				$throwPoints = array_merge($throwPoints, (yield new ExprAnalysisRequest(
					$stmt,
					new MethodCall($var, 'offsetSet'),
					$scope,
					$context,
					new NoopNodeCallback(),
				))->throwPoints);
			}

			return new ExprAnalysisResult(
				$exprResult->type,
				$exprResult->nativeType,
				$scope,
				hasYield: $hasYield,
				isAlwaysTerminating: $isAlwaysTerminating,
				throwPoints: $throwPoints,
				impurePoints: $impurePoints,
				specifiedTruthyTypes: new SpecifiedTypes(),
				specifiedFalseyTypes: new SpecifiedTypes(),
				specifiedNullTypes: new SpecifiedTypes(),
			);
		} elseif ($var instanceof PropertyFetch) {
			$objectResult = yield new ExprAnalysisRequest($stmt, $var->var, $scope, $context, $alternativeNodeCallback);
			$hasYield = $objectResult->hasYield;
			$throwPoints = $objectResult->throwPoints;
			$impurePoints = $objectResult->impurePoints;
			$isAlwaysTerminating = $objectResult->isAlwaysTerminating;
			$scope = $objectResult->scope;

			$propertyName = null;
			if ($var->name instanceof Node\Identifier) {
				$propertyName = $var->name->name;
			} else {
				$propertyNameResult = yield new ExprAnalysisRequest($stmt, $var->name, $scope, $context, $alternativeNodeCallback);
				$hasYield = $hasYield || $propertyNameResult->hasYield;
				$throwPoints = array_merge($throwPoints, $propertyNameResult->throwPoints);
				$impurePoints = array_merge($impurePoints, $propertyNameResult->impurePoints);
				$isAlwaysTerminating = $isAlwaysTerminating || $propertyNameResult->isAlwaysTerminating;
				$scope = $propertyNameResult->scope;
			}

			$scopeBeforeAssignEval = $scope;
			$exprGen = $processExprCallback($scope);
			yield from $exprGen;
			$result = $exprGen->getReturn();
			$hasYield = $hasYield || $result->hasYield;
			$throwPoints = array_merge($throwPoints, $result->throwPoints);
			$impurePoints = array_merge($impurePoints, $result->impurePoints);
			$isAlwaysTerminating = $isAlwaysTerminating || $result->isAlwaysTerminating;
			$scope = $result->scope;

			if ($var->name instanceof Expr && $this->phpVersion->supportsPropertyHooks()) {
				$throwPoints[] = InternalThrowPoint::createImplicit($scope, $var);
			}

			$propertyHolderType = $objectResult->type;
			if ($propertyName !== null && $propertyHolderType->hasInstanceProperty($propertyName)->yes()) {
				$propertyReflection = $propertyHolderType->getInstanceProperty($propertyName, $scope);
				$assignedExprType = $result->type;
				yield new NodeCallbackRequest(new PropertyAssignNode($var, $assignedExpr, $isAssignOp), $scopeBeforeAssignEval, $alternativeNodeCallback);
				if ($propertyReflection->canChangeTypeAfterAssignment()) {
					if ($propertyReflection->hasNativeType()) {
						$propertyNativeType = $propertyReflection->getNativeType();

						$assignedTypeIsCompatible = $propertyNativeType->isSuperTypeOf($assignedExprType)->yes();
						if (!$assignedTypeIsCompatible) {
							foreach (TypeUtils::flattenTypes($propertyNativeType) as $type) {
								if ($type->isSuperTypeOf($assignedExprType)->yes()) {
									$assignedTypeIsCompatible = true;
									break;
								}
							}
						}

						if ($assignedTypeIsCompatible) {
							$assignExprGen = $scope->assignExpression($var, $assignedExprType, $result->nativeType);
							yield from $assignExprGen;
							$scope = $assignExprGen->getReturn();
						} else {
							$assignExprGen = $scope->assignExpression(
								$var,
								TypeCombinator::intersect($assignedExprType->toCoercedArgumentType($scope->isDeclareStrictTypes()), $propertyNativeType),
								TypeCombinator::intersect($result->nativeType->toCoercedArgumentType($scope->isDeclareStrictTypes()), $propertyNativeType),
							);
							yield from $assignExprGen;
							$scope = $assignExprGen->getReturn();
						}
					} else {
						$assignExprGen = $scope->assignExpression($var, $assignedExprType, $result->nativeType);
						yield from $assignExprGen;
						$scope = $assignExprGen->getReturn();
					}
				}
				$declaringClass = $propertyReflection->getDeclaringClass();
				if ($declaringClass->hasNativeProperty($propertyName)) {
					$nativeProperty = $declaringClass->getNativeProperty($propertyName);
					if (
						!$nativeProperty->getNativeType()->accepts($assignedExprType, true)->yes()
					) {
						$throwPoints[] = InternalThrowPoint::createExplicit($scope, new ObjectType(TypeError::class), $assignedExpr, false);
					}
					if ($this->phpVersion->supportsPropertyHooks()) {
						$throwPoints = array_merge($throwPoints, $this->getPropertyAssignThrowPointsFromSetHook($scope, $var, $nativeProperty));
					}
					if ($enterExpressionAssign) {
						$scope = $scope->assignInitializedProperty($propertyHolderType, $propertyName);
					}
				}
			} else {
				// fallback
				$assignedExprType = $result->type;
				yield new NodeCallbackRequest(new PropertyAssignNode($var, $assignedExpr, $isAssignOp), $scopeBeforeAssignEval, $alternativeNodeCallback);
				$assignExprGen = $scope->assignExpression($var, $assignedExprType, $result->nativeType);
				yield from $assignExprGen;
				$scope = $assignExprGen->getReturn();
				// simulate dynamic property assign by __set to get throw points
				if (!$propertyHolderType->hasMethod('__set')->no()) {
					$throwPoints = array_merge($throwPoints, (yield new ExprAnalysisRequest(
						$stmt,
						new MethodCall($var->var, '__set'),
						$scope,
						$context,
						new NoopNodeCallback(),
					))->throwPoints);
				}
			}

			return new ExprAnalysisResult(
				$result->type,
				$result->nativeType,
				$scope,
				hasYield: $hasYield,
				isAlwaysTerminating: $isAlwaysTerminating,
				throwPoints: $throwPoints,
				impurePoints: $impurePoints,
				specifiedTruthyTypes: new SpecifiedTypes(),
				specifiedFalseyTypes: new SpecifiedTypes(),
				specifiedNullTypes: new SpecifiedTypes(),
			);
		} elseif ($var instanceof Expr\StaticPropertyFetch) {
			if ($var->class instanceof Node\Name) {
				$propertyHolderType = $scope->resolveTypeByName($var->class);
			} else {
				$varClassResult = yield new ExprAnalysisRequest($stmt, $var->class, $scope, $context, $alternativeNodeCallback);
				$propertyHolderType = $varClassResult->type;
			}

			$hasYield = false;
			$throwPoints = [];
			$impurePoints = [];
			$isAlwaysTerminating = false;

			$propertyName = null;
			if ($var->name instanceof Node\Identifier) {
				$propertyName = $var->name->name;
			} else {
				$propertyNameResult = yield new ExprAnalysisRequest($stmt, $var->name, $scope, $context, $alternativeNodeCallback);
				$hasYield = $propertyNameResult->hasYield;
				$throwPoints = $propertyNameResult->throwPoints;
				$impurePoints = $propertyNameResult->impurePoints;
				$isAlwaysTerminating = $propertyNameResult->isAlwaysTerminating;
				$scope = $propertyNameResult->scope;
			}

			$scopeBeforeAssignEval = $scope;
			$exprGen = $processExprCallback($scope);
			yield from $exprGen;
			$result = $exprGen->getReturn();
			$hasYield = $hasYield || $result->hasYield;
			$throwPoints = array_merge($throwPoints, $result->throwPoints);
			$impurePoints = array_merge($impurePoints, $result->impurePoints);
			$isAlwaysTerminating = $isAlwaysTerminating || $result->isAlwaysTerminating;
			$scope = $result->scope;

			if ($propertyName !== null) {
				$propertyReflection = $scope->getStaticPropertyReflection($propertyHolderType, $propertyName);
				$assignedExprType = $result->type;
				yield new NodeCallbackRequest(new PropertyAssignNode($var, $assignedExpr, $isAssignOp), $scopeBeforeAssignEval, $alternativeNodeCallback);
				if ($propertyReflection !== null && $propertyReflection->canChangeTypeAfterAssignment()) {
					if ($propertyReflection->hasNativeType()) {
						$propertyNativeType = $propertyReflection->getNativeType();
						$assignedTypeIsCompatible = $propertyNativeType->isSuperTypeOf($assignedExprType)->yes();

						if (!$assignedTypeIsCompatible) {
							foreach (TypeUtils::flattenTypes($propertyNativeType) as $type) {
								if ($type->isSuperTypeOf($assignedExprType)->yes()) {
									$assignedTypeIsCompatible = true;
									break;
								}
							}
						}

						if ($assignedTypeIsCompatible) {
							$assignExprGen = $scope->assignExpression($var, $assignedExprType, $result->nativeType);
							yield from $assignExprGen;
							$scope = $assignExprGen->getReturn();
						} else {
							$assignExprGen = $scope->assignExpression(
								$var,
								TypeCombinator::intersect($assignedExprType->toCoercedArgumentType($scope->isDeclareStrictTypes()), $propertyNativeType),
								TypeCombinator::intersect($result->nativeType->toCoercedArgumentType($scope->isDeclareStrictTypes()), $propertyNativeType),
							);
							yield from $assignExprGen;
							$scope = $assignExprGen->getReturn();
						}
					} else {
						$assignExprGen = $scope->assignExpression($var, $assignedExprType, $result->nativeType);
						yield from $assignExprGen;
						$scope = $assignExprGen->getReturn();
					}
				}
			} else {
				// fallback
				$assignedExprType = $result->type;
				yield new NodeCallbackRequest(new PropertyAssignNode($var, $assignedExpr, $isAssignOp), $scopeBeforeAssignEval, $alternativeNodeCallback);
				$assignExprGen = $scope->assignExpression($var, $assignedExprType, $result->nativeType);
				yield from $assignExprGen;
				$scope = $assignExprGen->getReturn();
			}

			return new ExprAnalysisResult(
				$result->type,
				$result->nativeType,
				$scope,
				hasYield: $hasYield,
				isAlwaysTerminating: $isAlwaysTerminating,
				throwPoints: $throwPoints,
				impurePoints: $impurePoints,
				specifiedTruthyTypes: new SpecifiedTypes(),
				specifiedFalseyTypes: new SpecifiedTypes(),
				specifiedNullTypes: new SpecifiedTypes(),
			);
		} elseif ($var instanceof List_) {
			$exprGen = $processExprCallback($scope);
			yield from $exprGen;
			$result = $exprGen->getReturn();
			$hasYield = $result->hasYield;
			$throwPoints = $result->throwPoints;
			$impurePoints = $result->impurePoints;
			$isAlwaysTerminating = $result->isAlwaysTerminating;
			$scope = $result->scope;
			foreach ($var->items as $i => $arrayItem) {
				if ($arrayItem === null) {
					continue;
				}

				$itemScope = $scope;
				if ($enterExpressionAssign) {
					$itemScope = $itemScope->enterExpressionAssign($arrayItem->value);
				}
				$itemScope = $this->assignHelper->lookForSetAllowedUndefinedExpressions($itemScope, $arrayItem->value);
				yield new NodeCallbackRequest($arrayItem, $itemScope, $alternativeNodeCallback);
				if ($arrayItem->key !== null) {
					$keyResult = yield new ExprAnalysisRequest($stmt, $arrayItem->key, $itemScope, $context->enterDeep(), $alternativeNodeCallback);
					$hasYield = $hasYield || $keyResult->hasYield;
					$throwPoints = array_merge($throwPoints, $keyResult->throwPoints);
					$impurePoints = array_merge($impurePoints, $keyResult->impurePoints);
					$isAlwaysTerminating = $isAlwaysTerminating || $keyResult->isAlwaysTerminating;
					$itemScope = $keyResult->scope;
				}

				$valueResult = yield new ExprAnalysisRequest($stmt, $arrayItem->value, $itemScope, $context->enterDeep(), $alternativeNodeCallback);
				$hasYield = $hasYield || $valueResult->hasYield;
				$throwPoints = array_merge($throwPoints, $valueResult->throwPoints);
				$impurePoints = array_merge($impurePoints, $valueResult->impurePoints);
				$isAlwaysTerminating = $isAlwaysTerminating || $valueResult->isAlwaysTerminating;

				if ($arrayItem->key === null) {
					$dimExpr = new Node\Scalar\Int_($i);
				} else {
					$dimExpr = $arrayItem->key;
				}
				$gen = $this->processAssignVar(
					$scope,
					$stmt,
					$arrayItem->value,
					new GetOffsetValueTypeExpr($assignedExpr, $dimExpr),
					$context,
					$alternativeNodeCallback,
					static function (GeneratorScope $scope): Generator {
						yield from [];
						return new ExprAnalysisResult(
							new MixedType(),
							new MixedType(),
							$scope,
							hasYield: false,
							isAlwaysTerminating: false,
							throwPoints: [],
							impurePoints: [],
							specifiedTruthyTypes: new SpecifiedTypes(),
							specifiedFalseyTypes: new SpecifiedTypes(),
							specifiedNullTypes: new SpecifiedTypes(),
						);
					},
					$enterExpressionAssign,
				);
				yield from $gen;
				$result = $gen->getReturn();
				$scope = $result->scope;
				$hasYield = $hasYield || $result->hasYield;
				$throwPoints = array_merge($throwPoints, $result->throwPoints);
				$impurePoints = array_merge($impurePoints, $result->impurePoints);
				$isAlwaysTerminating = $isAlwaysTerminating || $result->isAlwaysTerminating;
			}

			return new ExprAnalysisResult(
				$result->type,
				$result->nativeType,
				$scope,
				hasYield: $hasYield,
				isAlwaysTerminating: $isAlwaysTerminating,
				throwPoints: $throwPoints,
				impurePoints: $impurePoints,
				specifiedTruthyTypes: new SpecifiedTypes(),
				specifiedFalseyTypes: new SpecifiedTypes(),
				specifiedNullTypes: new SpecifiedTypes(),
			);
		} elseif ($var instanceof ExistingArrayDimFetch) {
			$dimFetchStack = [];
			$assignedPropertyExpr = $assignedExpr;
			while ($var instanceof ExistingArrayDimFetch) {
				$varForSetOffsetValue = $var->getVar();
				if ($varForSetOffsetValue instanceof PropertyFetch || $varForSetOffsetValue instanceof StaticPropertyFetch) {
					$varForSetOffsetValue = new OriginalPropertyTypeExpr($varForSetOffsetValue);
				}
				$assignedPropertyExpr = new SetExistingOffsetValueTypeExpr(
					$varForSetOffsetValue,
					$var->getDim(),
					$assignedPropertyExpr,
				);
				$dimFetchStack[] = $var;
				$var = $var->getVar();
			}

			// 1. eval root expr
			$varResult = yield new ExprAnalysisRequest($stmt, $var, $scope, $context->enterDeep(), new NoopNodeCallback());
			$hasYield = $varResult->hasYield;
			$throwPoints = $varResult->throwPoints;
			$impurePoints = $varResult->impurePoints;
			$isAlwaysTerminating = $varResult->isAlwaysTerminating;
			$scope = $varResult->scope;

			// 2. eval dimensions
			$offsetTypes = [];
			$offsetNativeTypes = [];
			foreach (array_reverse($dimFetchStack) as $dimFetch) {
				$dimExpr = $dimFetch->getDim();
				$dimExprResult = yield new TypeExprRequest($dimExpr);
				$offsetTypes[] = [$dimExprResult->type, $dimFetch];
				$offsetNativeTypes[] = [$dimExprResult->nativeType, $dimFetch];
			}

			// 3. eval assigned expr
			$exprGen = $processExprCallback($scope);
			yield from $exprGen;
			$exprResult = $exprGen->getReturn();
			$hasYield = $hasYield || $exprResult->hasYield;
			$throwPoints = array_merge($throwPoints, $exprResult->throwPoints);
			$impurePoints = array_merge($impurePoints, $exprResult->impurePoints);
			$isAlwaysTerminating = $isAlwaysTerminating || $exprResult->isAlwaysTerminating;
			$scope = $exprResult->scope;

			$valueToWrite = $exprResult->type;
			$nativeValueToWrite = $exprResult->nativeType;
			$varType = $varResult->type;
			$varNativeType = $varResult->nativeType;

			// 4. compose types
			$offsetValueType = $varType;
			$offsetNativeValueType = $varNativeType;
			$offsetValueTypeStack = [$offsetValueType];
			$offsetValueNativeTypeStack = [$offsetNativeValueType];
			foreach (array_slice($offsetTypes, 0, -1) as [$offsetType]) {
				$offsetValueType = $offsetValueType->getOffsetValueType($offsetType);
				$offsetValueTypeStack[] = $offsetValueType;
			}
			foreach (array_slice($offsetNativeTypes, 0, -1) as [$offsetNativeType]) {
				$offsetNativeValueType = $offsetNativeValueType->getOffsetValueType($offsetNativeType);
				$offsetValueNativeTypeStack[] = $offsetNativeValueType;
			}

			foreach (array_reverse($offsetTypes) as [$offsetType]) {
				/** @var Type $offsetValueType */
				$offsetValueType = array_pop($offsetValueTypeStack);
				$valueToWrite = $offsetValueType->setExistingOffsetValueType($offsetType, $valueToWrite);
			}
			foreach (array_reverse($offsetNativeTypes) as [$offsetNativeType]) {
				/** @var Type $offsetNativeValueType */
				$offsetNativeValueType = array_pop($offsetValueNativeTypeStack);
				$nativeValueToWrite = $offsetNativeValueType->setExistingOffsetValueType($offsetNativeType, $nativeValueToWrite);
			}

			if ($var instanceof Variable && is_string($var->name)) {
				yield new NodeCallbackRequest(new VariableAssignNode($var, $assignedPropertyExpr), $scope, $alternativeNodeCallback);
				$assignVarGen = $scope->assignVariable($var->name, $valueToWrite, $nativeValueToWrite, TrinaryLogic::createYes());
				yield from $assignVarGen;
				$scope = $assignVarGen->getReturn();
			} else {
				if ($var instanceof PropertyFetch || $var instanceof StaticPropertyFetch) {
					yield new NodeCallbackRequest(new PropertyAssignNode($var, $assignedPropertyExpr, $isAssignOp), $scope, $alternativeNodeCallback);
				}
				$assignExprGen = $scope->assignExpression(
					$var,
					$valueToWrite,
					$nativeValueToWrite,
				);
				yield from $assignExprGen;
				$scope = $assignExprGen->getReturn();
			}

			return new ExprAnalysisResult(
				$exprResult->type,
				$exprResult->nativeType,
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

		$exprGen = $processExprCallback($scope);
		yield from $exprGen;
		$result = $exprGen->getReturn();

		return new ExprAnalysisResult(
			$result->type,
			$result->nativeType,
			$result->scope,
			hasYield: $result->hasYield,
			isAlwaysTerminating: $result->isAlwaysTerminating,
			throwPoints: $result->throwPoints,
			impurePoints: $result->impurePoints,
			specifiedTruthyTypes: new SpecifiedTypes(),
			specifiedFalseyTypes: new SpecifiedTypes(),
			specifiedNullTypes: new SpecifiedTypes(),
		);
	}

	/**
	 * @param array<string, ConditionalExpressionHolder[]> $conditionalExpressions
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, array<string, ConditionalExpressionHolder[]>>
	 */
	private function processSureTypesForConditionalExpressionsAfterAssign(string $variableName, array $conditionalExpressions, SpecifiedTypes $specifiedTypes, Type $variableType): Generator
	{
		foreach ($specifiedTypes->getSureTypes() as $exprString => [$expr, $exprType]) {
			if (!$expr instanceof Variable) {
				continue;
			}
			if (!is_string($expr->name)) {
				continue;
			}

			if ($expr->name === $variableName) {
				continue;
			}

			if (!isset($conditionalExpressions[$exprString])) {
				$conditionalExpressions[$exprString] = [];
			}

			$holder = new ConditionalExpressionHolder([
				'$' . $variableName => ExpressionTypeHolder::createYes(new Variable($variableName), $variableType),
			], ExpressionTypeHolder::createYes(
				$expr,
				TypeCombinator::intersect((yield new TypeExprRequest($expr))->type, $exprType),
			));
			$conditionalExpressions[$exprString][$holder->getKey()] = $holder;
		}

		return $conditionalExpressions;
	}

	/**
	 * @param array<string, ConditionalExpressionHolder[]> $conditionalExpressions
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, array<string, ConditionalExpressionHolder[]>>
	 */
	private function processSureNotTypesForConditionalExpressionsAfterAssign(string $variableName, array $conditionalExpressions, SpecifiedTypes $specifiedTypes, Type $variableType): Generator
	{
		foreach ($specifiedTypes->getSureNotTypes() as $exprString => [$expr, $exprType]) {
			if (!$expr instanceof Variable) {
				continue;
			}
			if (!is_string($expr->name)) {
				continue;
			}

			if ($expr->name === $variableName) {
				continue;
			}

			if (!isset($conditionalExpressions[$exprString])) {
				$conditionalExpressions[$exprString] = [];
			}

			$holder = new ConditionalExpressionHolder([
				'$' . $variableName => ExpressionTypeHolder::createYes(new Variable($variableName), $variableType),
			], ExpressionTypeHolder::createYes(
				$expr,
				TypeCombinator::remove((yield new TypeExprRequest($expr))->type, $exprType),
			));
			$conditionalExpressions[$exprString][$holder->getKey()] = $holder;
		}

		return $conditionalExpressions;
	}

	private function unwrapAssign(Expr $expr): Expr
	{
		if ($expr instanceof Assign) {
			return $this->unwrapAssign($expr->expr);
		}

		return $expr;
	}

	/**
	 * @param list<ArrayDimFetch> $dimFetchStack
	 */
	private function isImplicitArrayCreation(array $dimFetchStack, GeneratorScope $scope): TrinaryLogic
	{
		if (count($dimFetchStack) === 0) {
			return TrinaryLogic::createNo();
		}

		$varNode = $dimFetchStack[0]->var;
		if (!$varNode instanceof Variable) {
			return TrinaryLogic::createNo();
		}

		if (!is_string($varNode->name)) {
			return TrinaryLogic::createNo();
		}

		return $scope->hasVariableType($varNode->name)->negate();
	}

	/**
	 * @param list<ArrayDimFetch> $dimFetchStack
	 * @param list<array{Type|null, ArrayDimFetch}> $offsetTypes
	 *
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, array{Type, list<array{Expr, Type}>}>
	 */
	private function produceArrayDimFetchAssignValueToWrite(array $dimFetchStack, array $offsetTypes, Type $offsetValueType, Type $valueToWrite, GeneratorScope $scope, bool $native): Generator
	{
		$originalValueToWrite = $valueToWrite;

		$offsetValueTypeStack = [$offsetValueType];
		foreach (array_slice($offsetTypes, 0, -1) as [$offsetType, $dimFetch]) {
			if ($offsetType === null) {
				$offsetValueType = new ConstantArrayType([], []);

			} else {
				$has = $offsetValueType->hasOffsetValueType($offsetType);
				if ($has->yes()) {
					$offsetValueType = $offsetValueType->getOffsetValueType($offsetType);
				} elseif ($has->maybe()) {
					if (!$scope->hasExpressionType($dimFetch)->yes()) {
						$offsetValueType = TypeCombinator::union($offsetValueType->getOffsetValueType($offsetType), new ConstantArrayType([], []));
					} else {
						$offsetValueType = $offsetValueType->getOffsetValueType($offsetType);
					}
				} else {
					$offsetValueType = new ConstantArrayType([], []);
				}
			}

			$offsetValueTypeStack[] = $offsetValueType;
		}

		foreach (array_reverse($offsetTypes) as $i => [$offsetType]) {
			/** @var Type $offsetValueType */
			$offsetValueType = array_pop($offsetValueTypeStack);
			if (
				!$offsetValueType instanceof MixedType
				&& !$offsetValueType->isConstantArray()->yes()
			) {
				$types = [
					new ArrayType(new MixedType(), new MixedType()),
					new ObjectType(ArrayAccess::class),
					new NullType(),
				];
				if ($offsetType !== null && $offsetType->isInteger()->yes()) {
					$types[] = new StringType();
				}
				$offsetValueType = TypeCombinator::intersect($offsetValueType, TypeCombinator::union(...$types));
			}

			$arrayDimFetch = $dimFetchStack[$i] ?? null;
			if (
				$offsetType !== null
				&& $arrayDimFetch !== null
				&& $scope->hasExpressionType($arrayDimFetch)->yes()
			) {
				$hasOffsetType = null;
				if ($offsetType instanceof ConstantStringType || $offsetType instanceof ConstantIntegerType) {
					$hasOffsetType = new HasOffsetValueType($offsetType, $valueToWrite);
				}
				$valueToWrite = $offsetValueType->setExistingOffsetValueType($offsetType, $valueToWrite);

				if ($valueToWrite->isArray()->yes()) {
					if ($hasOffsetType !== null) {
						$valueToWrite = TypeCombinator::intersect(
							$valueToWrite,
							$hasOffsetType,
						);
					} else {
						$valueToWrite = TypeCombinator::intersect(
							$valueToWrite,
							new NonEmptyArrayType(),
						);
					}
				}

			} else {
				$valueToWrite = $offsetValueType->setOffsetValueType($offsetType, $valueToWrite, $i === 0);
			}

			if ($arrayDimFetch === null || !$offsetValueType->isList()->yes()) {
				continue;
			}

			if (!$arrayDimFetch->dim instanceof Expr\BinaryOp\Plus) {
				continue;
			}

			if ( // keep list for $list[$index + 1] assignments
				$arrayDimFetch->dim->right instanceof Variable
				&& $arrayDimFetch->dim->left instanceof Node\Scalar\Int_
				&& $arrayDimFetch->dim->left->value === 1
				&& $scope->hasExpressionType(new ArrayDimFetch($arrayDimFetch->var, $arrayDimFetch->dim->right))->yes()
			) {
				$valueToWrite = TypeCombinator::intersect($valueToWrite, new AccessoryArrayListType());
			} elseif ( // keep list for $list[1 + $index] assignments
				$arrayDimFetch->dim->left instanceof Variable
				&& $arrayDimFetch->dim->right instanceof Node\Scalar\Int_
				&& $arrayDimFetch->dim->right->value === 1
				&& $scope->hasExpressionType(new ArrayDimFetch($arrayDimFetch->var, $arrayDimFetch->dim->left))->yes()
			) {
				$valueToWrite = TypeCombinator::intersect($valueToWrite, new AccessoryArrayListType());
			}
		}

		$additionalExpressions = [];
		$offsetValueType = $valueToWrite;
		$lastDimKey = array_key_last($dimFetchStack);
		foreach ($dimFetchStack as $key => $dimFetch) {
			if ($dimFetch->dim === null) {
				continue;
			}

			if ($key === $lastDimKey) {
				$offsetValueType = $originalValueToWrite;
			} else {
				$dimExprAnalysisResult = yield new TypeExprRequest($dimFetch->dim);
				$offsetType = $native ? $dimExprAnalysisResult->nativeType : $dimExprAnalysisResult->type;
				$offsetValueType = $offsetValueType->getOffsetValueType($offsetType);
			}

			$additionalExpressions[] = [$dimFetch, $offsetValueType];
		}

		return [$valueToWrite, $additionalExpressions];
	}

	/**
	 * @return InternalThrowPoint[]
	 */
	private function getPropertyAssignThrowPointsFromSetHook(
		GeneratorScope $scope,
		PropertyFetch $propertyFetch,
		PhpPropertyReflection $propertyReflection,
	): array
	{
		return $this->getThrowPointsFromPropertyHook($scope, $propertyFetch, $propertyReflection, 'set');
	}

	/**
	 * @param 'get'|'set' $hookName
	 * @return InternalThrowPoint[]
	 */
	private function getThrowPointsFromPropertyHook(
		GeneratorScope $scope,
		PropertyFetch $propertyFetch,
		PhpPropertyReflection $propertyReflection,
		string $hookName,
	): array
	{
		$scopeFunction = $scope->getFunction();
		if (
			$scopeFunction instanceof PhpMethodFromParserNodeReflection
			&& $scopeFunction->isPropertyHook()
			&& $propertyFetch->var instanceof Variable
			&& $propertyFetch->var->name === 'this'
			&& $propertyFetch->name instanceof Identifier
			&& $propertyFetch->name->toString() === $scopeFunction->getHookedPropertyName()
		) {
			return [];
		}
		$declaringClass = $propertyReflection->getDeclaringClass();
		if (!$propertyReflection->hasHook($hookName)) {
			if (
				$propertyReflection->isPrivate()
				|| $propertyReflection->isFinal()->yes()
				|| $declaringClass->isFinal()
			) {
				return [];
			}

			if ($this->implicitThrows) {
				return [InternalThrowPoint::createImplicit($scope, $propertyFetch)];
			}

			return [];
		}

		$getHook = $propertyReflection->getHook($hookName);
		$throwType = $getHook->getThrowType();

		if ($throwType !== null) {
			if (!$throwType->isVoid()->yes()) {
				return [InternalThrowPoint::createExplicit($scope, $throwType, $propertyFetch, true)];
			}
		} elseif ($this->implicitThrows) {
			return [InternalThrowPoint::createImplicit($scope, $propertyFetch)];
		}

		return [];
	}

}
