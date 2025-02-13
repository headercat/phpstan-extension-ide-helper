<?php 

namespace PHPStan\PhpDoc\Tag;
return;

use PHPStan\Type\Type;

/**
 * @api
 */
final class ParamTag implements TypedTag
{

	public function __construct(
		private Type $type,
		private bool $isVariadic,
	)
	{
	}

	public function getType(): Type
	{
		return $this->type;
	}

	public function isVariadic(): bool
	{
		return $this->isVariadic;
	}

	public function withType(Type $type): self
	{
		return new self($type, $this->isVariadic);
	}

}
