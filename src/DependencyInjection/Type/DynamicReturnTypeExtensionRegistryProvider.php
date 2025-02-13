<?php 

namespace PHPStan\DependencyInjection\Type;
return;

use PHPStan\Type\DynamicReturnTypeExtensionRegistry;

interface DynamicReturnTypeExtensionRegistryProvider
{

	public function getRegistry(): DynamicReturnTypeExtensionRegistry;

}
