<?php 

namespace PHPStan\Analyser;
return;

use PhpParser\Node;
use PHPStan\Turbo\ReferencedByTurboExtension;

#[ReferencedByTurboExtension(key: 'noopNodeCallback')]
final class NoopNodeCallback
{

	public function __invoke(Node $node, Scope $scope): void
	{
		// noop
	}

}
