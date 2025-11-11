<?php 

namespace PHPStan\Analyser\Generator;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;

final class NodeCallbackRequest
{

	public function __construct(
		public Node $node,
		public Scope $scope,
	)
	{
	}

}
