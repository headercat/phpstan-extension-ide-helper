<?php 

namespace PHPStan\PhpDoc;
return;

use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Php\PhpVersion;

#[AutowiredService]
final class JsonValidateStubFilesExtension implements StubFilesExtension
{

	public function __construct(private PhpVersion $phpVersion)
	{
	}

	public function getFiles(): array
	{
		if (!$this->phpVersion->supportsJsonValidate()) {
			return [];
		}

		return [__DIR__ . '/../../stubs/json_validate.stub'];
	}

}
