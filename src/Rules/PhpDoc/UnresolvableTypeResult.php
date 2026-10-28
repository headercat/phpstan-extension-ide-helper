<?php 

namespace PHPStan\Rules\PhpDoc;
return;

use PHPStan\Turbo\ReferencedByTurboExtension;

#[ReferencedByTurboExtension(key: 'unresolvableTypeResult')]
final class UnresolvableTypeResult
{

	/** @param list<string> $reasons */
	public function __construct(public readonly array $reasons)
	{
	}

}
