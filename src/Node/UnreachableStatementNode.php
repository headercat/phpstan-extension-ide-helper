<?php 

namespace PHPStan\Node;
return;

use PhpParser\Node\Stmt;

/**
 * @api
 */
final class UnreachableStatementNode extends Stmt implements VirtualNode
{

	/** @param Stmt[] $nextStatements */
	public function __construct(private Stmt $originalStatement, private array $nextStatements = [])
	{
		parent::__construct($originalStatement->getAttributes());
	}

	public function getOriginalStatement(): Stmt
	{
		return $this->originalStatement;
	}

	public function getType(): string
	{
		return 'PHPStan_Stmt_UnreachableStatementNode';
	}

	/**
	 * @return string[]
	 */
	public function getSubNodeNames(): array
	{
		return [];
	}

	/**
	 * @return Stmt[]
	 */
	public function getNextStatements(): array
	{
		return $this->nextStatements;
	}

}
