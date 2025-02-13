<?php 

namespace PHPStan\Reflection\BetterReflection\SourceLocator;
return;

use PHPStan\BetterReflection\SourceLocator\Type\Composer\Psr\PsrAutoloaderMapping;

interface OptimizedPsrAutoloaderLocatorFactory
{

	public function create(PsrAutoloaderMapping $mapping): OptimizedPsrAutoloaderLocator;

}
