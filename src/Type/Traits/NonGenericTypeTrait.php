<?php 

namespace PHPStan\Type\Traits;
return;

use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\Generic\TemplateTypeVariance;
use PHPStan\Type\Type;

trait NonGenericTypeTrait
{

	public function inferTemplateTypes(Type $receivedType): TemplateTypeMap
	{
		return TemplateTypeMap::createEmpty();
	}

	public function getReferencedTemplateTypes(TemplateTypeVariance $positionVariance): array
	{
		return [];
	}

}
