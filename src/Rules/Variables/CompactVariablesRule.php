<?php 

namespace PHPStan\Rules\Variables;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredParameter;
use PHPStan\DependencyInjection\RegisteredRule;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Type;
use function array_merge;
use function count;
use function sprintf;
use function strtolower;

/**
 * @implements Rule<Node\Expr\FuncCall>
 */
#[RegisteredRule(level: 0)]
final class CompactVariablesRule implements Rule
{

	public function __construct(
		#[AutowiredParameter]
		private bool $checkMaybeUndefinedVariables,
	)
	{
	}

	public function getNodeType(): string
	{
		return Node\Expr\FuncCall::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		if ($node->name instanceof Node\Expr) {
			return [];
		}

		$functionName = strtolower($node->name->toString());

		if ($functionName !== 'compact') {
			return [];
		}

		$functionArguments = $node->getArgs();
		$messages = [];

		foreach ($functionArguments as $argument) {
			$argumentType = $scope->getType($argument->value);
			$constantStrings = $this->findConstantStrings($argumentType);
			foreach ($constantStrings as $constantString) {
				$variableName = $constantString->getValue();
				$scopeHasVariable = $scope->hasVariableType($variableName);

				if ($scopeHasVariable->no()) {
					$messages[] = RuleErrorBuilder::message(
						sprintf('Call to function compact() contains undefined variable $%s.', $variableName),
					)->identifier('variable.undefined')->line($argument->getStartLine())->build();
				} elseif ($this->checkMaybeUndefinedVariables && $scopeHasVariable->maybe()) {
					$messages[] = RuleErrorBuilder::message(
						sprintf('Call to function compact() contains possibly undefined variable $%s.', $variableName),
					)->identifier('variable.undefined')->line($argument->getStartLine())->build();
				}
			}
		}

		return $messages;
	}

	/**
	 * @return list<ConstantStringType>
	 */
	private function findConstantStrings(Type $type): array
	{
		$constantStrings = $type->getConstantStrings();
		if (count($constantStrings) > 0) {
			return $constantStrings;
		}

		$result = [];
		foreach ($type->getConstantArrays() as $constantArrayType) {
			foreach ($constantArrayType->getValueTypes() as $valueType) {
				$constantStrings = $this->findConstantStrings($valueType);
				$result = array_merge($result, $constantStrings);
			}
		}

		return $result;
	}

}
