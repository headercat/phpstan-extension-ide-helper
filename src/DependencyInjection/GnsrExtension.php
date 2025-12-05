<?php 

namespace PHPStan\DependencyInjection;
return;

use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Override;
use PHPStan\Analyser\Analyser;
use PHPStan\Analyser\FileAnalyser;
use PHPStan\Analyser\Generator\GeneratorNodeScopeResolver;
use PHPStan\Analyser\Generator\GeneratorTypeSpecifier;
use PHPStan\ShouldNotHappenException;
use function getenv;

final class GnsrExtension extends CompilerExtension
{

	#[Override]
	public function beforeCompile()
	{
		$enable = getenv('PHPSTAN_GNSR');
		if ($enable !== '1') {
			return;
		}

		$builder = $this->getContainerBuilder();
		$analyserDef = $builder->getDefinitionByType(Analyser::class);
		if (!$analyserDef instanceof ServiceDefinition) {
			throw new ShouldNotHappenException();
		}
		$analyserDef->setArgument('nodeScopeResolver', '@' . GeneratorNodeScopeResolver::class);

		$fileAnalyserDef = $builder->getDefinitionByType(FileAnalyser::class);
		if (!$fileAnalyserDef instanceof ServiceDefinition) {
			throw new ShouldNotHappenException();
		}
		$fileAnalyserDef->setArgument('nodeScopeResolver', '@' . GeneratorNodeScopeResolver::class);

		$typeSpecifierDef = $builder->getDefinition('typeSpecifier');
		if (!$typeSpecifierDef instanceof ServiceDefinition) {
			throw new ShouldNotHappenException();
		}
		$typeSpecifierDef->setType(GeneratorTypeSpecifier::class);

		$typeSpecifierFactoryDef = $builder->getDefinition('typeSpecifierFactory');
		if (!$typeSpecifierFactoryDef instanceof ServiceDefinition) {
			throw new ShouldNotHappenException();
		}
		$typeSpecifierFactoryDef->setArgument('gnsr', true);
	}

}
