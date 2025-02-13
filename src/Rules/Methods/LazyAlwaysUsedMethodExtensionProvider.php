<?php 

namespace PHPStan\Rules\Methods;
return;

use PHPStan\DependencyInjection\Container;

final class LazyAlwaysUsedMethodExtensionProvider implements AlwaysUsedMethodExtensionProvider
{

	/** @var AlwaysUsedMethodExtension[]|null */
	private ?array $extensions = null;

	public function __construct(private Container $container)
	{
	}

	public function getExtensions(): array
	{
		return $this->extensions ??= $this->container->getServicesByTag(static::EXTENSION_TAG);
	}

}
