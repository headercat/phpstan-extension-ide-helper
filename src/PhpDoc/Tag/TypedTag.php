<?php 

namespace PHPStan\PhpDoc\Tag;
return;

use PHPStan\Type\Type;

/** @api */
interface TypedTag
{

	public function getType(): Type;

	/**
	 * @return static
	 */
	public function withType(Type $type): self;

}
