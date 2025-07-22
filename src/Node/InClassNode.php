<?php 

namespace PHPStan\Node;
return;

use Override;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PHPStan\Reflection\ClassReflection;

/**
 * @api
 */
final class InClassNode extends Node\Stmt implements VirtualNode
{

	public function __construct(private ClassLike $originalNode, private ClassReflection $classReflection)
	{
		parent::__construct($originalNode->getAttributes());
	}

	public function getOriginalNode(): ClassLike
	{
		return $this->originalNode;
	}

	public function getClassReflection(): ClassReflection
	{
		return $this->classReflection;
	}

	#[Override]
	public function getType(): string
	{
		return 'PHPStan_Stmt_InClassNode';
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
