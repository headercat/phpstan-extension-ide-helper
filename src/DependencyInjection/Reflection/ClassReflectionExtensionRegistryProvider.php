<?php 

namespace PHPStan\DependencyInjection\Reflection;
return;

use PHPStan\Reflection\ClassReflectionExtensionRegistry;

interface ClassReflectionExtensionRegistryProvider
{

	public function getRegistry(): ClassReflectionExtensionRegistry;

}
