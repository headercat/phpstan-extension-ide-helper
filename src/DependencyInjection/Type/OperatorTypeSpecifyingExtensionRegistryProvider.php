<?php 

namespace PHPStan\DependencyInjection\Type;
return;

use PHPStan\Type\OperatorTypeSpecifyingExtensionRegistry;

interface OperatorTypeSpecifyingExtensionRegistryProvider
{

	public function getRegistry(): OperatorTypeSpecifyingExtensionRegistry;

}
