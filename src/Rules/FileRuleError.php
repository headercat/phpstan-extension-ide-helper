<?php 

namespace PHPStan\Rules;
return;

/**
 * @api
 * @api-do-not-implement
 */
interface FileRuleError extends RuleError
{

	public function getFile(): string;

	public function getFileDescription(): string;

}
