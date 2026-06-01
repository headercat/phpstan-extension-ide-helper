<?php 

namespace PHPStan\Reflection\BetterReflection\SourceLocator;
return;

use PHPStan\DependencyInjection\AutowiredService;
use function array_key_exists;

#[AutowiredService]
final class OptimizedDirectorySourceLocatorRepository
{

	/** @var array<string, OptimizedDirectorySourceLocator> */
	private array $locators = [];

	public function __construct(private OptimizedDirectorySourceLocatorFactory $factory)
	{
	}

	public function getOrCreate(string $directory): OptimizedDirectorySourceLocator
	{
		if (array_key_exists($directory, $this->locators)) {
			return $this->locators[$directory];
		}

		$this->locators[$directory] = $this->factory->createByDirectory($directory);

		return $this->locators[$directory];
	}

}
