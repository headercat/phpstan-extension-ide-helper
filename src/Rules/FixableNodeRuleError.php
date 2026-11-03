<?php 

namespace PHPStan\Rules;
return;

interface FixableNodeRuleError extends RuleError
{

	/** @return non-empty-list<NodeFix> */
	public function getNodeFixes(): array;

}
