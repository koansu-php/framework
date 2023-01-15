<?php
/**
 *  * Created by mtils on 15.01.2023 at 11:29.
 **/

namespace Koansu\Text\Exceptions;

use OutOfBoundsException;

/**
 * Throw a DetectionFailedException if you analyse or detect something
 * and that fails. For example if you try to guess the mimetype of a file
 * or you need to extract an email address out of a string.
 **/
class DetectionFailedException extends OutOfBoundsException
{
    //
}