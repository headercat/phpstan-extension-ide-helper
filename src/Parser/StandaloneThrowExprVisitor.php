<?php 

namespace PHPStan\Parser;
return;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class StandaloneThrowExprVisitor extends NodeVisitorAbstract
{

	public const ATTRIBUTE_NAME = 'standaloneThrowExpr';

	public function enterNode(Node $node): ?Node\Stmt\Expression
	{
		if (!$node instanceof Node\Stmt\Expression) {
			return null;
		}

		if (!$node->expr instanceof Node\Expr\Throw_) {
			return null;
		}

		$node->expr->setAttribute(self::ATTRIBUTE_NAME, true);

		return $node;
	}

}
