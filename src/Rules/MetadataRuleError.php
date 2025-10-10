<?php 

namespace PHPStan\Rules;
return;

/** @api */
interface MetadataRuleError extends RuleError
{

	/**
	 * @return mixed[]
	 */
	public function getMetadata(): array;

}
