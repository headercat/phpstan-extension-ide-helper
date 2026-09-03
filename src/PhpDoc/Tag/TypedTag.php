<?php 

namespace PHPStan\PhpDoc\Tag;
return;

use PHPStan\Type\Type;

/**
 * @api
 * @api-do-not-implement
 */
interface TypedTag
{

	public function getType(): Type;

	/**
	 * @return static
	 */
	public function withType(Type $type): self;

}
