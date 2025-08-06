<?php 

namespace PHPStan\Node;
return;

use Override;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\NodeAbstract;
use PHPStan\Type\Type;

/**
 * @api
 */
final class CatchWithUnthrownExceptionNode extends NodeAbstract implements VirtualNode
{

	public function __construct(private Catch_ $originalNode, private Type $caughtType, private Type $originalCaughtType)
	{
		parent::__construct($originalNode->getAttributes());
	}

	public function getOriginalNode(): Catch_
	{
		return $this->originalNode;
	}

	public function getCaughtType(): Type
	{
		return $this->caughtType;
	}

	public function getOriginalCaughtType(): Type
	{
		return $this->originalCaughtType;
	}

	#[Override]
	public function getType(): string
	{
		return 'PHPStan_Node_CatchWithUnthrownExceptionNode';
	}

	/**
	 * @return string[]
	 */
	#[Override]
	public function getSubNodeNames(): array
	{
		return [];
	}

}
