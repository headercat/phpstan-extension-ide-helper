<?php 

namespace PHPStan\Reflection;
return;

use PHPStan\TrinaryLogic;
use PHPStan\Type\Type;

/** @api */
interface PropertyReflection extends ClassMemberReflection
{

	public function getReadableType(): Type;

	public function getWritableType(): Type;

	public function canChangeTypeAfterAssignment(): bool;

	public function isReadable(): bool;

	public function isWritable(): bool;

	public function isDeprecated(): TrinaryLogic;

	public function getDeprecatedDescription(): ?string;

	public function isInternal(): TrinaryLogic;

}
