<?php 

namespace PHPStan\Analyser\Generator;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;

final class NoopNodeCallback
{

	/**
	 * @param callable(Node, Scope): void $nodeCallback
	 */
	public function __invoke(Node $node, Scope $scope, callable $nodeCallback): void
	{
	}

}
