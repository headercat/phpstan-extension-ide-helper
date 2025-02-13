<?php 

namespace PHPStan\Parser;
return;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class NewAssignedToPropertyVisitor extends NodeVisitorAbstract
{

	public const ATTRIBUTE_NAME = 'assignedToProperty';

	public function enterNode(Node $node): ?Node
	{
		if ($node instanceof Node\Expr\Assign || $node instanceof Node\Expr\AssignRef) {
			if (
				($node->var instanceof Node\Expr\PropertyFetch || $node->var instanceof Node\Expr\StaticPropertyFetch)
				&& $node->expr instanceof Node\Expr\New_
			) {
				$node->expr->setAttribute(self::ATTRIBUTE_NAME, $node->var);
			}
		}
		return null;
	}

}
