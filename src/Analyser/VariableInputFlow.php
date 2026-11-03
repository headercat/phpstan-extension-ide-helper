<?php 

namespace PHPStan\Analyser;
return;

use PHPStan\Turbo\ShadowedByTurboExtension;

#[ShadowedByTurboExtension(implementation: __DIR__ . '/../../turbo-ext/src/VariableInputFlow.cpp')]
final class VariableInputFlow extends VariableFlow
{

	public function __construct(
		public readonly int $writeId,
		public readonly ?int $targetId,
	)
	{
		parent::__construct(self::SEQUENCE);
	}

}
