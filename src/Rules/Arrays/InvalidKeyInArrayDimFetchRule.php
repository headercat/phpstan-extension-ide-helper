<?php 

namespace PHPStan\Rules\Arrays;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredParameter;
use PHPStan\DependencyInjection\RegisteredRule;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Rules\RuleLevelHelper;
use PHPStan\Type\ErrorType;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;
use function sprintf;

/**
 * @implements Rule<Node\Expr\ArrayDimFetch>
 */
#[RegisteredRule(level: 3)]
final class InvalidKeyInArrayDimFetchRule implements Rule
{

	public function __construct(
		private RuleLevelHelper $ruleLevelHelper,
		#[AutowiredParameter]
		private bool $reportMaybes,
	)
	{
	}

	public function getNodeType(): string
	{
		return Node\Expr\ArrayDimFetch::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		if ($node->dim === null) {
			return [];
		}

		$varType = $this->ruleLevelHelper->findTypeToCheck(
			$scope,
			$node->var,
			'',
			static fn (Type $varType): bool => $varType->isArray()->no(),
		)->getType();

		if ($varType instanceof ErrorType) {
			return [];
		}

		$isArray = $varType->isArray();
		if ($isArray->no() || ($isArray->maybe() && !$this->reportMaybes)) {
			return [];
		}

		$dimensionType = $this->ruleLevelHelper->findTypeToCheck(
			$scope,
			$node->dim,
			'',
			static fn (Type $dimType): bool => AllowedArrayKeysTypes::getType()->isSuperTypeOf($dimType)->yes(),
		)->getType();
		if ($dimensionType instanceof ErrorType) {
			return [];
		}

		$isSuperType = AllowedArrayKeysTypes::getType()->isSuperTypeOf($dimensionType);
		if ($isSuperType->yes() || ($isSuperType->maybe() && !$this->reportMaybes)) {
			return [];
		}

		return [
			RuleErrorBuilder::message(
				sprintf(
					'%s array key type %s.',
					$isArray->yes() && $isSuperType->no() ? 'Invalid' : 'Possibly invalid',
					$dimensionType->describe(VerbosityLevel::typeOnly()),
				),
			)->identifier('offsetAccess.invalidOffset')->build(),
		];
	}

}
