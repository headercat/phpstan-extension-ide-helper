<?php 

namespace PHPStan\Reflection;
return;

use PHPStan\ShouldNotHappenException;

final class ReflectionProviderStaticAccessor
{

	private static ?ReflectionProvider $instance = null;

	private function __construct()
	{
	}

	public static function registerInstance(ReflectionProvider $reflectionProvider): void
	{
		self::$instance = $reflectionProvider;
	}

	public static function getInstance(): ReflectionProvider
	{
		if (self::$instance === null) {
			throw new ShouldNotHappenException();
		}
		return self::$instance;
	}

}
