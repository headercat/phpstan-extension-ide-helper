<?php 

namespace PHPStan\Analyser\Generator\ExprHandler;
return;

use Generator;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\ArgumentsNormalizer;
use PHPStan\Analyser\ExpressionContext;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\ExprAnalysisResult;
use PHPStan\Analyser\Generator\ExprHandler;
use PHPStan\Analyser\Generator\GeneratorNodeScopeResolver;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\InternalThrowPoint;
use PHPStan\Analyser\Generator\NodeHandler\ArgsHandler;
use PHPStan\Analyser\Generator\NoopNodeCallback;
use PHPStan\Analyser\Generator\PersistStorageRequest;
use PHPStan\Analyser\Generator\RestoreStorageRequest;
use PHPStan\Analyser\Generator\RunInFiberRequest;
use PHPStan\Analyser\Generator\StmtAnalysisRequest;
use PHPStan\Analyser\Generator\TypeExprRequest;
use PHPStan\Analyser\ImpurePoint;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\StatementContext;
use PHPStan\Analyser\ThrowPoint;
use PHPStan\DependencyInjection\AutowiredParameter;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\DependencyInjection\Type\DynamicReturnTypeExtensionRegistryProvider;
use PHPStan\DependencyInjection\Type\DynamicThrowTypeExtensionProvider;
use PHPStan\Node\MethodReturnStatementsNode;
use PHPStan\Parser\NewAssignedToPropertyVisitor;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\Dummy\DummyConstructorReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\ErrorType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Generic\GenericStaticType;
use PHPStan\Type\Generic\TemplateType;
use PHPStan\Type\Generic\TemplateTypeHelper;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\NeverType;
use PHPStan\Type\NonexistentParentClassType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StaticType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeTraverser;
use Throwable;
use function array_key_exists;
use function array_map;
use function array_merge;
use function count;
use function sprintf;

/**
 * @implements ExprHandler<New_>
 * @phpstan-import-type GeneratorTValueType from GeneratorNodeScopeResolver
 * @phpstan-import-type GeneratorTSendType from GeneratorNodeScopeResolver
 */
#[AutowiredService]
final class NewHandler implements ExprHandler
{

	public function __construct(
		private readonly ReflectionProvider $reflectionProvider,
		private readonly ArgsHandler $argsHandler,
		private readonly DynamicThrowTypeExtensionProvider $dynamicThrowTypeExtensionProvider,
		private readonly DynamicReturnTypeExtensionRegistryProvider $dynamicReturnTypeExtensionRegistryProvider,
		#[AutowiredParameter(ref: '%exceptions.implicitThrows%')]
		private readonly bool $implicitThrows,
	)
	{
	}

	public function supports(Expr $expr): bool
	{
		return $expr instanceof New_ && !$expr->isFirstClassCallable();
	}

