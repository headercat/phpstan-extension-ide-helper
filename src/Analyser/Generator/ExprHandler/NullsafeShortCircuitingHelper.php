<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use Generator;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PHPStan\Analyser\Generator\GeneratorNodeScopeResolver;
use PHPStan\Analyser\Generator\TypeExprRequest;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * @phpstan-import-type GeneratorTValueType from GeneratorNodeScopeResolver
 * @phpstan-import-type GeneratorTSendType from GeneratorNodeScopeResolver
 */
#[AutowiredService]
final class NullsafeShortCircuitingHelper
{

	/**
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, Type>
	 */
	public function getNullsafeShortCircuitingType(Expr $expr, Type $type): Generator
	{
		if ($expr instanceof Expr\NullsafePropertyFetch || $expr instanceof Expr\NullsafeMethodCall) {
			$varType = (yield new TypeExprRequest($expr->var))->type;
			if (TypeCombinator::containsNull($varType)) {
				return TypeCombinator::addNull($type);
			}

			return $type;
		}

		if ($expr instanceof Expr\ArrayDimFetch) {
			$gen = $this->getNullsafeShortCircuitingType($expr->var, $type);
			yield from $gen;
			return $gen->getReturn();
		}

		if ($expr instanceof PropertyFetch) {
			$gen = $this->getNullsafeShortCircuitingType($expr->var, $type);
			yield from $gen;
			return $gen->getReturn();
		}

		if ($expr instanceof Expr\StaticPropertyFetch && $expr->class instanceof Expr) {
			$gen = $this->getNullsafeShortCircuitingType($expr->class, $type);
			yield from $gen;
			return $gen->getReturn();
		}

		if ($expr instanceof MethodCall) {
			$gen = $this->getNullsafeShortCircuitingType($expr->var, $type);
			yield from $gen;
			return $gen->getReturn();
		}

		if ($expr instanceof Expr\StaticCall && $expr->class instanceof Expr) {
			$gen = $this->getNullsafeShortCircuitingType($expr->class, $type);
			yield from $gen;
			return $gen->getReturn();
		}

		return $type;
	}

}
