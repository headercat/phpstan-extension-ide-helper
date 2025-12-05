<?php 

namespace PHPStan\Analyser\Generator;
return;

use Generator;
use Override;
use PhpParser\Node;
use function get_class;
use function is_array;
use function sprintf;

/**
 * @phpstan-import-type GeneratorTValueType from GeneratorNodeScopeResolver
 * @phpstan-import-type GeneratorTSendType from GeneratorNodeScopeResolver
 */
final class IdentifiedGeneratorInStack
{

	public bool $sendResultOnComplete = true;

	/**
	 * @param (
	 *     Generator<int, GeneratorTValueType, GeneratorTSendType, StmtAnalysisResult>| // analyseStmt
	 *     Generator<int, GeneratorTValueType, GeneratorTSendType, StmtAnalysisResult>| // analyseStmts
	 *     Generator<int, GeneratorTValueType, GeneratorTSendType, void>| // analyseAttrGroups
	 *     Generator<int, GeneratorTValueType, GeneratorTSendType, ExprAnalysisResult>| // analyseExpr
	 *     Generator<int, GeneratorTValueType, GeneratorTSendType, TypeExprResult>| // analyseExprForType
	 *     Generator<int, GeneratorTValueType, GeneratorTSendType, RunInFiberResult<mixed>>| // runInFiber
	 *     Generator<int, GeneratorTValueType, GeneratorTSendType, null> // invokeNodeCallback
	 * ) $generator
	 * @param Node|Node[] $node
	 */
	public function __construct(
		public Generator $generator,
		public Node|array $node,
		public ?string $file,
		public ?int $line,
	)
	{
	}

	#[Override]
	public function __toString(): string
	{
		if ($this->file === null || $this->line === null) {
			return '';
		}

		if (is_array($this->node)) {
			return sprintf(
				"Stmts\n    -> %s on line %d",
				$this->file,
				$this->line,
			);
		}

		return sprintf(
			"%s:%d\n    -> %s on line %d",
			$this->file,
			$this->line,
			get_class($this->node),
			$this->node->getStartLine(),
		);
	}

}
