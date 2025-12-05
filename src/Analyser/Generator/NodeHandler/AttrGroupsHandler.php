<?php 

namespace PHPStan\Analyser\Generator\NodeHandler;
return;

use Generator;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\ArgumentsNormalizer;
use PHPStan\Analyser\ExpressionContext;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\GeneratorNodeScopeResolver;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\NodeCallbackRequest;
use PHPStan\Analyser\Generator\RunInFiberRequest;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;

/**
 * @phpstan-import-type GeneratorTValueType from GeneratorNodeScopeResolver
 * @phpstan-import-type GeneratorTSendType from GeneratorNodeScopeResolver
 */
#[AutowiredService]
final class AttrGroupsHandler
{

	public function __construct(
		private ReflectionProvider $reflectionProvider,
		private ArgsHandler $argsHandler,
	)
	{
	}

	/**
	 * @param Node\AttributeGroup[] $attrGroups
	 * @param (callable(Node, Scope, callable(Node, Scope): void): void)|null $alternativeNodeCallback
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, void>
	 */
	public function processAttributeGroups(
		Stmt $stmt,
		array $attrGroups,
		GeneratorScope $scope,
		?callable $alternativeNodeCallback,
	): Generator
	{
		foreach ($attrGroups as $attrGroup) {
			foreach ($attrGroup->attrs as $attr) {
				$className = $scope->resolveName($attr->name);
				if ($this->reflectionProvider->hasClass($className)) {
					$classReflection = $this->reflectionProvider->getClass($className);
					if ($classReflection->hasConstructor()) {
						$constructorReflection = $classReflection->getConstructor();
						$parametersAcceptor = (yield new RunInFiberRequest(static fn () => ParametersAcceptorSelector::selectFromArgs(
							$scope,
							$attr->args,
							$constructorReflection->getVariants(),
							$constructorReflection->getNamedArgumentsVariants(),
						)))->value;
						$expr = new New_($attr->name, $attr->args);
						$expr = ArgumentsNormalizer::reorderNewArguments($parametersAcceptor, $expr) ?? $expr;

						yield from $this->argsHandler->processArgs($stmt, $constructorReflection, null, $parametersAcceptor, $expr, $scope, ExpressionContext::createDeep(), $alternativeNodeCallback);
						yield new NodeCallbackRequest($attr, $scope, $alternativeNodeCallback);
						continue;
					}
				}

				foreach ($attr->args as $arg) {
					yield new ExprAnalysisRequest($stmt, $arg->value, $scope, ExpressionContext::createDeep(), $alternativeNodeCallback);
					yield new NodeCallbackRequest($arg, $scope, $alternativeNodeCallback);
				}
				yield new NodeCallbackRequest($attr, $scope, $alternativeNodeCallback);
			}
			yield new NodeCallbackRequest($attrGroup, $scope, $alternativeNodeCallback);
		}
	}

}
