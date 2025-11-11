<?php 

namespace PHPStan\Analyser\Generator;
return;

use PHPStan\Analyser\ExpressionTypeHolder;
use PHPStan\Analyser\ScopeContext;
use PHPStan\Reflection\Php\PhpFunctionFromParserNodeReflection;

interface InternalGeneratorScopeFactory
{

	/**
	 * @param array<string, ExpressionTypeHolder> $expressionTypes
	 * @param array<string, ExpressionTypeHolder> $nativeExpressionTypes
	 */
	public function create(
		ScopeContext $context,
		bool $declareStrictTypes = false,
		PhpFunctionFromParserNodeReflection|null $function = null,
		?string $namespace = null,
		array $expressionTypes = [],
		array $nativeExpressionTypes = [],
	): GeneratorScope;

}