	public function analyseExpr(
		Stmt $stmt,
		Expr $expr,
		GeneratorScope $scope,
		ExpressionContext $context,
		?callable $alternativeNodeCallback,
	): Generator
	{
		if ($expr->class instanceof Stmt\Class_) {
			$processGen = $this->processAnonymousClass($stmt, $expr, $expr->class, $scope, $context, $alternativeNodeCallback);
			yield from $processGen;
			return $processGen->getReturn();
		}

		$parametersAcceptor = null;
		$constructorReflection = null;
		$hasYield = false;
		$throwPoints = [];
		$impurePoints = [];
		$isAlwaysTerminating = false;
		$className = null;

		if ($expr->class instanceof Expr) {
			$classExprResult = yield new ExprAnalysisRequest($stmt, $expr->class, $scope, $context->enterDeep(), $alternativeNodeCallback);
			$scope = $classExprResult->scope;
			$hasYield = $classExprResult->hasYield;
			$throwPoints = $classExprResult->throwPoints;
			$impurePoints = $classExprResult->impurePoints;
			$isAlwaysTerminating = $classExprResult->isAlwaysTerminating;
			$type = $classExprResult->type->getObjectTypeOrClassStringObjectType();

			$objectClasses = $type->getObjectClassNames();
			if (count($objectClasses) === 1) {
				$objectExprResult = yield new ExprAnalysisRequest($stmt, new New_(new Name($objectClasses[0])), $scope, $context->enterDeep(), new NoopNodeCallback());
				$className = $objectClasses[0];
				$additionalThrowPoints = $objectExprResult->throwPoints;
			} else {
				$additionalThrowPoints = [InternalThrowPoint::createImplicit($scope, $expr)];
			}

			foreach ($additionalThrowPoints as $throwPoint) {
				$throwPoints[] = $throwPoint;
			}
		} else {
			$className = $scope->resolveName($expr->class);
		}

		$classReflection = null;
		if ($className !== null && $this->reflectionProvider->hasClass($className)) {
			$classReflection = $this->reflectionProvider->getClass($className);
			if ($classReflection->hasConstructor()) {
				$constructorReflection = $classReflection->getConstructor();
			}
		} else {
			$throwPoints[] = InternalThrowPoint::createImplicit($scope, $expr);
		}

		if ($expr->class instanceof Name) {
			$typeGen = $this->exactInstantiation($scope, $expr, $expr->class);
			yield from $typeGen;
			$type = $typeGen->getReturn();
		}

		if ($constructorReflection !== null) {
			if (!$constructorReflection->hasSideEffects()->no()) {
				$certain = $constructorReflection->isPure()->no();
				$impurePoints[] = new ImpurePoint(
					$scope,
					$expr,
					'new',
					sprintf('instantiation of class %s', $constructorReflection->getDeclaringClass()->getDisplayName()),
					$certain,
				);
			}
		} elseif ($classReflection === null) {
			$impurePoints[] = new ImpurePoint(
				$scope,
				$expr,
				'new',
				'instantiation of unknown class',
				false,
			);
		}

		if ($constructorReflection !== null) {
			$storage = yield new PersistStorageRequest();
			$parametersAcceptor = (yield new RunInFiberRequest(static fn () => ParametersAcceptorSelector::selectFromArgs(
				$scope,
				$expr->getArgs(),
				$constructorReflection->getVariants(),
				$constructorReflection->getNamedArgumentsVariants(),
			)))->value;
			yield new RestoreStorageRequest($storage);
			$expr = ArgumentsNormalizer::reorderNewArguments($parametersAcceptor, $expr) ?? $expr;
		}

		$argsGen = $this->argsHandler->processArgs($stmt, $constructorReflection, null, $parametersAcceptor, $expr, $scope, $context, $alternativeNodeCallback);
		yield from $argsGen;
		$argsResult = $argsGen->getReturn();

		if ($classReflection !== null && $constructorReflection !== null && $className !== null) {
			$constructorThrowPointGen = $this->getConstructorThrowPoint($constructorReflection, $parametersAcceptor, $classReflection, $expr, new Name\FullyQualified($className), $expr->getArgs(), $scope);
			yield from $constructorThrowPointGen;
			$constructorThrowPoint = $constructorThrowPointGen->getReturn();
			if ($constructorThrowPoint !== null) {
				$throwPoints[] = $constructorThrowPoint;
			}
		}

		return new ExprAnalysisResult(
			$type,
			$type,
			$argsResult->scope,
			hasYield: $hasYield || $argsResult->hasYield,
			throwPoints: array_merge($throwPoints, $argsResult->throwPoints),
			impurePoints: array_merge($impurePoints, $argsResult->impurePoints),
			isAlwaysTerminating: $isAlwaysTerminating || $argsResult->isAlwaysTerminating,
			specifiedTruthyTypes: new SpecifiedTypes(),
			specifiedFalseyTypes: new SpecifiedTypes(),
			specifiedNullTypes: new SpecifiedTypes(),
		);
	}

