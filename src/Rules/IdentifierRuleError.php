<?php 

namespace PHPStan\Rules;
return;

/**
 * @api
 * @api-do-not-implement
 */
interface IdentifierRuleError extends RuleError
{

	public function getIdentifier(): string;

}
