<?php
/**
 *  * Created by mtils on 23.02.2023 at 09:59.
 **/

namespace Koansu\Routing\Exceptions;

use Throwable;

class CsrfTokenException extends HttpStatusException
{
    public function __construct($message = "Crsf token mismatch", $code = 0, Throwable $previous = null)
    {
        parent::__construct(400, $message, $code, $previous);
    }
}