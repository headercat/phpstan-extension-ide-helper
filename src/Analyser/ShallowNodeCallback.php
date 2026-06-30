<?php 

namespace PHPStan\Analyser;
return;

use PhpParser\Node;

interface ShallowNodeCallback
{

	public function __invoke(Node $node, Scope $scope): void;

}
