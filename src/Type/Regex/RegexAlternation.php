<?php 

namespace PHPStan\Type\Regex;
return;

use function array_key_exists;

final class RegexAlternation
{

	/** @var array<int, list<int>> */
	private array $groupCombinations = [];

	public function __construct(
		private readonly int $alternationId,
		private readonly int $alternationsCount,
	)
	{
	}

	public function getId(): int
	{
		return $this->alternationId;
	}

	public function pushGroup(int $combinationIndex, RegexCapturingGroup $group): void
	{
		if (!array_key_exists($combinationIndex, $this->groupCombinations)) {
			$this->groupCombinations[$combinationIndex] = [];
		}

		$this->groupCombinations[$combinationIndex][] = $group->getId();
	}

	public function getAlternationsCount(): int
	{
		return $this->alternationsCount;
	}

	/**
	 * @return array<int, list<int>>
	 */
	public function getGroupCombinations(): array
	{
		return $this->groupCombinations;
	}

}