	/**
	 * @param (callable(Node, Scope, callable(Node, Scope): void): void)|null $alternativeNodeCallback
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, ExprAnalysisResult>
	 */
	private function processAnonymousClass(
		Stmt $stmt,
		New_ $expr,
		Stmt\Class_ $class,
		GeneratorScope $scope,
		ExpressionContext $context,
		?callable $alternativeNodeCallback,
	): Generator
	{
		$classReflection = $this->reflectionProvider->getAnonymousClassReflection($class, $scope); // populates $expr->class->name
		$throwPoints = [];
		$impurePoints = [];
		$constructorReflection = null;
		$parametersAcceptor = null;
		if ($classReflection->hasConstructor()) {
			$constructorReflection = $classReflection->getConstructor();
			$storage = yield new PersistStorageRequest();
			$parametersAcceptor = (yield new RunInFiberRequest(static fn () => ParametersAcceptorSelector::selectFromArgs(
				$scope,
				$expr->getArgs(),
				$constructorReflection->getVariants(),
				$constructorReflection->getNamedArgumentsVariants(),
			)))->value;
			yield new RestoreStorageRequest($storage);

			if ($constructorReflection->getDeclaringClass()->getName() === $classReflection->getName()) {
				$constructorResult = null;
				yield new StmtAnalysisRequest($class, $scope, StatementContext::createTopLevel(), static function (Node $node, Scope $scope, callable $nodeCallback) use ($classReflection, &$constructorResult): void {
					$nodeCallback($node, $scope);
					if (!$node instanceof MethodReturnStatementsNode) {
						return;
					}
					if ($constructorResult !== null) {
						return;
					}
					$currentClassReflection = $node->getClassReflection();
					if ($currentClassReflection->getName() !== $classReflection->getName()) {
						return;
					}
					if (!$currentClassReflection->hasConstructor()) {
						return;
					}
					if ($currentClassReflection->getConstructor()->getName() !== $node->getMethodReflection()->getName()) {
						return;
					}
					$constructorResult = $node;
				});
				if ($constructorResult !== null) {
					$throwPoints = array_map(static fn (ThrowPoint $point) => InternalThrowPoint::createFromPublic($point), $constructorResult->getStatementResult()->getThrowPoints());
					$impurePoints = $constructorResult->getImpurePoints();
				}
			} else {
				yield new StmtAnalysisRequest($class, $scope, StatementContext::createTopLevel(), $alternativeNodeCallback);
			}
		} else {
			yield new StmtAnalysisRequest($class, $scope, StatementContext::createTopLevel(), $alternativeNodeCallback);
		}

		$type = new ObjectType($classReflection->getName());
		$argsGen = $this->argsHandler->processArgs($stmt, $constructorReflection, null, $parametersAcceptor, $expr, $scope, $context, $alternativeNodeCallback);
		yield from $argsGen;
		$argsResult = $argsGen->getReturn();

		if ($constructorReflection !== null && $parametersAcceptor !== null) {
			$declaringClass = $constructorReflection->getDeclaringClass();
			$constructorThrowPointGen = $this->getConstructorThrowPoint($constructorReflection, $parametersAcceptor, $classReflection, $expr, new Name\FullyQualified($declaringClass->getName()), $expr->getArgs(), $scope);
			yield from $constructorThrowPointGen;
			$constructorThrowPoint = $constructorThrowPointGen->getReturn();
			if ($constructorThrowPoint !== null) {
				$throwPoints[] = $constructorThrowPoint;
			}

			if (!$constructorReflection->hasSideEffects()->no()) {
				$certain = $constructorReflection->isPure()->no();
				$impurePoints[] = new ImpurePoint(
					$scope,
					$expr,
					'new',
					sprintf('instantiation of class %s', $declaringClass->getDisplayName()),
					$certain,
				);
			}
		}

		return new ExprAnalysisResult(
			$type,
			$type,
			$argsResult->scope,
			hasYield: false,
			throwPoints: array_merge($throwPoints, $argsResult->throwPoints),
			impurePoints: array_merge($impurePoints, $argsResult->impurePoints),
			isAlwaysTerminating: $argsResult->isAlwaysTerminating,
			specifiedTruthyTypes: new SpecifiedTypes(),
			specifiedFalseyTypes: new SpecifiedTypes(),
			specifiedNullTypes: new SpecifiedTypes(),
		);
	}

