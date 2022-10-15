<?php
/**
 *  * Created by mtils on 08.10.2022 at 12:33.
 **/

namespace Koansu\Core\Exceptions;

use RuntimeException;

/**
 * This exception is thrown is something is not writable (and this is not caused
 * by in-application changeable permissions)
 */
class NotWritableException extends RuntimeException
{
    //
}