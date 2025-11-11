<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use Generator;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\ExprAnalysisResult;
use PHPStan\Analyser\Generator\ExprHandler;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\ShouldNotHappenException;

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
		return $expr instanceof FuncCall;
	}

	public function analyseExpr(Expr $expr, GeneratorScope $scope): Generator
	{
		if ($expr->name instanceof Expr) {
			$nameResult = yield new ExprAnalysisRequest($expr->name, $scope);
			$scope = $nameResult->scope;
		}

		$argTypes = [];

		foreach ($expr->getArgs() as $arg) {
			$argResult = yield new ExprAnalysisRequest($arg->value, $scope);
			$argTypes[] = $argResult->type;
			$scope = $argResult->scope;
		}

		if ($expr->name instanceof Name) {
			if ($this->reflectionProvider->hasFunction($expr->name, $scope)) {
				$function = $this->reflectionProvider->getFunction($expr->name, $scope);
				return new ExprAnalysisResult($function->getOnlyVariant()->getReturnType(), $scope);
			}
		}

		throw new ShouldNotHappenException('Not implemented');
	}

}
