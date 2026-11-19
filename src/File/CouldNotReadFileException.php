<?php 

namespace PHPStan\File;
return;

use PHPStan\AnalysedCodeException;
use function sprintf;

final class CouldNotReadFileException extends AnalysedCodeException
{

	public function __construct(string $fileName)
	{
		parent::__construct(sprintf('Could not read file: %s', $fileName));
	}

	public function getTip(): ?string
	{
		return null;
	}

}
