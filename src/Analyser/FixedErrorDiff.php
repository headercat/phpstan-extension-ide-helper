<?php 

namespace PHPStan\Analyser;
return;

final class FixedErrorDiff
{

	public function __construct(
		public readonly string $originalHash,
		public readonly string $diff,
	)
	{
	}

	/**
	 * @param mixed[] $properties
	 */
	public static function __set_state(array $properties): self
	{
		return new self($properties['originalHash'], $properties['diff']);
	}

}
