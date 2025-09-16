<?php 

namespace PHPStan\Rules;
return;

use PhpParser\Node;

final class ClassNameNodePair
{

	public function __construct(private string $className, private Node $node)
	{
	}

	public function getClassName(): string
	{
		return $this->className;
	}

	public function getNode(): Node
	{
		return $this->node;
	}

}
