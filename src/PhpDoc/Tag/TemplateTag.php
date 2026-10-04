<?php 

namespace PHPStan\PhpDoc\Tag;
return;

use PHPStan\Turbo\ReferencedByTurboExtension;
use PHPStan\Type\Generic\TemplateTypeVariance;
use PHPStan\Type\Type;

/**
 * @api
 */
#[ReferencedByTurboExtension(key: 'templateTag')]
final class TemplateTag
{

	/**
	 * @param non-empty-string $name
	 */
	public function __construct(private string $name, private Type $bound, private ?Type $default, private TemplateTypeVariance $variance)
	{
	}

	/**
	 * @return non-empty-string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	public function getBound(): Type
	{
		return $this->bound;
	}

	public function getDefault(): ?Type
	{
		return $this->default;
	}

	public function getVariance(): TemplateTypeVariance
	{
		return $this->variance;
	}

}
