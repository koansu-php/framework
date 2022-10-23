<?php
/**
 *  * Created by mtils on 08.10.2022 at 15:21.
 **/

namespace Koansu\Core\Exceptions;

/**
 * Throw a ResourceLockedException if access to a resource failed because of
 * a lock.
 **/
class ResourceLockedException extends ConcurrencyException
{
    //
}