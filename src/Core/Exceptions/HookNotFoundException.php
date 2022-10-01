<?php

namespace Koansu\Core\Exceptions;

use OutOfBoundsException;

/**
 * Throw a HookNotFoundException if a listener should listen a not existing hook.
 **/
class HookNotFoundException extends OutOfBoundsException
{
    //
}
