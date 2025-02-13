<?php 

namespace PHPStan\Node;
return;

use PhpParser\NodeAbstract;
use PHPStan\Analyser\StatementExitPoint;

/**
 * @api
 */
final class FinallyExitPointsNode extends NodeAbstract implements VirtualNode
{

	/**
	 * @param StatementExitPoint[] $finallyExitPoints
	 * @param StatementExitPoint[] $tryCatchExitPoints
	 */
	public function __construct(private array $finallyExitPoints, private array $tryCatchExitPoints)
	{
		parent::__construct([]);
	}

	/**
	 * @return StatementExitPoint[]
	 */
	public function getFinallyExitPoints(): array
	{
		return $this->finallyExitPoints;
	}

	/**
	 * @return StatementExitPoint[]
	 */
	public function getTryCatchExitPoints(): array
	{
		return $this->tryCatchExitPoints;
	}

	public function getType(): string
	{
		return 'PHPStan_Node_FinallyExitPointsNode';
	}

	/**
	 * @return string[]
	 */
	public function getSubNodeNames(): array
	{
		return [];
	}

}
