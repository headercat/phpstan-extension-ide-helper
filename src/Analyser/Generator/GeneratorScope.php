<?php 

namespace PHPStan\Analyser\Generator;
return;

use Fiber;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PHPStan\Analyser\ExpressionTypeHolder;
use PHPStan\Analyser\NodeCallbackInvoker;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\ScopeContext;
use PHPStan\Node\Printer\ExprPrinter;
use PHPStan\Php\PhpVersions;
use PHPStan\Reflection\ClassConstantReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\ExtendedPropertyReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\Php\PhpFunctionFromParserNodeReflection;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\ShouldNotHappenException;
use PHPStan\TrinaryLogic;
use PHPStan\Type\ClosureType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeWithClassName;
use function array_key_exists;

final class GeneratorScope implements Scope, NodeCallbackInvoker
{

	/** @var non-empty-string|null */
	private ?string $namespace;

	/**
	 * @param array<string, ExpressionTypeHolder> $expressionTypes
	 * @param array<string, ExpressionTypeHolder> $nativeExpressionTypes
	 */
	public function __construct(
		private InternalGeneratorScopeFactory $scopeFactory,
		private ExprPrinter $exprPrinter,
		private ScopeContext $context,
		private bool $declareStrictTypes = false,
		private PhpFunctionFromParserNodeReflection|null $function = null,
		?string $namespace = null,
		private array $expressionTypes = [],
		private array $nativeExpressionTypes = [],
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
		$exprString = $this->exprPrinter->printExpr($expr);
		if (array_key_exists($exprString, $this->expressionTypes)) {
			return $this->expressionTypes[$exprString]->getType();
		}

		return null;
	}

