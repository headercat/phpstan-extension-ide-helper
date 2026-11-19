<?php 

namespace PHPStan\Analyser;
return;

use PhpParser\Node;
use PHPStan\DependencyInjection\AutowiredService;

/**
 * @api
 */
#[AutowiredService]
final class ScopeFactory
{

	public function __construct(
		private InternalScopeFactoryFactory $internalScopeFactoryFactory,
	)
	{
	}

	/**
	 * @param callable(Node $node, Scope $scope): void $nodeCallback
	 */
	public function create(ScopeContext $context, ?callable $nodeCallback = null): MutatingScope
	{
		return $this->internalScopeFactoryFactory->create($nodeCallback)->create($context);
	}

}
