<?php 

namespace PHPStan\Rules\Methods;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\ShouldNotHappenException;

/**
 * @implements Collector<never, array{class-string, string, string, int}>
 */
final class NamedArgumentParameterMethodCallsCollector implements Collector
{

	public function getNodeType(): string
	{
		throw new ShouldNotHappenException();
	}

	public function processNode(Node $node, Scope $scope)
	{
		throw new ShouldNotHappenException();
	}

}
