<?php 

namespace PHPStan\Reflection\Type;
return;

use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Type\Type;

interface UnresolvedMethodPrototypeReflection
{

	public function doNotResolveTemplateTypeMapToBounds(): self;

	public function getNakedMethod(): ExtendedMethodReflection;

	public function getTransformedMethod(): ExtendedMethodReflection;

	public function withCalledOnType(Type $type): self;

}
