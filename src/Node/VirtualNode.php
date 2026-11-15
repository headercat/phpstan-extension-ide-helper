<?php 

namespace PHPStan\Node;
return;

use PhpParser\Node;
use PHPStan\Turbo\ReferencedByTurboExtension;

/**
 * @api
 * @api-do-not-implement
 */
#[ReferencedByTurboExtension(key: 'virtualNode')]
interface VirtualNode extends Node
{

}
