<?php 

namespace PHPStan\DependencyInjection;
return;

use Attribute;

/**
 * Wires a constructor parameter of type ExtensionsCollection to the collection
 * of all registered extensions implementing the given extension interface.
 *
 * The interface must be marked with the #[ExtensionInterface] attribute.
 *
 * Works thanks to https://github.com/ondrejmirtes/composer-attribute-collector
 * and AutowiredExtensionsExtension.
 */
#[Attribute(flags: Attribute::TARGET_PARAMETER)]
final class AutowiredExtensions
{

	/**
	 * @param class-string $of
	 */
	public function __construct(public string $of)
	{
	}

}
