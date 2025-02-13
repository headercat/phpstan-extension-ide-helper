<?php 

namespace PHPStan\Reflection;
return;

use PHPStan\Type\Type;

/** @api */
interface ParameterReflection
{

	public function getName(): string;

	public function isOptional(): bool;

	public function getType(): Type;

	public function passedByReference(): PassedByReference;

	public function isVariadic(): bool;

	public function getDefaultValue(): ?Type;

}
