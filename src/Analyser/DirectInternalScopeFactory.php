<?php 

namespace PHPStan\Analyser;
return;

use PHPStan\DependencyInjection\Type\DynamicReturnTypeExtensionRegistryProvider;
use PHPStan\DependencyInjection\Type\ExpressionTypeResolverExtensionRegistryProvider;
use PHPStan\Node\Printer\ExprPrinter;
use PHPStan\Parser\Parser;
use PHPStan\Php\PhpVersion;
use PHPStan\Reflection\AttributeReflectionFactory;
use PHPStan\Reflection\InitializerExprTypeResolver;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\Reflection\Php\PhpFunctionFromParserNodeReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Properties\PropertyReflectionFinder;

final class DirectInternalScopeFactory implements InternalScopeFactory
{

	/**
	 * @param int|array{min: int, max: int}|null $configPhpVersion
	 */
	public function __construct(
		private ReflectionProvider $reflectionProvider,
		private InitializerExprTypeResolver $initializerExprTypeResolver,
		private DynamicReturnTypeExtensionRegistryProvider $dynamicReturnTypeExtensionRegistryProvider,
		private ExpressionTypeResolverExtensionRegistryProvider $expressionTypeResolverExtensionRegistryProvider,
		private ExprPrinter $exprPrinter,
		private TypeSpecifier $typeSpecifier,
		private PropertyReflectionFinder $propertyReflectionFinder,
		private Parser $parser,
		private NodeScopeResolver $nodeScopeResolver,
		private RicherScopeGetTypeHelper $richerScopeGetTypeHelper,
		private PhpVersion $phpVersion,
		private AttributeReflectionFactory $attributeReflectionFactory,
		private int|array|null $configPhpVersion,
		private ConstantResolver $constantResolver,
	)
	{
	}

	public function create(
		ScopeContext $context,
		bool $declareStrictTypes = false,
		PhpFunctionFromParserNodeReflection|null $function = null,
		?string $namespace = null,
		array $expressionTypes = [],
		array $nativeExpressionTypes = [],
		array $conditionalExpressions = [],
		array $inClosureBindScopeClasses = [],
		?ParametersAcceptor $anonymousFunctionReflection = null,
		bool $inFirstLevelStatement = true,
		array $currentlyAssignedExpressions = [],
		array $currentlyAllowedUndefinedExpressions = [],
		array $inFunctionCallsStack = [],
		bool $afterExtractCall = false,
		?Scope $parentScope = null,
		bool $nativeTypesPromoted = false,
	): MutatingScope
	{
		return new MutatingScope(
			$this,
			$this->reflectionProvider,
			$this->initializerExprTypeResolver,
			$this->dynamicReturnTypeExtensionRegistryProvider->getRegistry(),
			$this->expressionTypeResolverExtensionRegistryProvider->getRegistry(),
			$this->exprPrinter,
			$this->typeSpecifier,
			$this->propertyReflectionFinder,
			$this->parser,
			$this->nodeScopeResolver,
			$this->richerScopeGetTypeHelper,
			$this->constantResolver,
			$context,
			$this->phpVersion,
			$this->attributeReflectionFactory,
			$this->configPhpVersion,
			$declareStrictTypes,
			$function,
			$namespace,
			$expressionTypes,
			$nativeExpressionTypes,
			$conditionalExpressions,
			$inClosureBindScopeClasses,
			$anonymousFunctionReflection,
			$inFirstLevelStatement,
			$currentlyAssignedExpressions,
			$currentlyAllowedUndefinedExpressions,
			$inFunctionCallsStack,
			$afterExtractCall,
			$parentScope,
			$nativeTypesPromoted,
		);
	}

}