	/**
	 * @param list<Node\Arg> $args
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, ?InternalThrowPoint>
	 */
	private function getConstructorThrowPoint(MethodReflection $constructorReflection, ParametersAcceptor $parametersAcceptor, ClassReflection $classReflection, New_ $new, Name $className, array $args, GeneratorScope $scope): Generator
	{
		$methodCall = new StaticCall($className, $constructorReflection->getName(), $args);
		$normalizedMethodCall = ArgumentsNormalizer::reorderStaticCallArguments($parametersAcceptor, $methodCall);
		if ($normalizedMethodCall !== null) {
			foreach ($this->dynamicThrowTypeExtensionProvider->getDynamicStaticMethodThrowTypeExtensions() as $extension) {
				if (!$extension->isStaticMethodSupported($constructorReflection)) {
					continue;
				}

				$throwType = (yield new RunInFiberRequest(static fn () => $extension->getThrowTypeFromStaticMethodCall($constructorReflection, $normalizedMethodCall, $scope)))->value;
				if ($throwType === null) {
					return null;
				}

				return InternalThrowPoint::createExplicit($scope, $throwType, $new, false);
			}
		}

		if ($constructorReflection->getThrowType() !== null) {
			$throwType = $constructorReflection->getThrowType();
			if (!$throwType->isVoid()->yes()) {
				return InternalThrowPoint::createExplicit($scope, $throwType, $new, true);
			}
		} elseif ($this->implicitThrows) {
			if (!$classReflection->is(Throwable::class)) {
				return InternalThrowPoint::createImplicit($scope, $methodCall);
			}
		}

		return null;
	}

