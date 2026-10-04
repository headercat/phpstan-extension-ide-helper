<?php 

namespace PHPStan\Analyser\ResultCache;
return;

use RuntimeException;

/**
 * The result cache file no longer holds what its index says - restore() discards such a cache.
 */
final class CachedExportedNodesUnreadableException extends RuntimeException
{

}
