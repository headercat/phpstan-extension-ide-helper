<?php 

namespace PHPStan\Type\Generic;
return;

final class TemplateTypeReference
{

	public function __construct(private TemplateType $type, private TemplateTypeVariance $positionVariance)
	{
	}

	public function getType(): TemplateType
	{
		return $this->type;
	}

	public function getPositionVariance(): TemplateTypeVariance
	{
		return $this->positionVariance;
	}

}
