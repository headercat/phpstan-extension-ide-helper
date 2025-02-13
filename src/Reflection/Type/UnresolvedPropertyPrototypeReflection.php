<?php 

namespace PHPStan\Reflection\Type;
return;

use PHPStan\Reflection\ExtendedPropertyReflection;
use PHPStan\Type\Type;

interface UnresolvedPropertyPrototypeReflection
{

	public function doNotResolveTemplateTypeMapToBounds(): self;

	public function getNakedProperty(): ExtendedPropertyReflection;

	public function getTransformedProperty(): ExtendedPropertyReflection;

	public function withFechedOnType(Type $type): self;

}
