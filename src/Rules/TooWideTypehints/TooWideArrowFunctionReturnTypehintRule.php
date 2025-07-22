<?php 

namespace PHPStan\Rules\TooWideTypehints;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\RegisteredRule;
use PHPStan\Node\InArrowFunctionNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\UnionType;
use PHPStan\Type\VerbosityLevel;
use function sprintf;

/**
 * @implements Rule<InArrowFunctionNode>
 */
#[RegisteredRule(level: 4)]
final class TooWideArrowFunctionReturnTypehintRule implements Rule
{

	public function getNodeType(): string
	{
		return InArrowFunctionNode::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$arrowFunction = $node->getOriginalNode();
		if ($arrowFunction->returnType === null) {
			return [];
		}

		$expr = $arrowFunction->expr;
		if ($expr instanceof Node\Expr\YieldFrom || $expr instanceof Node\Expr\Yield_) {
			return [];
		}

		$functionReturnType = $scope->getFunctionType($arrowFunction->returnType, false, false);
		if (!$functionReturnType instanceof UnionType) {
			return [];
		}

		$returnType = $scope->getType($expr);
		if ($returnType->isNull()->yes()) {
			return [];
		}
		$messages = [];
		foreach ($functionReturnType->getTypes() as $type) {
			if (!$type->isSuperTypeOf($returnType)->no()) {
				continue;
			}

			$messages[] = RuleErrorBuilder::message(sprintf(
				'Anonymous function never returns %s so it can be removed from the return type.',
				$type->describe(VerbosityLevel::getRecommendedLevelByType($type)),
			))->identifier('return.unusedType')->build();
		}

		return $messages;
	}

}
