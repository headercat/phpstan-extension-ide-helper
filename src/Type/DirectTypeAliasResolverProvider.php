<?php 

namespace PHPStan\Type;
return;

final class DirectTypeAliasResolverProvider implements TypeAliasResolverProvider
{

	public function __construct(private TypeAliasResolver $typeAliasResolver)
	{
	}

	public function getTypeAliasResolver(): TypeAliasResolver
	{
		return $this->typeAliasResolver;
	}

}
