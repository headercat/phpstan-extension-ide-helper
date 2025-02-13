<?php 

namespace PHPStan\Type\Traits;
return;

use PHPStan\Type\BooleanType;

trait UndecidedBooleanTypeTrait
{

	public function toBoolean(): BooleanType
	{
		return new BooleanType();
	}

}
