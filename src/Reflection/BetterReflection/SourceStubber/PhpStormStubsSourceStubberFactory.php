<?php 

namespace PHPStan\Reflection\BetterReflection\SourceStubber;
return;

use PhpParser\Parser;
use PHPStan\BetterReflection\SourceLocator\SourceStubber\PhpStormStubsSourceStubber;
use PHPStan\Node\Printer\Printer;
use PHPStan\Php\PhpVersion;

final class PhpStormStubsSourceStubberFactory
{

	public function __construct(private Parser $phpParser, private Printer $printer, private PhpVersion $phpVersion)
	{
	}

	public function create(): PhpStormStubsSourceStubber
	{
		return new PhpStormStubsSourceStubber($this->phpParser, $this->printer, $this->phpVersion->getVersionId());
	}

}
