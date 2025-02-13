<?php 

namespace PHPStan\Type;
return;

use PHPStan\Reflection\ClassReflection;

/** @api */
interface TypeWithClassName extends Type
{

	public function getClassName(): string;

	public function getAncestorWithClassName(string $className): ?self;

	public function getClassReflection(): ?ClassReflection;

}
