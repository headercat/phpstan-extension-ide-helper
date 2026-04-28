<?php 

namespace PHPStan\Rules;
return;

/**
 * @api
 * @api-do-not-implement
 */
interface LineRuleError extends RuleError
{

	public function getLine(): int;

}
