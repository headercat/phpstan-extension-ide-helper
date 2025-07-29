<?php 

namespace PHPStan\DependencyInjection\Type;
return;

use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\DependencyInjection\Container;

#[AutowiredService(as: ParameterOutTypeExtensionProvider::class)]
final class LazyParameterOutTypeExtensionProvider implements ParameterOutTypeExtensionProvider
{

	public const FUNCTION_TAG = 'phpstan.functionParameterOutTypeExtension';
	public const METHOD_TAG = 'phpstan.methodParameterOutTypeExtension';
	public const STATIC_METHOD_TAG = 'phpstan.staticMethodParameterOutTypeExtension';

	public function __construct(private Container $container)
	{
	}

	public function getFunctionParameterOutTypeExtensions(): array
	{
		return $this->container->getServicesByTag(self::FUNCTION_TAG);
	}

	public function getMethodParameterOutTypeExtensions(): array
	{
		return $this->container->getServicesByTag(self::METHOD_TAG);
	}

	public function getStaticMethodParameterOutTypeExtensions(): array
	{
		return $this->container->getServicesByTag(self::STATIC_METHOD_TAG);
	}

}
