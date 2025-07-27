<?php 

namespace PHPStan\Analyser;
return;

use PHPStan\Collectors\CollectedData;
use PHPStan\Dependency\RootExportedNode;

/**
 * @phpstan-type LinesToIgnore = array<string, array<int, non-empty-list<string>|null>>
 * @phpstan-import-type CollectorData from CollectedData
 */
final class FileAnalyserResult
{

	/**
	 * @param list<Error> $errors
	 * @param list<Error> $filteredPhpErrors
	 * @param list<Error> $allPhpErrors
	 * @param list<Error> $locallyIgnoredErrors
	 * @param CollectorData $collectedData
	 * @param list<string> $dependencies
	 * @param list<string> $usedTraitDependencies
	 * @param list<RootExportedNode> $exportedNodes
	 * @param LinesToIgnore $linesToIgnore
	 * @param LinesToIgnore $unmatchedLineIgnores
	 */
	public function __construct(
		private array $errors,
		private array $filteredPhpErrors,
		private array $allPhpErrors,
		private array $locallyIgnoredErrors,
		private array $collectedData,
		private array $dependencies,
		private array $usedTraitDependencies,
		private array $exportedNodes,
		private array $linesToIgnore,
		private array $unmatchedLineIgnores,
	)
	{
	}

	/**
	 * @return list<Error>
	 */
	public function getErrors(): array
	{
		return $this->errors;
	}

	/**
	 * @return list<Error>
	 */
	public function getFilteredPhpErrors(): array
	{
		return $this->filteredPhpErrors;
	}

	/**
	 * @return list<Error>
	 */
	public function getAllPhpErrors(): array
	{
		return $this->allPhpErrors;
	}

	/**
	 * @return list<Error>
	 */
	public function getLocallyIgnoredErrors(): array
	{
		return $this->locallyIgnoredErrors;
	}

	/**
	 * @return CollectorData
	 */
	public function getCollectedData(): array
	{
		return $this->collectedData;
	}

	/**
	 * @return list<string>
	 */
	public function getDependencies(): array
	{
		return $this->dependencies;
	}

	/**
	 * @return list<string>
	 */
	public function getUsedTraitDependencies(): array
	{
		return $this->usedTraitDependencies;
	}

	/**
	 * @return list<RootExportedNode>
	 */
	public function getExportedNodes(): array
	{
		return $this->exportedNodes;
	}

	/**
	 * @return LinesToIgnore
	 */
	public function getLinesToIgnore(): array
	{
		return $this->linesToIgnore;
	}

	/**
	 * @return LinesToIgnore
	 */
	public function getUnmatchedLineIgnores(): array
	{
		return $this->unmatchedLineIgnores;
	}

}
