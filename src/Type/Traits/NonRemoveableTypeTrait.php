<?php 

namespace PHPStan\Type\Traits;
return;

use PHPStan\Type\Type;

trait NonRemoveableTypeTrait
{

	public function tryRemove(Type $typeToRemove): ?Type
	{
		return null;
	}

}
