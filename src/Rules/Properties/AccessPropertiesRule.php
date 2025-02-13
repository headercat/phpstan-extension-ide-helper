<?php 

namespace PHPStan\Rules\Properties;
return;

use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<Node\Expr\PropertyFetch>
 */
final class AccessPropertiesRule implements Rule
{

	public function __construct(private AccessPropertiesCheck $check)
	{
	}

	public function getNodeType(): string
	{
		return PropertyFetch::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		return $this->check->check($node, $scope, false);
	}

}
