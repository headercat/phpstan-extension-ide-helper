<?php 

namespace PHPStan\Rules\Constants;
return;

use PHPStan\DependencyInjection\Container;

final class LazyAlwaysUsedClassConstantsExtensionProvider implements AlwaysUsedClassConstantsExtensionProvider
{

	/** @var AlwaysUsedClassConstantsExtension[]|null */
	private ?array $extensions = null;

	public function __construct(private Container $container)
	{
	}

	public function getExtensions(): array
	{
		if ($this->extensions === null) {
			$this->extensions = $this->container->getServicesByTag(AlwaysUsedClassConstantsExtensionProvider::EXTENSION_TAG);
		}

		return $this->extensions;
	}

}
