<?php 

namespace PHPStan\PhpDoc;
return;

final class DirectTypeNodeResolverExtensionRegistryProvider implements TypeNodeResolverExtensionRegistryProvider
{

	public function __construct(private TypeNodeResolverExtensionRegistry $registry)
	{
	}

	public function getRegistry(): TypeNodeResolverExtensionRegistry
	{
		return $this->registry;
	}

}
