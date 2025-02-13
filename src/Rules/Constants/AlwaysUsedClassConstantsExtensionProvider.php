<?php 

namespace PHPStan\Rules\Constants;
return;

interface AlwaysUsedClassConstantsExtensionProvider
{

	public const EXTENSION_TAG = 'phpstan.constants.alwaysUsedClassConstantsExtension';

	/**
	 * @return AlwaysUsedClassConstantsExtension[]
	 */
	public function getExtensions(): array;

}
