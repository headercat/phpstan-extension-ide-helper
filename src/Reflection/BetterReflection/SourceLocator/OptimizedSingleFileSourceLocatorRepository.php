<?php 

namespace PHPStan\Reflection\BetterReflection\SourceLocator;
return;

use PHPStan\DependencyInjection\AutowiredService;
use function array_key_exists;

#[AutowiredService]
final class OptimizedSingleFileSourceLocatorRepository
{

	/** @var array<string, OptimizedSingleFileSourceLocator> */
	private array $locators = [];

	public function __construct(private OptimizedSingleFileSourceLocatorFactory $factory)
	{
	}

	public function getOrCreate(string $fileName): OptimizedSingleFileSourceLocator
	{
		if (array_key_exists($fileName, $this->locators)) {
			return $this->locators[$fileName];
		}

		$this->locators[$fileName] = $this->factory->create($fileName);

		return $this->locators[$fileName];
	}

}
