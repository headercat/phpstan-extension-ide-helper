<?php 

namespace PHPStan\Rules\Properties;
return;

interface ReadWritePropertiesExtensionProvider
{

	public const EXTENSION_TAG = 'phpstan.properties.readWriteExtension';

	/**
	 * @return ReadWritePropertiesExtension[]
	 */
	public function getExtensions(): array;

}
