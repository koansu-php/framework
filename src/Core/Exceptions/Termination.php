<?php
/**
 *  * Created by mtils on 28.10.2022 at 16:29.
 **/

namespace Koansu\Core\Exceptions;

use RuntimeException;

/**
 * Class Termination
 *
 * Use this class to intentionally abort a process/handling/iteration without
 * further exception handling.
 * This is a tool exception and is not for errors.
 *
 * @package Ems\Contracts\Core\Exceptions
 */
class Termination extends RuntimeException
{
    //
}