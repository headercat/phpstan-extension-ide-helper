<?php 

namespace PHPStan\Type;
return;

use function spl_object_hash;

final class RecursionGuard
{

	/** @var true[] */
	private static array $context = [];

	/**
	 * @template T
	 * @param callable(): T $callback
	 * @return T|ErrorType
	 */
	public static function run(Type $type, callable $callback)
	{
		$key = $type->describe(VerbosityLevel::value());
		if (isset(self::$context[$key])) {
			return new ErrorType();
		}

		try {
			self::$context[$key] = true;
			return $callback();
		} finally {
			unset(self::$context[$key]);
		}
	}

	/**
	 * @template T
	 * @param callable(): T $callback
	 * @return T|ErrorType
	 */
	public static function runOnObjectIdentity(Type $type, callable $callback)
	{
		$key = spl_object_hash($type);
		if (isset(self::$context[$key])) {
			return new ErrorType();
		}

		try {
			self::$context[$key] = true;
			return $callback();
		} finally {
			unset(self::$context[$key]);
		}
	}

}