	public function assignVariable(string $variableName, Type $type): self
	{
		$exprString = '$' . $variableName;

		$expressionTypes = $this->expressionTypes;
		$expressionTypes[$exprString] = ExpressionTypeHolder::createYes(new Expr\Variable($variableName), $type);

		return $this->scopeFactory->create(
			$this->context,
			$this->declareStrictTypes,
			$this->function,
			$this->namespace,
			$expressionTypes,
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

	public function isInClass(): bool
	{
		// TODO: Implement isInClass() method.
		return false;
	}

	public function getClassReflection(): ?ClassReflection
	{
		// TODO: Implement getClassReflection() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function canAccessProperty(PropertyReflection $propertyReflection): bool
	{
		// TODO: Implement canAccessProperty() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function canReadProperty(ExtendedPropertyReflection $propertyReflection): bool
	{
		// TODO: Implement canReadProperty() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function canWriteProperty(ExtendedPropertyReflection $propertyReflection): bool
	{
		// TODO: Implement canWriteProperty() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function canCallMethod(MethodReflection $methodReflection): bool
	{
		// TODO: Implement canCallMethod() method.
		return true;
	}

	public function canAccessConstant(ClassConstantReflection $constantReflection): bool
	{
		// TODO: Implement canAccessConstant() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	/** @api */
	public function getNamespace(): ?string
	{
		return $this->namespace;
	}

	public function getFile(): string
	{
		// TODO: Implement getFile() method.
		return 'foo.php';
	}

	public function getFileDescription(): string
	{
		// TODO: Implement getFileDescription() method.
		return 'foo.php';
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

	public function isInTrait(): bool
	{
		// TODO: Implement isInTrait() method.
		return false;
	}

	public function getTraitReflection(): ?ClassReflection
	{
		// TODO: Implement getTraitReflection() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function getFunction(): ?PhpFunctionFromParserNodeReflection
	{
		// TODO: Implement getFunction() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function getFunctionName(): ?string
	{
		// TODO: Implement getFunctionName() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function getParentScope(): ?self
	{
		// TODO: Implement getParentScope() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function hasVariableType(string $variableName): TrinaryLogic
	{
		// TODO: Implement hasVariableType() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function getVariableType(string $variableName): Type
	{
		// TODO: Implement getVariableType() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function canAnyVariableExist(): bool
	{
		// TODO: Implement canAnyVariableExist() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function getDefinedVariables(): array
	{
		// TODO: Implement getDefinedVariables() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function getMaybeDefinedVariables(): array
	{
		// TODO: Implement getMaybeDefinedVariables() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function hasConstant(Name $name): bool
	{
		// TODO: Implement hasConstant() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function getPropertyReflection(Type $typeWithProperty, string $propertyName): ?ExtendedPropertyReflection
	{
		// TODO: Implement getPropertyReflection() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function getInstancePropertyReflection(Type $typeWithProperty, string $propertyName): ?ExtendedPropertyReflection
	{
		// TODO: Implement getInstancePropertyReflection() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function getStaticPropertyReflection(Type $typeWithProperty, string $propertyName): ?ExtendedPropertyReflection
	{
		// TODO: Implement getStaticPropertyReflection() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function getMethodReflection(Type $typeWithMethod, string $methodName): ?ExtendedMethodReflection
	{
		// TODO: Implement getMethodReflection() method.
		return null;
	}

	public function getConstantReflection(Type $typeWithConstant, string $constantName): ?ClassConstantReflection
	{
		// TODO: Implement getConstantReflection() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function getConstantExplicitTypeFromConfig(string $constantName, Type $constantType): Type
	{
		// TODO: Implement getConstantExplicitTypeFromConfig() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function getIterableKeyType(Type $iteratee): Type
	{
		// TODO: Implement getIterableKeyType() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function getIterableValueType(Type $iteratee): Type
	{
		// TODO: Implement getIterableValueType() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function isInAnonymousFunction(): bool
	{
		// TODO: Implement isInAnonymousFunction() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function getAnonymousFunctionReflection(): ?ClosureType
	{
		// TODO: Implement getAnonymousFunctionReflection() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function getAnonymousFunctionReturnType(): ?Type
	{
		// TODO: Implement getAnonymousFunctionReturnType() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	/** @api */
	public function getType(Expr $node): Type
	{
		return Fiber::suspend(new ExprAnalysisRequest($node, $this))->type;
	}

	public function getNativeType(Expr $expr): Type
	{
		// TODO: Implement getNativeType() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function getKeepVoidType(Expr $node): Type
	{
		// TODO: Implement getKeepVoidType() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function resolveName(Name $name): string
	{
		// TODO: Implement resolveName() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function resolveTypeByName(Name $name): TypeWithClassName
	{
		// TODO: Implement resolveTypeByName() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	/**
	 * @param mixed $value
	 */
	public function getTypeFromValue($value): Type
	{
		// TODO: Implement getTypeFromValue() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function hasExpressionType(Expr $node): TrinaryLogic
	{
		// TODO: Implement hasExpressionType() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function isInClassExists(string $className): bool
	{
		// TODO: Implement isInClassExists() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function isInFunctionExists(string $functionName): bool
	{
		// TODO: Implement isInFunctionExists() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function isInClosureBind(): bool
	{
		// TODO: Implement isInClosureBind() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function getFunctionCallStack(): array
	{
		// TODO: Implement getFunctionCallStack() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function getFunctionCallStackWithParameters(): array
	{
		// TODO: Implement getFunctionCallStackWithParameters() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function isParameterValueNullable(Param $parameter): bool
	{
		// TODO: Implement isParameterValueNullable() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	/**
	 * @param Node\Name|Node\Identifier|Node\ComplexType|null $type
	 */
	public function getFunctionType($type, bool $isNullable, bool $isVariadic): Type
	{
		// TODO: Implement getFunctionType() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function isInExpressionAssign(Expr $expr): bool
	{
		// TODO: Implement isInExpressionAssign() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function isUndefinedExpressionAllowed(Expr $expr): bool
	{
		// TODO: Implement isUndefinedExpressionAllowed() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function filterByTruthyValue(Expr $expr): Scope
	{
		// TODO: Implement filterByTruthyValue() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function filterByFalseyValue(Expr $expr): Scope
	{
		// TODO: Implement filterByFalseyValue() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function isInFirstLevelStatement(): bool
	{
		// TODO: Implement isInFirstLevelStatement() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function getPhpVersion(): PhpVersions
	{
		// TODO: Implement getPhpVersion() method.
		throw new ShouldNotHappenException('Not implemented yet');
	}

	public function invokeNodeCallback(Node $node): void
	{
		Fiber::suspend(new NodeCallbackRequest($node, $this));
	}

}
