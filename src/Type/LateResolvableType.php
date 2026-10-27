<?php 

namespace PHPStan\Type;
return;

use PHPStan\Turbo\ReferencedByTurboExtension;

/** @api */
#[ReferencedByTurboExtension(key: 'lateResolvableType')]
interface LateResolvableType
{

	public function resolve(): Type;

	public function isResolvable(): bool;

}
