<?php 

namespace PHPStan\Analyser\Generator;
return;

use ArrayAccess;
use Fiber;
use Generator;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\PropertyHook;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\ConditionalExpressionHolder;
use PHPStan\Analyser\ConstantResolver;
use PHPStan\Analyser\ExpressionTypeHolder;
use PHPStan\Analyser\NodeCallbackInvoker;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\ScopeContext;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Analyser\UndefinedVariableException;
use PHPStan\DependencyInjection\AutowiredParameter;
use PHPStan\DependencyInjection\GenerateFactory;
use PHPStan\Node\Expr\AlwaysRememberedExpr;
use PHPStan\Node\Expr\IntertwinedVariableByReferenceWithExpr;
use PHPStan\Node\Expr\ParameterVariableOriginalValueExpr;
use PHPStan\Node\Expr\PropertyInitializationExpr;
use PHPStan\Node\IssetExpr;
use PHPStan\Node\Printer\ExprPrinter;
use PHPStan\Parser\Parser;
use PHPStan\Php\PhpVersion;
use PHPStan\Php\PhpVersionFactory;
use PHPStan\Php\PhpVersions;
use PHPStan\Reflection\Assertions;
use PHPStan\Reflection\AttributeReflection;
use PHPStan\Reflection\AttributeReflectionFactory;
use PHPStan\Reflection\ClassConstantReflection;
use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\ExtendedPropertyReflection;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\InitializerExprContext;
use PHPStan\Reflection\InitializerExprTypeResolver;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\Php\PhpFunctionFromParserNodeReflection;
use PHPStan\Reflection\Php\PhpMethodFromParserNodeReflection;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Properties\PropertyReflectionFinder;
use PHPStan\ShouldNotHappenException;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\Accessory\HasOffsetValueType;
use PHPStan\Type\Accessory\NonEmptyArrayType;
use PHPStan\Type\Accessory\OversizedArrayType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BenevolentUnionType;
use PHPStan\Type\ClosureType;
use PHPStan\Type\ConditionalTypeForParameter;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\Constant\ConstantFloatType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ConstantTypeHelper;
use PHPStan\Type\ErrorType;
use PHPStan\Type\GeneralizePrecision;
use PHPStan\Type\Generic\TemplateTypeHelper;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\IntegerRangeType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StaticType;
use PHPStan\Type\StringType;
use PHPStan\Type\ThisType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeTraverser;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\TypeWithClassName;
use PHPStan\Type\UnionType;
use PHPStan\Type\VerbosityLevel;
use PHPStan\Type\VoidType;
use function abs;
use function array_filter;
use function array_key_exists;
use function array_key_first;
use function array_keys;
use function array_map;
use function array_merge;
use function array_pop;
use function array_slice;
use function array_values;
use function count;
use function explode;
use function get_class;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function ltrim;
use function sprintf;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function uksort;
use function usort;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

/**
 * @phpstan-import-type GeneratorTValueType from GeneratorNodeScopeResolver
 * @phpstan-import-type GeneratorTSendType from GeneratorNodeScopeResolver
 */
#[GenerateFactory(interface: InternalGeneratorScopeFactory::class)]
final class GeneratorScope implements Scope, NodeCallbackInvoker
{

	/** @var non-empty-string|null */
	private ?string $namespace;

	/**
	 * @param int|array{min: int, max: int}|null $configPhpVersion
	 * @param array<string, ExpressionTypeHolder> $expressionTypes
	 * @param array<string, ConditionalExpressionHolder[]> $conditionalExpressions
	 * @param list<non-empty-string> $inClosureBindScopeClasses
	 * @param array<string, true> $currentlyAssignedExpressions
	 * @param array<string, true> $currentlyAllowedUndefinedExpressions
	 * @param array<string, ExpressionTypeHolder> $nativeExpressionTypes
	 * @param list<array{MethodReflection|FunctionReflection|null, ParameterReflection|null}> $inFunctionCallsStack
	 */
	public function __construct(
		private InternalGeneratorScopeFactory $scopeFactory,
		private ReflectionProvider $reflectionProvider,
		private InitializerExprTypeResolver $initializerExprTypeResolver,
		private TypeSpecifier $typeSpecifier,
		private ExprPrinter $exprPrinter,
		private PropertyReflectionFinder $propertyReflectionFinder,
		#[AutowiredParameter(ref: '@currentPhpVersionSimpleParser')]
		private Parser $parser,
		private ConstantResolver $constantResolver,
		private ScopeContext $context,
		private PhpVersion $phpVersion,
		private AttributeReflectionFactory $attributeReflectionFactory,
		#[AutowiredParameter(ref: '%phpVersion%')]
		private int|array|null $configPhpVersion,
		private bool $declareStrictTypes = false,
		private PhpFunctionFromParserNodeReflection|null $function = null,
		?string $namespace = null,
		private array $expressionTypes = [],
		private array $nativeExpressionTypes = [],
		private array $conditionalExpressions = [],
		private array $inClosureBindScopeClasses = [],
		private ?ClosureType $anonymousFunctionReflection = null,
		private bool $inFirstLevelStatement = true,
		private array $currentlyAssignedExpressions = [],
		private array $currentlyAllowedUndefinedExpressions = [],
		private array $inFunctionCallsStack = [],
		private bool $afterExtractCall = false,
		private ?Scope $parentScope = null,
		private bool $nativeTypesPromoted = false,
	)
	{
		if ($namespace === '') {
			$namespace = null;
		}

		$this->namespace = $namespace;
	}

	/**
	 * This method is meant to be called for expressions for which the type
	 * should be stored in Scope itself.
	 *
	 * This is meant to be used only by handlers in PHPStan\Analyser\Generator namespace.
	 *
	 * All other code should use `getType()` method.
	 */
	public function getExpressionType(Expr $expr): ?Type
	{
		$exprString = $this->getNodeKey($expr);
		if (array_key_exists($exprString, $this->expressionTypes)) {
			return $this->expressionTypes[$exprString]->getType();
		}

		return null;
	}

	public function getNativeExpressionType(Expr $expr): ?Type
	{
		$exprString = $this->getNodeKey($expr);
		if (array_key_exists($exprString, $this->nativeExpressionTypes)) {
			return $this->nativeExpressionTypes[$exprString]->getType();
		}

		return null;
	}

	/** @api */
	public function hasExpressionType(Expr $node): TrinaryLogic
	{
		if ($node instanceof Variable && is_string($node->name)) {
			return $this->hasVariableType($node->name);
		}

		$exprString = $this->getNodeKey($node);
		if (!isset($this->expressionTypes[$exprString])) {
			return TrinaryLogic::createNo();
		}
		return $this->expressionTypes[$exprString]->getCertainty();
	}

	private function getNodeKey(Expr $node): string
	{
		return $this->exprPrinter->printExpr($node);
	}

	/**
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, self>
	 */
	public function assignVariable(string $variableName, Type $type, Type $nativeType, TrinaryLogic $certainty): Generator
	{
		$node = new Variable($variableName);
		$assignExprGen = $this->assignExpression($node, $type, $nativeType);
		yield from $assignExprGen;
		$scope = $assignExprGen->getReturn();
		if ($certainty->no()) {
			throw new ShouldNotHappenException();
		} elseif (!$certainty->yes()) {
			$exprString = '$' . $variableName;
			$scope->expressionTypes[$exprString] = new ExpressionTypeHolder($node, $type, $certainty);
			$scope->nativeExpressionTypes[$exprString] = new ExpressionTypeHolder($node, $nativeType, $certainty);
		}

		foreach ($scope->expressionTypes as $expressionType) {
			$intertwinedExpr = $expressionType->getExpr();
			if (!$intertwinedExpr instanceof IntertwinedVariableByReferenceWithExpr) {
				continue;
			}
			if (!$expressionType->getCertainty()->yes()) {
				continue;
			}
			if ($intertwinedExpr->getVariableName() !== $variableName) {
				continue;
			}

			$dependentExpr = $intertwinedExpr->getExpr();
			$has = $scope->hasExpressionType($dependentExpr);
			$assignedExpr = $intertwinedExpr->getAssignedExpr();
			$assignedExprType = $scope->getExpressionType($assignedExpr);
			$assignedExprNativeType = $scope->getNativeExpressionType($assignedExpr);
			if (
				$assignedExprType === null
				|| $assignedExprNativeType === null
			) {
				continue;
			}

			if (
				$dependentExpr instanceof Variable
				&& is_string($dependentExpr->name)
				&& !$has->no()
			) {
				$assignVarGen = $scope->assignVariable(
					$dependentExpr->name,
					$assignedExprType,
					$assignedExprNativeType,
					$has,
				);
				yield from $assignVarGen;
				$scope = $assignVarGen->getReturn();
			} else {
				$assignExprGen = $scope->assignExpression(
					$dependentExpr,
					$assignedExprType,
					$assignedExprNativeType,
				);
				yield from $assignExprGen;
				$scope = $assignExprGen->getReturn();
			}

		}

		return $scope;
	}

	/**
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, self>
	 */
	public function assignExpression(Expr $expr, Type $type, Type $nativeType): Generator
	{
		$scope = $this;
		if ($expr instanceof PropertyFetch) {
			$scope = $this->invalidateExpression($expr)
				->invalidateMethodsOnExpression($expr->var);
		} elseif ($expr instanceof Expr\StaticPropertyFetch) {
			$scope = $this->invalidateExpression($expr);
		} elseif ($expr instanceof Variable) {
			$scope = $this->invalidateExpression($expr);
		}

		$specExprTypeGen = $scope->specifyExpressionType($expr, $type, $nativeType, TrinaryLogic::createYes());
		yield from $specExprTypeGen;
		return $specExprTypeGen->getReturn();
	}

	/**
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, self>
	 */
	private function specifyExpressionType(Expr $expr, Type $type, Type $nativeType, TrinaryLogic $certainty): Generator
	{
		if ($expr instanceof ConstFetch) {
			$loweredConstName = strtolower($expr->name->toString());
			if (in_array($loweredConstName, ['true', 'false', 'null'], true)) {
				return $this;
			}
		}

		if ($expr instanceof FuncCall && $expr->name instanceof Name && $type->isFalse()->yes()) {
			$functionName = $this->reflectionProvider->resolveFunctionName($expr->name, $this);
			if ($functionName !== null && in_array(strtolower($functionName), [
				'is_dir',
				'is_file',
				'file_exists',
			], true)) {
				return $this;
			}
		}

		$scope = $this;
		if (
			$expr instanceof Expr\ArrayDimFetch
			&& $expr->dim !== null
			&& !$expr->dim instanceof Expr\PreInc
			&& !$expr->dim instanceof Expr\PreDec
			&& !$expr->dim instanceof Expr\PostDec
			&& !$expr->dim instanceof Expr\PostInc
		) {
			$dimType = (yield new TypeExprRequest($expr->dim))->type->toArrayKey();
			if ($dimType->isInteger()->yes() || $dimType->isString()->yes()) {
				$exprVarResult = yield new TypeExprRequest($expr->var);
				$exprVarType = $exprVarResult->type;
				if (!$exprVarType instanceof MixedType && !$exprVarType->isArray()->no()) {
					$types = [
						new ArrayType(new MixedType(), new MixedType()),
						new ObjectType(ArrayAccess::class),
						new NullType(),
					];
					if ($dimType->isInteger()->yes()) {
						$types[] = new StringType();
					}
					$offsetValueType = TypeCombinator::intersect($exprVarType, TypeCombinator::union(...$types));

					if ($dimType instanceof ConstantIntegerType || $dimType instanceof ConstantStringType) {
						$offsetValueType = TypeCombinator::intersect(
							$offsetValueType,
							new HasOffsetValueType($dimType, $type),
						);
					}

					$specifyExprGen = $scope->specifyExpressionType(
						$expr->var,
						$offsetValueType,
						$exprVarResult->nativeType,
						$certainty,
					);
					yield from $specifyExprGen;
					$scope = $specifyExprGen->getReturn();

				}
			}
		}

		if ($certainty->no()) {
			throw new ShouldNotHappenException();
		}

		$exprString = $this->getNodeKey($expr);
		$expressionTypes = $scope->expressionTypes;
		$expressionTypes[$exprString] = new ExpressionTypeHolder($expr, $type, $certainty);
		$nativeTypes = $scope->nativeExpressionTypes;
		$nativeTypes[$exprString] = new ExpressionTypeHolder($expr, $nativeType, $certainty);

		$scope = $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$expressionTypes,
			$nativeTypes,
			$this->conditionalExpressions,
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
			$this->inFirstLevelStatement,
			$this->currentlyAssignedExpressions,
			$this->currentlyAllowedUndefinedExpressions,
			$this->inFunctionCallsStack,
			$this->afterExtractCall,
			$this->parentScope,
			$this->nativeTypesPromoted,
		);

		if ($expr instanceof AlwaysRememberedExpr) {
			$alwaysExprGen = $scope->specifyExpressionType($expr->expr, $type, $nativeType, $certainty);
			yield from $alwaysExprGen;
			return $alwaysExprGen->getReturn();
		}

