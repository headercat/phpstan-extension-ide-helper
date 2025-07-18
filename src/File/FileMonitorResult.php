<?php 

namespace PHPStan\File;
return;

use function count;

final class FileMonitorResult
{

	/**
	 * @param string[] $newFiles
	 * @param string[] $changedFiles
	 * @param string[] $deletedFiles
	 */
	public function __construct(
		private array $newFiles,
		private array $changedFiles,
		private array $deletedFiles,
	)
	{
	}

	/**
	 * @return string[]
	 */
	public function getChangedFiles(): array
	{
		return $this->changedFiles;
	}

	public function hasAnyChanges(): bool
	{
		return count($this->newFiles) > 0
			|| count($this->changedFiles) > 0
			|| count($this->deletedFiles) > 0;
	}

}
