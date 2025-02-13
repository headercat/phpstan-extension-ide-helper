<?php 

namespace PHPStan\Rules\RuleErrors;
return;

use PHPStan\Rules\NonIgnorableRuleError;
use PHPStan\Rules\RuleError;

/**
 * @internal Use PHPStan\Rules\RuleErrorBuilder instead.
 */
final class RuleError65 implements RuleError, NonIgnorableRuleError
{

	public string $message;

	public function getMessage(): string
	{
		return $this->message;
	}

}
