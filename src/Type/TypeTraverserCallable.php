<?php 

namespace PHPStan\Type;
return;

/**
 * @api
 */
interface TypeTraverserCallable
{

	/**
	 * @param callable(Type): Type $traverse
	 */
	public function traverse(Type $type, callable $traverse): Type;

}
