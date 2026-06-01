<?php 

namespace PHPStan\DependencyInjection\Type;
return;

use PHPStan\Type\UnaryOperatorTypeSpecifyingExtensionRegistry;

interface UnaryOperatorTypeSpecifyingExtensionRegistryProvider
{

	public function getRegistry(): UnaryOperatorTypeSpecifyingExtensionRegistry;

}
