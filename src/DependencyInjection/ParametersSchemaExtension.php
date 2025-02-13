<?php 

namespace PHPStan\DependencyInjection;
return;

use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

final class ParametersSchemaExtension extends CompilerExtension
{

	public function getConfigSchema(): Schema
	{
		return Expect::arrayOf(Expect::type(Statement::class))->min(1);
	}

}
