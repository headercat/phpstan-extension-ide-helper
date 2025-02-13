<?php 

namespace PHPStan\PhpDoc\Tag;
return;

use PHPStan\Type\Type;

/**
 * @api
 */
final class RequireExtendsTag
{

	public function __construct(private Type $type)
	{
	}

	public function getType(): Type
	{
		return $this->type;
	}

}
