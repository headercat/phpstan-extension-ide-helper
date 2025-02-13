<?php 

namespace PHPStan\Analyser;
return;

/**
 * @api
 */
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
