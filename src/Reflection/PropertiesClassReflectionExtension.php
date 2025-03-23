<?php 

namespace PHPStan\Reflection;
return;

/**
 * This is the interface custom properties class reflection extensions implement.
 *
 * To register it in the configuration file use the `phpstan.broker.propertiesClassReflectionExtension` service tag:
 *
 * ```
 * services:
 * 	-
 *		class: App\PHPStan\MyPropertiesClassReflectionExtension
 *		tags:
 *			- phpstan.broker.propertiesClassReflectionExtension
 * ```
 *
 * Learn more: https://phpstan.org/developing-extensions/class-reflection-extensions
 *
 * @api
 */
interface PropertiesClassReflectionExtension
{

	public function hasProperty(ClassReflection $classReflection, string $propertyName): bool;

	public function getProperty(ClassReflection $classReflection, string $propertyName): PropertyReflection;

}
