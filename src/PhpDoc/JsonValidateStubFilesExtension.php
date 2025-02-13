<?php 

namespace PHPStan\PhpDoc;
return;

use PHPStan\Php\PhpVersion;

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
