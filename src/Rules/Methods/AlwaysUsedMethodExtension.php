<?php 

namespace PHPStan\Rules\Methods;
return;

use PHPStan\Reflection\ExtendedMethodReflection;

/**
 * This is the extension interface to implement if you want to describe an always-used class method.
 *
 * To register it in the configuration file use the `phpstan.methods.alwaysUsedMethodExtension` service tag:
 *
 * ```
 * services:
 * 	-
 *		class: App\PHPStan\MyExtension
 *		tags:
 *			- phpstan.methods.alwaysUsedMethodExtension
 * ```
 *
 * @api
 */
interface AlwaysUsedMethodExtension
{

	public function isAlwaysUsed(ExtendedMethodReflection $methodReflection): bool;

}
