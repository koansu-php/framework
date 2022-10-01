<?php

namespace Koansu\Core\Exceptions;

use OutOfBoundsException;

/**
 * Throw a KeyNotFoundException if a key does not exist inside a container,
 * array, collection, ...
 **/
class KeyNotFoundException extends OutOfBoundsException
{
}
