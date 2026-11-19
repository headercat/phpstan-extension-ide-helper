<?php 

namespace PHPStan\DependencyInjection\AttributeServices;
return;

use function str_starts_with;

/**
 * Installed Composer packages of one project the analysis runs against
 * (an entry of composerAutoloaderProjectPaths).
 */
final class ComposerProject
{

	/**
	 * @param array<string, ComposerPackage> $packagesByInstallPath install path => package, longest path first
	 */
	public function __construct(
		public array $packagesByInstallPath,
	)
	{
	}

	public function findPackageOfDirectory(string $directory): ?ComposerPackage
	{
		foreach ($this->packagesByInstallPath as $installPath => $package) {
			if ($directory === $installPath || str_starts_with($directory, $installPath . '/')) {
				return $package;
			}
		}

		return null;
	}

}
