<?php 

namespace PHPStan\Reflection\BetterReflection\SourceStubber;
return;

use PHPStan\BetterReflection\SourceLocator\SourceStubber\ReflectionSourceStubber;
use PHPStan\Node\Printer\Printer;
use PHPStan\Php\PhpVersion;

final class ReflectionSourceStubberFactory
{

	public function __construct(private Printer $printer, private PhpVersion $phpVersion)
	{
	}

	public function create(): ReflectionSourceStubber
	{
		return new ReflectionSourceStubber($this->printer, $this->phpVersion->getVersionId());
	}

}
