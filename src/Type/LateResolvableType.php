<?php 

namespace PHPStan\Type;
return;

/** @api */
interface LateResolvableType
{

	public function resolve(): Type;

	public function isResolvable(): bool;

}
