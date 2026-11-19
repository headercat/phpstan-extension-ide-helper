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
		public readonly ?string $tip,
	)
	{
	}

	public static function create(
		string $errorMessage,
		string $identifier,
		?string $tip = null,
	): self
	{
		return new self($errorMessage, $identifier, $tip);
	}

}
