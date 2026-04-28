<?php 

namespace PHPStan\Type;
return;

/**
 * A type whose value is known at analysis time — a compile-time constant scalar.
 *
 * Implemented by ConstantIntegerType, ConstantFloatType, ConstantStringType,
 * ConstantBooleanType, and NullType.
 *
 * Use Type::isConstantValue() to check if a type is constant without instanceof,
 * and Type::getConstantScalarTypes() to extract constant types from unions.
 *
 * @api
 */
interface ConstantScalarType extends Type
{

	/** @return int|float|string|bool|null */
	public function getValue();

}
