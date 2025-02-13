<?php 

namespace PHPStan\DependencyInjection\Type;
return;

use PHPStan\Type\ExpressionTypeResolverExtensionRegistry;

interface ExpressionTypeResolverExtensionRegistryProvider
{

	public function getRegistry(): ExpressionTypeResolverExtensionRegistry;

}
