<?php
/**
 *  * Created by mtils on 29.01.2023 at 21:14.
 **/

namespace Koansu\Validation\Exceptions;

use RuntimeException;

/**
 * Throw this exception if your validator can not merge rules and mergeRules
 * was called..
 */
class MergingFailedException extends RuntimeException
{
    //
}