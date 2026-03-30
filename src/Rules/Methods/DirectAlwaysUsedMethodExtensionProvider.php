<?php 

namespace PHPStan\Rules\Methods;
return;

final class DirectAlwaysUsedMethodExtensionProvider implements AlwaysUsedMethodExtensionProvider
{

	/**
	 * @param AlwaysUsedMethodExtension[] $extensions
	 */
	public function __construct(private array $extensions)
	{
	}

	public function getExtensions(): array
	{
		return $this->extensions;
	}

}
