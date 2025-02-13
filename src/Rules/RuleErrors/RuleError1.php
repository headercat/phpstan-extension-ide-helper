<?php 

namespace PHPStan\Rules\RuleErrors;
return;

use PHPStan\Rules\RuleError;

/**
 * @internal Use PHPStan\Rules\RuleErrorBuilder instead.
 */
final class RuleError1 implements RuleError
{

	public string $message;

	public function getMessage(): string
	{
		return $this->message;
	}

}
