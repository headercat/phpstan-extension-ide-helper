<?php 

namespace PHPStan\Rules\Classes;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredParameter;
use PHPStan\DependencyInjection\RegisteredRule;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\ClassNameCheck;
use PHPStan\Rules\ClassNameNodePair;
use PHPStan\Rules\ClassNameUsageLocation;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;
use function array_map;
use function sprintf;

/**
 * @implements Rule<Node\Stmt\TraitUse>
 */
#[RegisteredRule(level: 0)]
final class ExistingClassInTraitUseRule implements Rule
{

	public function __construct(
		private ClassNameCheck $classCheck,
		private ReflectionProvider $reflectionProvider,
		#[AutowiredParameter(ref: '%tips.discoveringSymbols%')]
		private bool $discoveringSymbolsTip,
	)
	{
	}

	public function getNodeType(): string
	{
		return Node\Stmt\TraitUse::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		if (!$scope->isInClass()) {
			throw new ShouldNotHappenException();
		}

		$classReflection = $scope->getClassReflection();

		$messages = $this->classCheck->checkClassNames(
			$scope,
			array_map(static fn (Node\Name $traitName): ClassNameNodePair => new ClassNameNodePair((string) $traitName, $traitName), $node->traits),
			ClassNameUsageLocation::from(ClassNameUsageLocation::TRAIT_USE, [
				'currentClassName' => $classReflection->isAnonymous() ? null : $classReflection->getName(),
			]),
		);

		if ($classReflection->isInterface()) {
			if (!$scope->isInTrait()) {
				foreach ($node->traits as $trait) {
					$messages[] = RuleErrorBuilder::message(sprintf('Interface %s uses trait %s.', $classReflection->getName(), (string) $trait))
						->identifier('interface.traitUse')
						->nonIgnorable()
						->build();
				}
			}
		} else {
			if ($scope->isInTrait()) {
				$currentName = sprintf('Trait %s', $scope->getTraitReflection()->getName());
			} else {
				if ($classReflection->isAnonymous()) {
					$currentName = 'Anonymous class';
				} else {
					$currentName = sprintf('Class %s', $classReflection->getName());
				}
			}
			foreach ($node->traits as $trait) {
				$traitName = (string) $trait;
				if (!$this->reflectionProvider->hasClass($traitName)) {
					$errorBuilder = RuleErrorBuilder::message(sprintf('%s uses unknown trait %s.', $currentName, $traitName))
						->identifier('trait.notFound')
						->nonIgnorable();

					if ($this->discoveringSymbolsTip) {
						$errorBuilder->discoveringSymbolsTip();
					}

					$messages[] = $errorBuilder->build();
				} else {
					$reflection = $this->reflectionProvider->getClass($traitName);
					if ($reflection->isClass()) {
						$messages[] = RuleErrorBuilder::message(sprintf('%s uses class %s.', $currentName, $reflection->getDisplayName()))
							->identifier('traitUse.class')
							->nonIgnorable()
							->build();
					} elseif ($reflection->isInterface()) {
						$messages[] = RuleErrorBuilder::message(sprintf('%s uses interface %s.', $currentName, $reflection->getDisplayName()))
							->identifier('traitUse.interface')
							->nonIgnorable()
							->build();
					} elseif ($reflection->isEnum()) {
						$messages[] = RuleErrorBuilder::message(sprintf('%s uses enum %s.', $currentName, $reflection->getDisplayName()))
							->identifier('traitUse.enum')
							->nonIgnorable()
							->build();
					}
				}
			}
		}

		return $messages;
	}

}
