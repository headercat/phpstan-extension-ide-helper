<?php 

namespace PHPStan\Node\Method;
return;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;

/**
 * @api
 */
final class MethodCall
{

	public function __construct(
		private Node\Expr\MethodCall|StaticCall|Array_ $node,
		private Scope $scope,
	)
	{
	}

	/**
	 * @return Node\Expr\MethodCall|StaticCall|Array_
	 */
	public function getNode()
	{
		return $this->node;
	}

	public function getScope(): Scope
	{
		return $this->scope;
	}

}
