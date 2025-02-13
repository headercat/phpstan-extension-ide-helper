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
		return TrinaryLogic::createMaybe();
	}

	public function isGreaterThanOrEqual(Type $otherType, PhpVersion $phpVersion): TrinaryLogic
	{
		return TrinaryLogic::createMaybe();
	}

}
