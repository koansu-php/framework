<?php

namespace Koansu\Core\Exceptions;

use OutOfBoundsException;

/**
 * Throw this exception if an option was not found (in Configurable or other
 * cases)
 **/
class UnsupportedOptionException extends OutOfBoundsException
{
}
