<?php 

namespace PHPStan\Analyser;
return;

use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\DependencyInjection\Container;
use PHPStan\Php\ComposerPhpVersionFactory;
use PHPStan\Reflection\ReflectionProvider\ReflectionProviderProvider;

#[AutowiredService]
final class ConstantResolverFactory
{

	public function __construct(
		private ReflectionProviderProvider $reflectionProviderProvider,
		private Container $container,
	)
	{
	}

	public function create(): ConstantResolver
	{
		$composerFactory = $this->container->getByType(ComposerPhpVersionFactory::class);

		return new ConstantResolver(
			$this->reflectionProviderProvider,
			$this->container->getParameter('dynamicConstantNames'),
			$this->container->getParameter('phpVersion'),
			$composerFactory,
			$this->container,
		);
	}

}
