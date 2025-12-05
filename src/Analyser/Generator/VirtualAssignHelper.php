<?php 

namespace PHPStan\Analyser\Generator;
return;

use Generator;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\ExpressionContext;
use PHPStan\Analyser\Generator\ExprHandler\AssignHandler;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Node\PropertyAssignNode;
use PHPStan\Node\VariableAssignNode;
use PHPStan\Type\MixedType;

/**
 * @phpstan-import-type GeneratorTValueType from GeneratorNodeScopeResolver
 * @phpstan-import-type GeneratorTSendType from GeneratorNodeScopeResolver
 */
#[AutowiredService]
final class VirtualAssignHelper
{

	public function __construct(
		private AssignHandler $assignHandler,
	)
	{
	}

	/**
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, ExprAnalysisResult>
	 */
	public function processVirtualAssign(
		GeneratorScope $scope,
		Stmt $stmt,
		Expr $var,
		Expr $assignedExpr,
	): Generator
	{
		$assignVarGen = $this->assignHandler->processAssignVar(
			$scope,
			$stmt,
			$var,
			$assignedExpr,
			ExpressionContext::createDeep(),
			static function (Node $node, Scope $scope, callable $nodeCallback): void {
				if (!$node instanceof PropertyAssignNode && !$node instanceof VariableAssignNode) {
					return;
				}

				$nodeCallback($node, $scope);
			},
			static function (GeneratorScope $scope): Generator {
				yield from [];
				return new ExprAnalysisResult(
					new MixedType(),
					new MixedType(),
					$scope,
					hasYield: false,
					isAlwaysTerminating: false,
					throwPoints: [],
					impurePoints: [],
					specifiedTruthyTypes: new SpecifiedTypes(),
					specifiedFalseyTypes: new SpecifiedTypes(),
					specifiedNullTypes: new SpecifiedTypes(),
				);
			},
			false,
		);
		yield from $assignVarGen;
		return $assignVarGen->getReturn();
	}

}
