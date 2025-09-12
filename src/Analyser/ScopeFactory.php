<?php 

namespace PHPStan\Analyser;
return;

use PHPStan\DependencyInjection\AutowiredService;

/**
 * @api
 */
#[AutowiredService]
final class ScopeFactory
{

	public function __construct(private InternalScopeFactory $internalScopeFactory)
	{
	}

	public function create(ScopeContext $context): MutatingScope
	{
		return $this->internalScopeFactory->create($context);
	}

}
