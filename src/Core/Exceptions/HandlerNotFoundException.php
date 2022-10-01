<?php

namespace Koansu\Core\Exceptions;

use OutOfBoundsException;

/**
 * This exception is thrown if a handler should be returned or used, but it was
 * not found or didn't match the request (or question).
 */
class HandlerNotFoundException extends ItemNotFoundException
{
    public const NO_HANDLERS_FOUND = 40400; // HTTP Not Found
    public const NO_HANDLER_ANSWERED = 40600; // HTTP Not Acceptable
}
