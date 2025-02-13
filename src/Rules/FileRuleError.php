<?php 

namespace PHPStan\Rules;
return;

/** @api */
interface FileRuleError extends RuleError
{

	public function getFile(): string;

	public function getFileDescription(): string;

}
