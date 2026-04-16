<?php 

namespace PHPStan\Reflection;
return;

use PHPStan\BetterReflection\Reflection\Adapter\ReflectionClass;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionEnum;
use PHPStan\PhpDoc\ResolvedPhpDocBlock;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\Generic\TemplateTypeVarianceMap;
use ReflectionClass as CoreReflectionClass;

interface ClassReflectionFactory
{

	/**
	 * @param ReflectionClass|ReflectionEnum $reflection
	 */
	public function create(
		string $displayName,
		CoreReflectionClass $reflection,
		?string $anonymousFilename,
		?TemplateTypeMap $resolvedTemplateTypeMap,
		?ResolvedPhpDocBlock $stubPhpDocBlock,
		?string $extraCacheKey = null,
		?TemplateTypeVarianceMap $resolvedCallSiteVarianceMap = null,
		?bool $finalByKeywordOverride = null,
	): ClassReflection;

}
