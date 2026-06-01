<?php 

namespace PHPStan\Type;
return;

class StringNeverAcceptingObjectWithToStringType extends StringType
{

	public function isSuperTypeOf(Type $type): IsSuperTypeOfResult
	{
		if ($type instanceof CompoundType) {
			return $type->isSubTypeOf($this);
		}

		$result = parent::isSuperTypeOf($type);
		if (!$type instanceof self) {
			$result = $result->and(IsSuperTypeOfResult::createMaybe());
		}

		return $result;
	}

	public function accepts(Type $type, bool $strictTypes): AcceptsResult
	{
		return parent::accepts($type, true);
	}

}
