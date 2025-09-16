<?php 

namespace PHPStan\Rules\InternalTag;
return;

use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Rules\RestrictedUsage\RestrictedMethodUsageExtension;
use PHPStan\Rules\RestrictedUsage\RestrictedUsage;
use function array_slice;
use function explode;
use function sprintf;
use function strtolower;

final class RestrictedInternalMethodUsageExtension implements RestrictedMethodUsageExtension
{

	public function __construct(private RestrictedInternalUsageHelper $helper)
	{
	}

	public function isRestrictedMethodUsage(
		ExtendedMethodReflection $methodReflection,
		Scope $scope,
	): ?RestrictedUsage
	{
		$isMethodInternal = $methodReflection->isInternal()->yes();
		$declaringClass = $methodReflection->getDeclaringClass();
		$isDeclaringClassInternal = $declaringClass->isInternal();
		if (!$isMethodInternal && !$isDeclaringClassInternal) {
			return null;
		}

		$declaringClassName = $declaringClass->getName();
		if (!$this->helper->shouldBeReported($scope, $declaringClassName)) {
			return null;
		}

		$namespace = array_slice(explode('\\', $declaringClassName), 0, -1)[0] ?? null;
		if ($namespace === null) {
			if (!$isMethodInternal) {
				return RestrictedUsage::create(
					sprintf(
						'Call to %smethod %s() of internal %s %s.',
						$methodReflection->isStatic() ? 'static ' : '',
						$methodReflection->getName(),
						strtolower($methodReflection->getDeclaringClass()->getClassTypeDescription()),
						$methodReflection->getDeclaringClass()->getDisplayName(),
					),
					sprintf(
						'%s.internal%s',
						$methodReflection->isStatic() ? 'staticMethod' : 'method',
						$methodReflection->getDeclaringClass()->getClassTypeDescription(),
					),
				);
			}

			return RestrictedUsage::create(
				sprintf(
					'Call to internal %smethod %s::%s().',
					$methodReflection->isStatic() ? 'static ' : '',
					$methodReflection->getDeclaringClass()->getDisplayName(),
					$methodReflection->getName(),
				),
				sprintf('%s.internal', $methodReflection->isStatic() ? 'staticMethod' : 'method'),
			);
		}

		if (!$isMethodInternal) {
			return RestrictedUsage::create(
				sprintf(
					'Call to %smethod %s() of internal %s %s from outside its root namespace %s.',
					$methodReflection->isStatic() ? 'static ' : '',
					$methodReflection->getName(),
					strtolower($methodReflection->getDeclaringClass()->getClassTypeDescription()),
					$methodReflection->getDeclaringClass()->getDisplayName(),
					$namespace,
				),
				sprintf(
					'%s.internal%s',
					$methodReflection->isStatic() ? 'staticMethod' : 'method',
					$methodReflection->getDeclaringClass()->getClassTypeDescription(),
				),
			);
		}

		return RestrictedUsage::create(
			sprintf(
				'Call to internal %smethod %s::%s() from outside its root namespace %s.',
				$methodReflection->isStatic() ? 'static ' : '',
				$methodReflection->getDeclaringClass()->getDisplayName(),
				$methodReflection->getName(),
				$namespace,
			),
			sprintf('%s.internal', $methodReflection->isStatic() ? 'staticMethod' : 'method'),
		);
	}

}
