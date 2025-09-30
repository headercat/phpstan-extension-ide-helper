<?php 

namespace PHPStan\Rules\Functions;
return;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\ParameterCastableToStringCheck;
use PHPStan\Rules\Rule;
use PHPStan\Type\Type;
use function count;
use function in_array;

/**
 * @implements Rule<Node\Expr\FuncCall>
 */
final class ParameterCastableToNumberRule implements Rule
{

	public function __construct(
		private ReflectionProvider $reflectionProvider,
		private ParameterCastableToStringCheck $parameterCastableToStringCheck,
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
			return [];
		}

		$functionReflection = $this->reflectionProvider->getFunction($node->name, $scope);
		$functionName = $functionReflection->getName();

		if (!in_array($functionName, ['array_sum', 'array_product'], true)) {
			return [];
		}

		$origArgs = $node->getArgs();

		if (count($origArgs) !== 1) {
			return [];
		}

		$parametersAcceptor = ParametersAcceptorSelector::selectFromArgs(
			$scope,
			$origArgs,
			$functionReflection->getVariants(),
			$functionReflection->getNamedArgumentsVariants(),
		);

		$errorMessage = 'Parameter %s of function %s expects an array of values castable to number, %s given.';
		$functionParameters = $parametersAcceptor->getParameters();
		$error = $this->parameterCastableToStringCheck->checkParameter(
			$origArgs[0],
			$scope,
			$errorMessage,
			static fn (Type $t) => $t->toNumber(),
			$functionName,
			$this->parameterCastableToStringCheck->getParameterName(
				$origArgs[0],
				0,
				$functionParameters[0] ?? null,
			),
		);

		return $error !== null
			? [$error]
			: [];
	}

}
