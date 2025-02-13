<?php 

namespace PHPStan\PhpDoc\Tag;
return;

use PHPStan\Type\Type;

/**
 * @api
 */
final class ReturnTag implements TypedTag
{

	public function __construct(private Type $type, private bool $isExplicit)
	{
	}

	public function getType(): Type
	{
		return $this->type;
	}

	public function isExplicit(): bool
	{
		return $this->isExplicit;
	}

	public function withType(Type $type): self
	{
		return new self($type, $this->isExplicit);
	}

	public function toImplicit(): self
	{
		return new self($this->type, false);
	}

}
