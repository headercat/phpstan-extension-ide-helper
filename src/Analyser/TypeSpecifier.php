<?php 

namespace PHPStan\Analyser;
return;

use PhpParser\Node\Expr;
use PHPStan\Type\Type;

/**
 * @api
 */
interface TypeSpecifier
{

	public function specifyTypesInCondition(
		Scope $scope,
		Expr $expr,
		TypeSpecifierContext $context,
	): SpecifiedTypes;

	public function create(
		Expr $expr,
		Type $type,
		TypeSpecifierContext $context,
		Scope $scope,
	): SpecifiedTypes;

}
