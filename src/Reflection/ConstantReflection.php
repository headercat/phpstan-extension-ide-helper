<?php 

namespace PHPStan\Reflection;
return;

use PHPStan\TrinaryLogic;
use PHPStan\Type\Type;

/** @api */
interface ConstantReflection
{

	public function getName(): string;

	public function getValueType(): Type;

	public function isDeprecated(): TrinaryLogic;

	public function getDeprecatedDescription(): ?string;

	public function isInternal(): TrinaryLogic;

	public function getFileName(): ?string;

}
