<?php 

namespace PHPStan\Parallel;
return;

final class Schedule
{

	/**
	 * @param array<array<string>> $jobs
	 */
	public function __construct(private int $numberOfProcesses, private array $jobs)
	{
	}

	public function getNumberOfProcesses(): int
	{
		return $this->numberOfProcesses;
	}

	/**
	 * @return array<array<string>>
	 */
	public function getJobs(): array
	{
		return $this->jobs;
	}

}
