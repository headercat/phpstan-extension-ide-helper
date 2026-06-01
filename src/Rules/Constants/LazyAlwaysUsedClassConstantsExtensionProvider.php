<?php 

namespace PHPStan\Rules\Constants;
return;

use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\DependencyInjection\Container;

#[AutowiredService]
final class LazyAlwaysUsedClassConstantsExtensionProvider implements AlwaysUsedClassConstantsExtensionProvider
{

	/** @var AlwaysUsedClassConstantsExtension[]|null */
	private ?array $extensions = null;

	public function __construct(private Container $container)
	{
	}

	public function getExtensions(): array
	{
		return $this->extensions ??= $this->container->getServicesByTag(AlwaysUsedClassConstantsExtensionProvider::EXTENSION_TAG);
	}

}
