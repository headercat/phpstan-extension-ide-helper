<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use Generator;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\ExpressionContext;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\ExprAnalysisResult;
use PHPStan\Analyser\Generator\ExprHandler;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\ShouldNotHappenException;
use function array_merge;

/**
 * @implements ExprHandler<FuncCall>
 */
#[AutowiredService]
final class FuncCallHandler implements ExprHandler
{

	public function __construct(private ReflectionProvider $reflectionProvider)
	{
	}

	public function supports(Expr $expr): bool
	{
		return $expr instanceof FuncCall && !$expr->isFirstClassCallable();
	}

	public function analyseExpr(
		Stmt $stmt,
		Expr $expr,
		GeneratorScope $scope,
		ExpressionContext $context,
		?callable $alternativeNodeCallback,
	): Generator
	{
		$throwPoints = [];
		$impurePoints = [];
		$isAlwaysTerminating = false;
		$hasYield = false;
		if ($expr->name instanceof Expr) {
			$nameResult = yield new ExprAnalysisRequest($stmt, $expr->name, $scope, $context->enterDeep(), $alternativeNodeCallback);
			$scope = $nameResult->scope;
			$throwPoints = $nameResult->throwPoints;
			$impurePoints = $nameResult->impurePoints;
			$isAlwaysTerminating = $nameResult->isAlwaysTerminating;
			$hasYield = $nameResult->hasYield;
		}

		$argTypes = [];

		foreach ($expr->getArgs() as $arg) {
			$argResult = yield new ExprAnalysisRequest($stmt, $arg->value, $scope, $context->enterDeep(), $alternativeNodeCallback);
			$argTypes[] = $argResult->type;
			$scope = $argResult->scope;
			$throwPoints = array_merge($throwPoints, $argResult->throwPoints);
			$impurePoints = array_merge($impurePoints, $argResult->impurePoints);
			$isAlwaysTerminating = $isAlwaysTerminating || $argResult->isAlwaysTerminating;
			$hasYield = $hasYield || $argResult->hasYield;
		}

		if ($expr->name instanceof Name) {
			if ($this->reflectionProvider->hasFunction($expr->name, $scope)) {
				$function = $this->reflectionProvider->getFunction($expr->name, $scope);
				return new ExprAnalysisResult(
					$function->getOnlyVariant()->getReturnType(),
					$function->getOnlyVariant()->getNativeReturnType(),
					$scope,
					hasYield: $hasYield,
					isAlwaysTerminating: $isAlwaysTerminating,
					throwPoints: $throwPoints,
					impurePoints: $impurePoints,
					specifiedTruthyTypes: new SpecifiedTypes(),
					specifiedFalseyTypes: new SpecifiedTypes(),
					specifiedNullTypes: new SpecifiedTypes(),
				);
			}
		}

		throw new ShouldNotHappenException('Not implemented');
	}

}
