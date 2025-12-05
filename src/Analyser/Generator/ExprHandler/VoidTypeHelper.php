<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\NullType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeTraverser;
use PHPStan\Type\UnionType;

#[AutowiredService]
final class VoidTypeHelper
{

	public function transformVoidToNull(Type $type): Type
	{
		return TypeTraverser::map($type, static function (Type $type, callable $traverse): Type {
			if ($type instanceof UnionType || $type instanceof IntersectionType) {
				return $traverse($type);
			}

			if ($type->isVoid()->yes()) {
				return new NullType();
			}

			return $type;
		});
	}

}
