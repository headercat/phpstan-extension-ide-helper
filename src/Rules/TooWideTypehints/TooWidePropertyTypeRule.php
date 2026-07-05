<?php 

namespace PHPStan\Rules\TooWideTypehints;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\RegisteredRule;
use PHPStan\Node\ClassPropertiesNode;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Rules\Properties\ReadWritePropertiesExtensionProvider;
use PHPStan\Rules\Rule;
use function sprintf;

/**
 * @implements Rule<ClassPropertiesNode>
 */
#[RegisteredRule(level: 4)]
final class TooWidePropertyTypeRule implements Rule
{

	public function __construct(
		private ReadWritePropertiesExtensionProvider $extensionProvider,
		private TooWideTypeCheck $check,
	)
	{
	}

	public function getNodeType(): string
	{
		return ClassPropertiesNode::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$errors = [];
		$classReflection = $node->getClassReflection();

		foreach ($node->getProperties() as $property) {
			if (!$property->isPrivate()) {
				continue;
			}
			if ($property->isDeclaredInTrait()) {
				continue;
			}
			if ($property->isPromoted()) {
				continue;
			}
			$propertyName = $property->getName();
			if (!$classReflection->hasNativeProperty($propertyName)) {
				continue;
			}

			$propertyReflection = $classReflection->getNativeProperty($propertyName);

			foreach ($this->extensionProvider->getExtensions() as $extension) {
				if ($extension->isAlwaysRead($propertyReflection, $propertyName)) {
					continue 2;
				}
				if ($extension->isAlwaysWritten($propertyReflection, $propertyName)) {
					continue 2;
				}
				if ($extension->isInitialized($propertyReflection, $propertyName)) {
					continue 2;
				}
			}

			$propertyDescription = $this->describePropertyByName($propertyReflection, $propertyName);
			$propertyErrors = $this->check->checkProperty(
				$property,
				$propertyReflection->getDeclaringClass(),
				$node->getPropertyAssigns(),
				$propertyReflection->getNativeType(),
				$propertyReflection->getPhpDocType(),
				$propertyDescription,
				$scope,
			);
			foreach ($propertyErrors as $error) {
				$errors[] = $error;
			}
		}
		return $errors;
	}

	private function describePropertyByName(PropertyReflection $property, string $propertyName): string
	{
		if (!$property->isStatic()) {
			return sprintf('Property %s::$%s', $property->getDeclaringClass()->getDisplayName(), $propertyName);
		}

		return sprintf('Static property %s::$%s', $property->getDeclaringClass()->getDisplayName(), $propertyName);
	}

}
