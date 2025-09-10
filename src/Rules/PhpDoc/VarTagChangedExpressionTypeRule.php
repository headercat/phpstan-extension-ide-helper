<?php 

namespace PHPStan\Rules\PhpDoc;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\RegisteredRule;
use PHPStan\Node\VarTagChangedExpressionTypeNode;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<VarTagChangedExpressionTypeNode>
 */
#[RegisteredRule(level: 2)]
final class VarTagChangedExpressionTypeRule implements Rule
{

	public function __construct(private VarTagTypeRuleHelper $varTagTypeRuleHelper)
	{
	}

	public function getNodeType(): string
	{
		return VarTagChangedExpressionTypeNode::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		return $this->varTagTypeRuleHelper->checkExprType($scope, $node->getExpr(), $node->getVarTag()->getType());
	}

}
