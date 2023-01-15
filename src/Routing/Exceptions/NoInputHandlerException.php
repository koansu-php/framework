<?php
/**
 *  * Created by mtils on 26.10.2022 at 18:48.
 **/

namespace Koansu\Routing\Exceptions;

use Koansu\Core\Exceptions\ConfigurationException;

/**
 * Class NoInputHandlerException
 *
 * This exception is thrown if the middleware didn't find an InputHandler
 */
class NoInputHandlerException extends ConfigurationException
{
    //
}