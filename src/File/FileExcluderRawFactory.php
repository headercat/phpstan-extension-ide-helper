<?php 

namespace PHPStan\File;
return;

interface FileExcluderRawFactory
{

	/**
	 * @param string[] $analyseExcludes
	 */
	public function create(
		array $analyseExcludes,
	): FileExcluder;

}
