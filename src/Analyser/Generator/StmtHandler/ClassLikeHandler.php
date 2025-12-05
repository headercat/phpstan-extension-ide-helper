<?php 

namespace PHPStan\Analyser\Generator\StmtHandler;
return;

use Generator;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PHPStan\Analyser\Generator\AttrGroupsAnalysisRequest;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\NodeCallbackRequest;
use PHPStan\Analyser\Generator\StmtAnalysisResult;
use PHPStan\Analyser\Generator\StmtHandler;
use PHPStan\Analyser\Generator\StmtsAnalysisRequest;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\StatementContext;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionClass;
use PHPStan\BetterReflection\Reflection\ReflectionEnum;
use PHPStan\BetterReflection\Reflector\Reflector;
use PHPStan\BetterReflection\SourceLocator\Ast\Strategy\NodeToReflection;
use PHPStan\BetterReflection\SourceLocator\Located\LocatedSource;
use PHPStan\DependencyInjection\AutowiredParameter;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\File\FileReader;
use PHPStan\Node\ClassConstantsNode;
use PHPStan\Node\ClassMethodsNode;
use PHPStan\Node\ClassPropertiesNode;
use PHPStan\Node\ClassStatementsGatherer;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ClassReflectionFactory;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Properties\ReadWritePropertiesExtensionProvider;
use PHPStan\ShouldNotHappenException;
use function base64_decode;
use function sprintf;
use function usort;
use const PHP_VERSION_ID;

/**
 * @implements StmtHandler<Class_|Interface_|Enum_>
 */
#[AutowiredService]
final class ClassLikeHandler implements StmtHandler
{

	public function __construct(
		private readonly ReflectionProvider $reflectionProvider,
		private readonly ReadWritePropertiesExtensionProvider $readWritePropertiesExtensionProvider,
		private readonly ClassReflectionFactory $classReflectionFactory,
		#[AutowiredParameter(ref: '@nodeScopeResolverReflector')]
		private readonly Reflector $reflector,
		#[AutowiredParameter]
		private readonly bool $narrowMethodScopeFromConstructor,
	)
	{
	}

	public function supports(Stmt $stmt): bool
	{
		return $stmt instanceof Class_ || $stmt instanceof Interface_ || $stmt instanceof Enum_;
	}

