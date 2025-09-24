<?php 

namespace PHPStan\Rules;
return;

/** @api */
interface LineRuleError extends RuleError
{

	public function getLine(): int;

}
