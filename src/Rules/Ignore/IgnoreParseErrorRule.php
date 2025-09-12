<?php 

namespace PHPStan\Rules\Ignore;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\RegisteredRule;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use function count;
use function sprintf;

/**
 * @implements Rule<FileNode>
 */
#[RegisteredRule(level: 0)]
final class IgnoreParseErrorRule implements Rule
{

	public function getNodeType(): string
	{
		return FileNode::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$nodes = $node->getNodes();
		if (count($nodes) === 0) {
			return [];
		}

		$firstNode = $nodes[0];
		$parseErrors = $firstNode->getAttribute('linesToIgnoreParseErrors', []);
		$errors = [];
		foreach ($parseErrors as $line => $lineParseErrors) {
			foreach ($lineParseErrors as $parseError) {
				$errors[] = RuleErrorBuilder::message(sprintf('Parse error in @phpstan-ignore: %s', $parseError))
					->line($line)
					->identifier('ignore.parseError')
					->nonIgnorable()
					->build();
			}
		}

		return $errors;
	}

}
