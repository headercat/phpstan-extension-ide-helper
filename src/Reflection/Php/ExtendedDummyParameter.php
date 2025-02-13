<?php 

namespace PHPStan\Reflection\Php;
return;

use PHPStan\Reflection\AttributeReflection;
use PHPStan\Reflection\ExtendedParameterReflection;
use PHPStan\Reflection\PassedByReference;
use PHPStan\TrinaryLogic;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;

final class ExtendedDummyParameter extends DummyParameter implements ExtendedParameterReflection
{

	/**
	 * @param list<AttributeReflection> $attributes
	 */
	public function __construct(
		string $name,
		Type $type,
		bool $optional,
		?PassedByReference $passedByReference,
		bool $variadic,
		?Type $defaultValue,
		private Type $nativeType,
		private Type $phpDocType,
		private ?Type $outType,
		private TrinaryLogic $immediatelyInvokedCallable,
		private ?Type $closureThisType,
		private array $attributes,
	)
	{
		parent::__construct($name, $type, $optional, $passedByReference, $variadic, $defaultValue);
	}

	public function getPhpDocType(): Type
	{
		return $this->phpDocType;
	}

	public function hasNativeType(): bool
	{
		return !$this->nativeType instanceof MixedType || $this->nativeType->isExplicitMixed();
	}

	public function getNativeType(): Type
	{
		return $this->nativeType;
	}

	public function getOutType(): ?Type
	{
		return $this->outType;
	}

	public function isImmediatelyInvokedCallable(): TrinaryLogic
	{
		return $this->immediatelyInvokedCallable;
	}

	public function getClosureThisType(): ?Type
	{
		return $this->closureThisType;
	}

	public function getAttributes(): array
	{
		return $this->attributes;
	}

}
