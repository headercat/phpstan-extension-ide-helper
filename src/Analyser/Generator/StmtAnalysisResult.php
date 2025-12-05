<?php 

namespace PHPStan\Analyser\Generator;
return;

use PHPStan\Analyser\ImpurePoint;
use PHPStan\Analyser\StatementResult;
use function array_map;

final class StmtAnalysisResult
{

	/**
	 * @param InternalStatementExitPoint[] $exitPoints
	 * @param InternalThrowPoint[] $throwPoints
	 * @param ImpurePoint[] $impurePoints
	 * @param InternalEndStatementResult[] $endStatements
	 */
	public function __construct(
		public readonly GeneratorScope $scope,
		public readonly bool $hasYield,
		public readonly bool $isAlwaysTerminating,
		public readonly array $exitPoints,
		public readonly array $throwPoints,
		public readonly array $impurePoints,
		public readonly array $endStatements = [],
	)
	{
	}

	public function toPublic(): StatementResult
	{
		return new StatementResult(
			$this->scope,
			$this->hasYield,
			$this->isAlwaysTerminating,
			array_map(static fn ($exitPoint) => $exitPoint->toPublic(), $this->exitPoints),
			array_map(static fn ($throwPoint) => $throwPoint->toPublic(), $this->throwPoints),
			$this->impurePoints,
			array_map(static fn ($endStatement) => $endStatement->toPublic(), $this->endStatements),
		);
	}

	/**
	 * @param InternalThrowPoint[] $throwPoints
	 */
	public function withThrowPoints(array $throwPoints): self
	{
		return new self(
			$this->scope,
			$this->hasYield,
			$this->isAlwaysTerminating,
			$this->exitPoints,
			$throwPoints,
			$this->impurePoints,
			$this->endStatements,
		);
	}

}
