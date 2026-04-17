<?php 

namespace PHPStan\Type\Traits;
return;

use PHPStan\Type\BooleanType;
use PHPStan\Type\Constant\ConstantBooleanType;

trait TruthyBooleanTypeTrait
{

	public function toBoolean(): BooleanType
	{
		return new ConstantBooleanType(true);
	}

}
