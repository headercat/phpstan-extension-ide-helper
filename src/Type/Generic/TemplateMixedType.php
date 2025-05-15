<?php 

namespace PHPStan\Type\Generic;
return;

use PHPStan\Type\AcceptsResult;
use PHPStan\Type\IsSuperTypeOfResult;
use PHPStan\Type\MixedType;
use PHPStan\Type\StrictMixedType;
use PHPStan\Type\Type;

/** @api */
final class TemplateMixedType extends MixedType implements TemplateType
{

	/** @use TemplateTypeTrait<MixedType> */
	use TemplateTypeTrait;

	/**
	 * @param non-empty-string $name
	 */
	public function __construct(
		TemplateTypeScope $scope,
		TemplateTypeStrategy $templateTypeStrategy,
		TemplateTypeVariance $templateTypeVariance,
		string $name,
		MixedType $bound,
		?Type $default,
	)
	{
		parent::__construct(true);

		$this->scope = $scope;
		$this->strategy = $templateTypeStrategy;
		$this->variance = $templateTypeVariance;
		$this->name = $name;
		$this->bound = $bound;
		$this->default = $default;
	}

	public function isSuperTypeOfMixed(MixedType $type): IsSuperTypeOfResult
	{
		return $this->isSuperTypeOf($type);
	}

	public function isAcceptedBy(Type $acceptingType, bool $strictTypes): AcceptsResult
	{
		$isSuperType = $this->isSuperTypeOf($acceptingType)->toAcceptsResult();
		if ($isSuperType->no()) {
			return $isSuperType;
		}
		return AcceptsResult::createYes();
	}

	public function toStrictMixedType(): TemplateStrictMixedType
	{
		return new TemplateStrictMixedType(
			$this->scope,
			$this->strategy,
			$this->variance,
			$this->name,
			new StrictMixedType(),
			$this->default,
		);
	}

}
