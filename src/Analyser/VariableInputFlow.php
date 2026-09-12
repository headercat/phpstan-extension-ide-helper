<?php 

namespace PHPStan\Analyser;
return;

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
