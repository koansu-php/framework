<?php
/**
 *  * Created by mtils on 26.10.2022 at 10:20.
 **/

namespace Koansu\Routing\Exceptions;

use Koansu\Http\Status;
use Throwable;

class RouteNotFoundException extends HttpStatusException
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct(Status::NOT_FOUND, $message, $code, $previous);
    }

}