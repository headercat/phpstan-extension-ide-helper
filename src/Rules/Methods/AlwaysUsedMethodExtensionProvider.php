<?php 

namespace PHPStan\Rules\Methods;
return;

interface AlwaysUsedMethodExtensionProvider
{

	public const EXTENSION_TAG = 'phpstan.methods.alwaysUsedMethodExtension';

	/**
	 * @return AlwaysUsedMethodExtension[]
	 */
	public function getExtensions(): array;

}
