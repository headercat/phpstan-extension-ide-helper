<?php 

namespace PHPStan\Analyser\Generator;
return;

use PHPStan\Analyser\ScopeContext;
use PHPStan\DependencyInjection\AutowiredService;

#[AutowiredService]
final class GeneratorScopeFactory
{

	public function __construct(
		private InternalGeneratorScopeFactory $internalScopeFactory,
	)
	{
	}

	public function create(ScopeContext $context): GeneratorScope
	{
		return $this->internalScopeFactory->create($context);
	}

}
