<?php 

namespace PHPStan\DependencyInjection\AttributeServices;
return;

/**
 * One installed Composer package of the analysed project.
 */
final class ComposerPackage
{

	/**
	 * @param string|null $cacheToken version identity usable as a cache key, or null when the
	 *                                installed files can change without the version changing
	 *                                (path repositories, missing reference)
	 */
	public function __construct(
		public string $name,
		public ?string $cacheToken,
	)
	{
	}

}
