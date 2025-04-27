<?php 

namespace PHPStan\Type\Php;
return;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\Php\SimpleXMLElementProperty;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Type\BenevolentUnionType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;

final class SimpleXMLElementClassPropertyReflectionExtension implements PropertiesClassReflectionExtension
{

	public function hasProperty(ClassReflection $classReflection, string $propertyName): bool
	{
		return $classReflection->is('SimpleXMLElement');
	}

	public function getProperty(ClassReflection $classReflection, string $propertyName): PropertyReflection
	{
		return new SimpleXMLElementProperty($propertyName, $classReflection, new BenevolentUnionType([new ObjectType($classReflection->getName()), new NullType()]));
	}

}
