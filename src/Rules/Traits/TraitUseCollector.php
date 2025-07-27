<?php 

namespace PHPStan\Rules\Traits;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\DependencyInjection\RegisteredCollector;
use function array_map;
use function array_values;

/**
 * @implements Collector<Node\Stmt\TraitUse, list<string>>
 */
#[RegisteredCollector(level: 4)]
final class TraitUseCollector implements Collector
{

	public function getNodeType(): string
	{
		return Node\Stmt\TraitUse::class;
	}

	/**
	 * @return list<string>
	 */
	public function processNode(Node $node, Scope $scope): array
	{
		return array_values(array_map(static fn (Node\Name $traitName) => $traitName->toString(), $node->traits));
	}

}
