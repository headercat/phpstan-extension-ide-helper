<?php 

namespace PHPStan\DependencyInjection;
return;

use Nette\DI\Config\Loader;
use PHPStan\File\FileHelper;

final class NeonLoader extends Loader
{

	public function __construct(
		private FileHelper $fileHelper,
		private ?string $generateBaselineFile,
	)
	{
	}

	/**
	 * @return mixed[]
	 */
	public function load(string $file, ?bool $merge = true): array
	{
		if ($this->generateBaselineFile === null) {
			return parent::load($file, $merge);
		}

		$normalizedFile = $this->fileHelper->normalizePath($file);
		if ($this->generateBaselineFile === $normalizedFile) {
			return [];
		}

		return parent::load($file, $merge);
	}

}
