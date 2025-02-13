<?php 

namespace PHPStan\Reflection\BetterReflection;
return;

use PHPStan\BetterReflection\Reflector\Reflector;

interface BetterReflectionProviderFactory
{

	public function create(
		Reflector $reflector,
	): BetterReflectionProvider;

}
