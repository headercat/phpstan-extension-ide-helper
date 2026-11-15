<?php 

namespace PHPStan\DependencyInjection;
return;

use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Override;

/**
 * Registers the top-level `attributeServicesDirectories` section so that Nette accepts it.
 *
 * The section itself is processed by ContainerFactory before the container compiles -
 * the directories feed the container cache key and AttributeServicesDiscoveryContext.
 */
#[ContainerExtension(name: 'attributeServicesDirectories')]
final class AttributeServicesDirectoriesExtension extends CompilerExtension
{

	#[Override]
	public function getConfigSchema(): Schema
	{
		return Expect::listOf('string');
	}

}
