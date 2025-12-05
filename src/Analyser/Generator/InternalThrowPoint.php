<?php 

namespace PHPStan\Analyser\Generator;
return;

use PhpParser\Node;
use PHPStan\Analyser\ThrowPoint;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Throwable;

final class InternalThrowPoint
{

	/**
	 * @param Node\Expr|Node\Stmt $node
	 */
	private function __construct(
		public readonly GeneratorScope $scope,
		public readonly Type $type,
		public readonly Node $node,
		public readonly bool $explicit,
		public readonly bool $canContainAnyThrowable,
	)
	{
	}

	public function toPublic(): ThrowPoint
	{
		if ($this->explicit) {
			return ThrowPoint::createExplicit($this->scope, $this->type, $this->node, $this->canContainAnyThrowable);
		}

		return ThrowPoint::createImplicit($this->scope, $this->node);
	}

	/**
	 * @param Node\Expr|Node\Stmt $node
	 */
	public static function createExplicit(GeneratorScope $scope, Type $type, Node $node, bool $canContainAnyThrowable): self
	{
		return new self($scope, $type, $node, true, $canContainAnyThrowable);
	}

	/**
	 * @param Node\Expr|Node\Stmt $node
	 */
	public static function createImplicit(GeneratorScope $scope, Node $node): self
	{
		return new self($scope, new ObjectType(Throwable::class), $node, false, true);
	}

	public static function createFromPublic(ThrowPoint $throwPoint): self
	{
		$scope = $throwPoint->getScope();
		if (!$scope instanceof GeneratorScope) {
			throw new ShouldNotHappenException();
		}

		return new self($scope, $throwPoint->getType(), $throwPoint->getNode(), $throwPoint->isExplicit(), $throwPoint->canContainAnyThrowable());
	}

	public function subtractCatchType(Type $catchType): self
	{
		return new self($this->scope, TypeCombinator::remove($this->type, $catchType), $this->node, $this->explicit, $this->canContainAnyThrowable);
	}

}
