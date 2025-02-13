<?php 

namespace PHPStan\Rules;
return;

use PhpParser\Node;

interface Registry
{

	/**
	 * @template TNodeType of Node
	 * @param class-string<TNodeType> $nodeType
	 * @return array<Rule<TNodeType>>
	 */
	public function getRules(string $nodeType): array;

}
