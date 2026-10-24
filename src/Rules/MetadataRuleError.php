<?php 

namespace PHPStan\Rules;
return;

/**
 * @api
 * @api-do-not-implement
 */
interface MetadataRuleError extends RuleError
{

	/**
	 * @return mixed[]
	 */
	public function getMetadata(): array;

}
