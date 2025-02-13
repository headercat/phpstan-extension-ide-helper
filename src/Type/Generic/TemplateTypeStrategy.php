<?php 

namespace PHPStan\Type\Generic;
return;

use PHPStan\Type\AcceptsResult;
use PHPStan\Type\Type;

interface TemplateTypeStrategy
{

	public function accepts(TemplateType $left, Type $right, bool $strictTypes): AcceptsResult;

	public function isArgument(): bool;

}
