<?php 

namespace PHPStan\Reflection\ReflectionProvider;
return;

use PHPStan\Reflection\ReflectionProvider;

final class ReflectionProviderFactory
{

	public function __construct(
		private ReflectionProvider $staticReflectionProvider,
	)
	{
	}

	public function create(): ReflectionProvider
	{
		return new MemoizingReflectionProvider($this->staticReflectionProvider);
	}

}
