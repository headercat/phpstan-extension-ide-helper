<?php 

namespace PHPStan\Rules\RestrictedUsage;
return;

/**
 * @api
 */
final class RestrictedUsage
{

	private function __construct(
		public readonly string $errorMessage,
		public readonly string $identifier,
	)
	{
	}

	public static function create(
		string $errorMessage,
		string $identifier,
	): self
	{
		return new self($errorMessage, $identifier);
	}

}
