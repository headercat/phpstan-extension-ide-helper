<?php 

namespace PHPStan\Analyser\Generator;
return;

use PhpParser\Node\Expr;
use PHPStan\ShouldNotHappenException;
use SplObjectStorage;
use function get_class;
use function sprintf;

final class ExprAnalysisResultStorage
{

	/** @var SplObjectStorage<Expr, ExprAnalysisResult> */
	private SplObjectStorage $expressionAnalysisResults;

	/** @var SplObjectStorage<Expr, array{list<IdentifiedGeneratorInStack>, ?string, ?int}> */
	private SplObjectStorage $whereAnalysed;

	public function __construct()
	{
		$this->expressionAnalysisResults = new SplObjectStorage();
		$this->whereAnalysed = new SplObjectStorage();
	}

	public function duplicate(): self
	{
		$new = new self();
		$new->expressionAnalysisResults->addAll($this->expressionAnalysisResults);
		$new->whereAnalysed->addAll($this->whereAnalysed);
		return $new;
	}

	/**
	 * @param list<IdentifiedGeneratorInStack> $stack
	 */
	public function storeExprAnalysisResult(Expr $expr, ExprAnalysisResult $result, array $stack, ?string $file, ?int $line): void
	{
		$this->expressionAnalysisResults[$expr] = $result;
		$this->whereAnalysed[$expr] = [$stack, $file, $line];
	}

	public function findExprAnalysisResult(Expr $expr): ?ExprAnalysisResult
	{
		return $this->expressionAnalysisResults[$expr] ?? null;
	}

	/**
	 * @return array{list<IdentifiedGeneratorInStack>, ?string, ?int}|null
	 */
	public function findExprAnalysisResultOrigin(Expr $expr): ?array
	{
		return $this->whereAnalysed[$expr] ?? null;
	}

	public function getExprAnalysisResult(Expr $expr): ExprAnalysisResult
	{
		if (!isset($this->expressionAnalysisResults[$expr])) {
			throw new ShouldNotHappenException(sprintf('Result for expr %s not found', get_class($expr)));
		}

		return $this->expressionAnalysisResults[$expr];
	}

}
