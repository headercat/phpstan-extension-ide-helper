<?php 

namespace PHPStan\Rules\RestrictedUsage;
return;

use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassConstantReflection;

/**
 * Extensions implementing this interface are called for each analysed class constant access.
 *
 * Extension can decide to create RestrictedUsage object
 * with error message & error identifier to be reported for this class constant access.
 *
 * Typical usage is to report errors for constants marked as @-deprecated or @-internal.
 *
 * To register it in the configuration file use the following tag:
 *
 * ```
 * services:
 * 	-
 *		class: App\PHPStan\MyExtension
 *		tags:
 *			- phpstan.restrictedClassConstantUsageExtension
 * ```
 *
 * @api
 */
interface RestrictedClassConstantUsageExtension
{

	public const CLASS_CONSTANT_EXTENSION_TAG = 'phpstan.restrictedClassConstantUsageExtension';

	public function isRestrictedClassConstantUsage(
		ClassConstantReflection $constantReflection,
		Scope $scope,
	): ?RestrictedUsage;

}
