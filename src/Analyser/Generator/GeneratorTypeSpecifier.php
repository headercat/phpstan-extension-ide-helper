<?php 

namespace PHPStan\Analyser\Generator;
return;

use Fiber;
use PhpParser\Node\Expr;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Type;

final class GeneratorTypeSpecifier implements TypeSpecifier
{

	public function __construct(
		private SpecifiedTypesHelper $specifiedTypesHelper,
	)
	{
	}

	public function specifyTypesInCondition(Scope $scope, Expr $expr, TypeSpecifierContext $context): SpecifiedTypes
	{
		if (!$scope instanceof GeneratorScope) {
			throw new ShouldNotHappenException();
		}

		/** @var ExprAnalysisResult $result */
		$result = Fiber::suspend(ExprAnalysisRequest::createNoopRequest($expr, $scope));
		if ($context->null()) {
			return $result->specifiedNullTypes;
		}
		if ($context->truthy()) {
			return $result->specifiedTruthyTypes;
		}
		if ($context->falsey()) {
			return $result->specifiedFalseyTypes;
		}

		throw new ShouldNotHappenException('Unknown TypeSpecifierContext');
	}

	public function create(Expr $expr, Type $type, TypeSpecifierContext $context, Scope $scope): SpecifiedTypes
	{
		return $this->specifiedTypesHelper->create($expr, $type, $context);
	}

}
