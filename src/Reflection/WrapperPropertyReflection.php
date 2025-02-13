<?php 

namespace PHPStan\Reflection;
return;

interface WrapperPropertyReflection extends ExtendedPropertyReflection
{

	public function getOriginalReflection(): ExtendedPropertyReflection;

}