		return $scope;
	}

	private function invalidateMethodsOnExpression(Expr $expressionToInvalidate): self
	{
		$exprStringToInvalidate = $this->getNodeKey($expressionToInvalidate);
		$expressionTypes = $this->expressionTypes;
		$nativeExpressionTypes = $this->nativeExpressionTypes;
		$invalidated = false;
		$nodeFinder = new NodeFinder();
		foreach ($expressionTypes as $exprString => $exprTypeHolder) {
			$expr = $exprTypeHolder->getExpr();
			$found = $nodeFinder->findFirst([$expr], function (Node $node) use ($exprStringToInvalidate): bool {
				if (!$node instanceof MethodCall) {
					return false;
				}

				return $this->getNodeKey($node->var) === $exprStringToInvalidate;
			});
			if ($found === null) {
				continue;
			}

			unset($expressionTypes[$exprString]);
			unset($nativeExpressionTypes[$exprString]);
			$invalidated = true;
		}

		if (!$invalidated) {
			return $this;
		}

		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$expressionTypes,
			$nativeExpressionTypes,
			$this->conditionalExpressions,
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
			$this->inFirstLevelStatement,
			$this->currentlyAssignedExpressions,
			$this->currentlyAllowedUndefinedExpressions,
			[],
			$this->afterExtractCall,
			$this->parentScope,
			$this->nativeTypesPromoted,
		);
	}

	/**
	 * @deprecated
	 */
	private function assignExpressionInternal(Expr $expr, Type $type, Type $nativeType): self
	{
		$scope = $this;
		if ($expr instanceof PropertyFetch) {
			$scope = $this->invalidateExpression($expr)
				->invalidateMethodsOnExpression($expr->var);
		} elseif ($expr instanceof Expr\StaticPropertyFetch) {
			$scope = $this->invalidateExpression($expr);
		} elseif ($expr instanceof Variable) {
			$scope = $this->invalidateExpression($expr);
		}

		return $scope->specifyExpressionTypeInternal($expr, $type, $nativeType, TrinaryLogic::createYes());
	}

	public function assignInitializedProperty(Type $fetchedOnType, string $propertyName): self
	{
		if (!$this->isInClass()) {
			return $this;
		}

		if (TypeUtils::findThisType($fetchedOnType) === null) {
			return $this;
		}

		$propertyReflection = $this->getInstancePropertyReflection($fetchedOnType, $propertyName);
		if ($propertyReflection === null) {
			return $this;
		}
		$declaringClass = $propertyReflection->getDeclaringClass();
		if ($this->getClassReflection()->getName() !== $declaringClass->getName()) {
			return $this;
		}
		if (!$declaringClass->hasNativeProperty($propertyName)) {
			return $this;
		}

		$expr = new PropertyInitializationExpr($propertyName);
		$exprHolder = ExpressionTypeHolder::createYes($expr, new MixedType());
		$exprString = $this->getNodeKey($expr);
		$expressionTypes = $this->expressionTypes;
		$expressionTypes[$exprString] = $exprHolder;
		$nativeTypes = $this->nativeExpressionTypes;
		$nativeTypes[$exprString] = $exprHolder;

		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$expressionTypes,
			$nativeTypes,
			$this->conditionalExpressions,
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
			$this->inFirstLevelStatement,
			$this->currentlyAssignedExpressions,
			$this->currentlyAllowedUndefinedExpressions,
			$this->inFunctionCallsStack,
			$this->afterExtractCall,
			$this->parentScope,
			$this->nativeTypesPromoted,
		);
	}

	public function invalidateExpression(Expr $expressionToInvalidate, bool $requireMoreCharacters = false): self
	{
		$expressionTypes = $this->expressionTypes;
		$nativeExpressionTypes = $this->nativeExpressionTypes;
		$invalidated = false;
		$exprStringToInvalidate = $this->getNodeKey($expressionToInvalidate);

		foreach ($expressionTypes as $exprString => $exprTypeHolder) {
			$exprExpr = $exprTypeHolder->getExpr();
			if (!$this->shouldInvalidateExpression($exprStringToInvalidate, $expressionToInvalidate, $exprExpr, $requireMoreCharacters)) {
				continue;
			}

			unset($expressionTypes[$exprString]);
			unset($nativeExpressionTypes[$exprString]);
			$invalidated = true;
		}

		$newConditionalExpressions = [];
		foreach ($this->conditionalExpressions as $conditionalExprString => $holders) {
			if (count($holders) === 0) {
				continue;
			}
			if ($this->shouldInvalidateExpression($exprStringToInvalidate, $expressionToInvalidate, $holders[array_key_first($holders)]->getTypeHolder()->getExpr())) {
				$invalidated = true;
				continue;
			}
			foreach ($holders as $holder) {
				$conditionalTypeHolders = $holder->getConditionExpressionTypeHolders();
				foreach ($conditionalTypeHolders as $conditionalTypeHolder) {
					if ($this->shouldInvalidateExpression($exprStringToInvalidate, $expressionToInvalidate, $conditionalTypeHolder->getExpr())) {
						$invalidated = true;
						continue 3;
					}
				}
			}
			$newConditionalExpressions[$conditionalExprString] = $holders;
		}

		if (!$invalidated) {
			return $this;
		}

		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$expressionTypes,
			$nativeExpressionTypes,
			$newConditionalExpressions,
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
			$this->inFirstLevelStatement,
			$this->currentlyAssignedExpressions,
			$this->currentlyAllowedUndefinedExpressions,
			[],
			$this->afterExtractCall,
			$this->parentScope,
			$this->nativeTypesPromoted,
		);
	}

	private function shouldInvalidateExpression(string $exprStringToInvalidate, Expr $exprToInvalidate, Expr $expr, bool $requireMoreCharacters = false): bool
	{
		if ($requireMoreCharacters && $exprStringToInvalidate === $this->getNodeKey($expr)) {
			return false;
		}

		// Variables will not contain traversable expressions. skip the NodeFinder overhead
		if ($expr instanceof Variable && is_string($expr->name) && !$requireMoreCharacters) {
			return $exprStringToInvalidate === $this->getNodeKey($expr);
		}

		$nodeFinder = new NodeFinder();
		$expressionToInvalidateClass = get_class($exprToInvalidate);
		$found = $nodeFinder->findFirst([$expr], function (Node $node) use ($expressionToInvalidateClass, $exprStringToInvalidate): bool {
			if (
				$exprStringToInvalidate === '$this'
				&& $node instanceof Name
				&& (
					in_array($node->toLowerString(), ['self', 'static', 'parent'], true)
					|| ($this->getClassReflection() !== null && $this->getClassReflection()->is($this->resolveName($node)))
				)
			) {
				return true;
			}

			if (!$node instanceof $expressionToInvalidateClass) {
				return false;
			}

			$nodeString = $this->getNodeKey($node);

			return $nodeString === $exprStringToInvalidate;
		});

		if ($found === null) {
			return false;
		}

		if (
			$expr instanceof PropertyFetch
			&& $requireMoreCharacters
			&& $this->isReadonlyPropertyFetch($expr, false)
		) {
			return false;
		}

		return true;
	}

	private function isReadonlyPropertyFetch(PropertyFetch $expr, bool $allowOnlyOnThis): bool
	{
		if (!$this->phpVersion->supportsReadOnlyProperties()) {
			return false;
		}

		while ($expr instanceof PropertyFetch) {
			if ($expr->var instanceof Variable) {
				if (
					$allowOnlyOnThis
					&& (
						! $expr->name instanceof Node\Identifier
						|| !is_string($expr->var->name)
						|| $expr->var->name !== 'this'
					)
				) {
					return false;
				}
			} elseif (!$expr->var instanceof PropertyFetch) {
				return false;
			}

			$propertyReflection = $this->propertyReflectionFinder->findPropertyReflectionFromNode($expr, $this);
			if ($propertyReflection === null) {
				return false;
			}

			$nativePropertyReflection = $propertyReflection->getNativeReflection();
			if ($nativePropertyReflection === null || !$nativePropertyReflection->isReadOnly()) {
				return false;
			}

			$expr = $expr->var;
		}

		return true;
	}

	/**
	 * @param ConditionalExpressionHolder[] $conditionalExpressionHolders
	 */
	public function addConditionalExpressions(string $exprString, array $conditionalExpressionHolders): self
	{
		$conditionalExpressions = $this->conditionalExpressions;
		$conditionalExpressions[$exprString] = $conditionalExpressionHolders;
		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$this->expressionTypes,
			$this->nativeExpressionTypes,
			$conditionalExpressions,
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
			$this->inFirstLevelStatement,
			$this->currentlyAssignedExpressions,
			$this->currentlyAllowedUndefinedExpressions,
			$this->inFunctionCallsStack,
			$this->afterExtractCall,
			$this->parentScope,
			$this->nativeTypesPromoted,
		);
	}

	/** @api */
	public function enterNamespace(string $namespaceName): self
	{
		return $this->scopeFactory->create(
			$this->context->beginFile(),
			$this->isDeclareStrictTypes(),
			null,
			$namespaceName,
		);
	}

	/** @api */
	public function isInClass(): bool
	{
		return $this->context->getClassReflection() !== null;
	}

	/** @api */
	public function getClassReflection(): ?ClassReflection
	{
		return $this->context->getClassReflection();
	}

	/** @api */
	public function enterClass(ClassReflection $classReflection): self
	{
		$thisHolder = ExpressionTypeHolder::createYes(new Variable('this'), new ThisType($classReflection));
		$constantTypes = $this->getConstantTypes();
		$constantTypes['$this'] = $thisHolder;
		$nativeConstantTypes = $this->getNativeConstantTypes();
		$nativeConstantTypes['$this'] = $thisHolder;

		return $this->scopeFactory->create(
			$this->context->enterClass($classReflection),
			$this->isDeclareStrictTypes(),
			null,
			$this->getNamespace(),
			$constantTypes,
			$nativeConstantTypes,
			[],
			[],
			null,
			true,
			[],
			[],
			[],
			false,
			$classReflection->isAnonymous() ? $this : null,
		);
	}

	public function enterTrait(ClassReflection $traitReflection): self
	{
		$namespace = null;
		$traitName = $traitReflection->getName();
		$traitNameParts = explode('\\', $traitName);
		if (count($traitNameParts) > 1) {
			$namespace = implode('\\', array_slice($traitNameParts, 0, -1));
		}
		return $this->scopeFactory->create(
			$this->context->enterTrait($traitReflection),
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$namespace,
			$this->expressionTypes,
			$this->nativeExpressionTypes,
			[],
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
		);
	}

	/**
	 * @api
	 * @deprecated Use canReadProperty() or canWriteProperty()
	 */
	public function canAccessProperty(PropertyReflection $propertyReflection): bool
	{
		return $this->canAccessClassMember($propertyReflection);
	}

	/** @api */
	public function canReadProperty(ExtendedPropertyReflection $propertyReflection): bool
	{
		return $this->canAccessClassMember($propertyReflection);
	}

	/** @api */
	public function canWriteProperty(ExtendedPropertyReflection $propertyReflection): bool
	{
		if (!$propertyReflection->isPrivateSet() && !$propertyReflection->isProtectedSet()) {
			return $this->canAccessClassMember($propertyReflection);
		}

		if (!$this->phpVersion->supportsAsymmetricVisibility()) {
			return $this->canAccessClassMember($propertyReflection);
		}

		$propertyDeclaringClass = $propertyReflection->getDeclaringClass();
		$canAccessClassMember = static function (ClassReflection $classReflection) use ($propertyReflection, $propertyDeclaringClass) {
			if ($propertyReflection->isPrivateSet()) {
				return $classReflection->getName() === $propertyDeclaringClass->getName();
			}

			// protected set

			if (
				$classReflection->getName() === $propertyDeclaringClass->getName()
				|| $classReflection->isSubclassOfClass($propertyDeclaringClass->removeFinalKeywordOverride())
			) {
				return true;
			}

			return $propertyReflection->getDeclaringClass()->isSubclassOfClass($classReflection);
		};

		foreach ($this->inClosureBindScopeClasses as $inClosureBindScopeClass) {
			if (!$this->reflectionProvider->hasClass($inClosureBindScopeClass)) {
				continue;
			}

			if ($canAccessClassMember($this->reflectionProvider->getClass($inClosureBindScopeClass))) {
				return true;
			}
		}

		if ($this->isInClass()) {
			return $canAccessClassMember($this->getClassReflection());
		}

		return false;
	}

	/** @api */
	public function canCallMethod(MethodReflection $methodReflection): bool
	{
		if ($this->canAccessClassMember($methodReflection)) {
			return true;
		}

		return $this->canAccessClassMember($methodReflection->getPrototype());
	}

	/** @api */
	public function canAccessConstant(ClassConstantReflection $constantReflection): bool
	{
		return $this->canAccessClassMember($constantReflection);
	}

	private function canAccessClassMember(ClassMemberReflection $classMemberReflection): bool
	{
		if ($classMemberReflection->isPublic()) {
			return true;
		}

		$classMemberDeclaringClass = $classMemberReflection->getDeclaringClass();
		$canAccessClassMember = static function (ClassReflection $classReflection) use ($classMemberReflection, $classMemberDeclaringClass) {
			if ($classMemberReflection->isPrivate()) {
				return $classReflection->getName() === $classMemberDeclaringClass->getName();
			}

			// protected

			if (
				$classReflection->getName() === $classMemberDeclaringClass->getName()
				|| $classReflection->isSubclassOfClass($classMemberDeclaringClass->removeFinalKeywordOverride())
			) {
				return true;
			}

			return $classMemberReflection->getDeclaringClass()->isSubclassOfClass($classReflection);
		};

		foreach ($this->inClosureBindScopeClasses as $inClosureBindScopeClass) {
			if (!$this->reflectionProvider->hasClass($inClosureBindScopeClass)) {
				continue;
			}

			if ($canAccessClassMember($this->reflectionProvider->getClass($inClosureBindScopeClass))) {
				return true;
			}
		}

		if ($this->isInClass()) {
			return $canAccessClassMember($this->getClassReflection());
		}

		return false;
	}

	public function filterTypeWithMethod(Type $typeWithMethod, string $methodName): ?Type
	{
		if ($typeWithMethod instanceof UnionType) {
			$typeWithMethod = $typeWithMethod->filterTypes(static fn (Type $innerType) => $innerType->hasMethod($methodName)->yes());
		}

		if (!$typeWithMethod->hasMethod($methodName)->yes()) {
			return null;
		}

		return $typeWithMethod;
	}

	/** @api */
	public function getMethodReflection(Type $typeWithMethod, string $methodName): ?ExtendedMethodReflection
	{
		$type = $this->filterTypeWithMethod($typeWithMethod, $methodName);
		if ($type === null) {
			return null;
		}

		return $type->getMethod($methodName, $this);
	}

	public function getNakedMethod(Type $typeWithMethod, string $methodName): ?ExtendedMethodReflection
	{
		$type = $this->filterTypeWithMethod($typeWithMethod, $methodName);
		if ($type === null) {
			return null;
		}

		return $type->getUnresolvedMethodPrototype($methodName, $this)->getNakedMethod();
	}

	public function getIterableKeyType(Type $iteratee): Type
	{
		if ($iteratee instanceof UnionType) {
			$filtered = $iteratee->filterTypes(static fn (Type $innerType) => $innerType->isIterable()->yes());
			if (!$filtered instanceof NeverType) {
				$iteratee = $filtered;
			}
		}

		return $iteratee->getIterableKeyType();
	}

	public function getIterableValueType(Type $iteratee): Type
	{
		if ($iteratee instanceof UnionType) {
			$filtered = $iteratee->filterTypes(static fn (Type $innerType) => $innerType->isIterable()->yes());
			if (!$filtered instanceof NeverType) {
				$iteratee = $filtered;
			}
		}

		return $iteratee->getIterableValueType();
	}

	/**
	 * @return string[]
	 */
	public function debug(): array
	{
		$descriptions = [];
		foreach ($this->expressionTypes as $name => $variableTypeHolder) {
			$key = sprintf('%s (%s)', $name, $variableTypeHolder->getCertainty()->describe());
			$descriptions[$key] = $variableTypeHolder->getType()->describe(VerbosityLevel::precise());
		}
		foreach ($this->nativeExpressionTypes as $exprString => $nativeTypeHolder) {
			$key = sprintf('native %s (%s)', $exprString, $nativeTypeHolder->getCertainty()->describe());
			$descriptions[$key] = $nativeTypeHolder->getType()->describe(VerbosityLevel::precise());
		}

		foreach ($this->conditionalExpressions as $exprString => $holders) {
			foreach (array_values($holders) as $i => $holder) {
				$key = sprintf('condition about %s #%d', $exprString, $i + 1);
				$parts = [];
				foreach ($holder->getConditionExpressionTypeHolders() as $conditionalExprString => $expressionTypeHolder) {
					$parts[] = $conditionalExprString . '=' . $expressionTypeHolder->getType()->describe(VerbosityLevel::precise());
				}
				$condition = implode(' && ', $parts);
				$descriptions[$key] = sprintf(
					'if %s then %s is %s (%s)',
					$condition,
					$exprString,
					$holder->getTypeHolder()->getType()->describe(VerbosityLevel::precise()),
					$holder->getTypeHolder()->getCertainty()->describe(),
				);
			}
		}

		return $descriptions;
	}

	/** @api */
	public function getNamespace(): ?string
	{
		return $this->namespace;
	}

	/** @api */
	public function getFile(): string
	{
		return $this->context->getFile();
	}

	public function getFileDescription(): string
	{
		if ($this->context->getTraitReflection() === null) {
			return $this->getFile();
		}

		/** @var ClassReflection $classReflection */
		$classReflection = $this->context->getClassReflection();

		$className = $classReflection->getDisplayName();
		if (!$classReflection->isAnonymous()) {
			$className = sprintf('class %s', $className);
		}

		$traitReflection = $this->context->getTraitReflection();
		if ($traitReflection->getFileName() === null) {
			throw new ShouldNotHappenException();
		}

		return sprintf(
			'%s (in context of %s)',
			$traitReflection->getFileName(),
			$className,
		);
	}

	/** @api */
	public function isDeclareStrictTypes(): bool
	{
		return $this->declareStrictTypes;
	}

	public function enterDeclareStrictTypes(): self
	{
		return $this->scopeFactory->create(
			$this->context,
			true,
			null,
			null,
			$this->expressionTypes,
			$this->nativeExpressionTypes,
		);
	}

	/** @api */
	public function isInTrait(): bool
	{
		return $this->context->getTraitReflection() !== null;
	}

	/** @api */
	public function getTraitReflection(): ?ClassReflection
	{
		return $this->context->getTraitReflection();
	}

	/** @api */
	public function getFunction(): ?PhpFunctionFromParserNodeReflection
	{
		return $this->function;
	}

	/** @api */
	public function getFunctionName(): ?string
	{
		return $this->function !== null ? $this->function->getName() : null;
	}

	/**
	 * @api
	 * @param Type[] $phpDocParameterTypes
	 * @param Type[] $parameterOutTypes
	 * @param array<string, bool> $immediatelyInvokedCallableParameters
	 * @param array<string, Type> $phpDocClosureThisTypeParameters
	 */
	public function enterFunction(
		Node\Stmt\Function_ $function,
		TemplateTypeMap $templateTypeMap,
		array $phpDocParameterTypes,
		?Type $phpDocReturnType,
		?Type $throwType,
		?string $deprecatedDescription,
		bool $isDeprecated,
		bool $isInternal,
		?bool $isPure = null,
		bool $acceptsNamedArguments = true,
		?Assertions $asserts = null,
		?string $phpDocComment = null,
		array $parameterOutTypes = [],
		array $immediatelyInvokedCallableParameters = [],
		array $phpDocClosureThisTypeParameters = [],
	): self
	{
		return $this->enterFunctionLike(
			new PhpFunctionFromParserNodeReflection(
				$function,
				$this->getFile(),
				$templateTypeMap,
				$this->getRealParameterTypes($function),
				array_map(static fn (Type $type): Type => TemplateTypeHelper::toArgument($type), $phpDocParameterTypes),
				$this->getRealParameterDefaultValues($function),
				$this->getParameterAttributes($function),
				$this->getFunctionType($function->returnType, $function->returnType === null, false),
				$phpDocReturnType !== null ? TemplateTypeHelper::toArgument($phpDocReturnType) : null,
				$throwType,
				$deprecatedDescription,
				$isDeprecated,
				$isInternal,
				$isPure,
				$acceptsNamedArguments,
				$asserts ?? Assertions::createEmpty(),
				$phpDocComment,
				array_map(static fn (Type $type): Type => TemplateTypeHelper::toArgument($type), $parameterOutTypes),
				$immediatelyInvokedCallableParameters,
				$phpDocClosureThisTypeParameters,
				$this->attributeReflectionFactory->fromAttrGroups($function->attrGroups, InitializerExprContext::fromStubParameter(null, $this->getFile(), $function)),
			),
			false,
		);
	}

	private function enterFunctionLike(
		PhpFunctionFromParserNodeReflection $functionReflection,
		bool $preserveConstructorScope,
	): self
	{
		$parametersByName = [];

		foreach ($functionReflection->getParameters() as $parameter) {
			$parametersByName[$parameter->getName()] = $parameter;
		}

		$expressionTypes = [];
		$nativeExpressionTypes = [];
		$conditionalTypes = [];

		if ($preserveConstructorScope) {
			$expressionTypes = $this->rememberConstructorExpressions($this->expressionTypes);
			$nativeExpressionTypes = $this->rememberConstructorExpressions($this->nativeExpressionTypes);
		}

		foreach ($functionReflection->getParameters() as $parameter) {
			$parameterType = $parameter->getType();

			if ($parameterType instanceof ConditionalTypeForParameter) {
				$targetParameterName = substr($parameterType->getParameterName(), 1);
				if (array_key_exists($targetParameterName, $parametersByName)) {
					$targetParameter = $parametersByName[$targetParameterName];

					$ifType = $parameterType->isNegated() ? $parameterType->getElse() : $parameterType->getIf();
					$elseType = $parameterType->isNegated() ? $parameterType->getIf() : $parameterType->getElse();

					$holder = new ConditionalExpressionHolder([
						$parameterType->getParameterName() => ExpressionTypeHolder::createYes(new Variable($targetParameterName), TypeCombinator::intersect($targetParameter->getType(), $parameterType->getTarget())),
					], new ExpressionTypeHolder(new Variable($parameter->getName()), $ifType, TrinaryLogic::createYes()));
					$conditionalTypes['$' . $parameter->getName()][$holder->getKey()] = $holder;

					$holder = new ConditionalExpressionHolder([
						$parameterType->getParameterName() => ExpressionTypeHolder::createYes(new Variable($targetParameterName), TypeCombinator::remove($targetParameter->getType(), $parameterType->getTarget())),
					], new ExpressionTypeHolder(new Variable($parameter->getName()), $elseType, TrinaryLogic::createYes()));
					$conditionalTypes['$' . $parameter->getName()][$holder->getKey()] = $holder;
				}
			}

			$paramExprString = '$' . $parameter->getName();
			if ($parameter->isVariadic()) {
				if (!$this->getPhpVersion()->supportsNamedArguments()->no() && $functionReflection->acceptsNamedArguments()->yes()) {
					$parameterType = new ArrayType(new UnionType([new IntegerType(), new StringType()]), $parameterType);
				} else {
					$parameterType = TypeCombinator::intersect(new ArrayType(new IntegerType(), $parameterType), new AccessoryArrayListType());
				}
			}
			$parameterNode = new Variable($parameter->getName());
			$expressionTypes[$paramExprString] = ExpressionTypeHolder::createYes($parameterNode, $parameterType);

			$parameterOriginalValueExpr = new ParameterVariableOriginalValueExpr($parameter->getName());
			$parameterOriginalValueExprString = $this->getNodeKey($parameterOriginalValueExpr);
			$expressionTypes[$parameterOriginalValueExprString] = ExpressionTypeHolder::createYes($parameterOriginalValueExpr, $parameterType);

			$nativeParameterType = $parameter->getNativeType();
			if ($parameter->isVariadic()) {
				if (!$this->getPhpVersion()->supportsNamedArguments()->no() && $functionReflection->acceptsNamedArguments()->yes()) {
					$nativeParameterType = new ArrayType(new UnionType([new IntegerType(), new StringType()]), $nativeParameterType);
				} else {
					$nativeParameterType = TypeCombinator::intersect(new ArrayType(new IntegerType(), $nativeParameterType), new AccessoryArrayListType());
				}
			}
			$nativeExpressionTypes[$paramExprString] = ExpressionTypeHolder::createYes($parameterNode, $nativeParameterType);
			$nativeExpressionTypes[$parameterOriginalValueExprString] = ExpressionTypeHolder::createYes($parameterOriginalValueExpr, $nativeParameterType);
		}

		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$functionReflection,
			$this->getNamespace(),
			array_merge($this->getConstantTypes(), $expressionTypes),
			array_merge($this->getNativeConstantTypes(), $nativeExpressionTypes),
			$conditionalTypes,
		);
	}

	/**
	 * @param array<string, ExpressionTypeHolder> $currentExpressionTypes
	 * @return array<string, ExpressionTypeHolder>
	 */
	private function rememberConstructorExpressions(array $currentExpressionTypes): array
	{
		$expressionTypes = [];
		foreach ($currentExpressionTypes as $exprString => $expressionTypeHolder) {
			$expr = $expressionTypeHolder->getExpr();
			if ($expr instanceof FuncCall) {
				if (
					!$expr->name instanceof Name
					|| !in_array($expr->name->name, ['class_exists', 'function_exists'], true)
				) {
					continue;
				}
			} elseif ($expr instanceof PropertyFetch) {
				if (!$this->isReadonlyPropertyFetch($expr, true)) {
					continue;
				}
			} elseif (!$expr instanceof ConstFetch && !$expr instanceof PropertyInitializationExpr) {
				continue;
			}

			$expressionTypes[$exprString] = $expressionTypeHolder;
		}

		if (array_key_exists('$this', $currentExpressionTypes)) {
			$expressionTypes['$this'] = $currentExpressionTypes['$this'];
		}

		return $expressionTypes;
	}

	public function rememberConstructorScope(): self
	{
		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			null,
			$this->getNamespace(),
			$this->rememberConstructorExpressions($this->expressionTypes),
			$this->rememberConstructorExpressions($this->nativeExpressionTypes),
			$this->conditionalExpressions,
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
			$this->inFirstLevelStatement,
			[],
			[],
			$this->inFunctionCallsStack,
			$this->afterExtractCall,
			$this->parentScope,
			$this->nativeTypesPromoted,
		);
	}

	/**
	 * @api
	 * @param Type[] $phpDocParameterTypes
	 * @param Type[] $parameterOutTypes
	 * @param array<string, bool> $immediatelyInvokedCallableParameters
	 * @param array<string, Type> $phpDocClosureThisTypeParameters
	 */
	public function enterClassMethod(
		Node\Stmt\ClassMethod $classMethod,
		TemplateTypeMap $templateTypeMap,
		array $phpDocParameterTypes,
		?Type $phpDocReturnType,
		?Type $throwType,
		?string $deprecatedDescription,
		bool $isDeprecated,
		bool $isInternal,
		bool $isFinal,
		?bool $isPure = null,
		bool $acceptsNamedArguments = true,
		?Assertions $asserts = null,
		?Type $selfOutType = null,
		?string $phpDocComment = null,
		array $parameterOutTypes = [],
		array $immediatelyInvokedCallableParameters = [],
		array $phpDocClosureThisTypeParameters = [],
		bool $isConstructor = false,
	): self
	{
		if (!$this->isInClass()) {
			throw new ShouldNotHappenException();
		}

		return $this->enterFunctionLike(
			new PhpMethodFromParserNodeReflection(
				$this->getClassReflection(),
				$classMethod,
				null,
				$this->getFile(),
				$templateTypeMap,
				$this->getRealParameterTypes($classMethod),
				array_map(fn (Type $type): Type => $this->transformStaticType(TemplateTypeHelper::toArgument($type)), $phpDocParameterTypes),
				$this->getRealParameterDefaultValues($classMethod),
				$this->getParameterAttributes($classMethod),
				$this->transformStaticType($this->getFunctionType($classMethod->returnType, false, false)),
				$phpDocReturnType !== null ? $this->transformStaticType(TemplateTypeHelper::toArgument($phpDocReturnType)) : null,
				$throwType !== null ? $this->transformStaticType(TemplateTypeHelper::toArgument($throwType)) : null,
				$deprecatedDescription,
				$isDeprecated,
				$isInternal,
				$isFinal,
				$isPure,
				$acceptsNamedArguments,
				$asserts ?? Assertions::createEmpty(),
				$selfOutType,
				$phpDocComment,
				array_map(fn (Type $type): Type => $this->transformStaticType(TemplateTypeHelper::toArgument($type)), $parameterOutTypes),
				$immediatelyInvokedCallableParameters,
				array_map(fn (Type $type): Type => $this->transformStaticType(TemplateTypeHelper::toArgument($type)), $phpDocClosureThisTypeParameters),
				$isConstructor,
				$this->attributeReflectionFactory->fromAttrGroups($classMethod->attrGroups, InitializerExprContext::fromStubParameter($this->getClassReflection()->getName(), $this->getFile(), $classMethod)),
			),
			!$classMethod->isStatic(),
		);
	}

	/**
	 * @param Type[] $phpDocParameterTypes
	 */
	public function enterPropertyHook(
		Node\PropertyHook $hook,
		string $propertyName,
		Identifier|Name|ComplexType|null $nativePropertyTypeNode,
		?Type $phpDocPropertyType,
		array $phpDocParameterTypes,
		?Type $throwType,
		?string $deprecatedDescription,
		bool $isDeprecated,
		?string $phpDocComment,
	): self
	{
		if (!$this->isInClass()) {
			throw new ShouldNotHappenException();
		}

		$phpDocParameterTypes = array_map(fn (Type $type): Type => $this->transformStaticType(TemplateTypeHelper::toArgument($type)), $phpDocParameterTypes);

		$hookName = $hook->name->toLowerString();
		if ($hookName === 'set') {
			if ($hook->params === []) {
				$hook = clone $hook;
				$hook->params = [
					new Node\Param(new Variable('value'), type: $nativePropertyTypeNode),
				];
			}

			$firstParam = $hook->params[0] ?? null;
			if (
				$firstParam !== null
				&& $phpDocPropertyType !== null
				&& $firstParam->var instanceof Variable
				&& is_string($firstParam->var->name)
			) {
				$valueParamPhpDocType = $phpDocParameterTypes[$firstParam->var->name] ?? null;
				if ($valueParamPhpDocType === null) {
					$phpDocParameterTypes[$firstParam->var->name] = $this->transformStaticType(TemplateTypeHelper::toArgument($phpDocPropertyType));
				}
			}

			$realReturnType = new VoidType();
			$phpDocReturnType = null;
		} elseif ($hookName === 'get') {
			$realReturnType = $this->getFunctionType($nativePropertyTypeNode, false, false);
			$phpDocReturnType = $phpDocPropertyType !== null ? $this->transformStaticType(TemplateTypeHelper::toArgument($phpDocPropertyType)) : null;
		} else {
			throw new ShouldNotHappenException();
		}

		$realParameterTypes = $this->getRealParameterTypes($hook);

		return $this->enterFunctionLike(
			new PhpMethodFromParserNodeReflection(
				$this->getClassReflection(),
				$hook,
				$propertyName,
				$this->getFile(),
				TemplateTypeMap::createEmpty(),
				$realParameterTypes,
				$phpDocParameterTypes,
				[],
				$this->getParameterAttributes($hook),
				$realReturnType,
				$phpDocReturnType,
				$throwType !== null ? $this->transformStaticType(TemplateTypeHelper::toArgument($throwType)) : null,
				$deprecatedDescription,
				$isDeprecated,
				false,
				false,
				false,
				true,
				Assertions::createEmpty(),
				null,
				$phpDocComment,
				[],
				[],
				[],
				false,
				$this->attributeReflectionFactory->fromAttrGroups($hook->attrGroups, InitializerExprContext::fromStubParameter($this->getClassReflection()->getName(), $this->getFile(), $hook)),
			),
			true,
		);
	}

	private function transformStaticType(Type $type): Type
	{
		return TypeTraverser::map($type, function (Type $type, callable $traverse): Type {
			if (!$this->isInClass()) {
				return $type;
			}
			if ($type instanceof StaticType) {
				$classReflection = $this->getClassReflection();
				$changedType = $type->changeBaseClass($classReflection);
				if ($classReflection->isFinal() && !$type instanceof ThisType) {
					$changedType = $changedType->getStaticObjectType();
				}
				return $traverse($changedType);
			}

			return $traverse($type);
		});
	}

	/**
	 * This assumes enterAnonymousFunctionWithoutReflection() has already been called
	 */
	public function enterAnonymousFunction(
		ClosureType $anonymousFunctionReflection,
	): self
	{
		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$this->expressionTypes,
			$this->nativeExpressionTypes,
			[],
			$this->inClosureBindScopeClasses,
			$anonymousFunctionReflection,
			true,
			[],
			[],
			$this->inFunctionCallsStack,
			false,
			$this,
			$this->nativeTypesPromoted,
		);
	}

	/**
	 * @param ParameterReflection[]|null $callableParameters
	 */
	public function enterAnonymousFunctionWithoutReflection(
		Expr\Closure $closure,
		?array $callableParameters,
	): self
	{
		$expressionTypes = [];
		$nativeTypes = [];
		foreach ($closure->params as $i => $parameter) {
			if (!$parameter->var instanceof Variable || !is_string($parameter->var->name)) {
				throw new ShouldNotHappenException();
			}
			$paramExprString = sprintf('$%s', $parameter->var->name);
			$isNullable = $this->isParameterValueNullable($parameter);
			$parameterType = $this->getFunctionType($parameter->type, $isNullable, $parameter->variadic);
			if ($callableParameters !== null) {
				if (isset($callableParameters[$i])) {
					$parameterType = self::intersectButNotNever($parameterType, $callableParameters[$i]->getType());
				} elseif (count($callableParameters) > 0) {
					$lastParameter = array_last($callableParameters);
					if ($lastParameter->isVariadic()) {
						$parameterType = self::intersectButNotNever($parameterType, $lastParameter->getType());
					} else {
						$parameterType = self::intersectButNotNever($parameterType, new MixedType());
					}
				} else {
					$parameterType = self::intersectButNotNever($parameterType, new MixedType());
				}
			}
			$holder = ExpressionTypeHolder::createYes($parameter->var, $parameterType);
			$expressionTypes[$paramExprString] = $holder;
			$nativeTypes[$paramExprString] = $holder;
		}

		$nonRefVariableNames = [];
		foreach ($closure->uses as $use) {
			if (!is_string($use->var->name)) {
				throw new ShouldNotHappenException();
			}
			$variableName = $use->var->name;
			$paramExprString = '$' . $use->var->name;
			if ($use->byRef) {
				$holder = ExpressionTypeHolder::createYes($use->var, new MixedType());
				$expressionTypes[$paramExprString] = $holder;
				$nativeTypes[$paramExprString] = $holder;
				continue;
			}
			$nonRefVariableNames[$variableName] = true;
			if ($this->hasVariableType($variableName)->no()) {
				$variableType = new ErrorType();
				$variableNativeType = new ErrorType();
			} else {
				$variableType = $this->getVariableType($variableName);
				$nativeExprHolder = $this->nativeExpressionTypes['$' . $variableName] ?? null;
				if ($nativeExprHolder !== null) {
					$variableNativeType = $nativeExprHolder->getType();
				} else {
					$variableNativeType = new ErrorType();
				}
			}
			$expressionTypes[$paramExprString] = ExpressionTypeHolder::createYes($use->var, $variableType);
			$nativeTypes[$paramExprString] = ExpressionTypeHolder::createYes($use->var, $variableNativeType);
		}

		$nonStaticExpressions = $this->invalidateStaticExpressions($this->expressionTypes);
		foreach ($nonStaticExpressions as $exprString => $typeHolder) {
			$expr = $typeHolder->getExpr();

			if ($expr instanceof Variable) {
				continue;
			}

			$variables = (new NodeFinder())->findInstanceOf([$expr], Variable::class);
			if ($variables === [] && !$this->expressionTypeIsUnchangeable($typeHolder)) {
				continue;
			}

			foreach ($variables as $variable) {
				if (!is_string($variable->name)) {
					continue 2;
				}
				if (!array_key_exists($variable->name, $nonRefVariableNames)) {
					continue 2;
				}
			}

			$expressionTypes[$exprString] = $typeHolder;
		}

		if ($this->hasVariableType('this')->yes() && !$closure->static) {
			$node = new Variable('this');
			$expressionTypes['$this'] = ExpressionTypeHolder::createYes($node, $this->getVariableType('this'));

			$nativeExprHolder = $this->nativeExpressionTypes['$this'] ?? null;
			if ($nativeExprHolder !== null) {
				$variableNativeType = $nativeExprHolder->getType();
			} else {
				$variableNativeType = new ErrorType();
			}
			$nativeTypes['$this'] = ExpressionTypeHolder::createYes($node, $variableNativeType);

			if ($this->phpVersion->supportsReadOnlyProperties()) {
				foreach ($nonStaticExpressions as $exprString => $typeHolder) {
					$expr = $typeHolder->getExpr();

					if (!$expr instanceof PropertyFetch) {
						continue;
					}

					if (!$this->isReadonlyPropertyFetch($expr, true)) {
						continue;
					}

					$expressionTypes[$exprString] = $typeHolder;
				}
			}
		}

		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			array_merge($this->getConstantTypes(), $expressionTypes),
			array_merge($this->getNativeConstantTypes(), $nativeTypes),
			[],
			$this->inClosureBindScopeClasses,
			new ClosureType(),
			true,
			[],
			[],
			[],
			false,
			$this,
			$this->nativeTypesPromoted,
		);
	}

	/**
	 * This assumes enterArrowFunctionWithoutReflection() has already been called
	 */
	public function enterArrowFunction(ClosureType $anonymousFunctionReflection): self
	{
		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$this->expressionTypes,
			$this->nativeExpressionTypes,
			$this->conditionalExpressions,
			$this->inClosureBindScopeClasses,
			$anonymousFunctionReflection,
			true,
			[],
			[],
			$this->inFunctionCallsStack,
			$this->afterExtractCall,
			$this->parentScope,
			$this->nativeTypesPromoted,
		);
	}

	/**
	 * @param ParameterReflection[]|null $callableParameters
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, self>
	 */
	public function enterArrowFunctionWithoutReflection(Expr\ArrowFunction $arrowFunction, ?array $callableParameters): Generator
	{
		$arrowFunctionScope = $this;
		foreach ($arrowFunction->params as $i => $parameter) {
			if ($parameter->type === null) {
				$parameterType = new MixedType();
			} else {
				$isNullable = $this->isParameterValueNullable($parameter);
				$parameterType = $this->getFunctionType($parameter->type, $isNullable, $parameter->variadic);
			}

			if ($callableParameters !== null) {
				if (isset($callableParameters[$i])) {
					$parameterType = self::intersectButNotNever($parameterType, $callableParameters[$i]->getType());
				} elseif (count($callableParameters) > 0) {
					$lastParameter = array_last($callableParameters);
					if ($lastParameter->isVariadic()) {
						$parameterType = self::intersectButNotNever($parameterType, $lastParameter->getType());
					} else {
						$parameterType = self::intersectButNotNever($parameterType, new MixedType());
					}
				} else {
					$parameterType = self::intersectButNotNever($parameterType, new MixedType());
				}
			}

			if (!$parameter->var instanceof Variable || !is_string($parameter->var->name)) {
				throw new ShouldNotHappenException();
			}
			$arrowFunctionScopeGen = $arrowFunctionScope->assignVariable($parameter->var->name, $parameterType, $parameterType, TrinaryLogic::createYes());
			yield from $arrowFunctionScopeGen;
			$arrowFunctionScope = $arrowFunctionScopeGen->getReturn();
		}

		if ($arrowFunction->static) {
			$arrowFunctionScope = $arrowFunctionScope->invalidateExpression(new Variable('this'));
		}

		return $this->scopeFactory->create(
			$arrowFunctionScope->context,
			$this->isDeclareStrictTypes(),
			$arrowFunctionScope->getFunction(),
			$arrowFunctionScope->getNamespace(),
			$this->invalidateStaticExpressions($arrowFunctionScope->expressionTypes),
			$arrowFunctionScope->nativeExpressionTypes,
			$arrowFunctionScope->conditionalExpressions,
			$arrowFunctionScope->inClosureBindScopeClasses,
			new ClosureType(),
			true,
			[],
			[],
			[],
			$arrowFunctionScope->afterExtractCall,
			$arrowFunctionScope->parentScope,
			$this->nativeTypesPromoted,
		);
	}

	public static function intersectButNotNever(Type $nativeType, Type $inferredType): Type
	{
		if ($nativeType->isSuperTypeOf($inferredType)->no()) {
			return $nativeType;
		}

		$result = TypeCombinator::intersect($nativeType, $inferredType);
		if (TypeCombinator::containsNull($nativeType)) {
			return TypeCombinator::addNull($result);
		}

		return $result;
	}

	/**
	 * @param array<string, ExpressionTypeHolder> $expressionTypes
	 * @return array<string, ExpressionTypeHolder>
	 */
	private function invalidateStaticExpressions(array $expressionTypes): array
	{
		$filteredExpressionTypes = [];
		$nodeFinder = new NodeFinder();
		foreach ($expressionTypes as $exprString => $expressionType) {
			$staticExpression = $nodeFinder->findFirst(
				[$expressionType->getExpr()],
				static fn ($node) => $node instanceof Expr\StaticCall || $node instanceof Expr\StaticPropertyFetch,
			);
			if ($staticExpression !== null) {
				continue;
			}
			$filteredExpressionTypes[$exprString] = $expressionType;
		}
		return $filteredExpressionTypes;
	}

	private function expressionTypeIsUnchangeable(ExpressionTypeHolder $typeHolder): bool
	{
		$expr = $typeHolder->getExpr();
		//$type = $typeHolder->getType();

		return $expr instanceof FuncCall
			&& !$expr->isFirstClassCallable()
			&& $expr->name instanceof FullyQualified
			&& $expr->name->toLowerString() === 'function_exists';
			/*&& isset($expr->getArgs()[0])
			&& count($this->getType($expr->getArgs()[0]->value)->getConstantStrings()) === 1
			&& $type->isTrue()->yes()*/
	}

	/**
	 * @return Type[]
	 */
	private function getRealParameterTypes(Node\FunctionLike $functionLike): array
	{
		$realParameterTypes = [];
		foreach ($functionLike->getParams() as $parameter) {
			if (!$parameter->var instanceof Variable || !is_string($parameter->var->name)) {
				throw new ShouldNotHappenException();
			}
			$realParameterTypes[$parameter->var->name] = $this->getFunctionType(
				$parameter->type,
				$this->isParameterValueNullable($parameter) && $parameter->flags === 0,
				false,
			);
		}

		return $realParameterTypes;
	}

	/**
	 * @return Type[]
	 */
	private function getRealParameterDefaultValues(Node\FunctionLike $functionLike): array
	{
		$realParameterDefaultValues = [];
		foreach ($functionLike->getParams() as $parameter) {
			if ($parameter->default === null) {
				continue;
			}
			if (!$parameter->var instanceof Variable || !is_string($parameter->var->name)) {
				throw new ShouldNotHappenException();
			}
			$realParameterDefaultValues[$parameter->var->name] = $this->initializerExprTypeResolver->getType($parameter->default, InitializerExprContext::fromScope($this));
		}

		return $realParameterDefaultValues;
	}

	/**
	 * @return array<string, list<AttributeReflection>>
	 */
	private function getParameterAttributes(ClassMethod|Function_|PropertyHook $functionLike): array
	{
		$parameterAttributes = [];
		$className = null;
		if ($this->isInClass()) {
			$className = $this->getClassReflection()->getName();
		}
		foreach ($functionLike->getParams() as $parameter) {
			if (!$parameter->var instanceof Variable || !is_string($parameter->var->name)) {
				throw new ShouldNotHappenException();
			}

			$parameterAttributes[$parameter->var->name] = $this->attributeReflectionFactory->fromAttrGroups($parameter->attrGroups, InitializerExprContext::fromStubParameter($className, $this->getFile(), $functionLike));
		}

		return $parameterAttributes;
	}

	/**
	 * @param MethodReflection|FunctionReflection|null $reflection
	 */
	public function pushInFunctionCall($reflection, ?ParameterReflection $parameter): self
	{
		$stack = $this->inFunctionCallsStack;
		$stack[] = [$reflection, $parameter];

		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$this->expressionTypes,
			$this->nativeExpressionTypes,
			$this->conditionalExpressions,
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
			$this->isInFirstLevelStatement(),
			$this->currentlyAssignedExpressions,
			$this->currentlyAllowedUndefinedExpressions,
			$stack,
			$this->afterExtractCall,
			$this->parentScope,
			$this->nativeTypesPromoted,
		);
	}

	public function popInFunctionCall(): self
	{
		$stack = $this->inFunctionCallsStack;
		array_pop($stack);

		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$this->expressionTypes,
			$this->nativeExpressionTypes,
			$this->conditionalExpressions,
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
			$this->isInFirstLevelStatement(),
			$this->currentlyAssignedExpressions,
			$this->currentlyAllowedUndefinedExpressions,
			$stack,
			$this->afterExtractCall,
			$this->parentScope,
			$this->nativeTypesPromoted,
		);
	}

	/**
	 * @param list<string> $scopeClasses
	 */
	public function enterClosureBind(?Type $thisType, ?Type $nativeThisType, array $scopeClasses): self
	{
		$expressionTypes = $this->expressionTypes;
		if ($thisType !== null) {
			$expressionTypes['$this'] = ExpressionTypeHolder::createYes(new Variable('this'), $thisType);
		} else {
			unset($expressionTypes['$this']);
		}

		$nativeExpressionTypes = $this->nativeExpressionTypes;
		if ($nativeThisType !== null) {
			$nativeExpressionTypes['$this'] = ExpressionTypeHolder::createYes(new Variable('this'), $nativeThisType);
		} else {
			unset($nativeExpressionTypes['$this']);
		}

		if ($scopeClasses === ['static'] && $this->isInClass()) {
			$scopeClasses = [$this->getClassReflection()->getName()];
		}

		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$expressionTypes,
			$nativeExpressionTypes,
			$this->conditionalExpressions,
			$scopeClasses,
			$this->anonymousFunctionReflection,
		);
	}

	public function restoreOriginalScopeAfterClosureBind(self $originalScope): self
	{
		$expressionTypes = $this->expressionTypes;
		if (isset($originalScope->expressionTypes['$this'])) {
			$expressionTypes['$this'] = $originalScope->expressionTypes['$this'];
		} else {
			unset($expressionTypes['$this']);
		}

		$nativeExpressionTypes = $this->nativeExpressionTypes;
		if (isset($originalScope->nativeExpressionTypes['$this'])) {
			$nativeExpressionTypes['$this'] = $originalScope->nativeExpressionTypes['$this'];
		} else {
			unset($nativeExpressionTypes['$this']);
		}

		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$expressionTypes,
			$nativeExpressionTypes,
			$this->conditionalExpressions,
			$originalScope->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
		);
	}

	public function restoreThis(self $restoreThisScope): self
	{
		$expressionTypes = $this->expressionTypes;
		$nativeExpressionTypes = $this->nativeExpressionTypes;

		if ($restoreThisScope->isInClass()) {
			$nodeFinder = new NodeFinder();
			$cb = static fn ($expr) => $expr instanceof Variable && $expr->name === 'this';
			foreach ($restoreThisScope->expressionTypes as $exprString => $expressionTypeHolder) {
				$expr = $expressionTypeHolder->getExpr();
				$thisExpr = $nodeFinder->findFirst([$expr], $cb);
				if ($thisExpr === null) {
					continue;
				}

				$expressionTypes[$exprString] = $expressionTypeHolder;
			}

			foreach ($restoreThisScope->nativeExpressionTypes as $exprString => $expressionTypeHolder) {
				$expr = $expressionTypeHolder->getExpr();
				$thisExpr = $nodeFinder->findFirst([$expr], $cb);
				if ($thisExpr === null) {
					continue;
				}

				$nativeExpressionTypes[$exprString] = $expressionTypeHolder;
			}
		} else {
			unset($expressionTypes['$this']);
			unset($nativeExpressionTypes['$this']);
		}

		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$expressionTypes,
			$nativeExpressionTypes,
			$this->conditionalExpressions,
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
			$this->inFirstLevelStatement,
			[],
			[],
			$this->inFunctionCallsStack,
			$this->afterExtractCall,
			$this->parentScope,
			$this->nativeTypesPromoted,
		);
	}

	public function enterClosureCall(Type $thisType, Type $nativeThisType): self
	{
		$expressionTypes = $this->expressionTypes;
		$expressionTypes['$this'] = ExpressionTypeHolder::createYes(new Variable('this'), $thisType);

		$nativeExpressionTypes = $this->nativeExpressionTypes;
		$nativeExpressionTypes['$this'] = ExpressionTypeHolder::createYes(new Variable('this'), $nativeThisType);

		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$expressionTypes,
			$nativeExpressionTypes,
			$this->conditionalExpressions,
			$thisType->getObjectClassNames(),
			$this->anonymousFunctionReflection,
		);
	}

	/** @api */
	public function getParentScope(): ?Scope
	{
		return $this->parentScope;
	}

	/** @api */
	public function hasVariableType(string $variableName): TrinaryLogic
	{
		if ($this->isGlobalVariable($variableName)) {
			return TrinaryLogic::createYes();
		}

		$varExprString = '$' . $variableName;
		if (!isset($this->expressionTypes[$varExprString])) {
			if ($this->canAnyVariableExist()) {
				return TrinaryLogic::createMaybe();
			}

			return TrinaryLogic::createNo();
		}

		return $this->expressionTypes[$varExprString]->getCertainty();
	}

	/** @api */
	public function getVariableType(string $variableName): Type
	{
		if ($this->hasVariableType($variableName)->maybe()) {
			if ($variableName === 'argc') {
				return IntegerRangeType::fromInterval(1, null);
			}
			if ($variableName === 'argv') {
				return TypeCombinator::intersect(
					new ArrayType(new IntegerType(), new StringType()),
					new NonEmptyArrayType(),
					new AccessoryArrayListType(),
				);
			}
			if ($this->canAnyVariableExist()) {
				return new MixedType();
			}
		}

		if ($this->hasVariableType($variableName)->no()) {
			throw new UndefinedVariableException($this, $variableName);
		}

		$varExprString = '$' . $variableName;
		if (!array_key_exists($varExprString, $this->expressionTypes)) {
			if ($this->isGlobalVariable($variableName)) {
				return new ArrayType(new BenevolentUnionType([new IntegerType(), new StringType()]), new MixedType(true));
			}
			return new MixedType();
		}

		return TypeUtils::resolveLateResolvableTypes($this->expressionTypes[$varExprString]->getType());
	}

	/** @api */
	public function canAnyVariableExist(): bool
	{
		return ($this->function === null && !$this->isInAnonymousFunction()) || $this->afterExtractCall;
	}

	public function afterExtractCall(): self
	{
		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$this->expressionTypes,
			$this->nativeExpressionTypes,
			[],
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
			$this->isInFirstLevelStatement(),
			$this->currentlyAssignedExpressions,
			$this->currentlyAllowedUndefinedExpressions,
			$this->inFunctionCallsStack,
			true,
			$this->parentScope,
			$this->nativeTypesPromoted,
		);
	}

	public function afterClearstatcacheCall(): self
	{
		$expressionTypes = $this->expressionTypes;
		$nativeExpressionTypes = $this->nativeExpressionTypes;
		foreach (array_keys($expressionTypes) as $exprString) {
			// list from https://www.php.net/manual/en/function.clearstatcache.php

			// stat(), lstat(), file_exists(), is_writable(), is_readable(), is_executable(), is_file(), is_dir(), is_link(), filectime(), fileatime(), filemtime(), fileinode(), filegroup(), fileowner(), filesize(), filetype(), and fileperms().
			foreach ([
				'stat',
				'lstat',
				'file_exists',
				'is_writable',
				'is_writeable',
				'is_readable',
				'is_executable',
				'is_file',
				'is_dir',
				'is_link',
				'filectime',
				'fileatime',
				'filemtime',
				'fileinode',
				'filegroup',
				'fileowner',
				'filesize',
				'filetype',
				'fileperms',
			] as $functionName) {
				if (!str_starts_with($exprString, $functionName . '(') && !str_starts_with($exprString, '\\' . $functionName . '(')) {
					continue;
				}

				unset($expressionTypes[$exprString]);
				unset($nativeExpressionTypes[$exprString]);
				continue 2;
			}
		}

		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$expressionTypes,
			$nativeExpressionTypes,
			$this->conditionalExpressions,
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
			$this->isInFirstLevelStatement(),
			$this->currentlyAssignedExpressions,
			$this->currentlyAllowedUndefinedExpressions,
			$this->inFunctionCallsStack,
			$this->afterExtractCall,
			$this->parentScope,
			$this->nativeTypesPromoted,
		);
	}

	public function afterOpenSslCall(string $openSslFunctionName): self
	{
		$expressionTypes = $this->expressionTypes;
		$nativeExpressionTypes = $this->nativeExpressionTypes;

		if (in_array($openSslFunctionName, [
			'openssl_cipher_iv_length',
			'openssl_cms_decrypt',
			'openssl_cms_encrypt',
			'openssl_cms_read',
			'openssl_cms_sign',
			'openssl_cms_verify',
			'openssl_csr_export_to_file',
			'openssl_csr_export',
			'openssl_csr_get_public_key',
			'openssl_csr_get_subject',
			'openssl_csr_new',
			'openssl_csr_sign',
			'openssl_decrypt',
			'openssl_dh_compute_key',
			'openssl_digest',
			'openssl_encrypt',
			'openssl_get_curve_names',
			'openssl_get_privatekey',
			'openssl_get_publickey',
			'openssl_open',
			'openssl_pbkdf2',
			'openssl_pkcs12_export_to_file',
			'openssl_pkcs12_export',
			'openssl_pkcs12_read',
			'openssl_pkcs7_decrypt',
			'openssl_pkcs7_encrypt',
			'openssl_pkcs7_read',
			'openssl_pkcs7_sign',
			'openssl_pkcs7_verify',
			'openssl_pkey_derive',
			'openssl_pkey_export_to_file',
			'openssl_pkey_export',
			'openssl_pkey_get_private',
			'openssl_pkey_get_public',
			'openssl_pkey_new',
			'openssl_private_decrypt',
			'openssl_private_encrypt',
			'openssl_public_decrypt',
			'openssl_public_encrypt',
			'openssl_random_pseudo_bytes',
			'openssl_seal',
			'openssl_sign',
			'openssl_spki_export_challenge',
			'openssl_spki_export',
			'openssl_spki_new',
			'openssl_spki_verify',
			'openssl_verify',
			'openssl_x509_checkpurpose',
			'openssl_x509_export_to_file',
			'openssl_x509_export',
			'openssl_x509_fingerprint',
			'openssl_x509_read',
			'openssl_x509_verify',
		], true)) {
			unset($expressionTypes['\openssl_error_string()']);
			unset($nativeExpressionTypes['\openssl_error_string()']);
		}

		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$expressionTypes,
			$nativeExpressionTypes,
			$this->conditionalExpressions,
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
			$this->isInFirstLevelStatement(),
			$this->currentlyAssignedExpressions,
			$this->currentlyAllowedUndefinedExpressions,
			$this->inFunctionCallsStack,
			$this->afterExtractCall,
			$this->parentScope,
			$this->nativeTypesPromoted,
		);
	}

	/**
	 * @api
	 * @return list<string>
	 */
	public function getDefinedVariables(): array
	{
		$variables = [];
		foreach ($this->expressionTypes as $exprString => $holder) {
			if (!$holder->getExpr() instanceof Variable) {
				continue;
			}
			if (!$holder->getCertainty()->yes()) {
				continue;
			}

			$variables[] = substr($exprString, 1);
		}

		return $variables;
	}

	/**
	 * @api
	 * @return list<string>
	 */
	public function getMaybeDefinedVariables(): array
	{
		$variables = [];
		foreach ($this->expressionTypes as $exprString => $holder) {
			if (!$holder->getExpr() instanceof Variable) {
				continue;
			}
			if (!$holder->getCertainty()->maybe()) {
				continue;
			}

			$variables[] = substr($exprString, 1);
		}

		return $variables;
	}

	private function isGlobalVariable(string $variableName): bool
	{
		return in_array($variableName, self::SUPERGLOBAL_VARIABLES, true);
	}

	/** @api */
	public function hasConstant(Name $name): bool
	{
		$isCompilerHaltOffset = $name->toString() === '__COMPILER_HALT_OFFSET__';
		if ($isCompilerHaltOffset) {
			return $this->fileHasCompilerHaltStatementCalls();
		}

		if ($this->getGlobalConstantType($name) !== null) {
			return true;
		}

		return $this->reflectionProvider->hasConstant($name, $this);
	}

	private function getGlobalConstantType(Name $name): ?Type
	{
		$fetches = [];
		if (!$name->isFullyQualified() && $this->getNamespace() !== null) {
			$fetches[] = new ConstFetch(new FullyQualified([$this->getNamespace(), $name->toString()]));
		}

		$fetches[] = new ConstFetch(new FullyQualified($name->toString()));
		$fetches[] = new ConstFetch($name);

		foreach ($fetches as $constFetch) {
			$constFetchType = $this->getExpressionType($constFetch);
			if ($constFetchType === null) {
				continue;
			}

			return $constFetchType;
		}

		return null;
	}

	/**
	 * @return array<string, ExpressionTypeHolder>
	 */
	private function getNativeConstantTypes(): array
	{
		$constantTypes = [];
		foreach ($this->nativeExpressionTypes as $exprString => $typeHolder) {
			$expr = $typeHolder->getExpr();
			if (!$expr instanceof ConstFetch) {
				continue;
			}
			$constantTypes[$exprString] = $typeHolder;
		}
		return $constantTypes;
	}

	private function fileHasCompilerHaltStatementCalls(): bool
	{
		$nodes = $this->parser->parseFile($this->getFile());
		foreach ($nodes as $node) {
			if ($node instanceof Node\Stmt\HaltCompiler) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @api
	 * @deprecated Use getInstancePropertyReflection or getStaticPropertyReflection instead
	 */
	public function getPropertyReflection(Type $typeWithProperty, string $propertyName): ?ExtendedPropertyReflection
	{
		if ($typeWithProperty instanceof UnionType) {
			$typeWithProperty = $typeWithProperty->filterTypes(static fn (Type $innerType) => $innerType->hasProperty($propertyName)->yes());
		}
		if (!$typeWithProperty->hasProperty($propertyName)->yes()) {
			return null;
		}

		return $typeWithProperty->getProperty($propertyName, $this);
	}

	/** @api */
	public function getInstancePropertyReflection(Type $typeWithProperty, string $propertyName): ?ExtendedPropertyReflection
	{
		if ($typeWithProperty instanceof UnionType) {
			$typeWithProperty = $typeWithProperty->filterTypes(static fn (Type $innerType) => $innerType->hasInstanceProperty($propertyName)->yes());
		}
		if (!$typeWithProperty->hasInstanceProperty($propertyName)->yes()) {
			return null;
		}

		return $typeWithProperty->getInstanceProperty($propertyName, $this);
	}

	/** @api */
	public function getStaticPropertyReflection(Type $typeWithProperty, string $propertyName): ?ExtendedPropertyReflection
	{
		if ($typeWithProperty instanceof UnionType) {
			$typeWithProperty = $typeWithProperty->filterTypes(static fn (Type $innerType) => $innerType->hasStaticProperty($propertyName)->yes());
		}
		if (!$typeWithProperty->hasStaticProperty($propertyName)->yes()) {
			return null;
		}

		return $typeWithProperty->getStaticProperty($propertyName, $this);
	}

	public function getConstantReflection(Type $typeWithConstant, string $constantName): ?ClassConstantReflection
	{
		if ($typeWithConstant instanceof UnionType) {
			$typeWithConstant = $typeWithConstant->filterTypes(static fn (Type $innerType) => $innerType->hasConstant($constantName)->yes());
		}
		if (!$typeWithConstant->hasConstant($constantName)->yes()) {
			return null;
		}

		return $typeWithConstant->getConstant($constantName);
	}

	public function getConstantExplicitTypeFromConfig(string $constantName, Type $constantType): Type
	{
		return $this->constantResolver->resolveConstantType($constantName, $constantType);
	}

	/**
	 * @return array<string, ExpressionTypeHolder>
	 */
	private function getConstantTypes(): array
	{
		$constantTypes = [];
		foreach ($this->expressionTypes as $exprString => $typeHolder) {
			$expr = $typeHolder->getExpr();
			if (!$expr instanceof ConstFetch) {
				continue;
			}
			$constantTypes[$exprString] = $typeHolder;
		}
		return $constantTypes;
	}

	/** @api */
	public function isInAnonymousFunction(): bool
	{
		return $this->anonymousFunctionReflection !== null;
	}

	/** @api */
	public function getAnonymousFunctionReflection(): ?ClosureType
	{
		return $this->anonymousFunctionReflection;
	}

	/** @api */
	public function getAnonymousFunctionReturnType(): ?Type
	{
		if ($this->anonymousFunctionReflection === null) {
			return null;
		}

		return $this->anonymousFunctionReflection->getReturnType();
	}

	/** @api */
	public function getType(Expr $node): Type
	{
		return Fiber::suspend(
			ExprAnalysisRequest::createNoopRequest($node, $this),
		)->type;
	}

	/** @api */
	public function getNativeType(Expr $expr): Type
	{
		return Fiber::suspend(
			ExprAnalysisRequest::createNoopRequest($expr, $this),
		)->nativeType;
	}

	public function doNotTreatPhpDocTypesAsCertain(): Scope
	{
		return $this->promoteNativeTypes();
	}

	private function promoteNativeTypes(): self
	{
		if ($this->nativeTypesPromoted) {
			return $this;
		}

		return $this->scopeFactory->create(
			$this->context,
			$this->declareStrictTypes,
			$this->function,
			$this->namespace,
			$this->nativeExpressionTypes,
			[],
			[],
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
			$this->inFirstLevelStatement,
			$this->currentlyAssignedExpressions,
			$this->currentlyAllowedUndefinedExpressions,
			$this->inFunctionCallsStack,
			$this->afterExtractCall,
			$this->parentScope,
			true,
		);
	}

	public function getKeepVoidType(Expr $node): Type
	{
		return Fiber::suspend(
			ExprAnalysisRequest::createNoopRequest($node, $this),
		)->keepVoidType;
	}

	/**
	 * @api
	 * @return non-empty-string
	 */
	public function resolveName(Name $name): string
	{
		$originalClass = (string) $name;
		if ($this->isInClass()) {
			$lowerClass = strtolower($originalClass);
			if (in_array($lowerClass, [
				'self',
				'static',
			], true)) {
				if ($this->inClosureBindScopeClasses !== [] && $this->inClosureBindScopeClasses !== ['static']) {
					return $this->inClosureBindScopeClasses[0];
				}
				return $this->getClassReflection()->getName();
			} elseif ($lowerClass === 'parent') {
				$currentClassReflection = $this->getClassReflection();
				if ($currentClassReflection->getParentClass() !== null) {
					return $currentClassReflection->getParentClass()->getName();
				}
			}
		}

		return $originalClass;
	}

	/** @api */
	public function resolveTypeByName(Name $name): TypeWithClassName
	{
		if ($name->toLowerString() === 'static' && $this->isInClass()) {
			if ($this->inClosureBindScopeClasses !== [] && $this->inClosureBindScopeClasses !== ['static']) {
				if ($this->reflectionProvider->hasClass($this->inClosureBindScopeClasses[0])) {
					return new StaticType($this->reflectionProvider->getClass($this->inClosureBindScopeClasses[0]));
				}
			}

			return new StaticType($this->getClassReflection());
		}

		$originalClass = $this->resolveName($name);
		if ($this->isInClass()) {
			if ($this->inClosureBindScopeClasses === [$originalClass]) {
				if ($this->reflectionProvider->hasClass($originalClass)) {
					return new ThisType($this->reflectionProvider->getClass($originalClass));
				}
				return new ObjectType($originalClass);
			}

			$thisType = new ThisType($this->getClassReflection());
			$ancestor = $thisType->getAncestorWithClassName($originalClass);
			if ($ancestor !== null) {
				return $ancestor;
			}
		}

		return new ObjectType($originalClass);
	}

	/**
	 * @api
	 * @param mixed $value
	 */
	public function getTypeFromValue($value): Type
	{
		return ConstantTypeHelper::getTypeFromValue($value);
	}

	/** @api */
	public function isInClassExists(string $className): bool
	{
		foreach ($this->inFunctionCallsStack as [$inFunctionCall]) {
			if (!$inFunctionCall instanceof FunctionReflection) {
				continue;
			}

			if (in_array($inFunctionCall->getName(), [
				'class_exists',
				'interface_exists',
				'trait_exists',
			], true)) {
				return true;
			}
		}
		$expr = new FuncCall(new FullyQualified('class_exists'), [
			new Arg(new String_(ltrim($className, '\\'))),
		]);

		$type = $this->getExpressionType($expr);
		if ($type === null) {
			return false;
		}

		return $type->isTrue()->yes();
	}

	/** @api */
	public function isInFunctionExists(string $functionName): bool
	{
		$expr = new FuncCall(new FullyQualified('function_exists'), [
			new Arg(new String_(ltrim($functionName, '\\'))),
		]);
		$type = $this->getExpressionType($expr);
		if ($type === null) {
			return false;
		}

		return $type->isTrue()->yes();
	}

	/** @api */
	public function isInClosureBind(): bool
	{
		return $this->inClosureBindScopeClasses !== [];
	}

	public function getFunctionCallStack(): array
	{
		return array_values(array_filter(
			array_map(static fn ($values) => $values[0], $this->inFunctionCallsStack),
			static fn (FunctionReflection|MethodReflection|null $reflection) => $reflection !== null,
		));
	}

	public function getFunctionCallStackWithParameters(): array
	{
		return array_values(array_filter(
			$this->inFunctionCallsStack,
			static fn ($item) => $item[0] !== null,
		));
	}

	public function isParameterValueNullable(Node\Param $parameter): bool
	{
		if ($parameter->default instanceof ConstFetch) {
			return strtolower((string) $parameter->default->name) === 'null';
		}

		return false;
	}

	/**
	 * @api
	 * @param Node\Name|Node\Identifier|Node\ComplexType|null $type
	 */
	public function getFunctionType($type, bool $isNullable, bool $isVariadic): Type
	{
		if ($isVariadic) {
			if (!$this->getPhpVersion()->supportsNamedArguments()->no()) {
				return new ArrayType(new UnionType([new IntegerType(), new StringType()]), $this->getFunctionType(
					$type,
					$isNullable,
					false,
				));
			}

			return TypeCombinator::intersect(new ArrayType(new IntegerType(), $this->getFunctionType(
				$type,
				$isNullable,
				false,
			)), new AccessoryArrayListType());
		}
		return $this->initializerExprTypeResolver->getFunctionType($type, $isNullable, false, InitializerExprContext::fromScope($this));
	}

	public function enterExpressionAssign(Expr $expr): self
	{
		$exprString = $this->getNodeKey($expr);
		$currentlyAssignedExpressions = $this->currentlyAssignedExpressions;
		$currentlyAssignedExpressions[$exprString] = true;

		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$this->expressionTypes,
			$this->nativeExpressionTypes,
			$this->conditionalExpressions,
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
			$this->isInFirstLevelStatement(),
			$currentlyAssignedExpressions,
			$this->currentlyAllowedUndefinedExpressions,
			[],
			$this->afterExtractCall,
			$this->parentScope,
			$this->nativeTypesPromoted,
		);
	}

	public function exitExpressionAssign(Expr $expr): self
	{
		$exprString = $this->getNodeKey($expr);
		$currentlyAssignedExpressions = $this->currentlyAssignedExpressions;
		unset($currentlyAssignedExpressions[$exprString]);

		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$this->expressionTypes,
			$this->nativeExpressionTypes,
			$this->conditionalExpressions,
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
			$this->isInFirstLevelStatement(),
			$currentlyAssignedExpressions,
			$this->currentlyAllowedUndefinedExpressions,
			[],
			$this->afterExtractCall,
			$this->parentScope,
			$this->nativeTypesPromoted,
		);
	}

	/** @api */
	public function isInExpressionAssign(Expr $expr): bool
	{
		$exprString = $this->getNodeKey($expr);
		return array_key_exists($exprString, $this->currentlyAssignedExpressions);
	}

	public function setAllowedUndefinedExpression(Expr $expr): self
	{
		if ($expr instanceof Expr\StaticPropertyFetch) {
			return $this;
		}

		$exprString = $this->getNodeKey($expr);
		$currentlyAllowedUndefinedExpressions = $this->currentlyAllowedUndefinedExpressions;
		$currentlyAllowedUndefinedExpressions[$exprString] = true;

		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$this->expressionTypes,
			$this->nativeExpressionTypes,
			$this->conditionalExpressions,
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
			$this->isInFirstLevelStatement(),
			$this->currentlyAssignedExpressions,
			$currentlyAllowedUndefinedExpressions,
			[],
			$this->afterExtractCall,
			$this->parentScope,
			$this->nativeTypesPromoted,
		);
	}

	public function unsetAllowedUndefinedExpression(Expr $expr): self
	{
		$exprString = $this->getNodeKey($expr);
		$currentlyAllowedUndefinedExpressions = $this->currentlyAllowedUndefinedExpressions;
		unset($currentlyAllowedUndefinedExpressions[$exprString]);

		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$this->expressionTypes,
			$this->nativeExpressionTypes,
			$this->conditionalExpressions,
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
			$this->isInFirstLevelStatement(),
			$this->currentlyAssignedExpressions,
			$currentlyAllowedUndefinedExpressions,
			[],
			$this->afterExtractCall,
			$this->parentScope,
			$this->nativeTypesPromoted,
		);
	}

	/** @api */
	public function isUndefinedExpressionAllowed(Expr $expr): bool
	{
		$exprString = $this->getNodeKey($expr);
		return array_key_exists($exprString, $this->currentlyAllowedUndefinedExpressions);
	}

	/**
	 * @deprecated
	 */
	public function filterByTruthyValue(Expr $expr): self
	{
		$specifiedTypes = $this->typeSpecifier->specifyTypesInCondition($this, $expr, TypeSpecifierContext::createTruthy());
		return $this->filterBySpecifiedTypes($specifiedTypes);
	}

	/**
	 * @deprecated
	 */
	public function filterByFalseyValue(Expr $expr): self
	{
		$specifiedTypes = $this->typeSpecifier->specifyTypesInCondition($this, $expr, TypeSpecifierContext::createFalsey());
		return $this->filterBySpecifiedTypes($specifiedTypes);
	}

	/**
	 * @deprecated
	 */
	private function filterBySpecifiedTypes(SpecifiedTypes $specifiedTypes): self
	{
		$typeSpecifications = [];
		foreach ($specifiedTypes->getSureTypes() as $exprString => [$expr, $type]) {
			if ($expr instanceof Node\Scalar || $expr instanceof Array_ || $expr instanceof Expr\UnaryMinus && $expr->expr instanceof Node\Scalar) {
				continue;
			}
			$typeSpecifications[] = [
				'sure' => true,
				'exprString' => (string) $exprString,
				'expr' => $expr,
				'type' => $type,
			];
		}
		foreach ($specifiedTypes->getSureNotTypes() as $exprString => [$expr, $type]) {
			if ($expr instanceof Node\Scalar || $expr instanceof Array_ || $expr instanceof Expr\UnaryMinus && $expr->expr instanceof Node\Scalar) {
				continue;
			}
			$typeSpecifications[] = [
				'sure' => false,
				'exprString' => (string) $exprString,
				'expr' => $expr,
				'type' => $type,
			];
		}

		usort($typeSpecifications, static function (array $a, array $b): int {
			$length = strlen($a['exprString']) - strlen($b['exprString']);
			if ($length !== 0) {
				return $length;
			}

			return $b['sure'] - $a['sure']; // @phpstan-ignore minus.leftNonNumeric, minus.rightNonNumeric
		});

		$scope = $this;
		$specifiedExpressions = [];
		foreach ($typeSpecifications as $typeSpecification) {
			$expr = $typeSpecification['expr'];
			$type = $typeSpecification['type'];

			if ($expr instanceof IssetExpr) {
				$issetExpr = $expr;
				$expr = $issetExpr->getExpr();

				if ($typeSpecification['sure']) {
					$scope = $scope->setExpressionCertaintyInternal(
						$expr,
						TrinaryLogic::createMaybe(),
					);
				} else {
					$scope = $scope->unsetExpressionInternal($expr);
				}

				continue;
			}

			if ($typeSpecification['sure']) {
				if ($specifiedTypes->shouldOverwrite()) {
					$scope = $scope->assignExpressionInternal($expr, $type, $type);
				} else {
					$scope = $scope->addTypeToExpressionInternal($expr, $type);
				}
			} else {
				$scope = $scope->removeTypeFromExpressionInternal($expr, $type);
			}
			$specifiedExpressions[$this->getNodeKey($expr)] = ExpressionTypeHolder::createYes($expr, $scope->getType($expr));
		}

		$conditions = [];
		foreach ($scope->conditionalExpressions as $conditionalExprString => $conditionalExpressions) {
			foreach ($conditionalExpressions as $conditionalExpression) {
				foreach ($conditionalExpression->getConditionExpressionTypeHolders() as $holderExprString => $conditionalTypeHolder) {
					if (!array_key_exists($holderExprString, $specifiedExpressions) || !$specifiedExpressions[$holderExprString]->equals($conditionalTypeHolder)) {
						continue 2;
					}
				}

				$conditions[$conditionalExprString][] = $conditionalExpression;
				$specifiedExpressions[$conditionalExprString] = $conditionalExpression->getTypeHolder();
			}
		}

		foreach ($conditions as $conditionalExprString => $expressions) {
			$certainty = TrinaryLogic::lazyExtremeIdentity($expressions, static fn (ConditionalExpressionHolder $holder) => $holder->getTypeHolder()->getCertainty());
			if ($certainty->no()) {
				unset($scope->expressionTypes[$conditionalExprString]);
			} else {
				$type = TypeCombinator::intersect(...array_map(static fn (ConditionalExpressionHolder $holder) => $holder->getTypeHolder()->getType(), $expressions));

				$scope->expressionTypes[$conditionalExprString] = array_key_exists($conditionalExprString, $scope->expressionTypes)
					? new ExpressionTypeHolder(
						$scope->expressionTypes[$conditionalExprString]->getExpr(),
						TypeCombinator::intersect($scope->expressionTypes[$conditionalExprString]->getType(), $type),
						TrinaryLogic::maxMin($scope->expressionTypes[$conditionalExprString]->getCertainty(), $certainty),
					)
					: $expressions[0]->getTypeHolder();
			}
		}

		return $scope->scopeFactory->create(
			$scope->context,
			$scope->isDeclareStrictTypes(),
			$scope->getFunction(),
			$scope->getNamespace(),
			$scope->expressionTypes,
			$scope->nativeExpressionTypes,
			array_merge($specifiedTypes->getNewConditionalExpressionHolders(), $scope->conditionalExpressions),
			$scope->inClosureBindScopeClasses,
			$scope->anonymousFunctionReflection,
			$scope->inFirstLevelStatement,
			$scope->currentlyAssignedExpressions,
			$scope->currentlyAllowedUndefinedExpressions,
			$scope->inFunctionCallsStack,
			$scope->afterExtractCall,
			$scope->parentScope,
			$scope->nativeTypesPromoted,
		);
	}

	/**
	 * @deprecated
	 */
	private function addTypeToExpressionInternal(Expr $expr, Type $type): self
	{
		$originalExprType = $this->getType($expr);
		$nativeType = $this->getNativeType($expr);

		if ($originalExprType->equals($nativeType)) {
			$newType = TypeCombinator::intersect($type, $originalExprType);
			if ($newType->isConstantScalarValue()->yes() && $newType->equals($originalExprType)) {
				// don't add the same type over and over again to improve performance
				return $this;
			}
			return $this->specifyExpressionTypeInternal($expr, $newType, $newType, TrinaryLogic::createYes());
		}

		return $this->specifyExpressionTypeInternal(
			$expr,
			TypeCombinator::intersect($type, $originalExprType),
			TypeCombinator::intersect($type, $nativeType),
			TrinaryLogic::createYes(),
		);
	}

	/**
	 * @deprecated
	 */
	private function removeTypeFromExpressionInternal(Expr $expr, Type $typeToRemove): self
	{
		$exprType = $this->getType($expr);
		if (
			$exprType instanceof NeverType ||
			$typeToRemove instanceof NeverType
		) {
			return $this;
		}
		return $this->specifyExpressionTypeInternal(
			$expr,
			TypeCombinator::remove($exprType, $typeToRemove),
			TypeCombinator::remove($this->getNativeType($expr), $typeToRemove),
			TrinaryLogic::createYes(),
		);
	}

	/**
	 * @deprecated
	 */
	private function setExpressionCertaintyInternal(Expr $expr, TrinaryLogic $certainty): self
	{
		if ($this->hasExpressionType($expr)->no()) {
			throw new ShouldNotHappenException();
		}

		$originalExprType = $this->getType($expr);
		$nativeType = $this->getNativeType($expr);

		return $this->specifyExpressionTypeInternal(
			$expr,
			$originalExprType,
			$nativeType,
			$certainty,
		);
	}

	/**
	 * @deprecated
	 */
	private function specifyExpressionTypeInternal(Expr $expr, Type $type, Type $nativeType, TrinaryLogic $certainty): self
	{
		if ($expr instanceof ConstFetch) {
			$loweredConstName = strtolower($expr->name->toString());
			if (in_array($loweredConstName, ['true', 'false', 'null'], true)) {
				return $this;
			}
		}

		if ($expr instanceof FuncCall && $expr->name instanceof Name && $type->isFalse()->yes()) {
			$functionName = $this->reflectionProvider->resolveFunctionName($expr->name, $this);
			if ($functionName !== null && in_array(strtolower($functionName), [
				'is_dir',
				'is_file',
				'file_exists',
			], true)) {
				return $this;
			}
		}

		$scope = $this;
		if (
			$expr instanceof Expr\ArrayDimFetch
			&& $expr->dim !== null
			&& !$expr->dim instanceof Expr\PreInc
			&& !$expr->dim instanceof Expr\PreDec
			&& !$expr->dim instanceof Expr\PostDec
			&& !$expr->dim instanceof Expr\PostInc
		) {
			$dimType = $scope->getType($expr->dim)->toArrayKey();
			if ($dimType->isInteger()->yes() || $dimType->isString()->yes()) {
				$exprVarType = $scope->getType($expr->var);
				if (!$exprVarType instanceof MixedType && !$exprVarType->isArray()->no()) {
					$types = [
						new ArrayType(new MixedType(), new MixedType()),
						new ObjectType(ArrayAccess::class),
						new NullType(),
					];
					if ($dimType->isInteger()->yes()) {
						$types[] = new StringType();
					}
					$offsetValueType = TypeCombinator::intersect($exprVarType, TypeCombinator::union(...$types));

					if ($dimType instanceof ConstantIntegerType || $dimType instanceof ConstantStringType) {
						$offsetValueType = TypeCombinator::intersect(
							$offsetValueType,
							new HasOffsetValueType($dimType, $type),
						);
					}

					$scope = $scope->specifyExpressionTypeInternal(
						$expr->var,
						$offsetValueType,
						$scope->getNativeType($expr->var),
						$certainty,
					);
				}
			}
		}

		if ($certainty->no()) {
			throw new ShouldNotHappenException();
		}

		$exprString = $this->getNodeKey($expr);
		$expressionTypes = $scope->expressionTypes;
		$expressionTypes[$exprString] = new ExpressionTypeHolder($expr, $type, $certainty);
		$nativeTypes = $scope->nativeExpressionTypes;
		$nativeTypes[$exprString] = new ExpressionTypeHolder($expr, $nativeType, $certainty);

		$scope = $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$expressionTypes,
			$nativeTypes,
			$this->conditionalExpressions,
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
			$this->inFirstLevelStatement,
			$this->currentlyAssignedExpressions,
			$this->currentlyAllowedUndefinedExpressions,
			$this->inFunctionCallsStack,
			$this->afterExtractCall,
			$this->parentScope,
			$this->nativeTypesPromoted,
		);

		if ($expr instanceof AlwaysRememberedExpr) {
			return $scope->specifyExpressionTypeInternal($expr->expr, $type, $nativeType, $certainty);
		}

		return $scope;
	}

	/**
	 * @deprecated
	 */
	private function unsetExpressionInternal(Expr $expr): self
	{
		$scope = $this;
		if ($expr instanceof Expr\ArrayDimFetch && $expr->dim !== null) {
			$exprVarType = $scope->getType($expr->var);
			$dimType = $scope->getType($expr->dim);
			$unsetType = $exprVarType->unsetOffset($dimType);
			$exprVarNativeType = $scope->getNativeType($expr->var);
			$dimNativeType = $scope->getNativeType($expr->dim);
			$unsetNativeType = $exprVarNativeType->unsetOffset($dimNativeType);
			$scope = $scope->assignExpressionInternal($expr->var, $unsetType, $unsetNativeType)->invalidateExpression(
				new FuncCall(new FullyQualified('count'), [new Arg($expr->var)]),
			)->invalidateExpression(
				new FuncCall(new FullyQualified('sizeof'), [new Arg($expr->var)]),
			)->invalidateExpression(
				new FuncCall(new Name('count'), [new Arg($expr->var)]),
			)->invalidateExpression(
				new FuncCall(new Name('sizeof'), [new Arg($expr->var)]),
			);

			if ($expr->var instanceof Expr\ArrayDimFetch && $expr->var->dim !== null) {
				$scope = $scope->assignExpressionInternal(
					$expr->var->var,
					$this->getType($expr->var->var)->setOffsetValueType(
						$scope->getType($expr->var->dim),
						$scope->getType($expr->var),
					),
					$this->getNativeType($expr->var->var)->setOffsetValueType(
						$scope->getNativeType($expr->var->dim),
						$scope->getNativeType($expr->var),
					),
				);
			}
		}

		return $scope->invalidateExpression($expr);
	}

	/**
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, self>
	 */
	public function applySpecifiedTypes(SpecifiedTypes $specifiedTypes): Generator
	{
		$typeSpecifications = [];
		foreach ($specifiedTypes->getSureTypes() as $exprString => [$expr, $type]) {
			if ($expr instanceof Node\Scalar || $expr instanceof Array_ || $expr instanceof Expr\UnaryMinus && $expr->expr instanceof Node\Scalar) {
				continue;
			}
			$typeSpecifications[] = [
				'sure' => true,
				'exprString' => (string) $exprString,
				'expr' => $expr,
				'type' => $type,
			];
		}
		foreach ($specifiedTypes->getSureNotTypes() as $exprString => [$expr, $type]) {
			if ($expr instanceof Node\Scalar || $expr instanceof Array_ || $expr instanceof Expr\UnaryMinus && $expr->expr instanceof Node\Scalar) {
				continue;
			}
			$typeSpecifications[] = [
				'sure' => false,
				'exprString' => (string) $exprString,
				'expr' => $expr,
				'type' => $type,
			];
		}

		usort($typeSpecifications, static function (array $a, array $b): int {
			$length = strlen($a['exprString']) - strlen($b['exprString']);
			if ($length !== 0) {
				return $length;
			}

			return $b['sure'] - $a['sure']; // @phpstan-ignore minus.leftNonNumeric, minus.rightNonNumeric
		});

		$scope = $this;
		$specifiedExpressions = [];
		foreach ($typeSpecifications as $typeSpecification) {
			$expr = $typeSpecification['expr'];
			$type = $typeSpecification['type'];

			if ($expr instanceof IssetExpr) {
				$issetExpr = $expr;
				$expr = $issetExpr->getExpr();

				if ($typeSpecification['sure']) {
					$gen = $scope->setExpressionCertainty(
						$expr,
						TrinaryLogic::createMaybe(),
					);
					yield from $gen;
					$scope = $gen->getReturn();
				} else {
					$gen = $scope->unsetExpression($expr);
					yield from $gen;
					$scope = $gen->getReturn();
				}

				continue;
			}

			if ($typeSpecification['sure']) {
				if ($specifiedTypes->shouldOverwrite()) {
					$gen = $scope->assignExpression($expr, $type, $type);
					yield from $gen;
					$scope = $gen->getReturn();
				} else {
					$gen = $scope->addTypeToExpression($expr, $type);
					yield from $gen;
					$scope = $gen->getReturn();
				}
			} else {
				$gen = $scope->removeTypeFromExpression($expr, $type);
				yield from $gen;
				$scope = $gen->getReturn();
			}

			$exprType = (yield ExprAnalysisRequest::createNoopRequest($expr, $scope))->type;
			$specifiedExpressions[$this->getNodeKey($expr)] = ExpressionTypeHolder::createYes($expr, $exprType);
		}

		$conditions = [];
		foreach ($scope->conditionalExpressions as $conditionalExprString => $conditionalExpressions) {
			foreach ($conditionalExpressions as $conditionalExpression) {
				foreach ($conditionalExpression->getConditionExpressionTypeHolders() as $holderExprString => $conditionalTypeHolder) {
					if (!array_key_exists($holderExprString, $specifiedExpressions) || !$specifiedExpressions[$holderExprString]->equals($conditionalTypeHolder)) {
						continue 2;
					}
				}

				$conditions[$conditionalExprString][] = $conditionalExpression;
				$specifiedExpressions[$conditionalExprString] = $conditionalExpression->getTypeHolder();
			}
		}

		foreach ($conditions as $conditionalExprString => $expressions) {
			$certainty = TrinaryLogic::lazyExtremeIdentity($expressions, static fn (ConditionalExpressionHolder $holder) => $holder->getTypeHolder()->getCertainty());
			if ($certainty->no()) {
				unset($scope->expressionTypes[$conditionalExprString]);
			} else {
				$type = TypeCombinator::intersect(...array_map(static fn (ConditionalExpressionHolder $holder) => $holder->getTypeHolder()->getType(), $expressions));

				$scope->expressionTypes[$conditionalExprString] = array_key_exists($conditionalExprString, $scope->expressionTypes)
					? new ExpressionTypeHolder(
						$scope->expressionTypes[$conditionalExprString]->getExpr(),
						TypeCombinator::intersect($scope->expressionTypes[$conditionalExprString]->getType(), $type),
						TrinaryLogic::maxMin($scope->expressionTypes[$conditionalExprString]->getCertainty(), $certainty),
					)
					: $expressions[0]->getTypeHolder();
			}
		}

		return $scope->scopeFactory->create(
			$scope->context,
			$scope->isDeclareStrictTypes(),
			$scope->getFunction(),
			$scope->getNamespace(),
			$scope->expressionTypes,
			$scope->nativeExpressionTypes,
			array_merge($specifiedTypes->getNewConditionalExpressionHolders(), $scope->conditionalExpressions),
			$scope->inClosureBindScopeClasses,
			$scope->anonymousFunctionReflection,
			$scope->inFirstLevelStatement,
			$scope->currentlyAssignedExpressions,
			$scope->currentlyAllowedUndefinedExpressions,
			$scope->inFunctionCallsStack,
			$scope->afterExtractCall,
			$scope->parentScope,
			$scope->nativeTypesPromoted,
		);
	}

	/**
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, self>
	 */
	private function setExpressionCertainty(Expr $expr, TrinaryLogic $certainty): Generator
	{
		if ($this->hasExpressionType($expr)->no()) {
			throw new ShouldNotHappenException();
		}

		$exprResult = (yield ExprAnalysisRequest::createNoopRequest($expr, $this));
		$gen = $this->specifyExpressionType(
			$expr,
			$exprResult->type,
			$exprResult->nativeType,
			$certainty,
		);
		yield from $gen;
		return $gen->getReturn();
	}

	/**
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, self>
	 */
	private function unsetExpression(Expr $expr): Generator
	{
		$scope = $this;
		if ($expr instanceof Expr\ArrayDimFetch && $expr->dim !== null) {
			$exprVarResult = (yield ExprAnalysisRequest::createNoopRequest($expr->var, $scope));
			$exprVarType = $exprVarResult->type;

			$dimResult = (yield ExprAnalysisRequest::createNoopRequest($expr->dim, $scope));
			$dimType = $dimResult->type;
			$unsetType = $exprVarType->unsetOffset($dimType);
			$exprVarNativeType = $exprVarResult->nativeType;
			$dimNativeType = $dimResult->nativeType;
			$unsetNativeType = $exprVarNativeType->unsetOffset($dimNativeType);

			$assignExprGen = $scope->assignExpression($expr->var, $unsetType, $unsetNativeType);
			yield from $assignExprGen;
			$scope = $assignExprGen->getReturn()->invalidateExpression(
				new FuncCall(new FullyQualified('count'), [new Arg($expr->var)]),
			)->invalidateExpression(
				new FuncCall(new FullyQualified('sizeof'), [new Arg($expr->var)]),
			)->invalidateExpression(
				new FuncCall(new Name('count'), [new Arg($expr->var)]),
			)->invalidateExpression(
				new FuncCall(new Name('sizeof'), [new Arg($expr->var)]),
			);

			if ($expr->var instanceof Expr\ArrayDimFetch && $expr->var->dim !== null) {
				$exprVarVarResult = (yield ExprAnalysisRequest::createNoopRequest($expr->var->var, $scope));
				$exprVarDimResult = (yield ExprAnalysisRequest::createNoopRequest($expr->var->dim, $scope));
				$assignExprGen = $scope->assignExpression(
					$expr->var->var,
					$exprVarVarResult->type->setOffsetValueType(
						$exprVarDimResult->type,
						$exprVarType,
					),
					$exprVarVarResult->nativeType->setOffsetValueType(
						$exprVarDimResult->nativeType,
						$exprVarNativeType,
					),
				);
				yield from $assignExprGen;
				$scope = $assignExprGen->getReturn();
			}
		}

		return $scope->invalidateExpression($expr);
	}

	/**
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, self>
	 */
	private function addTypeToExpression(Expr $expr, Type $type): Generator
	{
		$exprResult = yield ExprAnalysisRequest::createNoopRequest($expr, $this);
		$originalExprType = $exprResult->type;
		$nativeType = $exprResult->nativeType;

		if ($originalExprType->equals($nativeType)) {
			$newType = TypeCombinator::intersect($type, $originalExprType);
			if ($newType->isConstantScalarValue()->yes() && $newType->equals($originalExprType)) {
				// don't add the same type over and over again to improve performance
				return $this;
			}
			$gen = $this->specifyExpressionType($expr, $newType, $newType, TrinaryLogic::createYes());
			yield from $gen;
			return $gen->getReturn();
		}

		$gen = $this->specifyExpressionType(
			$expr,
			TypeCombinator::intersect($type, $originalExprType),
			TypeCombinator::intersect($type, $nativeType),
			TrinaryLogic::createYes(),
		);
		yield from $gen;
		return $gen->getReturn();
	}

	/**
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, self>
	 */
	private function removeTypeFromExpression(Expr $expr, Type $typeToRemove): Generator
	{
		$exprResult = yield ExprAnalysisRequest::createNoopRequest($expr, $this);
		$exprType = $exprResult->type;
		if (
			$exprType instanceof NeverType ||
			$typeToRemove instanceof NeverType
		) {
			return $this;
		}
		$gen = $this->specifyExpressionType(
			$expr,
			TypeCombinator::remove($exprType, $typeToRemove),
			TypeCombinator::remove($exprResult->nativeType, $typeToRemove),
			TrinaryLogic::createYes(),
		);
		yield from $gen;

		return $gen->getReturn();
	}

	/** @api */
	public function isInFirstLevelStatement(): bool
	{
		return $this->inFirstLevelStatement;
	}

	public function exitFirstLevelStatements(): self
	{
		if (!$this->inFirstLevelStatement) {
			return $this;
		}

		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$this->expressionTypes,
			$this->nativeExpressionTypes,
			$this->conditionalExpressions,
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
			false,
			$this->currentlyAssignedExpressions,
			$this->currentlyAllowedUndefinedExpressions,
			$this->inFunctionCallsStack,
			$this->afterExtractCall,
			$this->parentScope,
			$this->nativeTypesPromoted,
		);
	}

	public function mergeWith(?self $otherScope): self
	{
		if ($otherScope === null) {
			return $this;
		}
		$ourExpressionTypes = $this->expressionTypes;
		$theirExpressionTypes = $otherScope->expressionTypes;

		$mergedExpressionTypes = $this->mergeVariableHolders($ourExpressionTypes, $theirExpressionTypes);
		$conditionalExpressions = $this->intersectConditionalExpressions($otherScope->conditionalExpressions);
		$conditionalExpressions = $this->createConditionalExpressions(
			$conditionalExpressions,
			$ourExpressionTypes,
			$theirExpressionTypes,
			$mergedExpressionTypes,
		);
		$conditionalExpressions = $this->createConditionalExpressions(
			$conditionalExpressions,
			$theirExpressionTypes,
			$ourExpressionTypes,
			$mergedExpressionTypes,
		);
		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$mergedExpressionTypes,
			$this->mergeVariableHolders($this->nativeExpressionTypes, $otherScope->nativeExpressionTypes),
			$conditionalExpressions,
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
			$this->inFirstLevelStatement,
			[],
			[],
			[],
			$this->afterExtractCall && $otherScope->afterExtractCall,
			$this->parentScope,
			$this->nativeTypesPromoted,
		);
	}

	/**
	 * @param array<string, ConditionalExpressionHolder[]> $otherConditionalExpressions
	 * @return array<string, ConditionalExpressionHolder[]>
	 */
	private function intersectConditionalExpressions(array $otherConditionalExpressions): array
	{
		$newConditionalExpressions = [];
		foreach ($this->conditionalExpressions as $exprString => $holders) {
			if (!array_key_exists($exprString, $otherConditionalExpressions)) {
				continue;
			}

			$otherHolders = $otherConditionalExpressions[$exprString];
			foreach (array_keys($holders) as $key) {
				if (!array_key_exists($key, $otherHolders)) {
					continue 2;
				}
			}

			$newConditionalExpressions[$exprString] = $holders;
		}

		return $newConditionalExpressions;
	}

	/**
	 * @param array<string, ConditionalExpressionHolder[]> $conditionalExpressions
	 * @param array<string, ExpressionTypeHolder> $ourExpressionTypes
	 * @param array<string, ExpressionTypeHolder> $theirExpressionTypes
	 * @param array<string, ExpressionTypeHolder> $mergedExpressionTypes
	 * @return array<string, ConditionalExpressionHolder[]>
	 */
	private function createConditionalExpressions(
		array $conditionalExpressions,
		array $ourExpressionTypes,
		array $theirExpressionTypes,
		array $mergedExpressionTypes,
	): array
	{
		$newVariableTypes = $ourExpressionTypes;
		foreach ($theirExpressionTypes as $exprString => $holder) {
			if (!array_key_exists($exprString, $mergedExpressionTypes)) {
				continue;
			}

			if (!$mergedExpressionTypes[$exprString]->getType()->equals($holder->getType())) {
				continue;
			}

			unset($newVariableTypes[$exprString]);
		}

		$typeGuards = [];
		foreach ($newVariableTypes as $exprString => $holder) {
			if (!$holder->getCertainty()->yes()) {
				continue;
			}
			if (!array_key_exists($exprString, $mergedExpressionTypes)) {
				continue;
			}
			if ($mergedExpressionTypes[$exprString]->getType()->equals($holder->getType())) {
				continue;
			}

			$typeGuards[$exprString] = $holder;
		}

		if (count($typeGuards) === 0) {
			return $conditionalExpressions;
		}

		foreach ($newVariableTypes as $exprString => $holder) {
			if (
				array_key_exists($exprString, $mergedExpressionTypes)
				&& $mergedExpressionTypes[$exprString]->equals($holder)
			) {
				continue;
			}

			$variableTypeGuards = $typeGuards;
			unset($variableTypeGuards[$exprString]);

			if (count($variableTypeGuards) === 0) {
				continue;
			}

			$conditionalExpression = new ConditionalExpressionHolder($variableTypeGuards, $holder);
			$conditionalExpressions[$exprString][$conditionalExpression->getKey()] = $conditionalExpression;
		}

		foreach ($mergedExpressionTypes as $exprString => $mergedExprTypeHolder) {
			if (array_key_exists($exprString, $ourExpressionTypes)) {
				continue;
			}

			$conditionalExpression = new ConditionalExpressionHolder($typeGuards, new ExpressionTypeHolder($mergedExprTypeHolder->getExpr(), new ErrorType(), TrinaryLogic::createNo()));
			$conditionalExpressions[$exprString][$conditionalExpression->getKey()] = $conditionalExpression;
		}

		return $conditionalExpressions;
	}

	/**
	 * @param array<string, ExpressionTypeHolder> $ourVariableTypeHolders
	 * @param array<string, ExpressionTypeHolder> $theirVariableTypeHolders
	 * @return array<string, ExpressionTypeHolder>
	 */
	private function mergeVariableHolders(array $ourVariableTypeHolders, array $theirVariableTypeHolders): array
	{
		$intersectedVariableTypeHolders = [];
		$globalVariableCallback = fn (Node $node) => $node instanceof Variable && is_string($node->name) && $this->isGlobalVariable($node->name);
		$nodeFinder = new NodeFinder();
		foreach ($ourVariableTypeHolders as $exprString => $variableTypeHolder) {
			if (isset($theirVariableTypeHolders[$exprString])) {
				if ($variableTypeHolder === $theirVariableTypeHolders[$exprString]) {
					$intersectedVariableTypeHolders[$exprString] = $variableTypeHolder;
					continue;
				}

				$intersectedVariableTypeHolders[$exprString] = $variableTypeHolder->and($theirVariableTypeHolders[$exprString]);
			} else {
				$expr = $variableTypeHolder->getExpr();
				if ($nodeFinder->findFirst($expr, $globalVariableCallback) !== null) {
					continue;
				}

				$intersectedVariableTypeHolders[$exprString] = ExpressionTypeHolder::createMaybe($variableTypeHolder->getExpr(), $variableTypeHolder->getType());
			}
		}

		foreach ($theirVariableTypeHolders as $exprString => $variableTypeHolder) {
			if (isset($intersectedVariableTypeHolders[$exprString])) {
				continue;
			}

			$expr = $variableTypeHolder->getExpr();
			if ($nodeFinder->findFirst($expr, $globalVariableCallback) !== null) {
				continue;
			}

			$intersectedVariableTypeHolders[$exprString] = ExpressionTypeHolder::createMaybe($variableTypeHolder->getExpr(), $variableTypeHolder->getType());
		}

		return $intersectedVariableTypeHolders;
	}

	public function processFinallyScope(self $finallyScope, self $originalFinallyScope): self
	{
		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$this->processFinallyScopeVariableTypeHolders(
				$this->expressionTypes,
				$finallyScope->expressionTypes,
				$originalFinallyScope->expressionTypes,
			),
			$this->processFinallyScopeVariableTypeHolders(
				$this->nativeExpressionTypes,
				$finallyScope->nativeExpressionTypes,
				$originalFinallyScope->nativeExpressionTypes,
			),
			$this->conditionalExpressions,
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
			$this->inFirstLevelStatement,
			[],
			[],
			[],
			$this->afterExtractCall,
			$this->parentScope,
			$this->nativeTypesPromoted,
		);
	}

	/**
	 * @param array<string, ExpressionTypeHolder> $ourVariableTypeHolders
	 * @param array<string, ExpressionTypeHolder> $finallyVariableTypeHolders
	 * @param array<string, ExpressionTypeHolder> $originalVariableTypeHolders
	 * @return array<string, ExpressionTypeHolder>
	 */
	private function processFinallyScopeVariableTypeHolders(
		array $ourVariableTypeHolders,
		array $finallyVariableTypeHolders,
		array $originalVariableTypeHolders,
	): array
	{
		foreach ($finallyVariableTypeHolders as $exprString => $variableTypeHolder) {
			if (
				isset($originalVariableTypeHolders[$exprString])
				&& !$originalVariableTypeHolders[$exprString]->getType()->equals($variableTypeHolder->getType())
			) {
				$ourVariableTypeHolders[$exprString] = $variableTypeHolder;
				continue;
			}

			if (isset($originalVariableTypeHolders[$exprString])) {
				continue;
			}

			$ourVariableTypeHolders[$exprString] = $variableTypeHolder;
		}

		return $ourVariableTypeHolders;
	}

	/**
	 * @param Node\ClosureUse[] $byRefUses
	 */
	public function processClosureScope(
		self $closureScope,
		?self $prevScope,
		array $byRefUses,
	): self
	{
		$nativeExpressionTypes = $this->nativeExpressionTypes;
		$expressionTypes = $this->expressionTypes;
		if (count($byRefUses) === 0) {
			return $this;
		}

		foreach ($byRefUses as $use) {
			if (!is_string($use->var->name)) {
				throw new ShouldNotHappenException();
			}

			$variableName = $use->var->name;
			$variableExprString = '$' . $variableName;

			if (!$closureScope->hasVariableType($variableName)->yes()) {
				$holder = ExpressionTypeHolder::createYes($use->var, new NullType());
				$expressionTypes[$variableExprString] = $holder;
				$nativeExpressionTypes[$variableExprString] = $holder;
				continue;
			}

			$variableType = $closureScope->getVariableType($variableName);

			if ($prevScope !== null) {
				$prevVariableType = $prevScope->getVariableType($variableName);
				if (!$variableType->equals($prevVariableType)) {
					$variableType = TypeCombinator::union($variableType, $prevVariableType);
					$variableType = $this->generalizeType($variableType, $prevVariableType, 0);
				}
			}

			$expressionTypes[$variableExprString] = ExpressionTypeHolder::createYes($use->var, $variableType);
			$nativeExpressionTypes[$variableExprString] = ExpressionTypeHolder::createYes($use->var, $variableType);
		}

		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$expressionTypes,
			$nativeExpressionTypes,
			$this->conditionalExpressions,
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
			$this->inFirstLevelStatement,
			[],
			[],
			$this->inFunctionCallsStack,
			$this->afterExtractCall,
			$this->parentScope,
			$this->nativeTypesPromoted,
		);
	}

	public function processAlwaysIterableForeachScopeWithoutPollute(self $finalScope): self
	{
		$expressionTypes = $this->expressionTypes;
		foreach ($finalScope->expressionTypes as $variableExprString => $variableTypeHolder) {
			if (!isset($expressionTypes[$variableExprString])) {
				$expressionTypes[$variableExprString] = ExpressionTypeHolder::createMaybe($variableTypeHolder->getExpr(), $variableTypeHolder->getType());
				continue;
			}

			$expressionTypes[$variableExprString] = new ExpressionTypeHolder(
				$variableTypeHolder->getExpr(),
				$variableTypeHolder->getType(),
				$variableTypeHolder->getCertainty()->and($expressionTypes[$variableExprString]->getCertainty()),
			);
		}
		$nativeTypes = $this->nativeExpressionTypes;
		foreach ($finalScope->nativeExpressionTypes as $variableExprString => $variableTypeHolder) {
			if (!isset($nativeTypes[$variableExprString])) {
				$nativeTypes[$variableExprString] = ExpressionTypeHolder::createMaybe($variableTypeHolder->getExpr(), $variableTypeHolder->getType());
				continue;
			}

			$nativeTypes[$variableExprString] = new ExpressionTypeHolder(
				$variableTypeHolder->getExpr(),
				$variableTypeHolder->getType(),
				$variableTypeHolder->getCertainty()->and($nativeTypes[$variableExprString]->getCertainty()),
			);
		}

		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$expressionTypes,
			$nativeTypes,
			$this->conditionalExpressions,
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
			$this->inFirstLevelStatement,
			[],
			[],
			[],
			$this->afterExtractCall,
			$this->parentScope,
			$this->nativeTypesPromoted,
		);
	}

	public function generalizeWith(self $otherScope): self
	{
		$variableTypeHolders = $this->generalizeVariableTypeHolders(
			$this->expressionTypes,
			$otherScope->expressionTypes,
		);
		$nativeTypes = $this->generalizeVariableTypeHolders(
			$this->nativeExpressionTypes,
			$otherScope->nativeExpressionTypes,
		);

		return $this->scopeFactory->create(
			$this->context,
			$this->isDeclareStrictTypes(),
			$this->getFunction(),
			$this->getNamespace(),
			$variableTypeHolders,
			$nativeTypes,
			$this->conditionalExpressions,
			$this->inClosureBindScopeClasses,
			$this->anonymousFunctionReflection,
			$this->inFirstLevelStatement,
			[],
			[],
			[],
			$this->afterExtractCall,
			$this->parentScope,
			$this->nativeTypesPromoted,
		);
	}

	/**
	 * @param array<string, ExpressionTypeHolder> $variableTypeHolders
	 * @param array<string, ExpressionTypeHolder> $otherVariableTypeHolders
	 * @return array<string, ExpressionTypeHolder>
	 */
	private function generalizeVariableTypeHolders(
		array $variableTypeHolders,
		array $otherVariableTypeHolders,
	): array
	{
		uksort($variableTypeHolders, static fn (string $exprA, string $exprB): int => strlen($exprA) <=> strlen($exprB));

		$generalizedExpressions = [];
		$newVariableTypeHolders = [];
		foreach ($variableTypeHolders as $variableExprString => $variableTypeHolder) {
			foreach ($generalizedExpressions as $generalizedExprString => $generalizedExpr) {
				if (!$this->shouldInvalidateExpression($generalizedExprString, $generalizedExpr, $variableTypeHolder->getExpr())) {
					continue;
				}

				continue 2;
			}
			if (!isset($otherVariableTypeHolders[$variableExprString])) {
				$newVariableTypeHolders[$variableExprString] = $variableTypeHolder;
				continue;
			}

			$generalizedType = $this->generalizeType($variableTypeHolder->getType(), $otherVariableTypeHolders[$variableExprString]->getType(), 0);
			if (
				!$generalizedType->equals($variableTypeHolder->getType())
			) {
				$generalizedExpressions[$variableExprString] = $variableTypeHolder->getExpr();
			}
			$newVariableTypeHolders[$variableExprString] = new ExpressionTypeHolder(
				$variableTypeHolder->getExpr(),
				$generalizedType,
				$variableTypeHolder->getCertainty(),
			);
		}

		return $newVariableTypeHolders;
	}

	private function generalizeType(Type $a, Type $b, int $depth): Type
	{
		if ($a->equals($b)) {
			return $a;
		}

		$constantIntegers = ['a' => [], 'b' => []];
		$constantFloats = ['a' => [], 'b' => []];
		$constantBooleans = ['a' => [], 'b' => []];
		$constantStrings = ['a' => [], 'b' => []];
		$constantArrays = ['a' => [], 'b' => []];
		$generalArrays = ['a' => [], 'b' => []];
		$integerRanges = ['a' => [], 'b' => []];
		$otherTypes = [];

		foreach ([
			'a' => TypeUtils::flattenTypes($a),
			'b' => TypeUtils::flattenTypes($b),
		] as $key => $types) {
			foreach ($types as $type) {
				if ($type instanceof ConstantIntegerType) {
					$constantIntegers[$key][] = $type;
					continue;
				}
				if ($type instanceof ConstantFloatType) {
					$constantFloats[$key][] = $type;
					continue;
				}
				if ($type instanceof ConstantBooleanType) {
					$constantBooleans[$key][] = $type;
					continue;
				}
				if ($type instanceof ConstantStringType) {
					$constantStrings[$key][] = $type;
					continue;
				}
				if ($type->isConstantArray()->yes()) {
					$constantArrays[$key][] = $type;
					continue;
				}
				if ($type->isArray()->yes()) {
					$generalArrays[$key][] = $type;
					continue;
				}
				if ($type instanceof IntegerRangeType) {
					$integerRanges[$key][] = $type;
					continue;
				}

				$otherTypes[] = $type;
			}
		}

		$resultTypes = [];
		foreach ([
			$constantFloats,
			$constantBooleans,
			$constantStrings,
		] as $constantTypes) {
			if (count($constantTypes['a']) === 0) {
				if (count($constantTypes['b']) > 0) {
					$resultTypes[] = TypeCombinator::union(...$constantTypes['b']);
				}
				continue;
			} elseif (count($constantTypes['b']) === 0) {
				$resultTypes[] = TypeCombinator::union(...$constantTypes['a']);
				continue;
			}

			$aTypes = TypeCombinator::union(...$constantTypes['a']);
			$bTypes = TypeCombinator::union(...$constantTypes['b']);
			if ($aTypes->equals($bTypes)) {
				$resultTypes[] = $aTypes;
				continue;
			}

			$resultTypes[] = TypeCombinator::union(...$constantTypes['a'], ...$constantTypes['b'])->generalize(GeneralizePrecision::moreSpecific());
		}

		if (count($constantArrays['a']) > 0) {
			if (count($constantArrays['b']) === 0) {
				$resultTypes[] = TypeCombinator::union(...$constantArrays['a']);
			} else {
				$constantArraysA = TypeCombinator::union(...$constantArrays['a']);
				$constantArraysB = TypeCombinator::union(...$constantArrays['b']);
				if (
					$constantArraysA->getIterableKeyType()->equals($constantArraysB->getIterableKeyType())
					&& $constantArraysA->getArraySize()->getGreaterOrEqualType($this->phpVersion)->isSuperTypeOf($constantArraysB->getArraySize())->yes()
				) {
					$resultArrayBuilder = ConstantArrayTypeBuilder::createEmpty();
					foreach (TypeUtils::flattenTypes($constantArraysA->getIterableKeyType()) as $keyType) {
						$resultArrayBuilder->setOffsetValueType(
							$keyType,
							$this->generalizeType(
								$constantArraysA->getOffsetValueType($keyType),
								$constantArraysB->getOffsetValueType($keyType),
								$depth + 1,
							),
							!$constantArraysA->hasOffsetValueType($keyType)->and($constantArraysB->hasOffsetValueType($keyType))->negate()->no(),
						);
					}

					$resultTypes[] = $resultArrayBuilder->getArray();
				} else {
					$resultType = new ArrayType(
						TypeCombinator::union($this->generalizeType($constantArraysA->getIterableKeyType(), $constantArraysB->getIterableKeyType(), $depth + 1)),
						TypeCombinator::union($this->generalizeType($constantArraysA->getIterableValueType(), $constantArraysB->getIterableValueType(), $depth + 1)),
					);
					if (
						$constantArraysA->isIterableAtLeastOnce()->yes()
						&& $constantArraysB->isIterableAtLeastOnce()->yes()
						&& $constantArraysA->getArraySize()->getGreaterOrEqualType($this->phpVersion)->isSuperTypeOf($constantArraysB->getArraySize())->yes()
					) {
						$resultType = TypeCombinator::intersect($resultType, new NonEmptyArrayType());
					}
					if ($constantArraysA->isList()->yes() && $constantArraysB->isList()->yes()) {
						$resultType = TypeCombinator::intersect($resultType, new AccessoryArrayListType());
					}
					$resultTypes[] = $resultType;
				}
			}
		} elseif (count($constantArrays['b']) > 0) {
			$resultTypes[] = TypeCombinator::union(...$constantArrays['b']);
		}

		if (count($generalArrays['a']) > 0) {
			if (count($generalArrays['b']) === 0) {
				$resultTypes[] = TypeCombinator::union(...$generalArrays['a']);
			} else {
				$generalArraysA = TypeCombinator::union(...$generalArrays['a']);
				$generalArraysB = TypeCombinator::union(...$generalArrays['b']);

				$aValueType = $generalArraysA->getIterableValueType();
				$bValueType = $generalArraysB->getIterableValueType();
				if (
					$aValueType->isArray()->yes()
					&& $aValueType->isConstantArray()->no()
					&& $bValueType->isArray()->yes()
					&& $bValueType->isConstantArray()->no()
				) {
					$aDepth = self::getArrayDepth($aValueType) + $depth;
					$bDepth = self::getArrayDepth($bValueType) + $depth;
					if (
						($aDepth > 2 || $bDepth > 2)
						&& abs($aDepth - $bDepth) > 0
					) {
						$aValueType = new MixedType();
						$bValueType = new MixedType();
					}
				}

				$resultType = new ArrayType(
					TypeCombinator::union($this->generalizeType($generalArraysA->getIterableKeyType(), $generalArraysB->getIterableKeyType(), $depth + 1)),
					TypeCombinator::union($this->generalizeType($aValueType, $bValueType, $depth + 1)),
				);
				if ($generalArraysA->isIterableAtLeastOnce()->yes() && $generalArraysB->isIterableAtLeastOnce()->yes()) {
					$resultType = TypeCombinator::intersect($resultType, new NonEmptyArrayType());
				}
				if ($generalArraysA->isList()->yes() && $generalArraysB->isList()->yes()) {
					$resultType = TypeCombinator::intersect($resultType, new AccessoryArrayListType());
				}
				if ($generalArraysA->isOversizedArray()->yes() && $generalArraysB->isOversizedArray()->yes()) {
					$resultType = TypeCombinator::intersect($resultType, new OversizedArrayType());
				}
				$resultTypes[] = $resultType;
			}
		} elseif (count($generalArrays['b']) > 0) {
			$resultTypes[] = TypeCombinator::union(...$generalArrays['b']);
		}

		if (count($constantIntegers['a']) > 0) {
			if (count($constantIntegers['b']) === 0) {
				$resultTypes[] = TypeCombinator::union(...$constantIntegers['a']);
			} else {
				$constantIntegersA = TypeCombinator::union(...$constantIntegers['a']);
				$constantIntegersB = TypeCombinator::union(...$constantIntegers['b']);

				if ($constantIntegersA->equals($constantIntegersB)) {
					$resultTypes[] = $constantIntegersA;
				} else {
					$min = null;
					$max = null;
					foreach ($constantIntegers['a'] as $int) {
						if ($min === null || $int->getValue() < $min) {
							$min = $int->getValue();
						}
						if ($max !== null && $int->getValue() <= $max) {
							continue;
						}

						$max = $int->getValue();
					}

					$gotGreater = false;
					$gotSmaller = false;
					foreach ($constantIntegers['b'] as $int) {
						if ($int->getValue() > $max) {
							$gotGreater = true;
						}
						if ($int->getValue() >= $min) {
							continue;
						}

						$gotSmaller = true;
					}

					if ($gotGreater && $gotSmaller) {
						$resultTypes[] = new IntegerType();
					} elseif ($gotGreater) {
						$resultTypes[] = IntegerRangeType::fromInterval($min, null);
					} elseif ($gotSmaller) {
						$resultTypes[] = IntegerRangeType::fromInterval(null, $max);
					} else {
						$resultTypes[] = TypeCombinator::union($constantIntegersA, $constantIntegersB);
					}
				}
			}
		} elseif (count($constantIntegers['b']) > 0) {
			$resultTypes[] = TypeCombinator::union(...$constantIntegers['b']);
		}

		if (count($integerRanges['a']) > 0) {
			if (count($integerRanges['b']) === 0) {
				$resultTypes[] = TypeCombinator::union(...$integerRanges['a']);
			} else {
				$integerRangesA = TypeCombinator::union(...$integerRanges['a']);
				$integerRangesB = TypeCombinator::union(...$integerRanges['b']);

				if ($integerRangesA->equals($integerRangesB)) {
					$resultTypes[] = $integerRangesA;
				} else {
					$min = null;
					$max = null;
					foreach ($integerRanges['a'] as $range) {
						if ($range->getMin() === null) {
							$rangeMin = PHP_INT_MIN;
						} else {
							$rangeMin = $range->getMin();
						}
						if ($range->getMax() === null) {
							$rangeMax = PHP_INT_MAX;
						} else {
							$rangeMax = $range->getMax();
						}

						if ($min === null || $rangeMin < $min) {
							$min = $rangeMin;
						}
						if ($max !== null && $rangeMax <= $max) {
							continue;
						}

						$max = $rangeMax;
					}

					$gotGreater = false;
					$gotSmaller = false;
					foreach ($integerRanges['b'] as $range) {
						if ($range->getMin() === null) {
							$rangeMin = PHP_INT_MIN;
						} else {
							$rangeMin = $range->getMin();
						}
						if ($range->getMax() === null) {
							$rangeMax = PHP_INT_MAX;
						} else {
							$rangeMax = $range->getMax();
						}

						if ($rangeMax > $max) {
							$gotGreater = true;
						}
						if ($rangeMin >= $min) {
							continue;
						}

						$gotSmaller = true;
					}

					if ($min === PHP_INT_MIN) {
						$min = null;
					}
					if ($max === PHP_INT_MAX) {
						$max = null;
					}

					if ($gotGreater && $gotSmaller) {
						$resultTypes[] = new IntegerType();
					} elseif ($gotGreater) {
						$resultTypes[] = IntegerRangeType::fromInterval($min, null);
					} elseif ($gotSmaller) {
						$resultTypes[] = IntegerRangeType::fromInterval(null, $max);
					} else {
						$resultTypes[] = TypeCombinator::union($integerRangesA, $integerRangesB);
					}
				}
			}
		} elseif (count($integerRanges['b']) > 0) {
			$resultTypes[] = TypeCombinator::union(...$integerRanges['b']);
		}

		$accessoryTypes = array_map(
			static fn (Type $type): Type => $type->generalize(GeneralizePrecision::moreSpecific()),
			TypeUtils::getAccessoryTypes($a),
		);

		return TypeCombinator::union(TypeCombinator::intersect(
			TypeCombinator::union(...$resultTypes, ...$otherTypes),
			...$accessoryTypes,
		), ...$otherTypes);
	}

	private static function getArrayDepth(Type $type): int
	{
		$depth = 0;
		$arrays = TypeUtils::toBenevolentUnion($type)->getArrays();
		while (count($arrays) > 0) {
			$temp = $type->getIterableValueType();
			$type = $temp;
			$arrays = TypeUtils::toBenevolentUnion($type)->getArrays();
			$depth++;
		}

		return $depth;
	}

	public function equals(self $otherScope): bool
	{
		if (!$this->context->equals($otherScope->context)) {
			return false;
		}

		if (!$this->compareVariableTypeHolders($this->expressionTypes, $otherScope->expressionTypes)) {
			return false;
		}
		return $this->compareVariableTypeHolders($this->nativeExpressionTypes, $otherScope->nativeExpressionTypes);
	}

	/**
	 * @param array<string, ExpressionTypeHolder> $variableTypeHolders
	 * @param array<string, ExpressionTypeHolder> $otherVariableTypeHolders
	 */
	private function compareVariableTypeHolders(array $variableTypeHolders, array $otherVariableTypeHolders): bool
	{
		if (count($variableTypeHolders) !== count($otherVariableTypeHolders)) {
			return false;
		}
		foreach ($variableTypeHolders as $variableExprString => $variableTypeHolder) {
			if (!isset($otherVariableTypeHolders[$variableExprString])) {
				return false;
			}

			if (!$variableTypeHolder->getCertainty()->equals($otherVariableTypeHolders[$variableExprString]->getCertainty())) {
				return false;
			}

			if (!$variableTypeHolder->getType()->equals($otherVariableTypeHolders[$variableExprString]->getType())) {
				return false;
			}

			unset($otherVariableTypeHolders[$variableExprString]);
		}

		return true;
	}

	public function getPhpVersion(): PhpVersions
	{
		$constType = $this->getGlobalConstantType(new Name('PHP_VERSION_ID'));

		$isOverallPhpVersionRange = false;
		if (
			$constType instanceof IntegerRangeType
			&& $constType->getMin() === ConstantResolver::PHP_MIN_ANALYZABLE_VERSION_ID
			&& ($constType->getMax() === null || $constType->getMax() === PhpVersionFactory::MAX_PHP_VERSION)
		) {
			$isOverallPhpVersionRange = true;
		}

		if ($constType !== null && !$isOverallPhpVersionRange) {
			return new PhpVersions($constType);
		}

		if (is_array($this->configPhpVersion)) {
			return new PhpVersions(IntegerRangeType::fromInterval($this->configPhpVersion['min'], $this->configPhpVersion['max']));
		}
		return new PhpVersions(new ConstantIntegerType($this->phpVersion->getVersionId()));
	}

	public function invokeNodeCallback(Node $node): void
	{
		Fiber::suspend(new NodeCallbackRequest($node, $this, null));
	}

}
