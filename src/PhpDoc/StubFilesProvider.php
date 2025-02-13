<?php 

namespace PHPStan\PhpDoc;
return;

interface StubFilesProvider
{

	/** @return string[] */
	public function getStubFiles(): array;

	/** @return string[] */
	public function getProjectStubFiles(): array;

}
