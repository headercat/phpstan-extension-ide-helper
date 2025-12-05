<?php 

namespace PHPStan\Analyser\Generator;
return;

use PHPStan\Analyser\ImpurePoint;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Type\Type;
use PHPStan\Type\TypeUtils;

final class ExprAnalysisResult
{

	public readonly Type $type;

	public readonly Type $nativeType;

	public readonly Type $keepVoidType;

	/**
	 * @param InternalThrowPoint[] $throwPoints
	 * @param ImpurePoint[] $impurePoints
	 */
	public function __construct(
		Type $type,
		Type $nativeType,
		public readonly GeneratorScope $scope,
		public readonly bool $hasYield,
		public readonly bool $isAlwaysTerminating,
		public readonly array $throwPoints,
		public readonly array $impurePoints,
		public readonly SpecifiedTypes $specifiedTruthyTypes,
		public readonly SpecifiedTypes $specifiedFalseyTypes,
		public readonly SpecifiedTypes $specifiedNullTypes,
		?Type $keepVoidType = null,
	)
	{
		$this->type = TypeUtils::resolveLateResolvableTypes($type);
		$this->nativeType = TypeUtils::resolveLateResolvableTypes($nativeType);

		if ($keepVoidType !== null) {
			$this->keepVoidType = TypeUtils::resolveLateResolvableTypes($keepVoidType);
		} else {
			$this->keepVoidType = $this->type;
		}
	}

}
