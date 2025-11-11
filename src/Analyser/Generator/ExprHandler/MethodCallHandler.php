<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use Generator;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\ExprAnalysisResult;
use PHPStan\Analyser\Generator\ExprHandler;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\ShouldNotHappenException;

/**
 * @implements ExprHandler<MethodCall>
 */
#[AutowiredService]
final class MethodCallHandler implements ExprHandler
{

	public function supports(Expr $expr): bool
	{
		return $expr instanceof MethodCall;
	}

	public function analyseExpr(Expr $expr, GeneratorScope $scope): Generator
	{
		if (!$expr->name instanceof Identifier) {
			throw new ShouldNotHappenException('Not implemented');
		}

		$varResult = yield new ExprAnalysisRequest($expr->var, $scope);
		$currentScope = $varResult->scope;
		$argTypes = [];

		foreach ($expr->getArgs() as $arg) {
			$argResult = yield new ExprAnalysisRequest($arg->value, $currentScope);
			$argTypes[] = $argResult->type;
			$currentScope = $argResult->scope;
		}

		if ($varResult->type->hasMethod($expr->name->toString())->yes()) {
			$method = $varResult->type->getMethod($expr->name->toString(), $scope);
			return new ExprAnalysisResult($method->getOnlyVariant()->getReturnType(), $scope);
		}

		throw new ShouldNotHappenException('Not implemented');
	}

}
