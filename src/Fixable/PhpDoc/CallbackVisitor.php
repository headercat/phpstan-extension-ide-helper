<?php 

namespace PHPStan\Fixable\PhpDoc;
return;

use Override;
use PHPStan\PhpDocParser\Ast\AbstractNodeVisitor;
use PHPStan\PhpDocParser\Ast\Node;

final class CallbackVisitor extends AbstractNodeVisitor
{

	/** @var callable(Node): (Node|Node[]|null) */
	private $callback;

	/** @param callable(Node): (Node|Node[]|null) $callback */
	public function __construct(callable $callback)
	{
		$this->callback = $callback;
	}

	/**
	 * @return Node[]|Node|null
	 */
	#[Override]
	public function enterNode(Node $node): array|Node|null
	{
		$callback = $this->callback;

		return $callback($node);
	}

}
