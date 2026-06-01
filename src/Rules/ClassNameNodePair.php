<?php 

namespace PHPStan\Rules;
return;

use PhpParser\Node;

final class ClassNameNodePair
{

	/**
	 * @param non-empty-string $className
	 */
	public function __construct(private string $className, private Node $node)
	{
	}

	/**
	 * @return non-empty-string
	 */
	public function getClassName(): string
	{
		return $this->className;
	}

	public function getNode(): Node
	{
		return $this->node;
	}

}
