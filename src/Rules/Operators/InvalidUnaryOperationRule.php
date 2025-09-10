<?php 

namespace PHPStan\Rules\Operators;
return;

use PhpParser\Node;
use PHPStan\Analyser\MutatingScope;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\RegisteredRule;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Rules\RuleLevelHelper;
use PHPStan\ShouldNotHappenException;
use PHPStan\TrinaryLogic;
use PHPStan\Type\ErrorType;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;
use function sprintf;

/**
 * @implements Rule<Node\Expr>
 */
#[RegisteredRule(level: 2)]
final class InvalidUnaryOperationRule implements Rule
{

	public function __construct(
		private RuleLevelHelper $ruleLevelHelper,
	)
	{
	}

	public function getNodeType(): string
	{
		return Node\Expr::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		if (
			!$node instanceof Node\Expr\UnaryPlus
			&& !$node instanceof Node\Expr\UnaryMinus
			&& !$node instanceof Node\Expr\BitwiseNot
		) {
			return [];
		}

		$varName = '__PHPSTAN__LEFT__';
		$variable = new Node\Expr\Variable($varName);
		$newNode = clone $node;
		$newNode->setAttribute('phpstan_cache_printer', null);
		$newNode->expr = $variable;

		if ($node instanceof Node\Expr\BitwiseNot) {
			$callback = static fn (Type $type): bool => $type->isString()->yes() || $type->isInteger()->yes() || $type->isFloat()->yes();
		} else {
			$callback = static fn (Type $type): bool => !$type->toNumber() instanceof ErrorType;
		}

		$exprType = $this->ruleLevelHelper->findTypeToCheck(
			$scope,
			$node->expr,
			'',
			$callback,
		)->getType();
		if ($exprType instanceof ErrorType) {
			return [];
		}

		if (!$scope instanceof MutatingScope) {
			throw new ShouldNotHappenException();
		}

		$scope = $scope->assignVariable($varName, $exprType, $exprType, TrinaryLogic::createYes());
		if (!$scope->getType($newNode) instanceof ErrorType) {
			return [];
		}

		if ($node instanceof Node\Expr\UnaryPlus) {
			$operator = '+';
		} elseif ($node instanceof Node\Expr\UnaryMinus) {
			$operator = '-';
		} else {
			$operator = '~';
		}
		return [
			RuleErrorBuilder::message(sprintf(
				'Unary operation "%s" on %s results in an error.',
				$operator,
				$scope->getType($node->expr)->describe(VerbosityLevel::value()),
			))
				->line($node->expr->getStartLine())
				->identifier('unaryOp.invalid')
				->build(),
		];
	}

}
