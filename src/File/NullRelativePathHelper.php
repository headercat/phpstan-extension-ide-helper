<?php 

namespace PHPStan\File;
return;

final class NullRelativePathHelper implements RelativePathHelper
{

	public function getRelativePath(string $filename): string
	{
		return $filename;
	}

}
