<?php
/**
 *  * Created by mtils on 08.10.2022 at 15:21.
 **/

namespace Koansu\Filesystem\Exceptions;

use RuntimeException;

/**
 * Throw a ResourceNotFoundException if a resource like a database entry
 * or a file or a session wasn't found.
 **/
class ResourceLockedException extends RuntimeException
{
    //
}