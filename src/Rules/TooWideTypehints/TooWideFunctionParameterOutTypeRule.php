<?php 

namespace PHPStan\Rules\TooWideTypehints;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\RegisteredRule;
use PHPStan\Node\FunctionReturnStatementsNode;
use PHPStan\Rules\Rule;
use function sprintf;

/**
 * @implements Rule<FunctionReturnStatementsNode>
 */
#[RegisteredRule(level: 4)]
final class TooWideFunctionParameterOutTypeRule implements Rule
{

	public function __construct(
		private TooWideParameterOutTypeCheck $check,
	)
	{
	}

	public function getNodeType(): string
	{
		return FunctionReturnStatementsNode::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$inFunction = $node->getFunctionReflection();

		return $this->check->check(
			$node->getExecutionEnds(),
			$node->getReturnStatements(),
			$inFunction->getParameters(),
			sprintf('Function %s()', $inFunction->getName()),
		);
	}

}
