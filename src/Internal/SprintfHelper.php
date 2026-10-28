<?php 

namespace PHPStan\Internal;
return;

use function str_replace;

final class SprintfHelper
{

	public static function escapeFormatString(string $format): string
	{
		return str_replace('%', '%%', $format);
	}

}
