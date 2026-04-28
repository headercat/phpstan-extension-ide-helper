<?php 

namespace PHPStan\Type\Traits;
return;

use PHPStan\Type\BooleanType;
use PHPStan\Type\Constant\ConstantBooleanType;

trait FalseyBooleanTypeTrait
{

	public function toBoolean(): BooleanType
	{
		return new ConstantBooleanType(false);
	}

}
