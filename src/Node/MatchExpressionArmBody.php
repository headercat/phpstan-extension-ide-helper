<?php 

namespace PHPStan\Node;
return;

use PhpParser\Node\Expr;
use PHPStan\Analyser\Scope;
use PHPStan\Turbo\ReferencedByTurboExtension;

/**
 * @api
 */
#[ReferencedByTurboExtension(key: 'matchExpressionArmBody')]
final class MatchExpressionArmBody
{

	public function __construct(private Scope $scope, private Expr $body)
	{
	}

	public function getScope(): Scope
	{
		return $this->scope;
	}

	public function getBody(): Expr
	{
		return $this->body;
	}

}