	public function analyseStmt(Stmt $stmt, GeneratorScope $scope, StatementContext $context, ?callable $alternativeNodeCallback): Generator
	{
		if (!$context->isTopLevel()) {
			return new StmtAnalysisResult($scope, hasYield: false, isAlwaysTerminating: false, exitPoints: [], throwPoints: [], impurePoints: []);
		}

		if (isset($stmt->namespacedName)) {
			$classReflection = $this->getCurrentClassReflection($stmt, $stmt->namespacedName->toString(), $scope);
			$classScope = $scope->enterClass($classReflection);
		} elseif ($stmt instanceof Class_) {
			if ($stmt->name === null) {
				throw new ShouldNotHappenException();
			}
			if (!$stmt->isAnonymous()) {
				$classReflection = $this->reflectionProvider->getClass($stmt->name->toString());
			} else {
				$classReflection = $this->reflectionProvider->getAnonymousClassReflection($stmt, $scope);
			}
			$classScope = $scope->enterClass($classReflection);
		} else {
			throw new ShouldNotHappenException();
		}

		yield new NodeCallbackRequest(new InClassNode($stmt, $classReflection), $classScope, $alternativeNodeCallback);

		$classStatementsGatherer = new ClassStatementsGatherer($classReflection);

		yield new AttrGroupsAnalysisRequest($stmt, $stmt->attrGroups, $classScope, $classStatementsGatherer);

		$classLikeStatements = $stmt->stmts;
		if ($this->narrowMethodScopeFromConstructor) {
			// analyze static methods first; constructor next; instance methods and property hooks last so we can carry over the scope
			usort($classLikeStatements, static function ($a, $b) {
				if ($a instanceof Stmt\Property) {
					return 1;
				}
				if ($b instanceof Stmt\Property) {
					return -1;
				}

				if (!$a instanceof Stmt\ClassMethod || !$b instanceof Stmt\ClassMethod) {
					return 0;
				}

				return [!$a->isStatic(), $a->name->toLowerString() !== '__construct'] <=> [!$b->isStatic(), $b->name->toLowerString() !== '__construct'];
			});
		}

		yield new StmtsAnalysisRequest($stmt, $classLikeStatements, $classScope, $context, $classStatementsGatherer);
		yield new NodeCallbackRequest(new ClassPropertiesNode($stmt, $this->readWritePropertiesExtensionProvider, $classStatementsGatherer->getProperties(), $classStatementsGatherer->getPropertyUsages(), $classStatementsGatherer->getMethodCalls(), $classStatementsGatherer->getReturnStatementsNodes(), $classStatementsGatherer->getPropertyAssigns(), $classReflection), $classScope, $alternativeNodeCallback);
		yield new NodeCallbackRequest(new ClassMethodsNode($stmt, $classStatementsGatherer->getMethods(), $classStatementsGatherer->getMethodCalls(), $classReflection), $classScope, $alternativeNodeCallback);
		yield new NodeCallbackRequest(new ClassConstantsNode($stmt, $classStatementsGatherer->getConstants(), $classStatementsGatherer->getConstantFetches(), $classReflection), $classScope, $alternativeNodeCallback);
		$classReflection->evictPrivateSymbols();
		//$this->calledMethodResults = [];

		return new StmtAnalysisResult($scope, hasYield: false, isAlwaysTerminating: false, exitPoints: [], throwPoints: [], impurePoints: []);
	}

	private function getCurrentClassReflection(Class_|Interface_|Enum_ $stmt, string $className, GeneratorScope $scope): ClassReflection
	{
		if (!$this->reflectionProvider->hasClass($className)) {
			return $this->createAstClassReflection($stmt, $className, $scope);
		}

		$defaultClassReflection = $this->reflectionProvider->getClass($className);
		if ($defaultClassReflection->getFileName() !== $scope->getFile()) {
			return $this->createAstClassReflection($stmt, $className, $scope);
		}

		$startLine = $defaultClassReflection->getNativeReflection()->getStartLine();
		if ($startLine !== $stmt->getStartLine()) {
			return $this->createAstClassReflection($stmt, $className, $scope);
		}

		return $defaultClassReflection;
	}

	private function createAstClassReflection(Class_|Interface_|Enum_ $stmt, string $className, Scope $scope): ClassReflection
	{
		$nodeToReflection = new NodeToReflection();
		$betterReflectionClass = $nodeToReflection->__invoke(
			$this->reflector,
			$stmt,
			new LocatedSource(FileReader::read($scope->getFile()), $className, $scope->getFile()),
			$scope->getNamespace() !== null ? new Stmt\Namespace_(new Name($scope->getNamespace())) : null,
		);
		if (!$betterReflectionClass instanceof \PHPStan\BetterReflection\Reflection\ReflectionClass) {
			throw new ShouldNotHappenException();
		}

		$enumAdapter = base64_decode('UEhQU3RhblxCZXR0ZXJSZWZsZWN0aW9uXFJlZmxlY3Rpb25cQWRhcHRlclxSZWZsZWN0aW9uRW51bQ==', true);

		return $this->classReflectionFactory->create(
			$betterReflectionClass->getName(),
			$betterReflectionClass instanceof ReflectionEnum && PHP_VERSION_ID >= 80000 ? new $enumAdapter($betterReflectionClass) : new ReflectionClass($betterReflectionClass),
			null,
			null,
			null,
			sprintf('%s:%d', $scope->getFile(), $stmt->getStartLine()),
		);
	}

}
