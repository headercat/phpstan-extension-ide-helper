<?php 

namespace PHPStan\Type;
return;

interface SubtractableType extends Type
{

	public function subtract(Type $type): Type;

	public function getTypeWithoutSubtractedType(): Type;

	public function changeSubtractedType(?Type $subtractedType): Type;

	public function getSubtractedType(): ?Type;

}
