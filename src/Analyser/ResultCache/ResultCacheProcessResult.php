<?php 

namespace PHPStan\Analyser\ResultCache;
return;

use Closure;
use PHPStan\Analyser\AnalyserResult;
use PHPStan\Analyser\FinalizerResult;

final class ResultCacheProcessResult
{

	private ?LazyCollectedData $savedCollectedData = null;

	/**
	 * @param Closure(FinalizerResult): ?LazyCollectedData $saveCallback returns the collected data as it can be read back from the saved file, or null when nothing was saved
	 */
	public function __construct(private AnalyserResult $analyserResult, private Closure $saveCallback)
	{
	}

	public function getAnalyserResult(): AnalyserResult
	{
		return $this->analyserResult;
	}

	/**
	 * Writes the result cache, and says whether it was written.
	 *
	 * It is separate from ResultCacheManager::process() so that the caller can run the rules on
	 * CollectedDataNode on the merged result first, and save what they found too.
	 */
	public function save(FinalizerResult $finalizerResult): bool
	{
		$this->savedCollectedData = ($this->saveCallback)($finalizerResult);

		return $this->savedCollectedData !== null;
	}

	/**
	 * The collected data after save() wrote the cache file: the cached entries of the analyser result
	 * pointed into the file that the save replaced. Null when nothing was saved.
	 */
	public function getSavedCollectedData(): ?LazyCollectedData
	{
		return $this->savedCollectedData;
	}

}
