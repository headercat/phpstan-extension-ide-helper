<?php 

namespace PHPStan;
return;

use Exception;

abstract class AnalysedCodeException extends Exception
{

	abstract public function getTip(): ?string;

}
