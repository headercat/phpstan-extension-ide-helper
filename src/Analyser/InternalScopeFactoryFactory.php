<?php 

namespace PHPStan\Analyser;
return;

use PhpParser\Node;

interface InternalScopeFactoryFactory
{

	/**
	 * @param callable(Node $node, Scope $scope): void $nodeCallback
	 */
	public function create(
		?callable $nodeCallback,
	): InternalScopeFactory;

}
