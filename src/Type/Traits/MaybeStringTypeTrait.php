<?php 

namespace PHPStan\Type\Traits;
return;

use PHPStan\TrinaryLogic;

trait MaybeStringTypeTrait
{

	public function getConstantStrings(): array
	{
		return [];
	}

	public function isString(): TrinaryLogic
	{
		return TrinaryLogic::createMaybe();
	}

	public function isNumericString(): TrinaryLogic
	{
		return TrinaryLogic::createMaybe();
	}

	public function isDecimalIntegerString(): TrinaryLogic
	{
		return TrinaryLogic::createMaybe();
	}

	public function isNonEmptyString(): TrinaryLogic
	{
		return TrinaryLogic::createMaybe();
	}

	public function isNonFalsyString(): TrinaryLogic
	{
		return TrinaryLogic::createMaybe();
	}

	public function isLiteralString(): TrinaryLogic
	{
		return TrinaryLogic::createMaybe();
	}

	public function isLowercaseString(): TrinaryLogic
	{
		return TrinaryLogic::createMaybe();
	}

	public function isUppercaseString(): TrinaryLogic
	{
		return TrinaryLogic::createMaybe();
	}

	public function isClassString(): TrinaryLogic
	{
		return TrinaryLogic::createMaybe();
	}

	public function isScalar(): TrinaryLogic
	{
		return TrinaryLogic::createMaybe();
	}

}
