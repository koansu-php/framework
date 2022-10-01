<?php

namespace Koansu\Core\Exceptions;

use OutOfBoundsException;

/**
 * This exception is thrown if you expect a container contains an item, but it
 * does not. (like in_array() or $container->findOrFail($value))
 */
class ItemNotFoundException extends OutOfBoundsException
{
    //
}
