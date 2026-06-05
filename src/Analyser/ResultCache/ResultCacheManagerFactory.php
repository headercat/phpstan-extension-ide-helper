<?php 

namespace PHPStan\Analyser\ResultCache;
return;

interface ResultCacheManagerFactory
{

	/**
	 * @param array<string, string> $fileReplacements
	 */
	public function create(array $fileReplacements): ResultCacheManager;

}
