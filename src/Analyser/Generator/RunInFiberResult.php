<?php 

namespace PHPStan\Analyser\Generator;
return;

/**
 * @template-covariant T
 */
final class RunInFiberResult
{

	/**
	 * @readonly
	 * @var T
	 */
	public mixed $value;

	/**
	 * @param T $value
	 */
	public function __construct(
		mixed $value,
	)
	{
		$this->value = $value;
	}

}
