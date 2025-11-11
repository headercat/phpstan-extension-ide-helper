<?php 

namespace PHPStan\Analyser\Generator;
return;

use PHPStan\Type\Type;

final class ExprAnalysisResult
{

	public function __construct(
		public Type $type,
		public GeneratorScope $scope,
	)
	{
	}

}
