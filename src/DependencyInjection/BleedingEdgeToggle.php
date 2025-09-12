<?php 

namespace PHPStan\DependencyInjection;
return;

final class BleedingEdgeToggle
{

	private static bool $bleedingEdge = false;

	public static function isBleedingEdge(): bool // @phpstan-ignore shipmonk.deadMethod (kept for future use)
	{
		return self::$bleedingEdge;
	}

	public static function setBleedingEdge(bool $bleedingEdge): void
	{
		self::$bleedingEdge = $bleedingEdge;
	}

}
