<?php 

namespace PHPStan\Testing\PHPUnit;
return;

use PHPStan\DependencyInjection\InvalidIgnoredErrorExceptionTest;

final class ContainerInitializer
{

	public static function initialize(string $testClassName): void
	{
		// This test expects an exception during container initialization
		if ($testClassName === InvalidIgnoredErrorExceptionTest::class) {
			return;
		}

		$testClassName::getContainer();
	}

}
