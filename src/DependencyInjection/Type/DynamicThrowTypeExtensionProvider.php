<?php 

namespace PHPStan\DependencyInjection\Type;
return;

use PHPStan\Type\DynamicFunctionThrowTypeExtension;
use PHPStan\Type\DynamicMethodThrowTypeExtension;
use PHPStan\Type\DynamicStaticMethodThrowTypeExtension;

interface DynamicThrowTypeExtensionProvider
{

	/** @return DynamicFunctionThrowTypeExtension[] */
	public function getDynamicFunctionThrowTypeExtensions(): array;

	/** @return DynamicMethodThrowTypeExtension[] */
	public function getDynamicMethodThrowTypeExtensions(): array;

	/** @return DynamicStaticMethodThrowTypeExtension[] */
	public function getDynamicStaticMethodThrowTypeExtensions(): array;

}
