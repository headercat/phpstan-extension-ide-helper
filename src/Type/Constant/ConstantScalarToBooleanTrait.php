<?php 

namespace PHPStan\Type\Constant;
return;

use PHPStan\Type\BooleanType;

trait ConstantScalarToBooleanTrait
{

	public function toBoolean(): BooleanType
	{
		return new ConstantBooleanType((bool) $this->value);
	}

}
