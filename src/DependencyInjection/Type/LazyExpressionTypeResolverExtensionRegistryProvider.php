<?php 

namespace PHPStan\DependencyInjection\Type;
return;

use PHPStan\Broker\BrokerFactory;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\DependencyInjection\Container;
use PHPStan\Type\ExpressionTypeResolverExtensionRegistry;

#[AutowiredService(as: ExpressionTypeResolverExtensionRegistryProvider::class)]
final class LazyExpressionTypeResolverExtensionRegistryProvider implements ExpressionTypeResolverExtensionRegistryProvider
{

	private ?ExpressionTypeResolverExtensionRegistry $registry = null;

	public function __construct(private Container $container)
	{
	}

	public function getRegistry(): ExpressionTypeResolverExtensionRegistry
	{
		return $this->registry ??= new ExpressionTypeResolverExtensionRegistry(
			$this->container->getServicesByTag(BrokerFactory::EXPRESSION_TYPE_RESOLVER_EXTENSION_TAG),
		);
	}

}
