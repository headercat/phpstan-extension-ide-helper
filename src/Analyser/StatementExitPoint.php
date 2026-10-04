<?php 

namespace PHPStan\Analyser;
return;

use PhpParser\Node\Stmt;
use PHPStan\Turbo\ShadowedByTurboExtension;

/**
 * @api
 */
#[ShadowedByTurboExtension(implementation: __DIR__ . '/../../turbo-ext/src/StatementExitPoint.cpp')]
final class StatementExitPoint
{

	public function __construct(private Stmt $statement, private Scope $scope)
	{
	}

	public function getStatement(): Stmt
	{
		return $this->statement;
	}

	public function getScope(): Scope
	{
		return $this->scope;
	}

}
