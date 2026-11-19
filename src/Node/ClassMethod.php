<?php 

namespace PHPStan\Node;
return;

use PHPStan\Turbo\ReferencedByTurboExtension;

/**
 * @api
 */
#[ReferencedByTurboExtension(key: 'gatheredClassMethod')]
final class ClassMethod
{

	public function __construct(
		private \PhpParser\Node\Stmt\ClassMethod $node,
		private bool $isDeclaredInTrait,
	)
	{
	}

	public function getNode(): \PhpParser\Node\Stmt\ClassMethod
	{
		return $this->node;
	}

	public function isDeclaredInTrait(): bool
	{
		return $this->isDeclaredInTrait;
	}

}
