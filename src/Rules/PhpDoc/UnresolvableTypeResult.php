<?php 

namespace PHPStan\Rules\PhpDoc;
return;

final class UnresolvableTypeResult
{

	/** @param list<string> $reasons */
	public function __construct(public readonly array $reasons)
	{
	}

}
