<?php 

namespace PHPStan\Analyser\Generator;
return;

use PhpParser\Node\Expr;

final class StoreExprResultRequest
{

	/**
	 * @param list<IdentifiedGeneratorInStack> $stack
	 */
	public function __construct(
		public readonly Expr $expr,
		public readonly ExprAnalysisResult $result,
		public readonly array $stack,
		public readonly ?string $file,
		public readonly ?int $line,
	)
	{
	}

}
