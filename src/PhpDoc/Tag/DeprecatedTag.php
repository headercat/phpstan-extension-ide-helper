<?php 

namespace PHPStan\PhpDoc\Tag;
return;

/**
 * @api
 */
final class DeprecatedTag
{

	public function __construct(private ?string $message)
	{
	}

	public function getMessage(): ?string
	{
		return $this->message;
	}

}
