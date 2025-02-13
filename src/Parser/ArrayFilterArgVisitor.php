<?php 

namespace PHPStan\Parser;
return;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class ArrayFilterArgVisitor extends NodeVisitorAbstract
{

	public const ATTRIBUTE_NAME = 'isArrayFilterArg';

	public function enterNode(Node $node): ?Node
	{
		if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name) {
			$functionName = $node->name->toLowerString();
			if ($functionName === 'array_filter') {
				$args = $node->getRawArgs();
				if (isset($args[0])) {
					$args[0]->setAttribute(self::ATTRIBUTE_NAME, true);
				}
			}
		}
		return null;
	}

}
