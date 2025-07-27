<?php 

namespace PHPStan\PhpDoc;
return;

use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\DependencyInjection\Container;

#[AutowiredService(as: TypeNodeResolverExtensionRegistryProvider::class)]
final class LazyTypeNodeResolverExtensionRegistryProvider implements TypeNodeResolverExtensionRegistryProvider
{

	private ?TypeNodeResolverExtensionRegistry $registry = null;

	public function __construct(private Container $container)
	{
	}

	public function getRegistry(): TypeNodeResolverExtensionRegistry
	{
		return $this->registry ??= new TypeNodeResolverExtensionAwareRegistry(
			$this->container->getByType(TypeNodeResolver::class),
			$this->container->getServicesByTag(TypeNodeResolverExtension::EXTENSION_TAG),
		);
	}

}
