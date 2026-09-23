<?php 

namespace PHPStan\Rules\Playground;
return;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\FileTypeMapper;
use function count;
use function is_string;

/**
 * @implements Rule<Node\Stmt\Static_>
 */
final class StaticVarWithoutTypeRule implements Rule
{

	public function __construct(
		private FileTypeMapper $fileTypeMapper,
	)
	{
	}

	public function getNodeType(): string
	{
		return Node\Stmt\Static_::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$docComment = $node->getDocComment();
		$ruleError = RuleErrorBuilder::message('Static variable needs to be typed with PHPDoc @var tag.')
			->identifier('phpstanPlayground.staticWithoutType')
			->build();
		if ($docComment === null) {
			return [$ruleError];
		}
		$variableNames = [];
		foreach ($node->vars as $var) {
			if (!is_string($var->var->name)) {
				throw new ShouldNotHappenException();
			}

			$variableNames[] = $var->var->name;
		}

		$function = $scope->getFunction();
		$resolvedPhpDoc = $this->fileTypeMapper->getResolvedPhpDoc(
			$scope->getFile(),
			$scope->isInClass() ? $scope->getClassReflection()->getName() : null,
			$scope->isInTrait() ? $scope->getTraitReflection()->getName() : null,
			$function !== null ? $function->getName() : null,
			$docComment->getText(),
		);
		$varTags = [];
		foreach ($resolvedPhpDoc->getVarTags() as $key => $varTag) {
			$varTags[$key] = $varTag;
		}

		if (count($varTags) === 0) {
			return [$ruleError];
		}

		if (count($variableNames) === 1 && count($varTags) === 1 && isset($varTags[0])) {
			return [];
		}

		foreach ($variableNames as $variableName) {
			if (isset($varTags[$variableName])) {
				continue;
			}

			return [$ruleError];
		}

		return [];
	}

}
