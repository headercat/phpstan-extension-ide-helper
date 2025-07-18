<?php 

namespace PHPStan\Dependency\ExportedNode;
return;

use JsonSerializable;
use Override;
use PHPStan\Dependency\ExportedNode;
use PHPStan\ShouldNotHappenException;
use ReturnTypeWillChange;
use function array_map;
use function count;

final class ExportedClassConstantNode implements ExportedNode, JsonSerializable
{

	/**
	 * @param ExportedAttributeNode[] $attributes
	 */
	public function __construct(
		private string $name,
		private string $value,
		private array $attributes,
	)
	{
	}

	public function equals(ExportedNode $node): bool
	{
		if (!$node instanceof self) {
			return false;
		}

		if (count($this->attributes) !== count($node->attributes)) {
			return false;
		}

		foreach ($this->attributes as $i => $attribute) {
			if (!$attribute->equals($node->attributes[$i])) {
				return false;
			}
		}

		return $this->name === $node->name
			&& $this->value === $node->value;
	}

	/**
	 * @param mixed[] $properties
	 */
	public static function __set_state(array $properties): self
	{
		return new self(
			$properties['name'],
			$properties['value'],
			$properties['attributes'],
		);
	}

	/**
	 * @param mixed[] $data
	 */
	public static function decode(array $data): self
	{
		return new self(
			$data['name'],
			$data['value'],
			array_map(static function (array $attributeData): ExportedAttributeNode {
				if ($attributeData['type'] !== ExportedAttributeNode::class) {
					throw new ShouldNotHappenException();
				}
				return ExportedAttributeNode::decode($attributeData['data']);
			}, $data['attributes']),
		);
	}

	/**
	 * @return mixed
	 */
	#[ReturnTypeWillChange]
	#[Override]
	public function jsonSerialize()
	{
		return [
			'type' => self::class,
			'data' => [
				'name' => $this->name,
				'value' => $this->value,
				'attributes' => $this->attributes,
			],
		];
	}

}
