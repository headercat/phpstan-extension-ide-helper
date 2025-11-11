<?php 

namespace PHPStan\Type\Generic;
return;

use PHPStan\Type\AcceptsResult;
use PHPStan\Type\CompoundType;
use PHPStan\Type\Type;

/**
 * Template type strategy suitable for parameter type acceptance contexts
 */
final class TemplateTypeParameterStrategy implements TemplateTypeStrategy
{

	public function accepts(TemplateType $left, Type $right, bool $strictTypes): AcceptsResult
	{
		if ($right instanceof CompoundType) {
			return $right->isAcceptedBy($left, $strictTypes);
		}

		return $left->getBound()->accepts($right, $strictTypes);
	}

	public function isArgument(): bool
	{
		return false;
	}

}
