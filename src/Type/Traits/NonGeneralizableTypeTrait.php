<?php 

namespace PHPStan\Type\Traits;
return;

use PHPStan\Type\GeneralizePrecision;
use PHPStan\Type\Type;

trait NonGeneralizableTypeTrait
{

	public function generalize(GeneralizePrecision $precision): Type
	{
		return $this->traverse(static fn (Type $type) => $type->generalize($precision));
	}

}
