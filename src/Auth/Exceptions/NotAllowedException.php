<?php
/**
 *  * Created by mtils on 20.12.2022 at 21:50.
 **/

namespace Koansu\Auth\Exceptions;

use RuntimeException;

/**
 * This exception can be thrown if Auth::allowed() returned false when
 * checking the access.
 * In HTTP status this would be an 403 Forbidden.
 */
class NotAllowedException extends RuntimeException
{
    //
}