	/**
	 * @return Generator<int, GeneratorTValueType, GeneratorTSendType, Type>
	 */
	private function exactInstantiation(GeneratorScope $scope, New_ $node, Name $className): Generator
	{
		$resolvedClassName = $scope->resolveName($className);
		$isStatic = false;
		$lowercasedClassName = $className->toLowerString();
		if ($lowercasedClassName === 'static') {
			$isStatic = true;
		}

		if (!$this->reflectionProvider->hasClass($resolvedClassName)) {
			if ($lowercasedClassName === 'static') {
				if (!$scope->isInClass()) {
					return new ErrorType();
				}

				return new StaticType($scope->getClassReflection());
			}
			if ($lowercasedClassName === 'parent') {
				return new NonexistentParentClassType();
			}

			return new ObjectType($resolvedClassName);
		}

		$classReflection = $this->reflectionProvider->getClass($resolvedClassName);
		$nonFinalClassReflection = $classReflection;
		if (!$isStatic) {
			$classReflection = $classReflection->asFinal();
		}
		if ($classReflection->hasConstructor()) {
			$constructorMethod = $classReflection->getConstructor();
		} else {
			$constructorMethod = new DummyConstructorReflection($classReflection);
		}

		if ($constructorMethod->getName() === '') {
			throw new ShouldNotHappenException();
		}

		$resolvedTypes = [];
		$methodCall = new Expr\StaticCall(
			new Name($resolvedClassName),
			new Node\Identifier($constructorMethod->getName()),
			$node->getArgs(),
		);

		$storage = yield new PersistStorageRequest();
		$parametersAcceptor = (yield new RunInFiberRequest(static fn () => ParametersAcceptorSelector::selectFromArgs(
			$scope,
			$methodCall->getArgs(),
			$constructorMethod->getVariants(),
			$constructorMethod->getNamedArgumentsVariants(),
		)))->value;
		$normalizedMethodCall = ArgumentsNormalizer::reorderStaticCallArguments($parametersAcceptor, $methodCall);

		if ($normalizedMethodCall !== null) {
			foreach ($this->dynamicReturnTypeExtensionRegistryProvider->getRegistry()->getDynamicStaticMethodReturnTypeExtensionsForClass($classReflection->getName()) as $dynamicStaticMethodReturnTypeExtension) {
				if (!$dynamicStaticMethodReturnTypeExtension->isStaticMethodSupported($constructorMethod)) {
					continue;
				}

				$resolvedType = (yield new RunInFiberRequest(static fn () => $dynamicStaticMethodReturnTypeExtension->getTypeFromStaticMethodCall(
					$constructorMethod,
					$normalizedMethodCall,
					$scope,
				)))->value;
				if ($resolvedType === null) {
					continue;
				}

				$resolvedTypes[] = $resolvedType;
			}
		}

		if (count($resolvedTypes) > 0) {
			return TypeCombinator::union(...$resolvedTypes);
		}

		$methodResult = (yield new ExprAnalysisRequest(new Stmt\Expression($methodCall), $methodCall, $scope, ExpressionContext::createTopLevel(), new NoopNodeCallback()))->type;
		if ($methodResult instanceof NeverType && $methodResult->isExplicit()) {
			return $methodResult;
		}

		yield new RestoreStorageRequest($storage);

		$objectType = $isStatic ? new StaticType($classReflection) : new ObjectType($resolvedClassName, classReflection: $classReflection);
		if (!$classReflection->isGeneric()) {
			return $objectType;
		}

		$assignedToProperty = $node->getAttribute(NewAssignedToPropertyVisitor::ATTRIBUTE_NAME);
		if ($assignedToProperty !== null) {
			$constructorVariants = $constructorMethod->getVariants();
			if (count($constructorVariants) === 1) {
				$constructorVariant = $constructorVariants[0];
				$classTemplateTypes = $classReflection->getTemplateTypeMap()->getTypes();
				$originalClassTemplateTypes = $classTemplateTypes;
				foreach ($constructorVariant->getParameters() as $parameter) {
					TypeTraverser::map($parameter->getType(), static function (Type $type, callable $traverse) use (&$classTemplateTypes): Type {
						if ($type instanceof TemplateType && array_key_exists($type->getName(), $classTemplateTypes)) {
							$classTemplateType = $classTemplateTypes[$type->getName()];
							if ($classTemplateType instanceof TemplateType && $classTemplateType->getScope()->equals($type->getScope())) {
								unset($classTemplateTypes[$type->getName()]);
							}
							return $type;
						}

						return $traverse($type);
					});
				}

				if (count($classTemplateTypes) === count($originalClassTemplateTypes)) {
					$propertyType = TypeCombinator::removeNull((yield new TypeExprRequest($assignedToProperty))->type);
					$nonFinalObjectType = $isStatic ? new StaticType($nonFinalClassReflection) : new ObjectType($resolvedClassName, classReflection: $nonFinalClassReflection);
					if ($nonFinalObjectType->isSuperTypeOf($propertyType)->yes()) {
						return $propertyType;
					}
				}
			}
		}

		if ($constructorMethod instanceof DummyConstructorReflection) {
			if ($isStatic) {
				return new GenericStaticType(
					$classReflection,
					$classReflection->typeMapToList($classReflection->getTemplateTypeMap()->resolveToBounds()),
					null,
					[],
				);
			}

			$types = $classReflection->typeMapToList($classReflection->getTemplateTypeMap()->resolveToBounds());
			return new GenericObjectType(
				$resolvedClassName,
				$types,
				classReflection: $classReflection->withTypes($types)->asFinal(),
			);
		}

		if ($constructorMethod->getDeclaringClass()->getName() !== $classReflection->getName()) {
			if (!$constructorMethod->getDeclaringClass()->isGeneric()) {
				if ($isStatic) {
					return new GenericStaticType(
						$classReflection,
						$classReflection->typeMapToList($classReflection->getTemplateTypeMap()->resolveToBounds()),
						null,
						[],
					);
				}

				$types = $classReflection->typeMapToList($classReflection->getTemplateTypeMap()->resolveToBounds());
				return new GenericObjectType(
					$resolvedClassName,
					$types,
					classReflection: $classReflection->withTypes($types)->asFinal(),
				);
			}
			$newType = new GenericObjectType($resolvedClassName, $classReflection->typeMapToList($classReflection->getTemplateTypeMap()));
			$ancestorType = $newType->getAncestorWithClassName($constructorMethod->getDeclaringClass()->getName());
			if ($ancestorType === null) {
				if ($isStatic) {
					return new GenericStaticType(
						$classReflection,
						$classReflection->typeMapToList($classReflection->getTemplateTypeMap()->resolveToBounds()),
						null,
						[],
					);
				}

				$types = $classReflection->typeMapToList($classReflection->getTemplateTypeMap()->resolveToBounds());
				return new GenericObjectType(
					$resolvedClassName,
					$types,
					classReflection: $classReflection->withTypes($types)->asFinal(),
				);
			}
			$ancestorClassReflections = $ancestorType->getObjectClassReflections();
			if (count($ancestorClassReflections) !== 1) {
				if ($isStatic) {
					return new GenericStaticType(
						$classReflection,
						$classReflection->typeMapToList($classReflection->getTemplateTypeMap()->resolveToBounds()),
						null,
						[],
					);
				}

				$types = $classReflection->typeMapToList($classReflection->getTemplateTypeMap()->resolveToBounds());
				return new GenericObjectType(
					$resolvedClassName,
					$types,
					classReflection: $classReflection->withTypes($types)->asFinal(),
				);
			}

			$newParentNode = new New_(new Name($constructorMethod->getDeclaringClass()->getName()), $node->args);
			$newParentType = (yield new ExprAnalysisRequest(new Stmt\Expression($newParentNode), $newParentNode, $scope, ExpressionContext::createTopLevel(), new NoopNodeCallback()))->type;
			$newParentTypeClassReflections = $newParentType->getObjectClassReflections();
			if (count($newParentTypeClassReflections) !== 1) {
				if ($isStatic) {
					return new GenericStaticType(
						$classReflection,
						$classReflection->typeMapToList($classReflection->getTemplateTypeMap()->resolveToBounds()),
						null,
						[],
					);
				}

				$types = $classReflection->typeMapToList($classReflection->getTemplateTypeMap()->resolveToBounds());
				return new GenericObjectType(
					$resolvedClassName,
					$types,
					classReflection: $classReflection->withTypes($types)->asFinal(),
				);
			}
			$newParentTypeClassReflection = $newParentTypeClassReflections[0];

			$ancestorClassReflection = $ancestorClassReflections[0];
			$ancestorMapping = [];
			foreach ($ancestorClassReflection->getActiveTemplateTypeMap()->getTypes() as $typeName => $templateType) {
				if (!$templateType instanceof TemplateType) {
					continue;
				}

				$ancestorMapping[$typeName] = $templateType;
			}

			$resolvedTypeMap = [];
			foreach ($newParentTypeClassReflection->getActiveTemplateTypeMap()->getTypes() as $typeName => $type) {
				if (!array_key_exists($typeName, $ancestorMapping)) {
					continue;
				}

				$ancestorType = $ancestorMapping[$typeName];
				if (!$ancestorType->getBound()->isSuperTypeOf($type)->yes()) {
					continue;
				}

				if (!array_key_exists($ancestorType->getName(), $resolvedTypeMap)) {
					$resolvedTypeMap[$ancestorType->getName()] = $type;
					continue;
				}

				$resolvedTypeMap[$ancestorType->getName()] = TypeCombinator::union($resolvedTypeMap[$ancestorType->getName()], $type);
			}

			if ($isStatic) {
				return new GenericStaticType(
					$classReflection,
					$classReflection->typeMapToList(new TemplateTypeMap($resolvedTypeMap)),
					null,
					[],
				);
			}

			$types = $classReflection->typeMapToList(new TemplateTypeMap($resolvedTypeMap));
			return new GenericObjectType(
				$resolvedClassName,
				$types,
				classReflection: $classReflection->withTypes($types)->asFinal(),
			);
		}

		$resolvedTemplateTypeMap = $parametersAcceptor->getResolvedTemplateTypeMap();
		$types = $classReflection->typeMapToList($classReflection->getTemplateTypeMap());
		$newGenericType = new GenericObjectType(
			$resolvedClassName,
			$types,
			classReflection: $classReflection->withTypes($types)->asFinal(),
		);
		if ($isStatic) {
			$newGenericType = new GenericStaticType(
				$classReflection,
				$types,
				null,
				[],
			);
		}
		return TypeTraverser::map($newGenericType, static function (Type $type, callable $traverse) use ($resolvedTemplateTypeMap): Type {
			if ($type instanceof TemplateType && !$type->isArgument()) {
				$newType = $resolvedTemplateTypeMap->getType($type->getName());
				if ($newType === null || $newType instanceof ErrorType) {
					return $type->getDefault() ?? $type->getBound();
				}

				return TemplateTypeHelper::generalizeInferredTemplateType($type, $newType);
			}

			return $traverse($type);
		});
	}

}
