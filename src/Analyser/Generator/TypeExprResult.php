<?php 

namespace PHPStan\Analyser\Generator;
return;

use PHPStan\Type\Type;

final class TypeExprResult
{

	public function __construct(
		public readonly Type $type,
		public readonly Type $nativeType,
		public readonly Type $keepVoidType,
	)
	{
	}

}
