<?php 

namespace PHPStan\PhpDoc\Tag;
return;

use PHPStan\Type\Type;

/**
 * @api
 */
final class SelfOutTypeTag implements TypedTag
{

	public function __construct(private Type $type)
	{
	}

	public function getType(): Type
	{
		return $this->type;
	}

	public function withType(Type $type): self
	{
		return new self($type);
	}

}
