<?php 

namespace PHPStan\Rules\Functions;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\RegisteredRule;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use function count;

/**
 * @implements Rule<Node\FunctionLike>
 */
#[RegisteredRule(level: 0)]
final class VariadicParametersDeclarationRule implements Rule
{

	public function getNodeType(): string
	{
		return Node\FunctionLike::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$parameters = $node->getParams();
		$paramCount = count($parameters);

		if ($paramCount === 0) {
			return [];
		}

		$errors = [];

		foreach ($parameters as $index => $parameter) {
			if (!$parameter->variadic) {
				continue;
			}

			if ($paramCount - 1 === $index) {
				continue;
			}

			$errors[] = RuleErrorBuilder::message('Only the last parameter can be variadic.')
				->nonIgnorable()
				->identifier('parameter.variadicNotLast')
				->build();
		}

		return $errors;
	}

}
