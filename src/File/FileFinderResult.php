<?php 

namespace PHPStan\File;
return;

final class FileFinderResult
{

	/**
	 * @param string[] $files
	 */
	public function __construct(private array $files, private bool $onlyFiles)
	{
	}

	/**
	 * @return string[]
	 */
	public function getFiles(): array
	{
		return $this->files;
	}

	public function isOnlyFiles(): bool
	{
		return $this->onlyFiles;
	}

}
