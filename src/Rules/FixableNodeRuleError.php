<?php 

namespace PHPStan\Rules;
return;

use PhpParser\Node;

interface FixableNodeRuleError extends RuleError
{

	public function getOriginalNode(): Node;

	/** @return callable(Node): Node */
	public function getNewNodeCallable(): callable;

}
