<?php
/**
 *  * Created by mtils on 26.10.2022 at 10:15.
 **/

namespace Koansu\Routing\Exceptions;

use Koansu\Http\Status;
use Throwable;

class MethodNotAllowedException extends HttpStatusException
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct(Status::METHOD_NOT_ALLOWED, $message, $code, $previous);
    }
}