<?php 

namespace PHPStan\Rules\Playground;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\LineRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use function sprintf;

/**
 * @template TNodeType of Node
 * @implements Rule<TNodeType>
 */
final class PromoteParameterRule implements Rule
{

	/**
	 * @param Rule<TNodeType> $rule
	 * @param class-string<TNodeType>  $nodeType
	 */
	public function __construct(
		private Rule $rule,
		private string $nodeType,
		private bool $parameterValue,
		private string $parameterName,
	)
	{
	}

	public function getNodeType(): string
	{
		return $this->nodeType;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		if ($this->parameterValue) {
			return [];
		}

		if ($this->nodeType !== $this->rule->getNodeType()) {
			return [];
		}

		$errors = [];
		foreach ($this->rule->processNode($node, $scope) as $error) {
			$builder = RuleErrorBuilder::message($error->getMessage())
				->identifier('phpstanPlayground.configParameter')
				->tip(sprintf('This error would be reported if the <fg=cyan>%s: true</> parameter was enabled in your <fg=cyan>%%configurationFile%%</>.', $this->parameterName));
			if ($error instanceof LineRuleError) {
				$builder->line($error->getLine());
			}
			$errors[] = $builder->build();
		}

		return $errors;
	}

}
