<?php 

namespace PHPStan\Type\Traits;
return;

use PHPStan\Reflection\ClassMemberAccessAnswerer;
use PHPStan\ShouldNotHappenException;
use PHPStan\TrinaryLogic;

trait NonCallableTypeTrait
{

	public function isCallable(): TrinaryLogic
	{
		return TrinaryLogic::createNo();
	}

	public function getCallableParametersAcceptors(ClassMemberAccessAnswerer $scope): array
	{
		throw new ShouldNotHappenException();
	}

}
