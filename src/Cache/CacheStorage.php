<?php 

namespace PHPStan\Cache;
return;

interface CacheStorage
{

	/**
	 * @return mixed|null
	 */
	public function load(string $key, string $variableKey);

	/**
	 * @param mixed $data
	 */
	public function save(string $key, string $variableKey, $data): void;

}
