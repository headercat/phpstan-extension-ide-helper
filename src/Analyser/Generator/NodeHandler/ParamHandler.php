<?php 

namespace PHPStan\Analyser\Generator\NodeHandler;
return;

use Generator;
use PhpParser\Node;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\ExpressionContext;
use PHPStan\Analyser\Generator\AttrGroupsAnalysisRequest;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\GeneratorNodeScopeResolver;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\NodeCallbackRequest;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredService;

/**
 * @phpstan-import-type GeneratorTValueType from GeneratorNodeScopeResolver
 * @phpstan-import-type GeneratorTSendType from GeneratorNodeScopeResolver
 */
#[AutowiredService]
final class ParamHandler
{

	/**
	 * @param (callable(Node, Scope, callable(Node, Scope): void): void)|null $alternativeNodeCallback
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, void>
	 */
	public function processParam(
		Stmt $stmt,
		Param $param,
		GeneratorScope $scope,
		?callable $alternativeNodeCallback,
	): Generator
	{
		yield new AttrGroupsAnalysisRequest($stmt, $param->attrGroups, $scope, $alternativeNodeCallback);
		yield new NodeCallbackRequest($param, $scope, $alternativeNodeCallback);
		if ($param->type !== null) {
			yield new NodeCallbackRequest($param->type, $scope, $alternativeNodeCallback);
		}
		if ($param->default === null) {
			return;
		}

		yield new ExprAnalysisRequest($stmt, $param->default, $scope, ExpressionContext::createDeep(), $alternativeNodeCallback);
	}

}
