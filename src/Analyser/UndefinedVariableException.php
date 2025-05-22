<?php 

namespace PHPStan\Analyser;
return;

use PHPStan\AnalysedCodeException;
use function sprintf;

/**
 * @api
 *
 * Unchecked exception thrown from `PHPStan\Analyser\Scope::getVariableType()`
 * in case the user doesn't check `hasVariableType()` is not `no()`.
 */
final class UndefinedVariableException extends AnalysedCodeException
{

	public function __construct(private Scope $scope, private string $variableName)
	{
		parent::__construct(sprintf('Undefined variable: $%s', $variableName));
	}

	public function getScope(): Scope
	{
		return $this->scope;
	}

	public function getVariableName(): string
	{
		return $this->variableName;
	}

	public function getTip(): ?string
	{
		return null;
	}

}
