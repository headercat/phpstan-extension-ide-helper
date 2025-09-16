<?php 

namespace PHPStan\Reflection;
return;

use PHPStan\TrinaryLogic;
use PHPStan\Type\Type;

/** @api */
interface MethodReflection extends ClassMemberReflection
{

	public function getName(): string;

	public function getPrototype(): ClassMemberReflection;

	/**
	 * @return list<ParametersAcceptor>
	 */
	public function getVariants(): array;

	public function isDeprecated(): TrinaryLogic;

	public function getDeprecatedDescription(): ?string;

	public function isFinal(): TrinaryLogic;

	public function isInternal(): TrinaryLogic;

	public function getThrowType(): ?Type;

	public function hasSideEffects(): TrinaryLogic;

}
