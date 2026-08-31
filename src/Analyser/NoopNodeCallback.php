<?php 

namespace PHPStan\Analyser;
return;

use PhpParser\Node;

final class NoopNodeCallback
{

	public function __invoke(Node $node, Scope $scope): void
	{
		// noop
	}

}
