<?php 

namespace PHPStan\Reflection\Deprecation;
return;

/**
 * @api
 */
final class Deprecation
{

	private ?string $description = null;

	private function __construct()
	{
	}

	public static function create(): self
	{
		return new self();
	}

	public function getDescription(): ?string
	{
		return $this->description;
	}

	public static function createWithDescription(string $description): self
	{
		$clone = new self();
		$clone->description = $description;

		return $clone;
	}

}
