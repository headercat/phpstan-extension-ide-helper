<?php 

namespace PHPStan\Type\Traits;
return;

use PHPStan\Php\PhpVersion;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Type;

trait UndecidedComparisonCompoundTypeTrait
{

	use UndecidedComparisonTypeTrait;

	public function isGreaterThan(Type $otherType, PhpVersion $phpVersion): TrinaryLogic
	{
		if ($otherType->isNull()->yes() && $this->isObject()->yes()) {
			return TrinaryLogic::createYes();
		}

		return TrinaryLogic::createMaybe();
	}

	public function isGreaterThanOrEqual(Type $otherType, PhpVersion $phpVersion): TrinaryLogic
	{
		if ($otherType->isNull()->yes()) {
			return TrinaryLogic::createYes();
		}

		return TrinaryLogic::createMaybe();
	}

}
