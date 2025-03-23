<?php 

namespace PHPStan\Analyser;
return;

use PHPStan\DependencyInjection\Container;

final class IgnoreErrorExtensionProvider
{

	public function __construct(private Container $container)
	{
	}

	/**
	 * @return IgnoreErrorExtension[]
	 */
	public function getExtensions(): array
	{
		return $this->container->getServicesByTag(IgnoreErrorExtension::EXTENSION_TAG);
	}

}
