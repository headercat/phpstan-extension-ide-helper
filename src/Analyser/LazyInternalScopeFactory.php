<?php 

namespace PHPStan\Analyser;
return;

use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\DependencyInjection\Container;
use PHPStan\DependencyInjection\Type\DynamicReturnTypeExtensionRegistryProvider;
use PHPStan\DependencyInjection\Type\ExpressionTypeResolverExtensionRegistryProvider;
use PHPStan\Node\Printer\ExprPrinter;
use PHPStan\Php\PhpVersion;
use PHPStan\Reflection\AttributeReflectionFactory;
use PHPStan\Reflection\InitializerExprTypeResolver;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\Reflection\Php\PhpFunctionFromParserNodeReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Properties\PropertyReflectionFinder;

#[AutowiredService(as: InternalScopeFactory::class)]
final class LazyInternalScopeFactory implements InternalScopeFactory
{

	/** @var int|array{min: int, max: int}|null */
	private int|array|null $phpVersion;

	public function __construct(
		private Container $container,
	)
	{
		$this->phpVersion = $this->container->getParameter('phpVersion');
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
			$this->container->getByType(ReflectionProvider::class),
			$this->container->getByType(InitializerExprTypeResolver::class),
			$this->container->getByType(DynamicReturnTypeExtensionRegistryProvider::class)->getRegistry(),
			$this->container->getByType(ExpressionTypeResolverExtensionRegistryProvider::class)->getRegistry(),
			$this->container->getByType(ExprPrinter::class),
			$this->container->getByType(TypeSpecifier::class),
			$this->container->getByType(PropertyReflectionFinder::class),
			$this->container->getService('currentPhpVersionSimpleParser'),
			$this->container->getByType(NodeScopeResolver::class),
			$this->container->getByType(RicherScopeGetTypeHelper::class),
			$this->container->getByType(ConstantResolver::class),
			$context,
			$this->container->getByType(PhpVersion::class),
			$this->container->getByType(AttributeReflectionFactory::class),
			$this->phpVersion,
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
