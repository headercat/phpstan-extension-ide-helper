<?php 

namespace PHPStan\File;
return;

use PHPStan\ShouldNotHappenException;
use function array_diff;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_unique;
use function is_dir;
use function is_file;
use function sha1_file;

final class FileMonitor
{

	/** @var array<string, string>|null */
	private ?array $fileHashes = null;

	/** @var array<string>|null */
	private ?array $filePaths = null;

	/**
	 * @param string[] $analysedPaths
	 * @param string[] $analysedPathsFromConfig
	 * @param string[] $scanFiles
	 * @param string[] $scanDirectories
	 */
	public function __construct(
		private FileFinder $analyseFileFinder,
		private FileFinder $scanFileFinder,
		private array $analysedPaths,
		private array $analysedPathsFromConfig,
		private array $scanFiles,
		private array $scanDirectories,
	)
	{
	}

	/**
	 * @param array<string> $filePaths
	 */
	public function initialize(array $filePaths): void
	{
		$finderResult = $this->analyseFileFinder->findFiles($this->analysedPaths);
		$fileHashes = [];
		foreach (array_merge($finderResult->getFiles(), $filePaths, $this->getScannedFiles($finderResult->getFiles())) as $filePath) {
			$fileHashes[$filePath] = $this->getFileHash($filePath);
		}

		$this->fileHashes = $fileHashes;
		$this->filePaths = $filePaths;
	}

	public function getChanges(): FileMonitorResult
	{
		if ($this->fileHashes === null || $this->filePaths === null) {
			throw new ShouldNotHappenException();
		}
		$finderResult = $this->analyseFileFinder->findFiles($this->analysedPaths);
		$oldFileHashes = $this->fileHashes;
		$fileHashes = [];
		$newFiles = [];
		$changedFiles = [];
		$deletedFiles = [];
		foreach (array_merge($finderResult->getFiles(), $this->filePaths, $this->getScannedFiles($finderResult->getFiles())) as $filePath) {
			if (!array_key_exists($filePath, $oldFileHashes)) {
				$newFiles[] = $filePath;
				$fileHashes[$filePath] = $this->getFileHash($filePath);
				continue;
			}

			$oldHash = $oldFileHashes[$filePath];
			unset($oldFileHashes[$filePath]);
			$newHash = $this->getFileHash($filePath);
			$fileHashes[$filePath] = $newHash;
			if ($oldHash === $newHash) {
				continue;
			}

			$changedFiles[] = $filePath;
		}

		$this->fileHashes = $fileHashes;

		foreach (array_keys($oldFileHashes) as $file) {
			$deletedFiles[] = $file;
		}

		return new FileMonitorResult(
			$newFiles,
			$changedFiles,
			$deletedFiles,
		);
	}

	private function getFileHash(string $filePath): string
	{
		$hash = sha1_file($filePath);

		if ($hash === false) {
			throw new CouldNotReadFileException($filePath);
		}

		return $hash;
	}

	/**
	 * @param string[] $allAnalysedFiles
	 * @return array<string>
	 */
	private function getScannedFiles(array $allAnalysedFiles): array
	{
		$scannedFiles = $this->scanFiles;
		$analysedDirectories = [];
		foreach (array_merge($this->analysedPaths, $this->analysedPathsFromConfig) as $analysedPath) {
			if (is_file($analysedPath)) {
				continue;
			}

			if (!is_dir($analysedPath)) {
				continue;
			}

			$analysedDirectories[] = $analysedPath;
		}

		$directories = array_unique(array_merge($analysedDirectories, $this->scanDirectories));
		foreach ($this->scanFileFinder->findFiles($directories)->getFiles() as $file) {
			$scannedFiles[] = $file;
		}

		return array_diff($scannedFiles, $allAnalysedFiles);
	}

}
