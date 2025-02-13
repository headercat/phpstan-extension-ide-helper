<?php 

namespace PHPStan\Type;
return;

/** @api */
interface ConstantScalarType extends Type
{

	/**
	 * @return int|float|string|bool|null
	 */
	public function getValue();

}
