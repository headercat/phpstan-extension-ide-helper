<?php 

namespace PHPStan\Testing;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;
use function sprintf;

/**
 * @implements Rule<Node\Stmt\Trait_>
 */
final class NonexistentAnalysedTraitRule implements Rule
{

	public function __construct(private ReflectionProvider $reflectionProvider)
	{
	}

	public function getNodeType(): string
	{
		return Node\Stmt\Trait_::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		if ($node->namespacedName === null) {
			throw new ShouldNotHappenException();
		}
		$traitName = $node->namespacedName->toString();
		if ($this->reflectionProvider->hasClass($traitName)) {
			return [];
		}

		return [
			RuleErrorBuilder::message(sprintf(
				'Trait %s not found in ReflectionProvider. Configure "autoload-dev" section in composer.json to include your tests directory.',
				$traitName,
			))
				->identifier('phpstan.traitNotFound')
				->nonIgnorable()
				->build(),
		];
	}

}
