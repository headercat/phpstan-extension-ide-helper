<?php 

namespace PHPStan\Rules\Functions;
return;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredParameter;
use PHPStan\DependencyInjection\RegisteredRule;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use function sprintf;
use function strtolower;

/**
 * @implements Rule<Node\Expr\FuncCall>
 */
#[RegisteredRule(level: 0)]
final class CallToNonExistentFunctionRule implements Rule
{

	public function __construct(
		private ReflectionProvider $reflectionProvider,
		#[AutowiredParameter]
		private bool $checkFunctionNameCase,
		#[AutowiredParameter(ref: '%tips.discoveringSymbols%')]
		private bool $discoveringSymbolsTip,
	)
	{
	}

	public function getNodeType(): string
	{
		return FuncCall::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		if (!($node->name instanceof Node\Name)) {
			return [];
		}

		if (!$this->reflectionProvider->hasFunction($node->name, $scope)) {
			if ($scope->isInFunctionExists($node->name->toString())) {
				return [];
			}

			$errorBuilder = RuleErrorBuilder::message(sprintf('Function %s not found.', (string) $node->name))
				->identifier('function.notFound');

			if ($this->discoveringSymbolsTip) {
				$errorBuilder->discoveringSymbolsTip();
			}

			return [
				$errorBuilder->build(),
			];
		}

		$function = $this->reflectionProvider->getFunction($node->name, $scope);
		$name = (string) $node->name;

		if ($this->checkFunctionNameCase) {
			/** @var string $calledFunctionName */
			$calledFunctionName = $this->reflectionProvider->resolveFunctionName($node->name, $scope);
			if (
				strtolower($function->getName()) === strtolower($calledFunctionName)
				&& $function->getName() !== $calledFunctionName
			) {
				return [
					RuleErrorBuilder::message(sprintf(
						'Call to function %s() with incorrect case: %s',
						$function->getName(),
						$name,
					))->identifier('function.nameCase')->build(),
				];
			}
		}

		return [];
	}

}
