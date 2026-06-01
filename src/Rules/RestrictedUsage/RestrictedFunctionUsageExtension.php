<?php 

namespace PHPStan\Rules\RestrictedUsage;
return;

use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;

/**
 * Extensions implementing this interface are called for each analysed function call.
 *
 * Extension can decide to create RestrictedUsage object
 * with error message & error identifier to be reported for this function call.
 *
 * Typical usage is to report errors for functions marked as @-deprecated or @-internal.
 *
 * To register it in the configuration file use the following tag:
 *
 * ```
 * services:
 * 	-
 *		class: App\PHPStan\MyExtension
 *		tags:
 *			- phpstan.restrictedFunctionUsageExtension
 * ```
 *
 * @api
 */
interface RestrictedFunctionUsageExtension
{

	public const FUNCTION_EXTENSION_TAG = 'phpstan.restrictedFunctionUsageExtension';

	public function isRestrictedFunctionUsage(
		FunctionReflection $functionReflection,
		Scope $scope,
	): ?RestrictedUsage;

}
