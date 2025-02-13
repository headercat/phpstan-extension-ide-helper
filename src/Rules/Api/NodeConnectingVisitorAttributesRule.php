<?php 

namespace PHPStan\Rules\Api;
return;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ObjectType;
use function array_keys;
use function in_array;
use function sprintf;
use function str_starts_with;

/**
 * @implements Rule<MethodCall>
 */
final class NodeConnectingVisitorAttributesRule implements Rule
{

	public function getNodeType(): string
	{
		return MethodCall::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		if (!$node->name instanceof Node\Identifier) {
			return [];
		}
		if ($node->name->toLowerString() !== 'getattribute') {
			return [];
		}
		$calledOnType = $scope->getType($node->var);
		if (!(new ObjectType(Node::class))->isSuperTypeOf($calledOnType)->yes()) {
			return [];
		}
		$args = $node->getArgs();
		if (!isset($args[0])) {
			return [];
		}
		$argType = $scope->getType($args[0]->value);
		if (!$argType instanceof ConstantStringType) {
			return [];
		}
		if (!in_array($argType->getValue(), ['parent', 'previous', 'next'], true)) {
			return [];
		}
		if (!$scope->isInClass()) {
			return [];
		}

		$classReflection = $scope->getClassReflection();
		$hasPhpStanInterface = false;
		foreach (array_keys($classReflection->getInterfaces()) as $interfaceName) {
			if (!str_starts_with($interfaceName, 'PHPStan\\')) {
				continue;
			}

			$hasPhpStanInterface = true;
		}

		if (!$hasPhpStanInterface) {
			return [];
		}

		return [
			RuleErrorBuilder::message(sprintf('Node attribute \'%s\' is no longer available.', $argType->getValue()))
				->identifier('phpParser.nodeConnectingAttribute')
				->tip('See: https://phpstan.org/blog/preprocessing-ast-for-custom-rules')
				->build(),
		];
	}

}
