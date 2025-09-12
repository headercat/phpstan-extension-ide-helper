<?php 

namespace PHPStan\Type\Regex;
return;

use PHPStan\Type\Type;

final class RegexCapturingGroup
{

	public function __construct(
		private readonly int $id,
		private readonly ?string $name,
		private readonly ?RegexAlternation $alternation,
		private readonly bool $inOptionalQuantification,
		private readonly RegexCapturingGroup|RegexNonCapturingGroup|null $parent,
		private readonly Type $type,
		private readonly bool $forceNonOptional = false,
		private readonly ?Type $forceType = null,
	)
	{
	}

	public function getId(): int
	{
		return $this->id;
	}

	public function forceNonOptional(): self
	{
		return new self(
			$this->id,
			$this->name,
			$this->alternation,
			$this->inOptionalQuantification,
			$this->parent,
			$this->type,
			true,
			$this->forceType,
		);
	}

	public function forceType(Type $type): self
	{
		return new self(
			$this->id,
			$this->name,
			$this->alternation,
			$this->inOptionalQuantification,
			$this->parent,
			$type,
			$this->forceNonOptional,
			$this->forceType,
		);
	}

	public function withParent(RegexCapturingGroup|RegexNonCapturingGroup $parent): self
	{
		return new self(
			$this->id,
			$this->name,
			$this->alternation,
			$this->inOptionalQuantification,
			$parent,
			$this->type,
			$this->forceNonOptional,
			$this->forceType,
		);
	}

	public function resetsGroupCounter(): bool
	{
		return $this->parent instanceof RegexNonCapturingGroup && $this->parent->resetsGroupCounter();
	}

	/**
	 * @phpstan-assert-if-true !null $this->getAlternationId()
	 * @phpstan-assert-if-true !null $this->getAlternation()
	 */
	public function inAlternation(): bool
	{
		return $this->alternation !== null;
	}

	public function getAlternation(): ?RegexAlternation
	{
		return $this->alternation;
	}

	public function getAlternationId(): ?int
	{
		if ($this->alternation === null) {
			return null;
		}

		return $this->alternation->getId();
	}

	public function isOptional(): bool
	{
		if ($this->forceNonOptional) {
			return false;
		}

		return $this->inAlternation()
			|| $this->inOptionalQuantification
			|| $this->parent !== null && $this->parent->isOptional();
	}

	public function inOptionalQuantification(): bool
	{
		return $this->inOptionalQuantification;
	}

	public function inOptionalAlternation(): bool
	{
		if (!$this->inAlternation()) {
			return false;
		}

		$parent = $this->parent;
		while ($parent !== null && $parent->getAlternationId() === $this->getAlternationId()) {
			if (!$parent instanceof RegexNonCapturingGroup) {
				return false;
			}
			$parent = $parent->getParent();
		}
		return $parent !== null && $parent->isOptional();
	}

	public function isTopLevel(): bool
	{
		return $this->parent === null
			|| $this->parent instanceof RegexNonCapturingGroup && $this->parent->isTopLevel();
	}

	/** @phpstan-assert-if-true !null $this->getName() */
	public function isNamed(): bool
	{
		return $this->name !== null;
	}

	public function getName(): ?string
	{
		return $this->name;
	}

	public function getType(): Type
	{
		if ($this->forceType !== null) {
			return $this->forceType;
		}
		return $this->type;
	}

	public function getParent(): RegexCapturingGroup|RegexNonCapturingGroup|null
	{
		return $this->parent;
	}

}
