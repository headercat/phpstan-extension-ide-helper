<?php 

namespace PHPStan\Reflection;
return;

/** @api */
interface NamespaceAnswerer
{

	/**
	 * @return non-empty-string|null
	 */
	public function getNamespace(): ?string;

}
